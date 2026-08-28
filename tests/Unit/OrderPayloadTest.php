<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Order_Payload;

if ( ! class_exists( '\WC_Order' ) ) {
	class_alias( FakeOrder::class, '\WC_Order' );
}

final class OrderPayloadTest extends TestCase {
	public function test_maps_order_to_normalized_payload(): void {
		$payload = ( new Order_Payload() )->build( new FakeOrder() );

		self::assertSame( 1234, $payload['order_id'] );
		self::assertSame( '1234', $payload['order_number'] );
		self::assertSame( 'completed', $payload['status'] );
		self::assertSame( 'customer@example.test', $payload['customer']['email'] );
		self::assertSame( 42, $payload['items'][0]['product_id'] );
		self::assertSame( 'ABC-100', $payload['items'][0]['sku'] );
		self::assertSame( 2, $payload['items'][0]['quantity'] );
	}
}

class FakeOrder {
	public function get_id(): int { return 1234; }
	public function get_order_number(): string { return '1234'; }
	public function get_status(): string { return 'completed'; }
	public function get_currency(): string { return 'USD'; }
	public function get_total(): string { return '149.95'; }
	public function get_billing_email(): string { return 'customer@example.test'; }
	public function get_billing_first_name(): string { return 'John'; }
	public function get_billing_last_name(): string { return 'Smith'; }
	public function get_billing_company(): string { return ''; }
	public function get_billing_address_1(): string { return '1 Main St'; }
	public function get_billing_address_2(): string { return ''; }
	public function get_billing_city(): string { return 'Example'; }
	public function get_billing_state(): string { return 'CA'; }
	public function get_billing_postcode(): string { return '90210'; }
	public function get_billing_country(): string { return 'US'; }
	public function get_shipping_first_name(): string { return 'John'; }
	public function get_shipping_last_name(): string { return 'Smith'; }
	public function get_shipping_company(): string { return ''; }
	public function get_shipping_address_1(): string { return '1 Main St'; }
	public function get_shipping_address_2(): string { return ''; }
	public function get_shipping_city(): string { return 'Example'; }
	public function get_shipping_state(): string { return 'CA'; }
	public function get_shipping_postcode(): string { return '90210'; }
	public function get_shipping_country(): string { return 'US'; }
	public function get_items(): array { return array( new FakeOrderItem() ); }
}

class FakeOrderItem {
	public function get_product_id(): int { return 42; }
	public function get_variation_id(): int { return 0; }
	public function get_product(): FakeProduct { return new FakeProduct(); }
	public function get_name(): string { return 'Example Product'; }
	public function get_quantity(): int { return 2; }
	public function get_total(): string { return '99.90'; }
}

class FakeProduct {
	public function get_sku(): string { return 'ABC-100'; }
}
