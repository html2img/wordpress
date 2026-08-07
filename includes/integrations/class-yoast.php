<?php
/**
 * Yoast SEO integration.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Integrations;

use Html2Img\WordPress\Frontend\Social_Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feeds generated images to Yoast SEO through its presenter filters.
 */
class Yoast {

	/**
	 * Register the filters.
	 */
	public static function register() {
		add_filter( 'wpseo_opengraph_image', [ __CLASS__, 'image_url' ] );
		add_filter( 'wpseo_twitter_image', [ __CLASS__, 'image_url' ] );
		add_filter( 'wpseo_opengraph_image_width', [ __CLASS__, 'image_width' ] );
		add_filter( 'wpseo_opengraph_image_height', [ __CLASS__, 'image_height' ] );
		add_filter( 'wpseo_opengraph_image_type', [ __CLASS__, 'image_type' ] );
	}

	/**
	 * Filter callback for og:image:type. The generated image is always PNG.
	 *
	 * @param string $type Current value.
	 * @return string
	 */
	public static function image_type( $type ) {
		return null === self::image() ? $type : 'image/png';
	}

	/**
	 * The image the current page should use, when we have a say.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	private static function image() {
		if ( ! is_singular() ) {
			return null;
		}

		$post_id = get_queried_object_id();

		if ( Social_Image::defers_to_manual( self::has_manual_image( $post_id ) ) ) {
			return null;
		}

		return Social_Image::for_post( $post_id );
	}

	/**
	 * Whether the editor picked a social image in Yoast for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_manual_image( $post_id ) {
		return '' !== (string) get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true )
			|| '' !== (string) get_post_meta( $post_id, '_yoast_wpseo_twitter-image', true );
	}

	/**
	 * Filter callback for the image URL.
	 *
	 * @param string $url Current value.
	 * @return string
	 */
	public static function image_url( $url ) {
		$image = self::image();

		return null === $image ? $url : $image['url'];
	}

	/**
	 * Filter callback for og:image:width.
	 *
	 * @param mixed $width Current value.
	 * @return mixed
	 */
	public static function image_width( $width ) {
		$image = self::image();

		return null === $image ? $width : $image['width'];
	}

	/**
	 * Filter callback for og:image:height.
	 *
	 * @param mixed $height Current value.
	 * @return mixed
	 */
	public static function image_height( $height ) {
		$image = self::image();

		return null === $image ? $height : $image['height'];
	}
}
