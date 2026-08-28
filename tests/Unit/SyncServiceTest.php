<?php

namespace WCRAC\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WCRAC\Sync_Service;

final class SyncServiceTest extends TestCase {
	public function test_generates_stable_site_scoped_idempotency_key(): void {
		$key_one = Sync_Service::generate_idempotency_key( 'https://store.example.test', 123 );
		$key_two = Sync_Service::generate_idempotency_key( 'https://store.example.test', 123 );
		$other   = Sync_Service::generate_idempotency_key( 'https://another-store.example.test', 123 );

		self::assertSame( $key_one, $key_two );
		self::assertNotSame( $key_one, $other );
		self::assertStringStartsWith( 'wcrac_', $key_one );
	}

	public function test_retry_delays_follow_configured_backoff(): void {
		self::assertSame( 300, Sync_Service::retry_delay_for_attempt( 1 ) );
		self::assertSame( 900, Sync_Service::retry_delay_for_attempt( 2 ) );
		self::assertSame( 3600, Sync_Service::retry_delay_for_attempt( 3 ) );
	}
}
