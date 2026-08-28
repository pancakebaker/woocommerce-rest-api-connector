<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Settings;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcrac_wc_settings_page_constructor_calls'] = 0;
		$GLOBALS['wcrac_test_options'] = array(
			'wcrac_settings' => array(
				'api_token' => 'existing-token',
			),
		);
	}

	public function test_settings_constructor_calls_woocommerce_parent_constructor(): void {
		new Settings();

		self::assertSame( 1, $GLOBALS['wcrac_wc_settings_page_constructor_calls'] );
	}

	public function test_sanitizes_settings_and_preserves_existing_token_when_blank(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize_settings(
			array(
				'enabled'   => '1',
				'base_url'  => 'https://api.example.test/',
				'api_token' => '',
				'timeout'   => 99,
			)
		);

		self::assertSame( 'yes', $clean['enabled'] );
		self::assertSame( 'https://api.example.test', $clean['base_url'] );
		self::assertSame( 'existing-token', $clean['api_token'] );
		self::assertSame( 60, $clean['timeout'] );
	}

	public function test_masked_token_placeholder_does_not_overwrite_existing_token(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize_settings(
			array(
				'base_url'  => 'https://api.example.test',
				'api_token' => '********',
			)
		);

		self::assertSame( 'existing-token', $clean['api_token'] );
	}

	public function test_new_token_replaces_existing_token(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize_settings(
			array(
				'base_url'  => 'https://api.example.test',
				'api_token' => 'new-token',
			)
		);

		self::assertSame( 'new-token', $clean['api_token'] );
	}

	public function test_rejects_invalid_base_url(): void {
		self::assertSame( '', Settings::sanitize_base_url( 'javascript:alert(1)' ) );
	}

	public function test_rejects_plain_http_by_default(): void {
		self::assertFalse( Settings::is_valid_api_base_url( 'http://api.example.test' ) );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function test_allows_plain_http_only_with_development_constant(): void {
		define( 'WCRAC_ALLOW_INSECURE_HTTP', true );
		self::assertTrue( Settings::is_valid_api_base_url( 'http://api.example.test' ) );
	}

	/**
	 * @dataProvider unsafeUrls
	 */
	public function test_rejects_loopback_private_and_metadata_urls( string $url ): void {
		self::assertFalse( Settings::is_valid_api_base_url( $url ) );
	}

	public static function unsafeUrls(): array {
		return array(
			array( 'https://localhost' ),
			array( 'https://127.0.0.1' ),
			array( 'https://10.0.0.10' ),
			array( 'https://172.16.0.10' ),
			array( 'https://192.168.1.10' ),
			array( 'https://169.254.169.254' ),
			array( 'https://[::1]' ),
		);
	}
}