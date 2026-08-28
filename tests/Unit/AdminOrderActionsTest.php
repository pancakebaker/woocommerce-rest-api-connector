<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Admin_Order_Actions;
use WCRAC\Logger;
use WCRAC\Settings;
use WCRAC\Sync_Service;

final class AdminOrderActionsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcrac_test_options'] = array(
			'wcrac_settings' => array(
				'enabled'   => 'yes',
				'base_url'  => 'https://api.example.test',
				'api_token' => 'test-token',
				'timeout'   => 15,
			),
		);
		$GLOBALS['wcrac_scheduled_actions'] = array();
		unset( $GLOBALS['wcrac_as_enqueue_return'] );
	}

	/**
	 * @dataProvider refusedStatuses
	 */
	public function test_manual_retry_rejects_non_failed_statuses( string $status ): void {
		$order = new FakeAdminOrder( 321 );
		$order->update_meta_data( Sync_Service::META_STATUS, $status );
		$order->update_meta_data( Sync_Service::META_ATTEMPT_COUNT, 3 );

		$this->actions()->handle_retry_action( $order );

		self::assertSame( $status, $order->get_meta( Sync_Service::META_STATUS, true ) );
		self::assertSame( 3, $order->get_meta( Sync_Service::META_ATTEMPT_COUNT, true ) );
		self::assertSame( array(), $GLOBALS['wcrac_scheduled_actions'] );
	}

	public function test_retry_action_is_only_exposed_for_failed_order(): void {
		global $theorder;

		$theorder = new FakeAdminOrder( 321 );
		$theorder->update_meta_data( Sync_Service::META_STATUS, 'synced' );
		self::assertArrayNotHasKey( 'wcrac_retry_sync', $this->actions()->add_retry_action( array() ) );

		$theorder->update_meta_data( Sync_Service::META_STATUS, 'failed' );
		self::assertArrayHasKey( 'wcrac_retry_sync', $this->actions()->add_retry_action( array() ) );
	}

	public function test_manual_retry_accepts_failed_order_resets_attempts_and_preserves_idempotency_key(): void {
		$order = new FakeAdminOrder( 321 );
		$order->update_meta_data( Sync_Service::META_STATUS, 'failed' );
		$order->update_meta_data( Sync_Service::META_ATTEMPT_COUNT, 3 );
		$order->update_meta_data( Sync_Service::META_LAST_ERROR, 'previous error' );
		$order->update_meta_data( Sync_Service::META_IDEMPOTENCY_KEY, 'stable-key' );

		$this->actions()->handle_retry_action( $order );

		self::assertSame( 'pending', $order->get_meta( Sync_Service::META_STATUS, true ) );
		self::assertSame( 0, $order->get_meta( Sync_Service::META_ATTEMPT_COUNT, true ) );
		self::assertSame( '', $order->get_meta( Sync_Service::META_LAST_ERROR, true ) );
		self::assertSame( 'stable-key', $order->get_meta( Sync_Service::META_IDEMPOTENCY_KEY, true ) );
		self::assertNotSame( array(), $GLOBALS['wcrac_scheduled_actions'] );
	}

	public static function refusedStatuses(): array {
		return array(
			array( 'synced' ),
			array( 'pending' ),
			array( 'processing' ),
			array( '' ),
		);
	}

	private function actions(): Admin_Order_Actions {
		return new Admin_Order_Actions( new Settings(), new Logger() );
	}
}

class FakeAdminOrder extends \WC_Order {
	private int $id;
	private array $meta = array();
	public array $notes = array();

	public function __construct( int $id ) { $this->id = $id; }
	public function get_id(): int { return $this->id; }
	public function get_meta( string $key, bool $single = true ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
	public function delete_meta_data( string $key ): void { unset( $this->meta[ $key ] ); }
	public function save(): void {}
	public function add_order_note( string $note ): void { $this->notes[] = $note; }
}