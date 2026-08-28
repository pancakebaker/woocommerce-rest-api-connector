<?php
/**
 * Synchronization result value object.
 *
 * @package WCRAC
 */

namespace WCRAC;

final class Sync_Result {
	private bool $success;
	private bool $retryable;
	private int $status_code;
	private string $message;
	private string $external_id;

	private function __construct( bool $success, bool $retryable, int $status_code, string $message, string $external_id = '' ) {
		$this->success     = $success;
		$this->retryable   = $retryable;
		$this->status_code = $status_code;
		$this->message     = $message;
		$this->external_id = $external_id;
	}

	public static function success( string $message, int $status_code = 200, string $external_id = '' ): self {
		return new self( true, false, $status_code, $message, $external_id );
	}

	public static function failure( string $message, bool $retryable, int $status_code = 0 ): self {
		return new self( false, $retryable, $status_code, $message );
	}

	public function is_success(): bool {
		return $this->success;
	}

	public function is_retryable(): bool {
		return $this->retryable;
	}

	public function get_status_code(): int {
		return $this->status_code;
	}

	public function get_message(): string {
		return $this->message;
	}

	public function get_external_id(): string {
		return $this->external_id;
	}
}
