<?php
/**
 * Test bootstrap: minimal WordPress function stubs so the pure logic can be
 * tested without loading WordPress.
 *
 * @package Html2Img
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HTML2IMG_DIR', dirname( __DIR__ ) . '/' );
define( 'HTML2IMG_URL', 'https://example.test/wp-content/plugins/html2img/' );
define( 'HTML2IMG_VERSION', 'test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['h2i_test'] = [
	'options'    => [],
	'transients' => [],
	'http_queue' => [],
	'http_log'   => [],
];

/**
 * Reset the stub state between tests.
 */
function h2i_test_reset() {
	$GLOBALS['h2i_test'] = [
		'options'    => [],
		'transients' => [],
		'http_queue' => [],
		'http_log'   => [],
	];
}

/**
 * Queue a canned HTTP response for the next wp_remote_request call.
 *
 * @param mixed $response Array response or WP_Error.
 */
function h2i_test_queue_response( $response ) {
	$GLOBALS['h2i_test']['http_queue'][] = $response;
}

// Translation stubs.
function __( $text, $domain = 'default' ) { // phpcs:ignore
	return $text;
}
function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore
	return 1 === (int) $number ? $single : $plural;
}

// Escaping stubs matching WordPress behaviour closely enough for assertions.
function esc_html( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) { // phpcs:ignore
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) { // phpcs:ignore
	return filter_var( (string) $url, FILTER_SANITIZE_URL );
}
function sanitize_hex_color( $color ) { // phpcs:ignore
	if ( preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', (string) $color ) ) {
		return $color;
	}

	return null;
}

// Options and transients backed by the state array.
function get_option( $name, $fallback = false ) { // phpcs:ignore
	return array_key_exists( $name, $GLOBALS['h2i_test']['options'] ) ? $GLOBALS['h2i_test']['options'][ $name ] : $fallback;
}
function update_option( $name, $value, $autoload = null ) { // phpcs:ignore
	$GLOBALS['h2i_test']['options'][ $name ] = $value;

	return true;
}
function get_transient( $name ) { // phpcs:ignore
	return array_key_exists( $name, $GLOBALS['h2i_test']['transients'] ) ? $GLOBALS['h2i_test']['transients'][ $name ] : false;
}
function set_transient( $name, $value, $ttl = 0 ) { // phpcs:ignore
	$GLOBALS['h2i_test']['transients'][ $name ] = $value;

	return true;
}
function delete_transient( $name ) { // phpcs:ignore
	unset( $GLOBALS['h2i_test']['transients'][ $name ] );

	return true;
}
function wp_parse_args( $args, $defaults = [] ) { // phpcs:ignore
	return array_merge( $defaults, (array) $args );
}
function apply_filters( $hook, $value ) { // phpcs:ignore
	return $value;
}
function post_type_exists( $type ) { // phpcs:ignore
	return in_array( $type, [ 'post', 'page' ], true );
}
function wp_json_encode( $value ) { // phpcs:ignore
	return json_encode( $value ); // phpcs:ignore
}

// HTTP layer: canned responses queued per test.
class WP_Error { // phpcs:ignore
	/**
	 * Error message.
	 *
	 * @var string
	 */
	private $message;

	public function __construct( $code = '', $message = '' ) { // phpcs:ignore
		$this->message = $message;
	}

	public function get_error_message() { // phpcs:ignore
		return $this->message;
	}
}
function is_wp_error( $thing ) { // phpcs:ignore
	return $thing instanceof WP_Error;
}
function wp_remote_request( $url, $args = [] ) { // phpcs:ignore
	$GLOBALS['h2i_test']['http_log'][] = [
		'url'  => $url,
		'args' => $args,
	];

	if ( empty( $GLOBALS['h2i_test']['http_queue'] ) ) {
		return new WP_Error( 'no_response', 'No canned response queued.' );
	}

	return array_shift( $GLOBALS['h2i_test']['http_queue'] );
}
function wp_remote_retrieve_response_code( $response ) { // phpcs:ignore
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}
function wp_remote_retrieve_body( $response ) { // phpcs:ignore
	return isset( $response['body'] ) ? $response['body'] : '';
}

// Autoload the plugin classes under test.
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
