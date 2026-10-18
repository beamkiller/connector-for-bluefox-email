<?php
/**
 * Coupon tab, coupon support actions and bluefox field setup.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Bluefox_Client;
use CFBE\Coupon_Data;
use CFBE\Reward_Service;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon settings UI, "Create bluefox fields", resend coupon email, run poller now.
 */
final class Coupon_Tab {

	const REQUIRED_FIELDS = array(
		'name'          => 'string',
		'couponCode'    => 'string',
		'couponExpires' => 'date',
		'couponUsed'    => 'boolean',
	);

	/**
	 * Reward service.
	 *
	 * @var Reward_Service
	 */
	private $rewards;

	/**
	 * Constructor.
	 *
	 * @param Reward_Service $rewards Reward service.
	 */
	public function __construct( Reward_Service $rewards ) {
		$this->rewards = $rewards;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_filter( 'cfbe_sanitize_settings_coupon', array( $this, 'sanitize' ), 10, 3 );
		add_action( 'wp_ajax_cfbe_setup_fields', array( $this, 'ajax_setup_fields' ) );
		add_action( 'admin_post_cfbe_resend_coupon', array( $this, 'handle_resend' ) );
		add_action( 'admin_post_cfbe_poll_now', array( $this, 'handle_poll_now' ) );
	}

	/**
	 * Adds the Coupon tab (position 40).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['coupon'] = array(
			'label'    => __( 'Coupon', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 40,
		);
		return $tabs;
	}

	/**
	 * Sanitizes the `coupon` area (floats and ranges the generic sanitizer can't express).
	 *
	 * @param array $clean    Generic-sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public function sanitize( array $clean, array $input, array $previous = array() ): array {
		// On/off is the module switch on the General tab; the key stays for compatibility.
		$clean['email_id']      = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['email_id'] ?? '' ) );
		$clean['list_id']       = List_Options::sanitize( (string) ( $input['list_id'] ?? '' ), (string) ( $previous['list_id'] ?? '' ) );
		$type                   = 'fixed_cart' === ( $input['discount_type'] ?? '' ) ? 'fixed_cart' : 'percent';
		$amount                 = (float) str_replace( ',', '.', (string) ( $input['amount'] ?? 0 ) );
		$clean['discount_type'] = $type;
		$clean['amount']        = 'percent' === $type ? min( 100.0, max( 0.0, $amount ) ) : max( 0.0, $amount );
		if ( $clean['amount'] <= 0 ) {
			$clean['amount'] = (float) Settings::defaults( 'coupon' )['amount'];
			add_settings_error( 'cfbe_coupon', 'cfbe_amount', __( 'The discount amount must be greater than 0; the default value has been restored.', 'connector-for-bluefox-email' ) );
		}
		$clean['minimum_amount'] = max( 0.0, (float) str_replace( ',', '.', (string) ( $input['minimum_amount'] ?? 0 ) ) );
		$clean['expiry_days']    = min( 365, max( 1, (int) ( $input['expiry_days'] ?? 30 ) ) );
		$clean['reminder_days']  = min( 30, max( 1, (int) ( $input['reminder_days'] ?? 5 ) ) );
		// Saved from code without this key (Settings::update() merges the stored values in): keep what is stored.
		$clean['reminder_email_id'] = array_key_exists( 'reminder_email_id', $input ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['reminder_email_id'] ) : (string) ( $previous['reminder_email_id'] ?? '' );
		$prefix                     = strtoupper( (string) preg_replace( '/[^A-Za-z0-9-]/', '', (string) ( $input['code_prefix'] ?? '' ) ) );
		$clean['code_prefix']       = substr( $prefix, 0, 10 );
		return $clean;
	}

	/**
	 * Bundled email template (templates/emails/{name}.html), '' when missing.
	 *
	 * @param string $name welcome-coupon | confirm-subscription.
	 */
	public static function template( string $name ): string {
		$file = dirname( __DIR__ ) . '/templates/emails/' . sanitize_key( $name ) . '.html';
		if ( ! is_readable( $file ) ) {
			return '';
		}
		$html = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file bundled with the plugin.
		return false === $html ? '' : $html;
	}

