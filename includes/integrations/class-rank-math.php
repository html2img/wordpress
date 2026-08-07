<?php
/**
 * Rank Math integration.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Integrations;

use Html2Img\WordPress\Frontend\Social_Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feeds generated images to Rank Math through its opengraph filters.
 */
class Rank_Math {

	/**
	 * Register the filters.
	 */
	public static function register() {
		add_filter( 'rank_math/opengraph/facebook/image', [ __CLASS__, 'image_url' ] );
		add_filter( 'rank_math/opengraph/facebook/image_secure_url', [ __CLASS__, 'image_url' ] );
		add_filter( 'rank_math/opengraph/twitter/image', [ __CLASS__, 'image_url' ] );
	}

	/**
	 * Whether the editor picked a social image in Rank Math for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_manual_image( $post_id ) {
		return '' !== (string) get_post_meta( $post_id, 'rank_math_facebook_image', true )
			|| '' !== (string) get_post_meta( $post_id, 'rank_math_twitter_image', true );
	}

	/**
	 * Filter callback for the image URL.
	 *
	 * @param string $url Current value.
	 * @return string
	 */
	public static function image_url( $url ) {
		if ( ! is_singular() ) {
			return $url;
		}

		$post_id = get_queried_object_id();

		if ( Social_Image::defers_to_manual( self::has_manual_image( $post_id ) ) ) {
			return $url;
		}

		$image = Social_Image::for_post( $post_id );

		return null === $image ? $url : $image['url'];
	}
}
