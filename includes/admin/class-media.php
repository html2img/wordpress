<?php
/**
 * Media library behaviour.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Admin;

use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps generated attachments out of the media grid and picker unless the
 * user opted to see them.
 */
class Media {

	/**
	 * Hook the query filters.
	 */
	public static function register() {
		add_filter( 'ajax_query_attachments_args', [ __CLASS__, 'filter_grid' ] );
	}

	/**
	 * Exclude generated images from media queries.
	 *
	 * @param array $args WP_Query arguments for the grid.
	 * @return array
	 */
	public static function filter_grid( $args ) {
		if ( Options::get( 'show_in_media' ) ) {
			return $args;
		}

		$exclusion = [
			'key'     => '_html2img_generated',
			'compare' => 'NOT EXISTS',
		];

		if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
			$args['meta_query'][] = $exclusion;
		} else {
			$args['meta_query'] = [ $exclusion ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- NOT EXISTS on an indexed key, admin grid only.
		}

		return $args;
	}
}
