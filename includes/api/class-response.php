<?php
/**
 * Typed API result.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result of an API call, successful or not.
 *
 * Callers branch on the machine readable $code rather than matching
 * message strings.
 */
class Response {

	/**
	 * Whether the call succeeded.
	 *
	 * @var bool
	 */
	public $success = false;

	/**
	 * HTTP status code, 0 for transport failures.
	 *
	 * @var int
	 */
	public $status = 0;

	/**
	 * API error code such as insufficient_credits, or empty on success.
	 *
	 * @var string
	 */
	public $code = '';

	/**
	 * Human readable message for logs and notices.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Decoded response body.
	 *
	 * @var array
	 */
	public $data = [];

	/**
	 * Successful response.
	 *
	 * @param array $data   Decoded body.
	 * @param int   $status HTTP status.
	 * @return Response
	 */
	public static function ok( array $data, $status = 200 ) {
		$response          = new self();
		$response->success = true;
		$response->status  = $status;
		$response->data    = $data;

		return $response;
	}

	/**
	 * Failed response.
	 *
	 * @param string $code    Machine readable code.
	 * @param string $message Human readable message.
	 * @param int    $status  HTTP status, 0 for transport failures.
	 * @param array  $data    Decoded body if any.
	 * @return Response
	 */
	public static function error( $code, $message, $status = 0, array $data = [] ) {
		$response          = new self();
		$response->success = false;
		$response->code    = $code;
		$response->message = $message;
		$response->status  = $status;
		$response->data    = $data;

		return $response;
	}

	/**
	 * Convenience field access into the body.
	 *
	 * @param string $key      Body key.
	 * @param mixed  $fallback Value when absent.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $fallback;
	}

	/**
	 * Whether the failure was a lack of credits.
	 *
	 * @return bool
	 */
	public function out_of_credits() {
		return 'insufficient_credits' === $this->code;
	}
}
