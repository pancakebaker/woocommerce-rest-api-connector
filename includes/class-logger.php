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

	/**
	 * @param array<string, mixed> $context Log context.
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$context['source'] = self::SOURCE;
		unset( $context['api_token'], $context['authorization'], $context['payload'] );

		wc_get_logger()->{$level}( $message, $context );
	}
}
