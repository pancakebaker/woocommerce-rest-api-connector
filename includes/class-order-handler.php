<?php
/**
 * Completed order listener.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Order_Handler {
	private Settings $settings;
	private Logger $logger;

	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	public function register(): void {
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_completed_order' ), 10, 1 );
	}

	public function handle_completed_order( int $order_id ): void {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			$this->logger->warning( 'Completed order hook received an invalid order ID.', array( 'order_id' => $order_id ) );
			return;
		}

		$status = (string) $order->get_meta( Sync_Service::META_STATUS, true );
		if ( in_array( $status, array( 'pending', 'processing', 'synced' ), true ) ) {
			return;
		}

		$args = array( 'order_id' => $order_id );
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP ) ) {
			return;
		}

		$action_id = function_exists( 'as_enqueue_async_action' )
			? as_enqueue_async_action( Plugin::ACTION_SYNC_ORDER, $args, Plugin::ACTION_GROUP, true )
			: 0;

		if ( $action_id ) {
			$order->update_meta_data( Sync_Service::META_STATUS, 'pending' );
			$order->save();
			$this->logger->info( 'Scheduled completed order synchronization.', array( 'order_id' => $order_id ) );
			return;
		}

		$this->logger->error( 'Unable to schedule completed order synchronization.', array( 'order_id' => $order_id ) );
	}
}
