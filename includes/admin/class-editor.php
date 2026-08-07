<?php
/**
 * Editor panel, metabox and their ajax endpoints.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Api\Client;
use Html2Img\WordPress\Designs\Designs;
use Html2Img\WordPress\Designs\Renderer;
use Html2Img\WordPress\Generation\Generator;
use Html2Img\WordPress\Generation\Payload;
use Html2Img\WordPress\Generation\Queue;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Everything the edit screens need.
 */
class Editor {

	/**
	 * Hook the editor UI.
	 */
	public static function register() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'block_editor_assets' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_metabox' ] );

		add_action( 'wp_ajax_html2img_status', [ __CLASS__, 'ajax_status' ] );
		add_action( 'wp_ajax_html2img_regenerate', [ __CLASS__, 'ajax_regenerate' ] );
		add_action( 'wp_ajax_html2img_run_now', [ __CLASS__, 'ajax_run_now' ] );
		add_action( 'wp_ajax_html2img_toggle', [ __CLASS__, 'ajax_toggle' ] );
		add_action( 'wp_ajax_html2img_preview', [ __CLASS__, 'ajax_preview' ] );
		add_action( 'wp_ajax_html2img_test_render', [ __CLASS__, 'ajax_test_render' ] );
	}

	/**
	 * Gutenberg sidebar assets, only for enabled post types.
	 */
	public static function block_editor_assets() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, Options::post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'html2img-editor',
			HTML2IMG_URL . 'assets/js/editor-panel.js',
			[ 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ],
			HTML2IMG_VERSION,
			true
		);

		wp_localize_script( 'html2img-editor', 'html2imgEditor', self::js_config() );
	}

	/**
	 * Shared config for the editor scripts.
	 *
	 * @return array
	 */
	private static function js_config() {
		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'html2img_editor' ),
			'hasKey'  => Options::has_api_key(),
			'settingsUrl' => Settings_Page::url(),
		];
	}

	/**
	 * Classic editor metabox for enabled post types.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function add_metabox( $post_type ) {
		if ( ! in_array( $post_type, Options::post_types(), true ) ) {
			return;
		}

		if ( get_current_screen() && get_current_screen()->is_block_editor() ) {
			return;
		}

		add_meta_box(
			'html2img',
			__( 'OG Image', 'html2img' ),
			[ __CLASS__, 'render_metabox' ],
			$post_type,
			'side'
		);
	}

	/**
	 * Metabox markup. The JS drives it through the same ajax endpoints as
	 * the Gutenberg panel.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function render_metabox( $post ) {
		wp_enqueue_script(
			'html2img-metabox',
			HTML2IMG_URL . 'assets/js/metabox.js',
			[],
			HTML2IMG_VERSION,
			true
		);
		wp_localize_script( 'html2img-metabox', 'html2imgEditor', self::js_config() );
		?>
		<div id="html2img-metabox" data-post="<?php echo esc_attr( $post->ID ); ?>">
			<div class="html2img-metabox-body"><em><?php esc_html_e( 'Loading…', 'html2img' ); ?></em></div>
		</div>
		<?php
	}

	/**
	 * Current state of a post's OG image, polled by the panel.
	 */
	public static function ajax_status() {
		$post_id = self::guard();

		wp_send_json_success( self::status_payload( $post_id ) );
	}

	/**
	 * Queue a fresh render for a post.
	 */
	public static function ajax_regenerate() {
		$post_id = self::guard();

		delete_post_meta( $post_id, Post_Meta::CONTENT_HASH );
		Queue::queue( $post_id );

		wp_send_json_success( self::status_payload( $post_id ) );
	}

	/**
	 * Render synchronously, the fallback when wp-cron never picked the job
	 * up and the direct path for Regenerate on sites without loopback.
	 */
	public static function ajax_run_now() {
		$post_id = self::guard();

		Generator::render( $post_id, true );

		wp_send_json_success( self::status_payload( $post_id ) );
	}

	/**
	 * Toggle per-post generation.
	 */
	public static function ajax_toggle() {
		$post_id = self::guard();

		if ( Post_Meta::is_disabled( $post_id ) ) {
			delete_post_meta( $post_id, Post_Meta::DISABLED );
		} else {
			update_post_meta( $post_id, Post_Meta::DISABLED, '1' );
		}

		wp_send_json_success( self::status_payload( $post_id ) );
	}

	/**
	 * Browser preview of the active design with real post data, or sample
	 * data on the settings screen. Free, no API call.
	 */
	public static function ajax_preview() {
		check_ajax_referer( 'html2img_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		$variables = self::preview_variables();

		// Unsaved design choices from the settings screen apply to the
		// preview so people see what they are about to save.
		$design = isset( $_GET['design'] ) ? sanitize_key( wp_unslash( $_GET['design'] ) ) : null;

		if ( isset( $_GET['accent'] ) ) {
			$accent = sanitize_hex_color( wp_unslash( $_GET['accent'] ) );

			if ( $accent ) {
				$variables['accent_color'] = $accent;
			}
		}

		if ( isset( $_GET['background'] ) ) {
			$background = sanitize_hex_color( wp_unslash( $_GET['background'] ) );

			if ( $background ) {
				$variables['background_color'] = $background;
			}
		}

		$template = Designs::template_html( $design );
		$html     = Renderer::render( $template, $variables );

		header( 'Content-Type: text/html; charset=utf-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete HTML document with individually escaped variables, served for the sandboxed preview iframe.
		exit;
	}

	/**
	 * One real render of sample data, for the parity check next to the
	 * browser preview. Spends a credit and says so in the UI.
	 */
	public static function ajax_test_render() {
		check_ajax_referer( 'html2img_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( -1, 403 );
		}

		$design   = isset( $_POST['design'] ) ? sanitize_key( wp_unslash( $_POST['design'] ) ) : null;
		$template = Designs::template_html( $design );
		$html     = Renderer::render( $template, self::preview_variables() );

		$client   = new Client();
		$response = $client->render_html( $html, Designs::dimensions() );

		if ( ! $response->success ) {
			wp_send_json_error( [ 'message' => $response->message ] );
		}

		wp_send_json_success(
			[
				'url'     => (string) $response->get( 'url', '' ),
				'credits' => $response->get( 'credits_remaining' ),
			]
		);
	}

	/**
	 * Variables for previews: the most recent published post, else samples.
	 *
	 * @return array
	 */
	private static function preview_variables() {
		$recent = get_posts(
			[
				'post_type'   => Options::post_types(),
				'post_status' => 'publish',
				'numberposts' => 1,
			]
		);

		if ( ! empty( $recent ) ) {
			return Payload::variables( $recent[0]->ID );
		}

		$sample_title = __( 'A headline long enough to show how your card wraps across lines', 'html2img' );

		return [
			'title'            => $sample_title,
			'title_class'      => Payload::title_class( $sample_title ),
			'site_name'        => get_bloginfo( 'name' ),
			'tagline'          => get_bloginfo( 'description' ),
			'author'           => Options::get( 'show_author' ) ? __( 'Sam Author', 'html2img' ) : '',
			'excerpt'          => __( 'A short excerpt showing where supporting copy sits in this design.', 'html2img' ),
			'date'             => date_i18n( get_option( 'date_format' ) ),
			'featured_image'   => '',
			'logo'             => Payload::image_source( (int) Options::get( 'logo_id', 0 ) ),
			'accent_color'     => (string) Options::get( 'accent_color' ),
			'background_color' => (string) Options::get( 'background_color' ),
		];
	}

	/**
	 * Common request validation for the per-post endpoints.
	 *
	 * @return int Post ID.
	 */
	private static function guard() {
		check_ajax_referer( 'html2img_editor', 'nonce' );

		$post_id = isset( $_REQUEST['post_id'] ) ? absint( $_REQUEST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( -1, 403 );
		}

		return $post_id;
	}

	/**
	 * The state object the panel and metabox render from.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function status_payload( $post_id ) {
		$image_url    = Post_Meta::image_url( $post_id );
		$generated_at = (int) get_post_meta( $post_id, Post_Meta::GENERATED_AT, true );
		$status       = (string) get_post_meta( $post_id, Post_Meta::STATUS, true );
		$error        = (string) get_post_meta( $post_id, Post_Meta::ERROR, true );

		return [
			'status'      => $status ? $status : ( '' !== $image_url ? 'ok' : 'none' ),
			'disabled'    => Post_Meta::is_disabled( $post_id ),
			'imageUrl'    => $image_url,
			'generatedAt' => $generated_at ? sprintf(
				/* translators: %s: human readable time difference. */
				__( '%s ago', 'html2img' ),
				human_time_diff( $generated_at )
			) : '',
			'error'       => $error,
			'stuck'       => Queue::is_stuck( $post_id ),
		];
	}
}
