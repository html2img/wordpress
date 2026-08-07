<?php
/**
 * Cached account status.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Caches /me in a transient and keeps the balance fresh from render responses.
 */
class Account {

	const TRANSIENT = 'html2img_account';
	const PAUSED    = 'html2img_paused';
	const TTL       = 10 * MINUTE_IN_SECONDS;

	/**
	 * Account data, from cache or the API.
	 *
	 * @param bool $force Skip the cache.
	 * @return array|null Null when the key is missing or invalid.
	 */
	public static function get( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$client   = new Client();
		$response = $client->me();

		if ( ! $response->success ) {
			return null;
		}

		$account = [
			'email'             => (string) $response->get( 'email', '' ),
			'plan'              => (string) $response->get( 'plan', '' ),
			'plan_name'         => (string) $response->get( 'plan_name', '' ),
			'active'            => (bool) $response->get( 'active', false ),
			'free_plan'         => (bool) $response->get( 'free_plan', true ),
			'credits_remaining' => (int) $response->get( 'credits_remaining', 0 ),
			'credits_reset_at'  => (string) $response->get( 'credits_reset_at', '' ),
		];

		set_transient( self::TRANSIENT, $account, self::TTL );

		if ( $account['credits_remaining'] > 0 ) {
			delete_transient( self::PAUSED );
		}

		return $account;
	}

	/**
	 * Overwrite the cached balance from a render response, which returns
	 * credits_remaining on every call.
	 *
	 * @param int $credits_remaining New balance.
	 */
	public static function update_balance( $credits_remaining ) {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			$cached['credits_remaining'] = (int) $credits_remaining;
			set_transient( self::TRANSIENT, $cached, self::TTL );
		}

		if ( (int) $credits_remaining > 0 ) {
			delete_transient( self::PAUSED );
		}
	}

	/**
	 * Stop sending renders until credits are seen again.
	 */
	public static function pause() {
		set_transient( self::PAUSED, time(), HOUR_IN_SECONDS );
	}

	/**
	 * Whether rendering is paused after an out of credits response.
	 *
	 * @return bool
	 */
	public static function is_paused() {
		return false !== get_transient( self::PAUSED );
	}

	/**
	 * Balance threshold below which the low credit notice shows.
	 *
	 * Max of 20 and 10 percent of the plan allowance, parsed from the plan
	 * name, which reads like "1,000 Credits".
	 *
	 * @param array $account Account data.
	 * @return int
	 */
	public static function low_credit_threshold( array $account ) {
		$allowance = (int) str_replace( ',', '', $account['plan_name'] );

		return max( 20, (int) ceil( $allowance / 10 ) );
	}

	/**
	 * Drop the cache, for use after key changes.
	 */
	public static function forget() {
		delete_transient( self::TRANSIENT );
		delete_transient( self::PAUSED );
	}
}