	/**
	 * Human preview line of the configured coupon.
	 *
	 * @param array $values `coupon` settings.
	 */
	public static function preview( array $values ): string {
		$parts = array(
			/* translators: %s: discount, e.g. 10% */
			sprintf( __( '%s discount', 'connector-for-bluefox-email' ), Coupon_Data::discount_label( (string) $values['discount_type'], (float) $values['amount'] ) ),
			/* translators: %d: days */
			sprintf( __( 'valid for %d days', 'connector-for-bluefox-email' ), (int) $values['expiry_days'] ),
			__( 'single use, only with the subscriber\'s email address', 'connector-for-bluefox-email' ),
		);
		if ( (float) $values['minimum_amount'] > 0 ) {
			/* translators: %s: minimum order amount */
			$parts[] = sprintf( __( 'minimum order %s', 'connector-for-bluefox-email' ), Coupon_Data::money( (float) $values['minimum_amount'] ) );
		}
		if ( ! empty( $values['individual_use'] ) ) {
			$parts[] = __( 'cannot be combined with other coupons', 'connector-for-bluefox-email' );
		}
		if ( ! empty( $values['exclude_sale_items'] ) ) {
			$parts[] = __( 'not valid for sale items', 'connector-for-bluefox-email' );
		}
		return $values['code_prefix'] . 'K7M2QX9P – ' . implode( ', ', $parts ) . '.';
	}

	/**
	 * Renders the Coupon tab.
	 */
	public function render(): void {
		$cfbe_values = Settings::get( 'coupon' );
		include __DIR__ . '/views/tab-coupon.php';
	}

	/**
	 * AJAX: creates missing bluefox contact fields (idempotent).
	 */
	public function ajax_setup_fields(): void {
		if ( false === check_ajax_referer( Admin_Ajax::NONCE, 'nonce', false ) || ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return;
		}
		$client = Bluefox_Client::from_settings();
		if ( is_wp_error( $client ) ) {
			wp_send_json_error( array( 'message' => $client->get_error_message() ), 400 );
			return;
		}
		$result = self::ensure_fields( $client );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success(
			array(
				/* translators: 1: created field names, 2: existing field names */
				'message' => sprintf( __( 'Created: %1$s. Already existed: %2$s.', 'connector-for-bluefox-email' ), $result['created'] ? implode( ', ', $result['created'] ) : '–', $result['existing'] ? implode( ', ', $result['existing'] ) : '–' ),
			)
		);
	}

	/**
	 * Creates the missing REQUIRED_FIELDS.
	 *
	 * @param Bluefox_Client $client Client.
	 * @return array{created: string[], existing: string[]}|\WP_Error
	 */
	public static function ensure_fields( Bluefox_Client $client ) {
		$fields = $client->get_contact_fields();
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$have = array_column( $fields, 'name' );
		$out  = array(
			'created'  => array(),
			'existing' => array(),
		);
		foreach ( self::REQUIRED_FIELDS as $name => $type ) {
			if ( in_array( $name, $have, true ) ) {
				$out['existing'][] = $name;
				continue;
			}
			$created = $client->create_contact_field( $name, $type );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$out['created'][] = $name;
		}
		return $out;
	}

	/**
	 * Admin-post: resend a coupon email.
	 */
	public function handle_resend(): void {
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified below.
		check_admin_referer( 'cfbe_resend_coupon_' . $email );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), 403 );
		}
		$result = $this->rewards->resend( $email );
		$this->back( is_wp_error( $result ) ? 'resend_failed' : 'resent' );
	}

	/**
	 * Admin-post: run the poller now.
	 */
	public function handle_poll_now(): void {
		check_admin_referer( 'cfbe_poll_now' );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), 403 );
		}
		$queued = $this->rewards->poll();
		$this->back( 'polled', $queued );
	}

	/**
	 * Redirects back to the Subscribers tab with a notice flag.
	 *
	 * @param string $notice Notice key.
	 * @param int    $count  Optional count.
	 */
	private function back( string $notice, int $count = 0 ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => Admin_Page::SLUG,
					'tab'         => 'subscribers',
					'cfbe_notice' => $notice,
					'cfbe_count'  => $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
