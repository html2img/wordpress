<?php
/**
 * All in One SEO integration.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Integrations;

use Html2Img\WordPress\Frontend\Social_Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feeds generated images to All in One SEO through its tag array filters.
 */
class Aioseo {

	/**
	 * Register the filters.
	 */
	public static function register() {
		add_filter( 'aioseo_facebook_tags', [ __CLASS__, 'facebook_tags' ] );
		add_filter( 'aioseo_twitter_tags', [ __CLASS__, 'twitter_tags' ] );
	}

	/**
	 * Whether the editor picked a social image in AIOSEO for this post.
	 *
	 * AIOSEO stores per post settings in its own table, reached through its
	 * models. Anything other than the default source counts as a manual
	 * choice. Failures read as no manual image.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_manual_image( $post_id ) {
		if ( ! function_exists( 'aioseo' ) || ! class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			return false;
		}

		try {
			$aioseo_post = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );

			if ( ! $aioseo_post || ! $aioseo_post->exists() ) {
				return false;
			}

			$og_type      = isset( $aioseo_post->og_image_type ) ? (string) $aioseo_post->og_image_type : 'default';
			$twitter_type = isset( $aioseo_post->twitter_image_type ) ? (string) $aioseo_post->twitter_image_type : 'default';
			$twitter_own  = ! empty( $aioseo_post->twitter_use_og ) ? 'default' : $twitter_type;

			return ( 'default' !== $og_type && '' !== $og_type ) || ( 'default' !== $twitter_own && '' !== $twitter_own );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * The image for the current page, or null to leave AIOSEO alone.
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
	 * Override the image keys in the Facebook tag array.
	 *
	 * @param array $tags Tag name to value.
	 * @return array
	 */
	public static function facebook_tags( $tags ) {
		$image = self::image();

		if ( null === $image ) {
			return $tags;
		}

		$tags['og:image']        = $image['url'];
		$tags['og:image:width']  = $image['width'];
		$tags['og:image:height'] = $image['height'];

		if ( isset( $tags['og:image:secure_url'] ) ) {
			$tags['og:image:secure_url'] = $image['url'];
		}

		return $tags;
	}

	/**
	 * Override the image keys in the Twitter tag array.
	 *
	 * @param array $tags Tag name to value.
	 * @return array
	 */
	public static function twitter_tags( $tags ) {
		$image = self::image();

		if ( null === $image ) {
			return $tags;
		}

		$tags['twitter:image'] = $image['url'];
		$tags['twitter:card']  = 'summary_large_image';

		return $tags;
	}
}
