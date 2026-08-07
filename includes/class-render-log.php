<?php
/**
 * Render history.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded log of the last 20 renders, shown on the settings screen.
 */
class Render_Log {

	const OPTION = 'html2img_render_log';
	const LIMIT  = 20;

	/**
	 * Record a render outcome.
	 *
	 * @param int      $post_id Post ID.
	 * @param string   $outcome ok, failed or failed_credits.
	 * @param string   $message Optional detail for failures.
	 * @param int|null $credits Credits remaining after the call, when known.
	 */
	public static function add( $post_id, $outcome, $message = '', $credits = null ) {
		$log = self::entries();

		array_unshift(
			$log,
			[
				'time'    => time(),
				'post_id' => (int) $post_id,
				'title'   => get_the_title( $post_id ),
				'outcome' => $outcome,
				'message' => $message,
				'credits' => $credits,
			]
		);

		update_option( self::OPTION, array_slice( $log, 0, self::LIMIT ), false );
	}

	/**
	 * All entries, newest first.
	 *
	 * @return array
	 */
	public static function entries() {
		$log = get_option( self::OPTION, [] );

		return is_array( $log ) ? $log : [];
	}
}
