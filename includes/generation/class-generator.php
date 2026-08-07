<?php
/**
 * Render pipeline.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Generation;

use Html2Img\WordPress\Api\Account;
use Html2Img\WordPress\Api\Client;
use Html2Img\WordPress\Designs\Designs;
use Html2Img\WordPress\Designs\Renderer;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;
use Html2Img\WordPress\Render_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a post's OG image through the API and stores the result.
 */
class Generator {

	/**
	 * Whether a post needs a render.
	 *
	 * True when the content hash or design fingerprint moved, or the stored
	 * image is gone. A matching pair with a live image costs nothing.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function needs_render( $post_id ) {
		return self::decide(
			(string) get_post_meta( $post_id, Post_Meta::CONTENT_HASH, true ),
			(string) get_post_meta( $post_id, Post_Meta::FINGERPRINT, true ),
			static function () use ( $post_id ) {
				return Payload::content_hash( $post_id );
			},
			static function () {
				return Designs::fingerprint();
			},
			'' !== Post_Meta::image_url( $post_id )
		);
	}

	/**
	 * The regeneration decision itself, free of WordPress state.
	 *
	 * Current values arrive as callables so the cheap checks run first and
	 * hashing only happens when it can still change the answer.
	 *
	 * @param string   $stored_hash        Content hash from the last render.
	 * @param string   $stored_fingerprint Design fingerprint from the last render.
	 * @param callable $current_hash       Returns the content hash for the post now.
	 * @param callable $current_fingerprint Returns the active design fingerprint.
	 * @param bool     $has_image          Whether the stored image still exists.
	 * @return bool
	 */
	public static function decide( $stored_hash, $stored_fingerprint, callable $current_hash, callable $current_fingerprint, $has_image ) {
		if ( '' === $stored_hash || '' === $stored_fingerprint ) {
			return true;
		}

		if ( ! $has_image ) {
			return true;
		}

		if ( $stored_fingerprint !== $current_fingerprint() ) {
			return true;
		}

		return $stored_hash !== $current_hash();
	}

	/**
	 * Render now, synchronously.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Render even when nothing changed.
	 * @return array{status: string, message: string}
	 */
	public static function render( $post_id, $force = false ) {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return [
				'status'  => 'skipped',
				'message' => __( 'Post is not published.', 'html2img' ),
			];
		}

		if ( Post_Meta::is_disabled( $post_id ) ) {
			return [
				'status'  => 'skipped',
				'message' => __( 'Generation is disabled for this post.', 'html2img' ),
			];
		}

		$lock_key = 'html2img_lock_' . $post_id;

		if ( false !== get_transient( $lock_key ) ) {
			return [
				'status'  => 'locked',
				'message' => __( 'A render for this post is already running.', 'html2img' ),
			];
		}

		set_transient( $lock_key, time(), 2 * MINUTE_IN_SECONDS );

