<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Api_Client;
use WCRAC\Logger;
use WCRAC\Order_Payload;
use WCRAC\Settings;
use WCRAC\Sync_Result;
use WCRAC\Sync_Service;

final class SyncServiceTest extends TestCase {
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
		unset( $GLOBALS['wcrac_as_schedule_return'], $GLOBALS['wcrac_as_enqueue_return'], $GLOBALS['wcrac_test_orders'] );
	}

	public function test_generates_stable_site_scoped_idempotency_key(): void {
		$key_one = Sync_Service::generate_idempotency_key( 'https://store.example.test', 123 );
		$key_two = Sync_Service::generate_idempotency_key( 'https://store.example.test', 123 );
		$other   = Sync_Service::generate_idempotency_key( 'https://another-store.example.test', 123 );

		self::assertSame( $key_one, $key_two );
		self::assertNotSame( $key_one, $other );
		self::assertStringStartsWith( 'wcrac_', $key_one );
	}

	public function test_retry_delays_follow_configured_backoff_without_unreachable_extra_delay(): void {
		self::assertSame( 300, Sync_Service::retry_delay_for_attempt( 1 ) );
		self::assertSame( 900, Sync_Service::retry_delay_for_attempt( 2 ) );
		self::assertSame( 0, Sync_Service::retry_delay_for_attempt( 3 ) );
	}

	public function test_retry_scheduling_failure_marks_failed_not_pending(): void {
		$GLOBALS['wcrac_as_schedule_return'] = 0;
		$order = new FakeSyncOrder( 123 );
		$service = $this->serviceWithResponse( array( 'response' => array( 'code' => 503 ), 'body' => '{}' ) );

		$service->record_result( $order, Sync_Result::failure( 'HTTP 503', true, 503 ), 1 );

		self::assertSame( 'failed', $order->get_meta( Sync_Service::META_STATUS, true ) );
		self::assertSame( 'Retry scheduling failed; manual retry is required.', $order->get_meta( Sync_Service::META_LAST_ERROR, true ) );
	}

	public function test_max_three_automated_attempts_per_cycle(): void {
		$service = $this->serviceWithResponse( array( 'response' => array( 'code' => 503 ), 'body' => '{}' ) );
		$order = new FakeSyncOrder( 123 );

		$service->record_result( $order, Sync_Result::failure( 'HTTP 503', true, 503 ), 1 );
		self::assertSame( 'pending', $order->get_meta( Sync_Service::META_STATUS, true ) );

		$service->record_result( $order, Sync_Result::failure( 'HTTP 503', true, 503 ), 2 );
		self::assertSame( 'pending', $order->get_meta( Sync_Service::META_STATUS, true ) );

		$service->record_result( $order, Sync_Result::failure( 'HTTP 503', true, 503 ), 3 );
		self::assertSame( 'failed', $order->get_meta( Sync_Service::META_STATUS, true ) );
	}

	public function test_unexpected_worker_exception_exits_processing_state(): void {
		$GLOBALS['wcrac_as_schedule_return'] = 0;
		$order = new FakeSyncOrder( 123 );
		$GLOBALS['wcrac_test_orders'] = array( 123 => $order );

		$client = new Api_Client(
			new Settings(),
			new Logger(),
			static function (): void {
				throw new \RuntimeException( "Boom\nBearer secret-token" );
			}
		);

		$service = new Sync_Service( new Settings(), $client, new Order_Payload(), new Logger() );
		$service->sync_order( 123 );

		self::assertSame( 'failed', $order->get_meta( Sync_Service::META_STATUS, true ) );
		self::assertStringNotContainsString( 'secret-token', $order->get_meta( Sync_Service::META_LAST_ERROR, true ) );
	}

	private function serviceWithResponse( $response ): Sync_Service {
		$client = new Api_Client(
			new Settings(),
			new Logger(),
			static function () use ( $response ) {
				return $response;
			}
		);

		return new Sync_Service( new Settings(), $client, new Order_Payload(), new Logger() );
	}
}

class FakeSyncOrder extends \WC_Order {
	private int $id;
	private array $meta = array();

	public function __construct( int $id ) {
		$this->id = $id;
	}

	public function get_id(): int { return $this->id; }
	public function get_meta( string $key, bool $single = true ) { return $this->meta[ $key ] ?? ''; }
	public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
	public function delete_meta_data( string $key ): void { unset( $this->meta[ $key ] ); }
	public function save(): void {}
	public function get_items(): array { return array(); }
	public function get_order_number(): string { return (string) $this->id; }
	public function get_status(): string { return 'completed'; }
	public function get_currency(): string { return 'USD'; }
	public function get_total(): string { return '10.00'; }
	public function get_billing_email(): string { return 'customer@example.test'; }
	public function get_billing_first_name(): string { return 'John'; }
	public function get_billing_last_name(): string { return 'Smith'; }
	public function get_billing_company(): string { return ''; }
	public function get_billing_address_1(): string { return ''; }
	public function get_billing_address_2(): string { return ''; }
	public function get_billing_city(): string { return ''; }
	public function get_billing_state(): string { return ''; }
	public function get_billing_postcode(): string { return ''; }
	public function get_billing_country(): string { return 'US'; }
	public function get_shipping_first_name(): string { return 'John'; }
	public function get_shipping_last_name(): string { return 'Smith'; }
	public function get_shipping_company(): string { return ''; }
	public function get_shipping_address_1(): string { return ''; }
	public function get_shipping_address_2(): string { return ''; }
	public function get_shipping_city(): string { return ''; }
	public function get_shipping_state(): string { return ''; }
	public function get_shipping_postcode(): string { return ''; }
	public function get_shipping_country(): string { return 'US'; }
}