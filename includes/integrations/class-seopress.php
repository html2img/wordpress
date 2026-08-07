<?php
/**
 * SEOPress integration.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Integrations;

use Html2Img\WordPress\Frontend\Social_Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Feeds generated images to SEOPress. Its thumb filters receive the whole
 * meta tag string rather than a URL.
 */
class Seopress {

	/**
	 * Register the filters.
	 */
	public static function register() {
		add_filter( 'seopress_social_og_thumb', [ __CLASS__, 'og_markup' ] );
		add_filter( 'seopress_social_twitter_card_thumbnail', [ __CLASS__, 'twitter_markup' ] );
	}

	/**
	 * Whether the editor picked a social image in SEOPress for this post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function has_manual_image( $post_id ) {
		return '' !== (string) get_post_meta( $post_id, '_seopress_social_fb_img', true )
			|| '' !== (string) get_post_meta( $post_id, '_seopress_social_twitter_img', true );
	}

	/**
	 * The image for the current page, or null to leave SEOPress alone.
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
	 * Replace the og:image tag block.
	 *
	 * @param string $markup Current markup.
	 * @return string
	 */
	public static function og_markup( $markup ) {
		$image = self::image();

		if ( null === $image ) {
			return $markup;
		}

		return sprintf( '<meta property="og:image" content="%s" />', esc_url( $image['url'] ) ) . "\n"
			. sprintf( '<meta property="og:image:width" content="%d" />', (int) $image['width'] ) . "\n"
			. sprintf( '<meta property="og:image:height" content="%d" />', (int) $image['height'] ) . "\n";
	}

	/**
	 * Replace the twitter:image tag.
	 *
	 * @param string $markup Current markup.
	 * @return string
	 */
	public static function twitter_markup( $markup ) {
		$image = self::image();

		if ( null === $image ) {
			return $markup;
		}

		return sprintf( '<meta name="twitter:image" content="%s" />', esc_url( $image['url'] ) );
	}
}
