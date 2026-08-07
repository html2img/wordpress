<?php
/**
 * Settings screen.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Api\Account;
use Html2Img\WordPress\Api\Client;
use Html2Img\WordPress\Designs\Designs;
use Html2Img\WordPress\Integrations\Seo;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Render_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > OG Images.
 */
class Settings_Page {

	const PAGE = 'html2img';

	/**
	 * Hook the page and its saving.
	 */
	public static function register() {
		add_action( 'admin_menu', [ __CLASS__, 'add_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'assets' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( HTML2IMG_FILE ), [ __CLASS__, 'action_links' ] );
	}

	/**
	 * Add the menu entry.
	 */
	public static function add_page() {
		add_options_page(
			__( 'OG Images', 'html2img' ),
			__( 'OG Images', 'html2img' ),
			'manage_options',
			self::PAGE,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( self::url() ),
				esc_html__( 'Settings', 'html2img' )
			)
		);

		return $links;
	}

	/**
	 * URL of the settings screen.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Register the option and its sanitiser.
	 */
	public static function register_setting() {
		register_setting(
			'html2img',
			Options::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
			]
		);
	}

	/**
	 * Admin CSS and JS for our screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		$ours = [ 'settings_page_' . self::PAGE, 'tools_page_html2img-tools' ];

		if ( ! in_array( $hook, $ours, true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'html2img-admin', HTML2IMG_URL . 'assets/css/admin.css', [], HTML2IMG_VERSION );
		wp_enqueue_script( 'html2img-admin', HTML2IMG_URL . 'assets/js/admin.js', [ 'jquery' ], HTML2IMG_VERSION, true );
		wp_localize_script(
			'html2img-admin',
			'html2imgAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'html2img_admin' ),
				'i18n'    => [
					'testRenderConfirm' => __( 'Render a test image through the API? This uses one credit.', 'html2img' ),
					'rendering'         => __( 'Rendering…', 'html2img' ),
					'failed'            => __( 'The render failed. Check the key and try again.', 'html2img' ),
					'bulkDone'          => __( 'All done.', 'html2img' ),
					'bulkStopped'       => __( 'Stopped. The run can be resumed from here.', 'html2img' ),
					'bulkCredits'       => __( 'Stopped: the account is out of credits. Progress is saved.', 'html2img' ),
				],
			]
		);
	}

	/**
	 * Sanitise and validate submitted settings.
	 *
	 * @param array $input Raw input.
	 * @return array Settings to store.
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : [];
		$current = Options::all();
		$output  = $current;

		// The key field is blank unless the user typed a new one.
		if ( isset( $input['api_key'] ) && '' !== trim( $input['api_key'] ) ) {
			$candidate = sanitize_text_field( trim( $input['api_key'] ) );
			$client    = new Client( $candidate );
			$check     = $client->me();

			if ( $check->success ) {
				$output['api_key'] = $candidate;
				Account::forget();
			} else {
				add_settings_error(
					'html2img',
					'html2img_key',
					sprintf(
						/* translators: %s: error message from the API. */
						__( 'That API key was not accepted: %s The previous key is unchanged.', 'html2img' ),
						$check->message
					)
				);
			}
		}

		if ( isset( $input['remove_key'] ) && '1' === $input['remove_key'] ) {
			$output['api_key'] = '';
			Account::forget();
		}

		$output['post_types'] = [];

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $type ) {
				$type = sanitize_key( $type );

				if ( post_type_exists( $type ) ) {
					$output['post_types'][] = $type;
				}
			}
		}

		$designs          = Designs::all();
		$design           = isset( $input['design'] ) ? sanitize_key( $input['design'] ) : $current['design'];
		$output['design'] = ( 'custom' === $design || isset( $designs[ $design ] ) ) ? $design : 'classic';

		$accent                     = isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : '';
		$background                 = isset( $input['background_color'] ) ? sanitize_hex_color( $input['background_color'] ) : '';
		$output['accent_color']     = $accent ? $accent : $current['accent_color'];
		$output['background_color'] = $background ? $background : $current['background_color'];

		$output['logo_id']             = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;
		$output['show_author']         = ! empty( $input['show_author'] );
		$output['show_site_name']      = ! empty( $input['show_site_name'] );
		$output['custom_template']     = isset( $input['custom_template'] ) ? self::sanitize_template( $input['custom_template'] ) : '';
		$output['storage']             = isset( $input['storage'] ) && 'cdn' === $input['storage'] ? 'cdn' : 'media';
		$output['always_override']     = ! empty( $input['always_override'] );
		$output['show_in_media']       = ! empty( $input['show_in_media'] );
		$output['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] );

		return $output;
	}

	/**
	 * Custom templates come from admins with unfiltered_html in practice,
	 * but scripts still have no business inside a rendered image.
	 *
	 * @param string $template Raw template.
	 * @return string
	 */
	private static function sanitize_template( $template ) {
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return wp_kses_post( $template );
		}

		return (string) preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $template );
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Options::all();
		$account  = Options::has_api_key() ? Account::get() : null;
		$designs  = Designs::all();
		$log      = Render_Log::entries();

		include HTML2IMG_DIR . 'includes/admin/views/settings.php';
	}
}
