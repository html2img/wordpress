<?php
/**
 * Admin notices.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Api\Account;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onboarding, low credit and aggregated failure notices.
 */
class Notices {

	/**
	 * Hook the notices and their dismissals.
	 */
	public static function register() {
		add_action( 'admin_notices', [ __CLASS__, 'output' ] );
		add_action( 'wp_ajax_html2img_dismiss_notice', [ __CLASS__, 'dismiss' ] );
	}

	/**
	 * Decide which notice, if any, this screen shows. One at a time is
	 * plenty.
	 */
	public static function output() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Options::has_api_key() ) {
			self::onboarding();

			return;
		}

		$waiting = self::credits_waiting_count();

		if ( $waiting > 0 ) {
			self::out_of_credits( $waiting );

			return;
		}

		self::maybe_low_credits();
	}

	/**
	 * Connect notice, dismissable per session but back next page load
	 * until a key is saved.
	 */
	private static function onboarding() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && 'settings_page_html2img' === $screen->id ) {
			return;
		}

		// Dismissal quiets the notice for a day. It keeps coming back until
		// a key is saved, without being obnoxious about it.
		$dismissed_at = (int) get_user_meta( get_current_user_id(), 'html2img_notice_dismissed', true );

		if ( $dismissed_at && ( time() - $dismissed_at ) < DAY_IN_SECONDS ) {
			return;
		}

		$url = 'https://html2img.com/?utm_source=wordpress-plugin&utm_medium=admin-notice&utm_campaign=onboarding';
		?>
		<div class="notice notice-info is-dismissible html2img-notice" data-html2img-notice="onboarding">
			<p>
				<strong><?php esc_html_e( 'Auto OG Images', 'html2img' ); ?></strong>
				&mdash;
				<?php esc_html_e( 'Connect your HTML to Image account to start generating OG images automatically. New accounts get 50 free credits.', 'html2img' ); ?>
				<a href="<?php echo esc_url( Settings_Page::url() ); ?>"><?php esc_html_e( 'Connect your account', 'html2img' ); ?></a>
				|
				<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create an account', 'html2img' ); ?></a>
			</p>
		</div>
		<script>
			document.addEventListener( 'click', function ( event ) {
				var notice = event.target.closest ? event.target.closest( '.html2img-notice' ) : null;
				if ( ! notice || ! event.target.classList.contains( 'notice-dismiss' ) ) {
					return;
				}
				var data = new FormData();
				data.append( 'action', 'html2img_dismiss_notice' );
				data.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( 'html2img_dismiss' ) ); ?> );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } );
			} );
		</script>
		<?php
	}

	/**
	 * One aggregated notice when renders are waiting on credits.
	 *
	 * @param int $waiting Number of posts in the failed_credits state.
	 */
	private static function out_of_credits( $waiting ) {
		$upgrade = 'https://html2img.com/pricing?utm_source=wordpress-plugin&utm_medium=admin-notice&utm_campaign=out-of-credits';
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Auto OG Images', 'html2img' ); ?></strong>
				&mdash;
				<?php
				printf(
					/* translators: %s: number of posts. */
					esc_html( _n( 'OG image generation is paused: your account is out of credits. %s post is waiting.', 'OG image generation is paused: your account is out of credits. %s posts are waiting.', $waiting, 'html2img' ) ),
					esc_html( number_format_i18n( $waiting ) )
				);
				?>
				<a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Top up your credits', 'html2img' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Low balance warning from the cached account data. Never triggers an
	 * API call of its own.
	 */
	private static function maybe_low_credits() {
		$account = get_transient( Account::TRANSIENT );

		if ( ! is_array( $account ) || $account['credits_remaining'] <= 0 ) {
			return;
		}

		if ( $account['credits_remaining'] >= Account::low_credit_threshold( $account ) ) {
			return;
		}

		$upgrade = 'https://html2img.com/pricing?utm_source=wordpress-plugin&utm_medium=admin-notice&utm_campaign=low-credits';
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Auto OG Images', 'html2img' ); ?></strong>
				&mdash;
				<?php
				printf(
					/* translators: %s: number of credits. */
					esc_html__( 'Your account is down to %s credits. OG image generation stops when they run out.', 'html2img' ),
					esc_html( number_format_i18n( $account['credits_remaining'] ) )
				);
				?>
				<a href="<?php echo esc_url( $upgrade ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Top up your credits', 'html2img' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Posts waiting on credits, cached briefly so listing screens stay fast.
	 *
	 * @return int
	 */
	private static function credits_waiting_count() {
		$cached = get_transient( 'html2img_waiting_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$query = new \WP_Query(
			[
				'post_type'              => Options::post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded and cached.
					[
						'key'   => Post_Meta::STATUS,
						'value' => Post_Meta::STATUS_FAILED_CREDITS,
					],
				],
			]
		);

		$count = (int) $query->found_posts;
		set_transient( 'html2img_waiting_count', $count, 5 * MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Ajax dismissal for the onboarding notice.
	 */
	public static function dismiss() {
		check_ajax_referer( 'html2img_dismiss', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), 'html2img_notice_dismissed', time() );
		wp_send_json_success();
	}
}
