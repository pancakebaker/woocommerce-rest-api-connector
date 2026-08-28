<?php
/**
 * Synchronization service.
 *
 * @package WCRAC
 */

namespace WCRAC;

use Throwable;

defined( 'ABSPATH' ) || exit;

final class Sync_Service {
	public const META_STATUS = '_wcrac_sync_status';
	public const META_ATTEMPT_COUNT = '_wcrac_sync_attempt_count';
	public const META_LAST_SYNC_AT = '_wcrac_last_sync_at';
	public const META_LAST_ERROR = '_wcrac_last_error';
	public const META_EXTERNAL_ID = '_wcrac_external_id';
	public const META_IDEMPOTENCY_KEY = '_wcrac_idempotency_key';

	public const MAX_ATTEMPTS = 3;

	private Settings $settings;
	private Api_Client $api_client;
	private Order_Payload $payload_builder;
	private Logger $logger;

	public function __construct( Settings $settings, Api_Client $api_client, Order_Payload $payload_builder, Logger $logger ) {
		$this->settings        = $settings;
		$this->api_client      = $api_client;
		$this->payload_builder = $payload_builder;
		$this->logger          = $logger;
	}

	public function sync_order( int $order_id ): void {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			$this->logger->error( 'Unable to synchronize missing or invalid WooCommerce order.', array( 'order_id' => $order_id ) );
			return;
		}

		if ( 'synced' === (string) $order->get_meta( self::META_STATUS, true ) ) {
			return;
		}

		$attempt = (int) $order->get_meta( self::META_ATTEMPT_COUNT, true ) + 1;
		$order->update_meta_data( self::META_STATUS, 'processing' );
		$order->update_meta_data( self::META_ATTEMPT_COUNT, $attempt );

		try {
			$idempotency_key = (string) $order->get_meta( self::META_IDEMPOTENCY_KEY, true );
			if ( '' === $idempotency_key ) {
				$idempotency_key = self::generate_idempotency_key( home_url(), $order_id );
				$order->update_meta_data( self::META_IDEMPOTENCY_KEY, $idempotency_key );
			}

			$order->save();

			$result = $this->api_client->send_order( $this->payload_builder->build( $order ), $idempotency_key );
			$this->record_result( $order, $result, $attempt );
		} catch ( Throwable $throwable ) {
			$message = Logger::sanitize_message( 'Unexpected synchronization error: ' . get_class( $throwable ) . ' ' . $throwable->getMessage() );
			$this->logger->error(
				'Unexpected synchronization worker error.',
				array(
					'order_id' => $order_id,
					'attempt'  => $attempt,
					'error'    => $message,
				)
			);
			$this->record_result( $order, Sync_Result::failure( $message, true, 0 ), $attempt );
		}
	}

	public function record_result( \WC_Order $order, Sync_Result $result, int $attempt ): void {
		$order_id = (int) $order->get_id();

		if ( $result->is_success() ) {
			$order->update_meta_data( self::META_STATUS, 'synced' );
			$order->update_meta_data( self::META_LAST_SYNC_AT, gmdate( 'c' ) );
			$order->delete_meta_data( self::META_LAST_ERROR );
			if ( '' !== $result->get_external_id() ) {
				$order->update_meta_data( self::META_EXTERNAL_ID, $result->get_external_id() );
			}
			$order->save();

			$this->logger->info(
				'Order synchronization succeeded.',
				array(
					'order_id'    => $order_id,
					'attempt'     => $attempt,
					'http_status' => $result->get_status_code(),
				)
			);
			return;
		}

		$error = Logger::sanitize_message( $result->get_message() );
		$order->update_meta_data( self::META_LAST_ERROR, $error );

		if ( $result->is_retryable() && $attempt < self::MAX_ATTEMPTS ) {
			if ( $this->schedule_retry( $order_id, $attempt ) ) {
				$order->update_meta_data( self::META_STATUS, 'pending' );
				$order->save();
				return;
			}

			$error = 'Retry scheduling failed; manual retry is required.';
			$order->update_meta_data( self::META_LAST_ERROR, $error );
			$this->logger->error( 'Retry scheduling failed; order marked failed.', array( 'order_id' => $order_id ) );
		}

		$order->update_meta_data( self::META_STATUS, 'failed' );
		$order->save();

		$this->logger->error(
			'Order synchronization failed.',
			array(
				'order_id'    => $order_id,
				'attempt'     => $attempt,
				'http_status' => $result->get_status_code(),
				'error'       => $error,
			)
		);
	}

	public function schedule_retry( int $order_id, int $attempt ): bool {
		$delay = self::retry_delay_for_attempt( $attempt );
		if ( $delay <= 0 ) {
			return false;
		}

		$args = array( 'order_id' => $order_id );

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP ) ) {
			return true;
		}

		$action_id = function_exists( 'as_schedule_single_action' )
			? as_schedule_single_action( time() + $delay, Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP, true )
			: 0;

		if ( $action_id ) {
			$this->logger->warning(
				'Scheduled order synchronization retry.',
				array(
					'order_id'      => $order_id,
					'attempt'       => $attempt,
					'retry_delay_s' => $delay,
				)
			);
			return true;
		}

		$this->logger->error( 'Unable to schedule order synchronization retry.', array( 'order_id' => $order_id ) );
		return false;
	}

	public static function retry_delay_for_attempt( int $attempt ): int {
		return match ( $attempt ) {
			1 => 5 * MINUTE_IN_SECONDS,
			2 => 15 * MINUTE_IN_SECONDS,
			default => 0,
		};
	}

	public static function generate_idempotency_key( string $site_url, int $order_id ): string {
		$site = strtolower( trim( $site_url ) );
		return 'wcrac_' . hash( 'sha256', $site . '|' . $order_id );
	}
}