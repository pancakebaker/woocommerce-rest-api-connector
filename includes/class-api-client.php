<?php
/**
 * External API client.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Api_Client {
	/** @var callable|null */
	private $transport;
	private Settings $settings;
	private Logger $logger;

	public function __construct( Settings $settings, Logger $logger, ?callable $transport = null ) {
		$this->settings  = $settings;
		$this->logger    = $logger;
		$this->transport = $transport;
	}

	/**
	 * @param array<string, mixed> $payload Order payload.
	 */
	public function send_order( array $payload, string $idempotency_key ): Sync_Result {
		return $this->request( 'POST', '/api/v1/orders', $payload, $idempotency_key );
	}

	public function test_connection(): Sync_Result {
		return $this->request( 'GET', '/api/v1/health' );
	}

	/**
	 * @param array<string, mixed>|null $body Request body.
	 */
	private function request( string $method, string $path, ?array $body = null, string $idempotency_key = '' ): Sync_Result {
		$base_url = $this->settings->get_base_url();
		$token    = $this->settings->get_api_token();

		if ( '' === $base_url || '' === $token ) {
			return Sync_Result::failure( 'API base URL and token are required.', false, 0 );
		}

		$args = array(
			'method'  => $method,
			'timeout' => $this->settings->get_timeout(),
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( '' !== $idempotency_key ) {
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = $this->dispatch( $base_url . $path, $args );

		if ( is_wp_error( $response ) ) {
			return Sync_Result::failure( $response->get_error_message(), true, 0 );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw    = (string) wp_remote_retrieve_body( $response );
		$data        = '' !== $body_raw ? json_decode( $body_raw, true ) : array();

		if ( '' !== $body_raw && JSON_ERROR_NONE !== json_last_error() ) {
			return Sync_Result::failure( 'Malformed JSON response from external API.', self::is_retryable_status( $status_code ), $status_code );
		}

		if ( $status_code >= 200 && $status_code < 300 ) {
			$external_id = is_array( $data ) && isset( $data['id'] ) ? (string) $data['id'] : '';
			return Sync_Result::success( 'External API request succeeded.', $status_code, $external_id );
		}

		return Sync_Result::failure(
			sprintf( 'External API returned HTTP %d.', $status_code ),
			self::is_retryable_status( $status_code ),
			$status_code
		);
	}

	/**
	 * @param array<string, mixed> $args Request arguments.
	 */
	private function dispatch( string $url, array $args ) {
		if ( null !== $this->transport ) {
			return call_user_func( $this->transport, $url, $args );
		}

		return wp_remote_request( $url, $args );
	}

	public static function is_retryable_status( int $status_code ): bool {
		return 408 === $status_code || 429 === $status_code || $status_code >= 500;
	}
}
