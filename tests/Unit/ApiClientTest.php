<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Api_Client;
use WCRAC\Logger;
use WCRAC\Settings;

final class ApiClientTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcrac_test_options'] = array(
			'wcrac_settings' => array(
				'enabled'   => 'yes',
				'base_url'  => 'https://api.example.test',
				'api_token' => 'test-token',
				'timeout'   => 15,
			),
		);
	}

	public function test_retryable_status_classification(): void {
		self::assertTrue( Api_Client::is_retryable_status( 408 ) );
		self::assertTrue( Api_Client::is_retryable_status( 429 ) );
		self::assertTrue( Api_Client::is_retryable_status( 500 ) );
		self::assertTrue( Api_Client::is_retryable_status( 503 ) );

		self::assertFalse( Api_Client::is_retryable_status( 400 ) );
		self::assertFalse( Api_Client::is_retryable_status( 401 ) );
		self::assertFalse( Api_Client::is_retryable_status( 404 ) );
		self::assertFalse( Api_Client::is_retryable_status( 409 ) );
	}

	public function test_send_order_returns_success_and_external_id(): void {
		$client = $this->clientWithResponse(
			array(
				'response' => array( 'code' => 201 ),
				'body'     => '{"id":"external-123"}',
			)
		);

		$result = $client->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertTrue( $result->is_success() );
		self::assertSame( 201, $result->get_status_code() );
		self::assertSame( 'external-123', $result->get_external_id() );
	}

	public function test_empty_success_response_is_accepted(): void {
		$result = $this->clientWithResponse(
			array(
				'response' => array( 'code' => 204 ),
				'body'     => '',
			)
		)->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertTrue( $result->is_success() );
		self::assertSame( 204, $result->get_status_code() );
		self::assertSame( '', $result->get_external_id() );
	}

	public function test_malformed_non_empty_success_response_is_contract_failure_not_retryable(): void {
		$result = $this->clientWithResponse(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '{bad-json',
			)
		)->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertFalse( $result->is_success() );
		self::assertFalse( $result->is_retryable() );
		self::assertSame( 200, $result->get_status_code() );
	}

	public function test_server_error_is_retryable(): void {
		$result = $this->clientWithResponse(
			array(
				'response' => array( 'code' => 503 ),
				'body'     => '{"error":"unavailable"}',
			)
		)->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertFalse( $result->is_success() );
		self::assertTrue( $result->is_retryable() );
		self::assertSame( 503, $result->get_status_code() );
	}

	public function test_client_error_is_not_retryable(): void {
		$result = $this->clientWithResponse(
			array(
				'response' => array( 'code' => 400 ),
				'body'     => '{"error":"bad request"}',
			)
		)->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertFalse( $result->is_success() );
		self::assertFalse( $result->is_retryable() );
		self::assertSame( 400, $result->get_status_code() );
	}

	public function test_network_error_is_retryable_and_sanitized(): void {
		$client = $this->clientWithResponse( new \WP_Error( 'http_request_failed', "Connection timed out.\nAuthorization: Bearer secret-token" ) );
		$result = $client->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertFalse( $result->is_success() );
		self::assertTrue( $result->is_retryable() );
		self::assertSame( 0, $result->get_status_code() );
		self::assertStringNotContainsString( 'secret-token', $result->get_message() );
	}

	public function test_rejects_unsafe_configured_url_before_transport(): void {
		$GLOBALS['wcrac_test_options']['wcrac_settings']['base_url'] = 'http://api.example.test';
		$called = false;
		$client = new Api_Client(
			new Settings(),
			new Logger(),
			static function () use ( &$called ) {
				$called = true;
				return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
			}
		);

		$result = $client->send_order( array( 'order_id' => 123 ), 'idem-key' );

		self::assertFalse( $result->is_success() );
		self::assertFalse( $result->is_retryable() );
		self::assertFalse( $called );
	}

	private function clientWithResponse( $response ): Api_Client {
		return new Api_Client(
			new Settings(),
			new Logger(),
			static function () use ( $response ) {
				return $response;
			}
		);
	}
}