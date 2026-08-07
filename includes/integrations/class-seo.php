<?php
/**
 * SEO plugin detection and dispatch.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Integrations;

use Html2Img\WordPress\Frontend\Meta_Tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exactly one system outputs social tags. An active SEO plugin owns the
 * head and receives our image through its filters; with none active the
 * plugin outputs the tags itself.
 */
class Seo {

	/**
	 * Register whichever integration applies.
	 */
	public static function register() {
		$active = self::active_plugin();

		switch ( $active ) {
			case 'yoast':
				Yoast::register();
				break;
			case 'rankmath':
				Rank_Math::register();
				break;
			case 'aioseo':
				Aioseo::register();
				break;
			case 'seopress':
				Seopress::register();
				break;
			default:
				Meta_Tags::register();
		}
	}

	/**
	 * The active SEO plugin, first match wins.
	 *
	 * @return string yoast, rankmath, aioseo, seopress or empty.
	 */
	public static function active_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}

		if ( class_exists( 'RankMath' ) ) {
			return 'rankmath';
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'seopress';
		}

		return '';
	}

	/**
	 * Label for the settings screen line naming who controls the tags.
	 *
	 * @return string
	 */
	public static function controller_label() {
		$labels = [
			'yoast'    => __( 'Yoast SEO is outputting your social tags. HTML to Image feeds generated images to it.', 'html2img' ),
			'rankmath' => __( 'Rank Math is outputting your social tags. HTML to Image feeds generated images to it.', 'html2img' ),
			'aioseo'   => __( 'All in One SEO is outputting your social tags. HTML to Image feeds generated images to it.', 'html2img' ),
			'seopress' => __( 'SEOPress is outputting your social tags. HTML to Image feeds generated images to it.', 'html2img' ),
		];

		$active = self::active_plugin();

		if ( isset( $labels[ $active ] ) ) {
			return $labels[ $active ];
		}

		return __( 'No SEO plugin detected. HTML to Image outputs the og:image and twitter:image tags itself.', 'html2img' );
	}
}
