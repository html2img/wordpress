<?php
/**
 * Post meta keys and accessors.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every meta key the plugin writes, in one place.
 */
class Post_Meta {

	const IMAGE_ID     = '_html2img_image_id';
	const CDN_URL      = '_html2img_cdn_url';
	const RENDER_ID    = '_html2img_render_id';
	const EXPIRES_AT   = '_html2img_expires_at';
	const CONTENT_HASH = '_html2img_content_hash';
	const FINGERPRINT  = '_html2img_fingerprint';
	const GENERATED_AT = '_html2img_generated_at';
	const STATUS       = '_html2img_status';
	const ERROR        = '_html2img_error';
	const DISABLED     = '_html2img_disabled';
	const QUEUED_AT    = '_html2img_queued_at';

	const STATUS_QUEUED         = 'queued';
	const STATUS_GENERATING     = 'generating';
	const STATUS_OK             = 'ok';
	const STATUS_FAILED_CREDITS = 'failed_credits';
	const STATUS_FAILED         = 'failed';

	/**
	 * Attachment ID of the generated image, verified to still exist.
	 *
	 * @param int $post_id Post ID.
	 * @return int Zero when there is none.
	 */
	public static function image_id( $post_id ) {
		$image_id = (int) get_post_meta( $post_id, self::IMAGE_ID, true );

		if ( $image_id && 'attachment' !== get_post_type( $image_id ) ) {
			return 0;
		}

		return $image_id;
	}

	/**
	 * Public URL of the current OG image for a post, honouring the storage mode.
	 *
	 * @param int $post_id Post ID.
	 * @return string Empty when no usable image exists.
	 */
	public static function image_url( $post_id ) {
		if ( 'cdn' === Options::get( 'storage' ) ) {
			$url        = (string) get_post_meta( $post_id, self::CDN_URL, true );
			$expires_at = (string) get_post_meta( $post_id, self::EXPIRES_AT, true );

			if ( '' !== $expires_at && strtotime( $expires_at ) < time() ) {
				return '';
			}

			if ( '' !== $url ) {
				return $url;
			}
		}

		$image_id = self::image_id( $post_id );

		if ( ! $image_id ) {
			return '';
		}

		return (string) wp_get_attachment_url( $image_id );
	}

	/**
	 * Whether generation is switched off for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_disabled( $post_id ) {
		return '1' === get_post_meta( $post_id, self::DISABLED, true );
	}

	/**
	 * Record a render outcome on the post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  One of the STATUS_* constants.
	 * @param string $error   Optional error message.
	 */
	public static function set_status( $post_id, $status, $error = '' ) {
		update_post_meta( $post_id, self::STATUS, $status );

		if ( '' !== $error ) {
			update_post_meta( $post_id, self::ERROR, $error );
		} else {
			delete_post_meta( $post_id, self::ERROR );
		}
	}

	/**
	 * Remove all plugin meta from a post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function clear( $post_id ) {
		$keys = [
			self::IMAGE_ID,
			self::CDN_URL,
			self::RENDER_ID,
			self::EXPIRES_AT,
			self::CONTENT_HASH,
			self::FINGERPRINT,
			self::GENERATED_AT,
			self::STATUS,
			self::ERROR,
			self::QUEUED_AT,
		];

		foreach ( $keys as $key ) {
			delete_post_meta( $post_id, $key );
		}
	}
}
