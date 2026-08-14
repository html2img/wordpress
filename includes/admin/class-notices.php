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
 * Connect, low credit and aggregated failure notices.
 *
 * Everything here is confined to the plugin's own screens and the plugins
 * list, so the rest of the dashboard is never interrupted.
 */
class Notices {

	/**
	 * User meta holding the dismissal of the connect notice.
	 */
	const DISMISSED_META = 'html2img_notice_dismissed';

	/**
	 * Hook the notices and their dismissals.
	 */
	public static function register() {
		add_action( 'admin_notices', [ __CLASS__, 'output' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_action( 'wp_ajax_html2img_dismiss_notice', [ __CLASS__, 'dismiss' ] );
	}

	/**
	 * Screens this plugin may speak on: its own two pages and the plugins
	 * list, where someone has just activated it.
	 *
	 * @return bool
	 */
	private static function is_own_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return in_array(
			$screen->id,
			[ 'plugins', 'plugins-network', 'settings_page_' . Settings_Page::PAGE, 'tools_page_html2img-tools' ],
			true
		);
	}

	/**
	 * Which notice, if any, the current screen should carry. One at a time
	 * is plenty.
	 *
	 * @return string One of onboarding, credits, low_credits or an empty string.
	 */
	private static function current() {
		if ( ! current_user_can( 'manage_options' ) || ! self::is_own_screen() ) {
			return '';
		}

		if ( ! Options::has_api_key() ) {
			return self::onboarding_due() ? 'onboarding' : '';
		}

		if ( self::credits_waiting_count() > 0 ) {
			return 'credits';
		}

		return self::low_credits() ? 'low_credits' : '';
	}

	/**
	 * The connect notice is skipped on the settings screen, where the key
	 * field is already in front of the user, and stays gone once dismissed.
	 *
	 * @return bool
	 */
	private static function onboarding_due() {
		$screen = get_current_screen();

		if ( $screen && 'settings_page_' . Settings_Page::PAGE === $screen->id ) {
			return false;
		}

		return ! get_user_meta( get_current_user_id(), self::DISMISSED_META, true );
	}

	/**
	 * The dismissal script, loaded only on the screen that shows a
	 * dismissable notice.
	 */
	public static function assets() {
		if ( 'onboarding' !== self::current() ) {
			return;
		}

		wp_enqueue_script(
			'html2img-notice',
			HTML2IMG_URL . 'assets/js/notice.js',
			[],
			HTML2IMG_VERSION,
			true
		);

		wp_localize_script(
			'html2img-notice',
			'html2imgNotice',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'html2img_dismiss' ),
			]
		);
	}

	/**
	 * Print the notice this screen earned.
	 */
	public static function output() {
		switch ( self::current() ) {
			case 'onboarding':
				self::onboarding();
				break;
			case 'credits':
				self::out_of_credits( self::credits_waiting_count() );
				break;
			case 'low_credits':
				self::low_credits_notice();
				break;
		}
	}

	/**
	 * Connect notice, dismissable for good.
	 */
	private static function onboarding() {
		?>
		<div class="notice notice-info is-dismissible html2img-notice">
			<p>
				<strong><?php esc_html_e( 'Auto OG Images', 'html2img' ); ?></strong>
				&mdash;
				<?php esc_html_e( 'Connect your HTML to Image account to start generating OG images automatically.', 'html2img' ); ?>
				<a href="<?php echo esc_url( Settings_Page::url() ); ?>"><?php esc_html_e( 'Connect your account', 'html2img' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * One aggregated notice when renders are waiting on credits.
	 *
	 * @param int $waiting Number of posts in the failed_credits state.
	 */
	private static function out_of_credits( $waiting ) {
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
				<a href="https://app.html2img.com/dashboard" target="_blank" rel="noopener"><?php esc_html_e( 'Open your account dashboard', 'html2img' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Whether the cached account is running low. Never triggers an API call
	 * of its own.
	 *
	 * @return bool
	 */
	private static function low_credits() {
		$account = get_transient( Account::TRANSIENT );

		if ( ! is_array( $account ) || $account['credits_remaining'] <= 0 ) {
			return false;
		}

		return $account['credits_remaining'] < Account::low_credit_threshold( $account );
	}

	/**
	 * Low balance warning from the cached account data.
	 */
	private static function low_credits_notice() {
		$account = get_transient( Account::TRANSIENT );
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
				<a href="https://app.html2img.com/dashboard" target="_blank" rel="noopener"><?php esc_html_e( 'Open your account dashboard', 'html2img' ); ?></a>
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
	 * Ajax dismissal for the connect notice.
	 */
	public static function dismiss() {
		check_ajax_referer( 'html2img_dismiss', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), self::DISMISSED_META, time() );
		wp_send_json_success();
	}
}
