<?php
/**
 * Resolves the social image for a post.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Frontend;

use Html2Img\WordPress\Designs\Designs;
use Html2Img\WordPress\Options;
use Html2Img\WordPress\Post_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place that decides which image, if any, the plugin offers a page.
 */
class Social_Image {

	/**
	 * Image details for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{url: string, width: int, height: int}|null Null when the
	 *         plugin should stay out of the way.
	 */
	public static function for_post( $post_id ) {
		if ( ! in_array( get_post_type( $post_id ), Options::post_types(), true ) ) {
			return null;
		}

		if ( Post_Meta::is_disabled( $post_id ) ) {
			return null;
		}

		$url = Post_Meta::image_url( $post_id );

		if ( '' === $url ) {
			return null;
		}

		$dimensions = Designs::dimensions();
		$width      = (int) $dimensions['width'] * (int) $dimensions['dpi'];
		$height     = (int) $dimensions['height'] * (int) $dimensions['dpi'];

		$image_id = Post_Meta::image_id( $post_id );

		if ( $image_id && 'cdn' !== Options::get( 'storage' ) ) {
			$src = wp_get_attachment_image_src( $image_id, 'full' );

			if ( $src ) {
				$width  = (int) $src[1];
				$height = (int) $src[2];
			}
		}

		return [
			'url'    => $url,
			'width'  => $width,
			'height' => $height,
		];
	}

	/**
	 * Whether the generated image should defer to a manually chosen one.
	 *
	 * @param bool $has_manual Whether the active SEO plugin has a manual
	 *                         social image for the post.
	 * @return bool True when the plugin must stay out of the way.
	 */
	public static function defers_to_manual( $has_manual ) {
		if ( ! $has_manual ) {
			return false;
		}

		return ! Options::get( 'always_override' );
	}
}
