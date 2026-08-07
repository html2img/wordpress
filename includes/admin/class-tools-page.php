<?php
/**
 * Bulk regeneration screen.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Api\Account;
use Html2Img\WordPress\Designs\Designs;
use Html2Img\WordPress\Generation\Generator;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools > OG Images: regenerate stale or all, in resumable batches.
 */
class Tools_Page {

	const PAGE  = 'html2img-tools';
	const STATE = 'html2img_bulk_state';
	const BATCH = 5;

	/**
	 * Hook the page and endpoints.
	 */
	public static function register() {
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'wp_ajax_html2img_bulk_start', [ __CLASS__, 'ajax_start' ] );
		add_action( 'wp_ajax_html2img_bulk_step', [ __CLASS__, 'ajax_step' ] );
		add_action( 'wp_ajax_html2img_bulk_stop', [ __CLASS__, 'ajax_stop' ] );
	}

	/**
	 * Add the menu entry.
	 */
	public static function add_page() {
		add_management_page(
			__( 'OG Images', 'html2img' ),
			__( 'OG Images', 'html2img' ),
			'manage_options',
			self::PAGE,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Post IDs whose stored fingerprint differs from the active design.
	 *
	 * @return int[]
	 */
	public static function stale_ids() {
		$fingerprint = Designs::fingerprint();

		$query = new \WP_Query(
			[
				'post_type'              => Options::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin tools screen only.
					'relation' => 'OR',
					[
						'key'     => Post_Meta::FINGERPRINT,
						'value'   => $fingerprint,
						'compare' => '!=',
					],
					[
						'key'     => Post_Meta::FINGERPRINT,
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);

		$ids = array_map( 'intval', $query->posts );

		return array_values( array_filter( $ids, [ __CLASS__, 'eligible' ] ) );
	}

	/**
	 * Every eligible published post of the enabled types.
	 *
	 * @return int[]
	 */
	public static function all_ids() {
		$query = new \WP_Query(
			[
				'post_type'              => Options::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$ids = array_map( 'intval', $query->posts );

		return array_values( array_filter( $ids, [ __CLASS__, 'eligible' ] ) );
	}

	/**
	 * Whether a post takes part in bulk runs.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function eligible( $post_id ) {
		return ! Post_Meta::is_disabled( $post_id );
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$account     = Options::has_api_key() ? Account::get() : null;
		$stale_count = count( self::stale_ids() );
		$all_count   = count( self::all_ids() );
		$state       = get_option( self::STATE, null );

		include HTML2IMG_DIR . 'includes/admin/views/tools.php';
	}

	/**
	 * Start (or restart) a bulk run and store the checkpoint.
	 */
	public static function ajax_start() {
		check_ajax_referer( 'html2img_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		$mode = isset( $_POST['mode'] ) && 'all' === $_POST['mode'] ? 'all' : 'stale';
		$ids  = 'all' === $mode ? self::all_ids() : self::stale_ids();

		$state = [
			'mode'       => $mode,
			'ids'        => $ids,
			'total'      => count( $ids ),
			'done'       => 0,
			'failed'     => 0,
			'started_at' => time(),
		];

		update_option( self::STATE, $state, false );

		wp_send_json_success( self::progress( $state ) );
	}

	/**
	 * Process one batch and report progress. The state option is the
	 * checkpoint, so an interrupted run resumes where it stopped.
	 */
	public static function ajax_step() {
		check_ajax_referer( 'html2img_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		$state = get_option( self::STATE, null );

		if ( ! is_array( $state ) || empty( $state['ids'] ) ) {
			delete_option( self::STATE );
			wp_send_json_success(
				[
					'complete' => true,
					'done'     => is_array( $state ) ? (int) $state['done'] : 0,
					'total'    => is_array( $state ) ? (int) $state['total'] : 0,
					'failed'   => is_array( $state ) ? (int) $state['failed'] : 0,
				]
			);
		}

		$batch = array_splice( $state['ids'], 0, self::BATCH );

		foreach ( $batch as $index => $post_id ) {
			$result = Generator::render( (int) $post_id, true );

			if ( 'failed_credits' === $result['status'] ) {
				// Put the unprocessed remainder back, including this post,
				// and stop cleanly. The run resumes once credits exist.
				$state['ids'] = array_merge(
					[ (int) $post_id ],
					array_slice( $batch, $index + 1 ),
					$state['ids']
				);
				update_option( self::STATE, $state, false );

				$out                 = self::progress( $state );
				$out['outOfCredits'] = true;
				wp_send_json_success( $out );
			}

			++$state['done'];

			if ( 'failed' === $result['status'] ) {
				++$state['failed'];
			}
		}

		$complete = empty( $state['ids'] );

		if ( $complete ) {
			delete_option( self::STATE );
		} else {
			update_option( self::STATE, $state, false );
		}

		$out             = self::progress( $state );
		$out['complete'] = $complete;
		wp_send_json_success( $out );
	}

	/**
	 * Abandon the checkpoint.
	 */
	public static function ajax_stop() {
		check_ajax_referer( 'html2img_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		delete_option( self::STATE );
		wp_send_json_success();
	}

	/**
	 * Progress payload for the JS.
	 *
	 * @param array $state Current state.
	 * @return array
	 */
	private static function progress( array $state ) {
		$account = get_transient( Account::TRANSIENT );

		return [
			'done'      => (int) $state['done'],
			'total'     => (int) $state['total'],
			'failed'    => (int) $state['failed'],
			'remaining' => count( $state['ids'] ),
			'credits'   => is_array( $account ) ? (int) $account['credits_remaining'] : null,
			'complete'  => false,
		];
	}
}
