<?php
/**
 * Template renderer.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Designs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Substitutes {{placeholders}} into design templates.
 *
 * Supports three constructs and nothing more:
 *   {{token}}            value, escaped according to its type
 *   {{#token}}...{{/token}}  kept only when the value is non-empty
 *   {{^token}}...{{/token}}  kept only when the value is empty
 *
 * Values arrive pre-typed: 'text' is HTML escaped, 'url' is attribute
 * escaped and 'color' must already be a validated hex colour.
 */
class Renderer {

	/**
	 * Token types by name. Anything not listed is treated as text.
	 *
	 * @var array<string, string>
	 */
	private static $types = [
		'featured_image'   => 'url',
		'logo'             => 'url',
		'accent_color'     => 'color',
		'background_color' => 'color',
	];

	/**
	 * Render a template with the given variables.
	 *
	 * @param string $template Template HTML with placeholders.
	 * @param array  $vars     Variable name to string value.
	 * @return string Complete HTML.
	 */
	public static function render( $template, array $vars ) {
		$html = self::sections( $template, $vars );

		foreach ( $vars as $name => $value ) {
			$html = str_replace( '{{' . $name . '}}', self::escape( $name, (string) $value ), $html );
		}

		// Unknown tokens vanish rather than leaking braces into the image.
		$html = (string) preg_replace( '/\{\{[a-z0-9_]+\}\}/', '', $html );

		return $html;
	}

	/**
	 * Resolve conditional sections, innermost first.
	 *
	 * @param string $template Template HTML.
	 * @param array  $vars     Variables.
	 * @return string
	 */
	private static function sections( $template, array $vars ) {
		$pattern = '/\{\{([#^])([a-z0-9_]+)\}\}((?:(?!\{\{[#^][a-z0-9_]+\}\}).)*?)\{\{\/\2\}\}/s';

		while ( preg_match( $pattern, $template ) ) {
			$template = (string) preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $vars ) {
					$present = isset( $vars[ $matches[2] ] ) && '' !== (string) $vars[ $matches[2] ];
					$keep    = ( '#' === $matches[1] ) === $present;

					return $keep ? $matches[3] : '';
				},
				$template
			);
		}

		return $template;
	}

	/**
	 * Escape a value for its slot.
	 *
	 * @param string $name  Token name.
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape( $name, $value ) {
		$type = isset( self::$types[ $name ] ) ? self::$types[ $name ] : 'text';

		if ( 'url' === $type ) {
			// Data URIs are built by the plugin from local files and must
			// survive; anything else gets full attribute escaping.
			if ( 0 === strpos( $value, 'data:image/' ) ) {
				return $value;
			}

			return esc_attr( $value );
		}

		if ( 'color' === $type ) {
			$hex = sanitize_hex_color( $value );

			return $hex ? $hex : '';
		}

		return esc_html( $value );
	}
}
