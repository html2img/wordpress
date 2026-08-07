<?php
/**
 * Render queue.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Generation;

use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues renders off the editor save so publishing never waits on the API.
 */
class Queue {

	const CRON_HOOK = 'html2img_render_post';

	/**
	 * Hook everything up.
	 */
	public static function register() {
		add_action( 'wp_after_insert_post', [ __CLASS__, 'on_save' ], 20, 2 );
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_job' ] );
	}

	/**
	 * Post save handler. Fires after terms and the featured image are
	 * persisted on both REST and classic saves, which save_post does not
	 * guarantee.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function on_save( $post_id, $post ) {
		if ( ! self::should_generate( $post_id, $post ) ) {
			return;
		}

		if ( ! Generator::needs_render( $post_id ) ) {
			return;
		}

		self::queue( $post_id );
	}

	/**
	 * Whether the plugin generates for this post at all.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool
	 */
	public static function should_generate( $post_id, $post ) {
		$should = true;

		if ( ! $post || 'publish' !== $post->post_status ) {
			$should = false;
		} elseif ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			$should = false;
		} elseif ( ! in_array( $post->post_type, Options::post_types(), true ) ) {
			$should = false;
		} elseif ( Post_Meta::is_disabled( $post_id ) ) {
			$should = false;
		} elseif ( ! Options::has_api_key() ) {
			$should = false;
		}

		/**
		 * Filters the final decision on generating an image for a post.
		 *
		 * @param bool     $should  Whether an image will be generated.
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 */
		return (bool) apply_filters( 'html2img_should_generate', $should, $post_id, $post );
	}

	/**
	 * Queue a render as an immediately scheduled single cron event.
	 *
	 * A post already queued is left alone: the job reads fresh state when
	 * it runs, so a second save before then is covered by the first job.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function queue( $post_id ) {
		$queued_at = (int) get_post_meta( $post_id, Post_Meta::QUEUED_AT, true );

		if ( $queued_at && ( time() - $queued_at ) < 2 * MINUTE_IN_SECONDS ) {
			return;
		}

		update_post_meta( $post_id, Post_Meta::QUEUED_AT, time() );
		Post_Meta::set_status( $post_id, Post_Meta::STATUS_QUEUED );

		if ( ! wp_next_scheduled( self::CRON_HOOK, [ (int) $post_id ] ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK, [ (int) $post_id ] );
		}

		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
			spawn_cron();
		}
	}

	/**
	 * Cron callback.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function run_job( $post_id ) {
		Generator::render( (int) $post_id, false );
	}

	/**
	 * Whether a queued job looks stuck, meaning wp-cron has not picked it
	 * up within two minutes. The editor panel offers a manual run then.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_stuck( $post_id ) {
		$status    = (string) get_post_meta( $post_id, Post_Meta::STATUS, true );
		$queued_at = (int) get_post_meta( $post_id, Post_Meta::QUEUED_AT, true );

		return Post_Meta::STATUS_QUEUED === $status
			&& $queued_at
			&& ( time() - $queued_at ) > 2 * MINUTE_IN_SECONDS;
	}
}
