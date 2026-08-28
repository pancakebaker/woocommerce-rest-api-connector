<?php
/**
 * Plugin Name: WooCommerce REST API Connector
 * Description: Demonstrates asynchronous synchronization of completed WooCommerce orders to a configurable external REST API.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * Author: Technical Sample
 * Text Domain: woocommerce-rest-api-connector
 *
 * @package WCRAC
 */

defined( 'ABSPATH' ) || exit;

define( 'WCRAC_VERSION', '0.1.0' );
define( 'WCRAC_PLUGIN_FILE', __FILE__ );
define( 'WCRAC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCRAC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$wcrac_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wcrac_autoload ) ) {
	require_once $wcrac_autoload;
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'WCRAC\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$parts    = array_map(
				static function ( string $part ): string {
					return strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $part ) );
				},
				explode( '_', $relative )
			);
			$file     = WCRAC_PLUGIN_DIR . 'includes/class-' . implode( '-', $parts ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-warning"><p>%s</p></div>',
						esc_html__( 'WooCommerce REST API Connector requires WooCommerce 8.0 or newer to be active.', 'woocommerce-rest-api-connector' )
					);
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.0', '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					printf(
						'<div class="notice notice-warning"><p>%s</p></div>',
						esc_html__( 'WooCommerce REST API Connector requires WooCommerce 8.0 or newer.', 'woocommerce-rest-api-connector' )
					);
				}
			);
			return;
		}

		\WCRAC\Plugin::instance()->init();
	}
);
