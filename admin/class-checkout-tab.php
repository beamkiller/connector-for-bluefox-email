<?php
/**
 * Checkout (checkout checkbox) settings tab.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Settings;
use CFBE\Signup_Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tab and sanitizes the `checkout` area.
 */
final class Checkout_Tab {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_filter( 'cfbe_sanitize_settings_checkout', array( $this, 'sanitize' ), 10, 3 );
	}

	/**
	 * Adds the Checkout tab (position 35).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['checkout'] = array(
			'label'    => __( 'Checkout', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 35,
		);
		return $tabs;
	}

	/**
	 * Sanitizes the `checkout` area.
	 *
	 * @param array $clean    Generic-sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public function sanitize( array $clean, array $input, array $previous = array() ): array {
		$defaults = Settings::defaults( 'checkout' );
		// On/off is the module switch on the General tab; the key stays for compatibility.
		$label                  = sanitize_text_field( (string) ( $input['label'] ?? '' ) );
		$clean['label']         = '' !== $label ? $label : $defaults['label'];
		$clean['description']   = sanitize_text_field( (string) ( $input['description'] ?? '' ) );
		$clean['list_id']       = List_Options::sanitize( (string) ( $input['list_id'] ?? '' ), (string) ( $previous['list_id'] ?? '' ) );
		$clean['tags']          = implode( ', ', Signup_Validator::clean_tags( explode( ',', (string) ( $input['tags'] ?? '' ) ) ) );
		$clean['account_optin'] = ! empty( $input['account_optin'] );
		return $clean;
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_values = Settings::get( 'checkout' );
		$cfbe_lists  = List_Options::lists( (string) $cfbe_values['list_id'] );
		include __DIR__ . '/views/tab-checkout.php';
	}
}
