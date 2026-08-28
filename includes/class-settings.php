<?php
/**
 * WooCommerce settings page.
 *
 * @package WCRAC
 */

namespace WCRAC;

defined( 'ABSPATH' ) || exit;

final class Settings extends \WC_Settings_Page {
	private const MASKED_TOKEN = '********';

	private string $option_name;

	public function __construct( string $option_name = 'wcrac_settings' ) {
		$this->option_name = $option_name;
		$this->id          = 'wcrac';
		$this->label       = __( 'REST API Connector', 'woocommerce-rest-api-connector' );

		parent::__construct();

		add_action( 'admin_init', array( $this, 'register_options' ) );
		add_action( 'woocommerce_admin_field_wcrac_test_connection', array( $this, 'render_test_connection_field' ) );
		add_action( 'woocommerce_update_options_wcrac', array( $this, 'save' ) );
		add_action( 'admin_post_wcrac_test_connection', array( $this, 'handle_test_connection' ) );
	}

	public function register_options(): void {
		register_setting(
			'wcrac_settings',
			$this->option_name,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'enabled'   => 'no',
			'base_url'  => '',
			'api_token' => '',
			'timeout'   => 15,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		$options = get_option( $this->option_name, array() );
		return array_merge( $this->defaults(), is_array( $options ) ? $options : array() );
	}

	public function is_enabled(): bool {
		return 'yes' === $this->get( 'enabled' );
	}

	public function get( string $key ) {
		$options = $this->get_options();
		return $options[ $key ] ?? null;
	}

	public function get_base_url(): string {
		return (string) $this->get( 'base_url' );
	}

	public function get_api_token(): string {
		return (string) $this->get( 'api_token' );
	}

	public function get_timeout(): int {
		return max( 1, min( 60, (int) $this->get( 'timeout' ) ) );
	}

	/**
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$current = $this->get_options();
		$base    = isset( $input['base_url'] ) ? self::sanitize_base_url( (string) $input['base_url'] ) : '';
		$token   = isset( $input['api_token'] ) ? sanitize_text_field( wp_unslash( (string) $input['api_token'] ) ) : '';
		$timeout = isset( $input['timeout'] ) ? absint( $input['timeout'] ) : 15;

		if ( '' === $token || self::MASKED_TOKEN === $token ) {
			$token = (string) $current['api_token'];
		}

		return array(
			'enabled'   => empty( $input['enabled'] ) ? 'no' : 'yes',
			'base_url'  => $base,
			'api_token' => $token,
			'timeout'   => max( 1, min( 60, $timeout ) ),
		);
	}

	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$raw = isset( $_POST[ $this->option_name ] ) && is_array( $_POST[ $this->option_name ] )
			? wp_unslash( $_POST[ $this->option_name ] )
			: array();

		update_option( $this->option_name, $this->sanitize_settings( $raw ) );
	}

	public static function sanitize_base_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		$url = esc_url_raw( $url, array( 'https', 'http' ) );
		if ( ! self::is_valid_api_base_url( $url ) ) {
			return '';
		}

		return untrailingslashit( $url );
	}

	public static function is_valid_api_base_url( string $url ): bool {
		if ( '' === trim( $url ) || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( 'https' !== $scheme && ! ( 'http' === $scheme && defined( 'WCRAC_ALLOW_INSECURE_HTTP' ) && WCRAC_ALLOW_INSECURE_HTTP ) ) {
			return false;
		}

		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		$host = strtolower( trim( (string) $parts['host'], "[] \t\n\r\0\x0B." ) );
		if ( '' === $host || 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) {
			return false;
		}

		if ( self::is_unsafe_host_or_ip( $host ) ) {
			return false;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		$addresses = function_exists( 'gethostbynamel' ) ? gethostbynamel( $host ) : false;
		if ( is_array( $addresses ) ) {
			foreach ( $addresses as $address ) {
				if ( self::is_unsafe_host_or_ip( $address ) ) {
					return false;
				}
			}
		}

		return true;
	}

	private static function is_unsafe_host_or_ip( string $host ): bool {
		$host = strtolower( trim( $host, '[]' ) );

		if ( '169.254.169.254' === $host ) {
			return true;
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}

		return false;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings(): array {
		$options = $this->get_options();

		return array(
			array(
				'title' => __( 'REST API Connector', 'woocommerce-rest-api-connector' ),
				'type'  => 'title',
				'desc'  => __( 'Synchronize completed WooCommerce orders to a fictional external REST API.', 'woocommerce-rest-api-connector' ),
				'id'    => 'wcrac_settings_title',
			),
			array(
				'title'   => __( 'Enable synchronization', 'woocommerce-rest-api-connector' ),
				'id'      => $this->option_name . '[enabled]',
				'type'    => 'checkbox',
				'default' => 'no',
				'value'   => $options['enabled'],
			),
			array(
				'title'    => __( 'API base URL', 'woocommerce-rest-api-connector' ),
				'id'       => $this->option_name . '[base_url]',
				'type'     => 'url',
				'default'  => '',
				'value'    => $options['base_url'],
				'desc_tip' => __( 'HTTPS base URL only, for example https://api.example.test.', 'woocommerce-rest-api-connector' ),
			),
			array(
				'title'    => __( 'API token', 'woocommerce-rest-api-connector' ),
				'id'       => $this->option_name . '[api_token]',
				'type'     => 'password',
				'default'  => '',
				'value'    => '',
				'desc_tip' => __( 'Leave blank to keep the currently saved token.', 'woocommerce-rest-api-connector' ),
			),
			array(
				'title'             => __( 'Request timeout', 'woocommerce-rest-api-connector' ),
				'id'                => $this->option_name . '[timeout]',
				'type'              => 'number',
				'default'           => 15,
				'value'             => $options['timeout'],
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 60,
					'step' => 1,
				),
			),
			array(
				'type' => 'wcrac_test_connection',
				'id'   => 'wcrac_test_connection',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'wcrac_settings_end',
			),
		);
	}

	public function render_test_connection_field(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$url = wp_nonce_url( admin_url( 'admin-post.php?action=wcrac_test_connection' ), 'wcrac_test_connection' );
		printf(
			'<tr valign="top"><th scope="row" class="titledesc">%s</th><td class="forminp"><a class="button" href="%s">%s</a></td></tr>',
			esc_html__( 'Test connection', 'woocommerce-rest-api-connector' ),
			esc_url( $url ),
			esc_html__( 'Test Connection', 'woocommerce-rest-api-connector' )
		);
	}

	public function handle_test_connection(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to test this connection.', 'woocommerce-rest-api-connector' ) );
		}

		check_admin_referer( 'wcrac_test_connection' );

		$client = new Api_Client( $this, new Logger() );
		$result = $client->test_connection();
		$type   = $result->is_success() ? 'success' : 'error';

		add_settings_error( 'wcrac_messages', 'wcrac_test_connection', Logger::sanitize_message( $result->get_message() ), $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=wcrac&settings-updated=true' ) );
		exit;
	}
}