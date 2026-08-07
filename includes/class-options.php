<?php
/**
 * Settings access.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes the single html2img_settings option.
 */
class Options {

	const OPTION = 'html2img_settings';

	/**
	 * Cached settings for the request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'api_key'             => '',
			'post_types'          => [ 'post', 'page' ],
			'design'              => 'classic',
			'accent_color'        => '#6366f1',
			'background_color'    => '#0b1220',
			'logo_id'             => 0,
			'show_author'         => true,
			'show_site_name'      => true,
			'custom_template'     => '',
			'storage'             => 'media',
			'always_override'     => false,
			'show_in_media'       => false,
			'delete_on_uninstall' => false,
		];
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, [] );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
		}

		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Value when the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Update settings and reset the request cache.
	 *
	 * @param array $values Partial settings to merge in.
	 */
	public static function update( array $values ) {
		$merged = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged );
		self::$cache = $merged;
	}

	/**
	 * The API key, filtered so hosts can inject it from config.
	 *
	 * @return string
	 */
	public static function api_key() {
		/**
		 * Filters the HTML to Image API key.
		 *
		 * @param string $api_key The stored key.
		 */
		return (string) apply_filters( 'html2img_api_key', self::get( 'api_key', '' ) );
	}

	/**
	 * Whether a key is saved.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		return '' !== trim( self::api_key() );
	}

	/**
	 * Post types the plugin generates images for.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		$types = (array) self::get( 'post_types', [ 'post', 'page' ] );

		return array_values( array_filter( $types, 'post_type_exists' ) );
	}
}
