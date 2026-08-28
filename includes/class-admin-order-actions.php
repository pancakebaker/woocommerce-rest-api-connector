<?php
/**
 * Admin-side manual retry.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Admin_Order_Actions {
	private Settings $settings;
	private Logger $logger;

	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function register(): void {
		add_filter( 'woocommerce_order_actions', array( $this, 'add_retry_action' ) );
		add_action( 'woocommerce_order_action_wcrac_retry_sync', array( $this, 'handle_retry_action' ) );
	}

	/**
	 * @param array<string, string> $actions Existing order actions.
	 * @return array<string, string>
	 */
	public function add_retry_action( array $actions ): array {
		global $theorder;

		if ( $theorder instanceof \WC_Order && ! self::is_retryable_status( (string) $theorder->get_meta( Sync_Service::META_STATUS, true ) ) ) {
			return $actions;
		}

		$actions['wcrac_retry_sync'] = __( 'Retry REST API synchronization', 'woocommerce-rest-api-connector' );
		return $actions;
	}

	public function handle_retry_action( \WC_Order $order ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! $this->settings->is_enabled() ) {
			$order->add_order_note( __( 'REST API synchronization is disabled; retry was not scheduled.', 'woocommerce-rest-api-connector' ) );
			return;
		}

		$order_id = (int) $order->get_id();
		$status   = (string) $order->get_meta( Sync_Service::META_STATUS, true );

		if ( ! self::is_retryable_status( $status ) ) {
			$order->add_order_note( __( 'REST API synchronization retry is only available for failed synchronizations.', 'woocommerce-rest-api-connector' ) );
			$this->logger->warning(
				'Manual synchronization retry refused for non-failed order.',
				array(
					'order_id' => $order_id,
					'status'   => $status,
				)
			);
			return;
		}

		$args = array( 'order_id' => $order_id );

		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP ) ) {
			$order->add_order_note( __( 'REST API synchronization retry is already scheduled.', 'woocommerce-rest-api-connector' ) );
			return;
		}

		$action_id = function_exists( 'as_enqueue_async_action' )
			? as_enqueue_async_action( Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP, true )
			: 0;

		if ( $action_id ) {
			$order->update_meta_data( Sync_Service::META_STATUS, 'pending' );
			$order->update_meta_data( Sync_Service::META_ATTEMPT_COUNT, 0 );
			$order->delete_meta_data( Sync_Service::META_LAST_ERROR );
			$order->save();
			$order->add_order_note( __( 'REST API synchronization retry was scheduled as a new controlled attempt cycle.', 'woocommerce-rest-api-connector' ) );
			$this->logger->info( 'Manual order synchronization retry scheduled.', array( 'order_id' => $order_id ) );
			return;
		}

		$order->add_order_note( __( 'REST API synchronization retry could not be scheduled.', 'woocommerce-rest-api-connector' ) );
		$this->logger->error( 'Manual order synchronization retry could not be scheduled.', array( 'order_id' => $order_id ) );
	}

	public static function is_retryable_status( string $status ): bool {
		return 'failed' === $status;
	}
}