<?php
/**
 * Plugin Name: HTML to Image: Dynamic Open Graph Images
 * Plugin URI: https://html2img.com/docs/integrations/wordpress/
 * Description: Generates a unique Open Graph image for every post and page through the HTML to Image API. Pick a design, connect your account and publish.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: HTML to Image
 * Author URI: https://html2img.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: html2img
 *
 * @package Html2Img
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HTML2IMG_VERSION', '1.0.0' );
define( 'HTML2IMG_FILE', __FILE__ );
define( 'HTML2IMG_DIR', plugin_dir_path( __FILE__ ) );
define( 'HTML2IMG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload plugin classes.
 *
 * Maps Html2Img\WordPress\Api\Client to includes/api/class-client.php,
 * following the WordPress file naming convention within a PSR-4 shaped tree.
 *
 * @param string $class_name Fully qualified class name.
 */
spl_autoload_register(
	function ( $class_name ) {
		if ( strpos( $class_name, 'Html2Img\\WordPress\\' ) !== 0 ) {
			return;
		}

		$relative = substr( $class_name, strlen( 'Html2Img\\WordPress\\' ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$path     = strtolower( implode( '/', $parts ) );
		$filename = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		$file     = HTML2IMG_DIR . 'includes/' . ( $path ? $path . '/' : '' ) . $filename;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, [ 'Html2Img\\WordPress\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Html2Img\\WordPress\\Plugin', 'deactivate' ] );

add_action(
	'plugins_loaded',
	function () {
		Html2Img\WordPress\Plugin::instance()->boot();
	}
);
