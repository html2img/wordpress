<?php
/**
 * Plugin bootstrap.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress;

use Html2Img\WordPress\Admin\Editor;
use Html2Img\WordPress\Admin\List_Actions;
use Html2Img\WordPress\Admin\Media;
use Html2Img\WordPress\Admin\Notices;
use Html2Img\WordPress\Admin\Settings_Page;
use Html2Img\WordPress\Admin\Tools_Page;
use Html2Img\WordPress\Api\Account;
use Html2Img\WordPress\Generation\Queue;
use Html2Img\WordPress\Integrations\Seo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin together.
 */
class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register everything.
	 */
	public function boot() {
		Queue::register();

		add_action( 'init', [ Seo::class, 'register' ] );

		if ( is_admin() ) {
			Settings_Page::register();
			Notices::register();
			Tools_Page::register();
			Editor::register();
			List_Actions::register();
			Media::register();
		}
	}

	/**
	 * Activation: nothing to build, but flag the onboarding notice.
	 */
	public static function activate() {
		if ( ! Options::has_api_key() ) {
			delete_user_meta( get_current_user_id(), 'html2img_notice_dismissed' );
		}
	}

	/**
	 * Deactivation: clear scheduled events and caches.
	 */
	public static function deactivate() {
		wp_unschedule_hook( Queue::CRON_HOOK );
		Account::forget();
	}
}