		try {
			return self::do_render( $post_id, $force );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * The render itself, called under the per-post lock.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Render even when nothing changed.
	 * @return array{status: string, message: string}
	 */
	private static function do_render( $post_id, $force ) {
		delete_post_meta( $post_id, Post_Meta::QUEUED_AT );

		if ( ! $force && ! self::needs_render( $post_id ) ) {
			Post_Meta::set_status( $post_id, Post_Meta::STATUS_OK );

			return [
				'status'  => 'unchanged',
				'message' => __( 'Nothing changed, no credit spent.', 'html2img' ),
			];
		}

		if ( ! $force && Account::is_paused() ) {
			Post_Meta::set_status( $post_id, Post_Meta::STATUS_FAILED_CREDITS );

			return [
				'status'  => 'failed_credits',
				'message' => __( 'Rendering is paused: the account is out of credits.', 'html2img' ),
			];
		}

		Post_Meta::set_status( $post_id, Post_Meta::STATUS_GENERATING );

		$variables = Payload::variables( $post_id );
		$template  = Designs::template_html();
		$html      = Renderer::render( $template, $variables );
		$args      = Designs::dimensions();

		/**
		 * Filters the render arguments before the API call.
		 *
		 * @param array $args    width, height, dpi and html.
		 * @param int   $post_id Post ID.
		 */
		$args = (array) apply_filters( 'html2img_render_args', array_merge( $args, [ 'html' => $html ] ), $post_id );

		$html = $args['html'];
		unset( $args['html'] );

		$client   = new Client();
		$response = $client->render_html( $html, $args );

		if ( ! $response->success ) {
			return self::handle_failure( $post_id, $response );
		}

		$cdn_url = (string) $response->get( 'url', '' );

		if ( '' === $cdn_url ) {
			Post_Meta::set_status( $post_id, Post_Meta::STATUS_FAILED, __( 'The API returned no image URL.', 'html2img' ) );
			Render_Log::add( $post_id, 'failed', __( 'No image URL in the response.', 'html2img' ) );

			return [
				'status'  => 'failed',
				'message' => __( 'The API returned no image URL.', 'html2img' ),
			];
		}

		$credits = $response->get( 'credits_remaining' );

		if ( null !== $credits ) {
			Account::update_balance( (int) $credits );
		}

		$old_image_id = Post_Meta::image_id( $post_id );
		$image_id     = 0;

		if ( 'cdn' !== Options::get( 'storage' ) ) {
			$image_id = self::sideload( $cdn_url, $post_id );

			if ( is_wp_error( $image_id ) ) {
				// The credit is spent and the render exists on the CDN, so
				// keep the CDN URL rather than throwing the render away.
				Post_Meta::set_status( $post_id, Post_Meta::STATUS_FAILED, $image_id->get_error_message() );
				Render_Log::add( $post_id, 'failed', $image_id->get_error_message() );

				return [
					'status'  => 'failed',
					'message' => $image_id->get_error_message(),
				];
			}
		}

		update_post_meta( $post_id, Post_Meta::CDN_URL, $cdn_url );
		update_post_meta( $post_id, Post_Meta::RENDER_ID, (string) $response->get( 'id', '' ) );
		update_post_meta( $post_id, Post_Meta::EXPIRES_AT, (string) $response->get( 'expires_at', '' ) );
		update_post_meta( $post_id, Post_Meta::CONTENT_HASH, Payload::content_hash( $post_id ) );
		update_post_meta( $post_id, Post_Meta::FINGERPRINT, Designs::fingerprint() );
		update_post_meta( $post_id, Post_Meta::GENERATED_AT, time() );

		if ( $image_id ) {
			update_post_meta( $post_id, Post_Meta::IMAGE_ID, $image_id );
		}

		Post_Meta::set_status( $post_id, Post_Meta::STATUS_OK );

		// Only remove the previous image once its replacement is stored.
		if ( $old_image_id && $image_id && $old_image_id !== $image_id ) {
			wp_delete_attachment( $old_image_id, true );
		}

		Render_Log::add( $post_id, 'ok', '', null === $credits ? null : (int) $credits );

		/**
		 * Fires after an OG image was generated and stored.
		 *
		 * @param int    $post_id  Post ID.
		 * @param int    $image_id Attachment ID, 0 in CDN mode.
		 * @param array  $data     Full API response body.
		 */
		do_action( 'html2img_after_generate', $post_id, (int) $image_id, $response->data );

		return [
			'status'  => 'ok',
			'message' => __( 'Image generated.', 'html2img' ),
		];
	}

	/**
	 * Store a failure and translate it for the caller.
	 *
	 * @param int                              $post_id  Post ID.
	 * @param \Html2Img\WordPress\Api\Response $response Failed response.
	 * @return array{status: string, message: string}
	 */
	private static function handle_failure( $post_id, $response ) {
		if ( $response->out_of_credits() ) {
			Account::pause();
			Account::update_balance( 0 );
			Post_Meta::set_status( $post_id, Post_Meta::STATUS_FAILED_CREDITS );
			Render_Log::add( $post_id, 'failed_credits', $response->message, 0 );

			return [
				'status'  => 'failed_credits',
				'message' => __( 'The account is out of credits. The previous image is untouched.', 'html2img' ),
			];
		}

		Post_Meta::set_status( $post_id, Post_Meta::STATUS_FAILED, $response->message );
		Render_Log::add( $post_id, 'failed', $response->code . ': ' . $response->message );

		return [
			'status'  => 'failed',
			'message' => $response->message,
		];
	}

	/**
	 * Download the rendered PNG into the media library.
	 *
	 * @param string $url     CDN URL of the render.
	 * @param int    $post_id Post the attachment belongs to.
	 * @return int|\WP_Error Attachment ID.
	 */
	private static function sideload( $url, $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$temp = download_url( $url, 60 );

		if ( is_wp_error( $temp ) ) {
			return $temp;
		}

		$post = get_post( $post_id );
		$slug = $post ? $post->post_name : (string) $post_id;

		$file = [
			'name'     => sanitize_file_name( 'og-' . $slug . '-' . time() . '.png' ),
			'tmp_name' => $temp,
		];

		$image_id = media_handle_sideload(
			$file,
			$post_id,
			sprintf(
				/* translators: %s: post title. */
				__( 'OG image: %s', 'html2img' ),
				get_the_title( $post_id )
			)
		);

		if ( is_wp_error( $image_id ) ) {
			wp_delete_file( $temp );

			return $image_id;
		}

		update_post_meta( $image_id, '_html2img_generated', '1' );

		return (int) $image_id;
	}
}
