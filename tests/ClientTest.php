<?php
/**
 * API client error mapping tests.
 *
 * @package Html2Img
 */

use Html2Img\WordPress\Api\Client;
use PHPUnit\Framework\TestCase;

/**
 * Every documented error shape maps to a typed result.
 */
class ClientTest extends TestCase {

	protected function setUp(): void {
		h2i_test_reset();
	}

	private function canned( $status, array $body ) {
		return [
			'response' => [ 'code' => $status ],
			'body'     => json_encode( $body ),
		];
	}

	public function test_missing_key_short_circuits_without_http() {
		$client   = new Client( '' );
		$response = $client->me();

		$this->assertFalse( $response->success );
		$this->assertSame( 'missing_api_key', $response->code );
		$this->assertCount( 0, $GLOBALS['h2i_test']['http_log'] );
	}

	public function test_sends_key_header_and_parses_success() {
		h2i_test_queue_response( $this->canned( 200, [
			'success'           => true,
			'id'                => 'abc',
			'url'               => 'https://i.html2img.com/x.png',
			'credits_remaining' => 41,
			'expires_at'        => null,
		] ) );

		$client   = new Client( 'htim_test' );
		$response = $client->render_html( '<html></html>' );

		$this->assertTrue( $response->success );
		$this->assertSame( 'https://i.html2img.com/x.png', $response->get( 'url' ) );
		$this->assertSame( 41, $response->get( 'credits_remaining' ) );

		$request = $GLOBALS['h2i_test']['http_log'][0];
		$this->assertSame( 'htim_test', $request['args']['headers']['X-API-Key'] );
		$this->assertSame( 'https://app.html2img.com/api/html', $request['url'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame( 1200, $body['width'] );
		$this->assertSame( 630, $body['height'] );
		$this->assertSame( 2, $body['dpi'] );
	}

	public function test_invalid_key_maps_to_code() {
		h2i_test_queue_response( $this->canned( 401, [
			'error' => 'Invalid API key',
			'code'  => 'invalid_api_key',
		] ) );

		$response = ( new Client( 'htim_bad' ) )->me();

		$this->assertFalse( $response->success );
		$this->assertSame( 'invalid_api_key', $response->code );
		$this->assertSame( 401, $response->status );
	}

	public function test_out_of_credits_maps_to_code() {
		h2i_test_queue_response( $this->canned( 402, [
			'error'             => 'Insufficient credits',
			'code'              => 'insufficient_credits',
			'credits_remaining' => 0,
			'message'           => 'You have used your free credits. Upgrade to a paid plan to keep rendering.',
		] ) );

		$response = ( new Client( 'htim_test' ) )->render_html( '<html></html>' );

		$this->assertFalse( $response->success );
		$this->assertTrue( $response->out_of_credits() );
		$this->assertSame( 0, $response->get( 'credits_remaining' ) );
	}

	public function test_not_subscribed_maps_to_code() {
		h2i_test_queue_response( $this->canned( 403, [
			'error' => 'You must be subscribed to use this service',
			'code'  => 'not_subscribed',
		] ) );

		$response = ( new Client( 'htim_test' ) )->render_html( '<html></html>' );

		$this->assertSame( 'not_subscribed', $response->code );
		$this->assertFalse( $response->out_of_credits() );
	}

	public function test_timeout_maps_to_code() {
		h2i_test_queue_response( $this->canned( 504, [
			'error'   => 'Request timed out',
			'code'    => 'timeout_error',
			'message' => 'Render job exceeded the allotted time.',
		] ) );

		$response = ( new Client( 'htim_test' ) )->render_html( '<html></html>' );

		$this->assertSame( 'timeout_error', $response->code );
		$this->assertSame( 'Render job exceeded the allotted time.', $response->message );
	}

	public function test_transport_failure_maps_to_connection_error() {
		h2i_test_queue_response( new WP_Error( 'http_request_failed', 'cURL error 28' ) );

		$response = ( new Client( 'htim_test' ) )->render_html( '<html></html>' );

		$this->assertFalse( $response->success );
		$this->assertSame( 'connection_error', $response->code );
		$this->assertSame( 'cURL error 28', $response->message );
	}

	public function test_unknown_status_gets_fallback_code() {
		h2i_test_queue_response( [
			'response' => [ 'code' => 418 ],
			'body'     => 'not json',
		] );

		$response = ( new Client( 'htim_test' ) )->render_html( '<html></html>' );

		$this->assertSame( 'http_418', $response->code );
	}
}
