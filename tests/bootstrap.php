<?php
/**
 * PHPUnit bootstrap with small WordPress/WooCommerce function shims.
 */

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! class_exists( 'WC_Settings_Page' ) ) {
	class WC_Settings_Page {
		public string $id = '';
		public string $label = '';

		public function __construct() {
			$GLOBALS['wcrac_wc_settings_page_constructor_calls'] = ($GLOBALS['wcrac_wc_settings_page_constructor_calls'] ?? 0) + 1;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {}
}

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can() {
		return $GLOBALS['wcrac_current_user_can'] ?? true;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', wp_strip_all_tags( (string) $value ) ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( $url ) {
		$parts = parse_url( (string) $url );
		return isset( $parts['scheme'], $parts['host'] ) && in_array( $parts['scheme'], array( 'http', 'https' ), true );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url ) {
		return parse_url( $url );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['wcrac_test_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value ) {
		$GLOBALS['wcrac_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url() {
		return $GLOBALS['wcrac_home_url'] ?? 'https://store.example.test';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->message = $message;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'wc_get_order' ) ) {
	function wc_get_order( $order_id ) {
		return $GLOBALS['wcrac_test_orders'][ $order_id ] ?? null;
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	function as_has_scheduled_action( $hook, $args = array(), $group = '' ) {
		$key = $hook . '|' . $group . '|' . md5( serialize( $args ) );
		return ! empty( $GLOBALS['wcrac_scheduled_actions'][ $key ] );
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '', $unique = false ) {
		if ( array_key_exists( 'wcrac_as_schedule_return', $GLOBALS ) ) {
			return $GLOBALS['wcrac_as_schedule_return'];
		}
		$key = $hook . '|' . $group . '|' . md5( serialize( $args ) );
		$GLOBALS['wcrac_scheduled_actions'][ $key ] = compact( 'timestamp', 'hook', 'args', 'group', 'unique' );
		return count( $GLOBALS['wcrac_scheduled_actions'] );
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( $hook, $args = array(), $group = '', $unique = false ) {
		if ( array_key_exists( 'wcrac_as_enqueue_return', $GLOBALS ) ) {
			return $GLOBALS['wcrac_as_enqueue_return'];
		}
		$key = $hook . '|' . $group . '|' . md5( serialize( $args ) );
		$GLOBALS['wcrac_scheduled_actions'][ $key ] = compact( 'hook', 'args', 'group', 'unique' );
		return count( $GLOBALS['wcrac_scheduled_actions'] );
	}
}
if ( ! function_exists( 'wp_safe_remote_request' ) ) {
	function wp_safe_remote_request( $url, $args = array() ) {
		return $GLOBALS['wcrac_safe_remote_response'] ?? array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
	}
}