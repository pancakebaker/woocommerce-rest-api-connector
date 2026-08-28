<?php
/**
 * Safe WooCommerce logging wrapper.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Logger {
	private const SOURCE = 'woocommerce-rest-api-connector';

	/**
	 * @param array<string, mixed> $context Log context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context Log context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context Log context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	public static function sanitize_message( string $message, int $max_length = 300 ): string {
		$message = preg_replace( '/Bearer\s+[^\s]+/i', 'Bearer [redacted]', $message ) ?? '';
		$message = preg_replace( '/(api[_-]?token|access[_-]?token|refresh[_-]?token|client[_-]?secret|password)(\s*[=:]\s*)[^\s&]+/i', '$1$2[redacted]', $message ) ?? '';
		$message = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $message ) ?? '';
		$message = trim( $message );

		if ( strlen( $message ) > $max_length ) {
			$message = substr( $message, 0, $max_length - 3 ) . '...';
		}

		return $message;
	}

	/**
	 * @param array<string, mixed> $context Log context.
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$context['source'] = self::SOURCE;
		unset( $context['api_token'], $context['authorization'], $context['Authorization'], $context['payload'], $context['headers'] );

		foreach ( $context as $key => $value ) {
			if ( is_string( $value ) ) {
				$context[ $key ] = self::sanitize_message( $value );
			}
		}

		wc_get_logger()->{$level}( self::sanitize_message( $message ), $context );
	}
}