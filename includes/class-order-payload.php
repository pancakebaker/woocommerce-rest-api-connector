<?php
/**
 * Order payload mapper.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Order_Payload {
	/**
	 * @return array<string, mixed>
	 */
	public function build( \WC_Order $order ): array {
		$items = array();

		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$items[] = array(
				'product_id'   => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'sku'          => $product && is_callable( array( $product, 'get_sku' ) ) ? (string) $product->get_sku() : '',
				'name'         => (string) $item->get_name(),
				'quantity'     => (int) $item->get_quantity(),
				'total'        => (string) $item->get_total(),
			);
		}

		return array(
			'order_id'     => (int) $order->get_id(),
			'order_number' => (string) $order->get_order_number(),
			'status'       => (string) $order->get_status(),
			'currency'     => (string) $order->get_currency(),
			'total'        => (string) $order->get_total(),
			'customer'     => array(
				'email'      => (string) $order->get_billing_email(),
				'first_name' => (string) $order->get_billing_first_name(),
				'last_name'  => (string) $order->get_billing_last_name(),
			),
			'billing'      => array(
				'first_name' => (string) $order->get_billing_first_name(),
				'last_name'  => (string) $order->get_billing_last_name(),
				'company'    => (string) $order->get_billing_company(),
				'address_1'  => (string) $order->get_billing_address_1(),
				'address_2'  => (string) $order->get_billing_address_2(),
				'city'       => (string) $order->get_billing_city(),
				'state'      => (string) $order->get_billing_state(),
				'postcode'   => (string) $order->get_billing_postcode(),
				'country'    => (string) $order->get_billing_country(),
			),
			'shipping'     => array(
				'first_name' => (string) $order->get_shipping_first_name(),
				'last_name'  => (string) $order->get_shipping_last_name(),
				'company'    => (string) $order->get_shipping_company(),
				'address_1'  => (string) $order->get_shipping_address_1(),
				'address_2'  => (string) $order->get_shipping_address_2(),
				'city'       => (string) $order->get_shipping_city(),
				'state'      => (string) $order->get_shipping_state(),
				'postcode'   => (string) $order->get_shipping_postcode(),
				'country'    => (string) $order->get_shipping_country(),
			),
			'items'        => $items,
		);
	}
}
