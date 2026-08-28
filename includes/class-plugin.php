<?php
/**
 * Plugin bootstrap.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	public const OPTION_NAME = 'wcrac_settings';
	public const ACTION_SYNC_ORDER = 'wcrac_sync_order';
	public const ACTION_GROUP = 'woocommerce-rest-api-connector';

	private static ?Plugin $instance = null;

	private Logger $logger;
	private Settings $settings;
	private Sync_Service $sync_service;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->logger       = new Logger();
		$this->settings     = new Settings( self::OPTION_NAME );
		$this->sync_service = new Sync_Service(
			$this->settings,
			new Api_Client( $this->settings, $this->logger ),
			new Order_Payload(),
			$this->logger
		);
	}

	public function init(): void {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings_page' ) );

		( new Order_Handler( $this->settings, $this->logger ) )->register();
		( new Admin_Order_Actions( $this->settings, $this->logger ) )->register();

		add_action( self::ACTION_SYNC_ORDER, array( $this->sync_service, 'sync_order' ), 10, 1 );
	}

	/**
	 * @param array<int, object> $settings_pages WooCommerce settings pages.
	 * @return array<int, object>
	 */
	public function register_settings_page( array $settings_pages ): array {
		$settings_pages[] = $this->settings;
		return $settings_pages;
	}
}
