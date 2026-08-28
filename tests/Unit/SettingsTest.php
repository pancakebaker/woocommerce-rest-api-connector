<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Settings;

final class SettingsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wcrac_test_options'] = array(
			'wcrac_settings' => array(
				'api_token' => 'existing-token',
			),
		);
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

	public function test_rejects_invalid_base_url(): void {
		self::assertSame( '', Settings::sanitize_base_url( 'javascript:alert(1)' ) );
	}
}
