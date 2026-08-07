<?php
/**
 * HTTP client for the HTML to Image API.
 *
 * @package Html2Img
 */

namespace Html2Img\WordPress\Api;

use Html2Img\WordPress\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper over the WordPress HTTP API.
 *
 * Every call is server side and carries the X-API-Key header. The key
 * never reaches the front end or any script context.
 */
class Client {

	const BASE_URL = 'https://app.html2img.com/api';

	/**
	 * API key for this client.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Key to use, defaults to the saved setting.
	 */
	public function __construct( $api_key = null ) {
		$this->api_key = null === $api_key ? Options::api_key() : $api_key;
	}

	/**
	 * Account status and credit balance. Free to call, never spends a credit.
	 *
	 * @return Response
	 */
	public function me() {
		return $this->request( 'GET', '/me', null, 15 );
	}

	/**
	 * Render an HTML document to a PNG.
	 *
	 * @param string $html Complete HTML document.
	 * @param array  $args Render arguments: width, height, dpi.
	 * @return Response
	 */
	public function render_html( $html, array $args = [] ) {
		$body = array_merge(
			[
				'width'  => 1200,
				'height' => 630,
				'dpi'    => 2,
			],
			$args,
			[ 'html' => $html ]
		);

		return $this->request( 'POST', '/html', $body, 60 );
	}

	/**
	 * Perform a request and normalise the outcome.
	 *
	 * @param string     $method  GET or POST.
	 * @param string     $path    Path under the API base.
	 * @param array|null $body    JSON body for POST.
	 * @param int        $timeout Seconds.
	 * @return Response
	 */
	private function request( $method, $path, $body = null, $timeout = 30 ) {
		if ( '' === trim( $this->api_key ) ) {
			return Response::error( 'missing_api_key', __( 'No API key is set.', 'html2img' ) );
		}

		$args = [
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => [
				'X-API-Key'    => $this->api_key,
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		];

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$raw = wp_remote_request( self::BASE_URL . $path, $args );

		if ( is_wp_error( $raw ) ) {
			return Response::error( 'connection_error', $raw->get_error_message() );
		}

		$status  = (int) wp_remote_retrieve_response_code( $raw );
		$decoded = json_decode( wp_remote_retrieve_body( $raw ), true );
		$decoded = is_array( $decoded ) ? $decoded : [];

		if ( $status >= 200 && $status < 300 ) {
			return Response::ok( $decoded, $status );
		}

		$code    = isset( $decoded['code'] ) ? (string) $decoded['code'] : 'http_' . $status;
		$message = isset( $decoded['message'] ) ? (string) $decoded['message'] : '';

		if ( '' === $message && isset( $decoded['error'] ) ) {
			$message = (string) $decoded['error'];
		}

		if ( '' === $message ) {
			$message = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The API returned HTTP %d.', 'html2img' ),
				$status
			);
		}

		return Response::error( $code, $message, $status, $decoded );
	}
}
