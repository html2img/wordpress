<?php
/**
 * Design registry.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Designs;

use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundled designs, the custom template and the design fingerprint.
 */
class Designs {

	/**
	 * Bundled designs.
	 *
	 * @return array<string, array{name: string, file: string}>
	 */
	public static function built_in() {
		$base = HTML2IMG_DIR . 'designs/';

		return [
			'classic'   => [
				'name' => __( 'Classic', 'html2img' ),
				'file' => $base . 'classic.html',
			],
			'split'     => [
				'name' => __( 'Split', 'html2img' ),
				'file' => $base . 'split.html',
			],
			'photo'     => [
				'name' => __( 'Photo', 'html2img' ),
				'file' => $base . 'photo.html',
			],
			'editorial' => [
				'name' => __( 'Editorial', 'html2img' ),
				'file' => $base . 'editorial.html',
			],
			'minimal'   => [
				'name' => __( 'Minimal', 'html2img' ),
				'file' => $base . 'minimal.html',
			],
		];
	}

	/**
	 * All designs including any registered through the filter.
	 *
	 * @return array<string, array{name: string, file: string}>
	 */
	public static function all() {
		/**
		 * Filters the available designs.
		 *
		 * Each entry maps a slug to ['name' => label, 'file' => absolute path
		 * to an HTML template using the documented placeholders].
		 *
		 * @param array $designs Registered designs.
		 */
		return (array) apply_filters( 'html2img_designs', self::built_in() );
	}

	/**
	 * The active design slug, 'custom' when the custom template is in use.
	 *
	 * @return string
	 */
	public static function current_slug() {
		$slug = (string) Options::get( 'design', 'classic' );
		$all  = self::all();

		if ( 'custom' !== $slug && ! isset( $all[ $slug ] ) ) {
			$slug = 'classic';
		}

		return $slug;
	}

	/**
	 * Template HTML for a design slug.
	 *
	 * @param string|null $slug Design slug, defaults to the active one.
	 * @return string
	 */
	public static function template_html( $slug = null ) {
		$slug = null === $slug ? self::current_slug() : $slug;

		if ( 'custom' === $slug ) {
			return (string) Options::get( 'custom_template', '' );
		}

		$all = self::all();

		if ( ! isset( $all[ $slug ]['file'] ) || ! file_exists( $all[ $slug ]['file'] ) ) {
			return '';
		}

		return (string) file_get_contents( $all[ $slug ]['file'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file.
	}

	/**
	 * Render dimensions.
	 *
	 * @return array{width: int, height: int, dpi: int}
	 */
	public static function dimensions() {
		/**
		 * Filters the render dimensions sent to the API.
		 *
		 * @param array $dimensions width, height and dpi.
		 */
		return (array) apply_filters(
			'html2img_dimensions',
			[
				'width'  => 1200,
				'height' => 630,
				'dpi'    => 2,
			]
		);
	}

	/**
	 * Fingerprint of the active design and everything that changes its output.
	 *
	 * Posts whose stored fingerprint differs are stale and countable, which
	 * powers the bulk regeneration estimate.
	 *
	 * @return string
	 */
	public static function fingerprint() {
		$logo_id   = (int) Options::get( 'logo_id', 0 );
		$logo_file = $logo_id ? get_attached_file( $logo_id ) : '';
		$logo_time = $logo_file && file_exists( $logo_file ) ? (string) filemtime( $logo_file ) : '';

		$source = [
			'slug'       => self::current_slug(),
			'template'   => self::template_html(),
			'accent'     => (string) Options::get( 'accent_color' ),
			'background' => (string) Options::get( 'background_color' ),
			'logo'       => $logo_id . ':' . $logo_time,
			'author'     => (bool) Options::get( 'show_author' ),
			'site_name'  => (bool) Options::get( 'show_site_name' ),
			'dimensions' => self::dimensions(),
		];

		return hash( 'sha256', (string) wp_json_encode( $source ) );
	}
}
