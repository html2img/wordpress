<?php
/**
 * Posts list row and bulk actions.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Generation\Queue;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Regenerate from the posts list, one at a time or in bulk.
 */
class List_Actions {

	/**
	 * Hook the actions for every enabled post type.
	 */
	public static function register() {
		add_filter( 'post_row_actions', [ __CLASS__, 'row_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ __CLASS__, 'row_action' ], 10, 2 );
		add_action( 'admin_init', [ __CLASS__, 'handle_row_action' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_bulk' ] );
		add_action( 'admin_notices', [ __CLASS__, 'queued_notice' ] );
	}

	/**
	 * Add the row action link.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Row post.
	 * @return array
	 */
	public static function row_action( $actions, $post ) {
		if ( ! in_array( $post->post_type, Options::post_types(), true )
			|| 'publish' !== $post->post_status
			|| ! Options::has_api_key()
			|| ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				[
					'html2img_action' => 'regenerate',
					'post_id'         => $post->ID,
				]
			),
			'html2img_row_' . $post->ID
		);

		$actions['html2img'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Regenerate OG image', 'html2img' )
		);

		return $actions;
	}

	/**
	 * Handle the row action.
	 */
	public static function handle_row_action() {
		if ( ! isset( $_GET['html2img_action'] ) || 'regenerate' !== $_GET['html2img_action'] ) {
			return;
		}

		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

		check_admin_referer( 'html2img_row_' . $post_id );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You cannot regenerate this image.', 'html2img' ), 403 );
		}

		delete_post_meta( $post_id, Post_Meta::CONTENT_HASH );
		Queue::queue( $post_id );

		wp_safe_redirect(
			add_query_arg(
				'html2img_queued',
				1,
				remove_query_arg( [ 'html2img_action', 'post_id', '_wpnonce' ] )
			)
		);
		exit;
	}

	/**
	 * Register the bulk action on every enabled type's list table.
	 */
	public static function register_bulk() {
		foreach ( Options::post_types() as $type ) {
			add_filter( "bulk_actions-edit-{$type}", [ __CLASS__, 'bulk_action' ] );
			add_filter( "handle_bulk_actions-edit-{$type}", [ __CLASS__, 'handle_bulk' ], 10, 3 );
		}
	}

	/**
	 * Add the bulk action option.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public static function bulk_action( $actions ) {
		if ( Options::has_api_key() ) {
			$actions['html2img_regenerate'] = __( 'Regenerate OG images', 'html2img' );
		}

		return $actions;
	}

	/**
	 * Queue every selected post.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Chosen action.
	 * @param array  $post_ids Selected posts.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $post_ids ) {
		if ( 'html2img_regenerate' !== $action ) {
			return $redirect;
		}

		$queued = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			delete_post_meta( $post_id, Post_Meta::CONTENT_HASH );
			Queue::queue( (int) $post_id );
			++$queued;
		}

		return add_query_arg( 'html2img_queued', $queued, $redirect );
	}

	/**
	 * Confirmation after queueing.
	 */
	public static function queued_notice() {
		if ( ! isset( $_GET['html2img_queued'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			return;
		}

		$count = absint( $_GET['html2img_queued'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				printf(
					/* translators: %s: number of posts. */
					esc_html( _n( '%s OG image queued for regeneration.', '%s OG images queued for regeneration.', $count, 'html2img' ) ),
					esc_html( number_format_i18n( $count ) )
				);
				?>
			</p>
		</div>
		<?php
	}
}
