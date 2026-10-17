<?php
/**
 * Switches WooCommerce customer emails off where a bluefox rule replaces them.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `woocommerce_email_enabled_{id}` for the emails in Rules::WC_EMAILS. WooCommerce's email stays on while
 * bluefox is not connected or the order emails switch is off, so a customer is never left without the email.
 */
final class WC_Email_Switch {

	/**
	 * Whether bluefox is configured.
	 *
	 * @var bool
	 */
	private $connected;

	/**
	 * Constructor.
	 *
	 * @param bool $connected Whether bluefox is configured.
	 */
	public function __construct( bool $connected ) {
		$this->connected = $connected;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		foreach ( Rules::WC_EMAILS as $id ) {
			add_filter( 'woocommerce_email_enabled_' . $id, array( $this, 'filter' ), 20, 3 );
		}
	}

	/**
	 * Returns false for a WooCommerce email that a bluefox rule replaces.
	 *
	 * @param bool  $enabled Enabled in WooCommerce.
	 * @param mixed $order   Order (unused).
	 * @param mixed $email   WC_Email.
	 */
	public function filter( $enabled, $order = null, $email = null ) {
		if ( ! $enabled || ! $this->connected || ! Modules::enabled( 'order_emails' ) ) {
			return $enabled;
		}
		$id = is_object( $email ) && isset( $email->id ) ? (string) $email->id : substr( (string) current_filter(), strlen( 'woocommerce_email_enabled_' ) );
		return ! in_array( $id, Rules::replaced_wc_emails( (array) Module::settings()['rules'] ), true );
	}

	/**
	 * Whether WooCommerce's email for an event is switched off by a rule (for the admin screens).
	 *
	 * @param string $event Event.
	 */
	public static function replaced( string $event ): bool {
		return isset( Rules::WC_EMAILS[ $event ] ) && in_array( Rules::WC_EMAILS[ $event ], Rules::replaced_wc_emails( (array) Module::settings()['rules'] ), true );
	}
}
