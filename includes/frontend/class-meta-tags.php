<?php
/**
 * Direct meta tag output.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs og:image and twitter:image tags when no SEO plugin is active.
 */
class Meta_Tags {

	/**
	 * Guard against printing twice however often wp_head runs.
	 *
	 * @var bool
	 */
	private static $printed = false;

	/**
	 * Hook the output.
	 */
	public static function register() {
		add_action( 'wp_head', [ __CLASS__, 'output' ], 5 );
	}

	/**
	 * Print the tags for singular views of enabled post types.
	 */
	public static function output() {
		if ( self::$printed || ! is_singular() ) {
			return;
		}

		$image = Social_Image::for_post( get_queried_object_id() );

		if ( null === $image ) {
			return;
		}

		self::$printed = true;

		echo self::markup( $image, get_the_title( get_queried_object_id() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside markup().
	}

	/**
	 * The tag markup, escaped and testable.
	 *
	 * @param array  $image url, width and height.
	 * @param string $alt   Alt text.
	 * @return string
	 */
	public static function markup( array $image, $alt = '' ) {
		$lines = [
			sprintf( '<meta property="og:image" content="%s" />', esc_url( $image['url'] ) ),
			sprintf( '<meta property="og:image:width" content="%d" />', (int) $image['width'] ),
			sprintf( '<meta property="og:image:height" content="%d" />', (int) $image['height'] ),
			'<meta property="og:image:type" content="image/png" />',
		];

		if ( '' !== $alt ) {
			$lines[] = sprintf( '<meta property="og:image:alt" content="%s" />', esc_attr( $alt ) );
		}

		$lines[] = '<meta name="twitter:card" content="summary_large_image" />';
		$lines[] = sprintf( '<meta name="twitter:image" content="%s" />', esc_url( $image['url'] ) );

		return "\n" . implode( "\n", $lines ) . "\n";
	}
}
