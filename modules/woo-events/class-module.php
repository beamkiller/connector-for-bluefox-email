<?php
/**
 * WooCommerce emails module entry.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Modules;
use CFBE\Plugin;
use CFBE\Rate_Limiter;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Sends bluefox emails on store events (order status, first order, customer note, review request, abandoned cart).
 * Loaded by `Plugin::FEATURE_MODULES` when the `order_emails` or `abandoned_cart` switch is on (WooCommerce active);
 * each switch covers its own events and settings tab.
 */
final class Module {

	const AREA = 'woo_events';

	const DEFAULTS = array(
		'rules'                    => array(),
		'cart_tracking'            => true,
		'cart_optin_only'          => true,
		'cart_idle_minutes'        => 60,
		'cart_expiry_days'         => 30,
		// Optional recovery coupon in the abandoned cart reminder.
		'cart_coupon'              => false,
		'cart_coupon_type'         => 'percent',
		'cart_coupon_amount'       => 10,
		'cart_coupon_days'         => 7,
		'cart_coupon_min'          => 0,
		'cart_coupon_prefix'       => 'CART-',
		'cart_coupon_individual'   => true,
		'cart_coupon_exclude_sale' => true,
		// At most one recovery coupon per email in this many days (0 = no limit).
		'cart_coupon_cooldown'     => 30,
	);

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Settings of the area merged over the defaults.
	 */
	public static function settings(): array {
		return array_merge( self::DEFAULTS, (array) Settings::get( self::AREA ) );
	}

	/**
	 * Whether the switch covering an event is on (abandoned cart → `abandoned_cart`, others → `order_emails`).
	 *
	 * @param string $event Event.
	 */
	public static function event_on( string $event ): bool {
		return Modules::enabled( Rules::EVENT_ABANDONED_CART === $event ? 'abandoned_cart' : 'order_emails' );
	}

	/**
	 * Hooks everything.
	 */
	public function register(): void {
		Installer::maybe_upgrade();

		add_filter( 'cfbe_settings_areas', array( $this, 'add_area' ) );
		add_filter( 'cfbe_settings_defaults_' . self::AREA, array( $this, 'defaults' ) );

		global $wpdb;
		$carts      = new Cart_Repository( $wpdb );
		$coupons    = new Recovery_Coupon();
		$dispatcher = new Dispatcher( $this->plugin->client(), $this->plugin->jobs(), $this->plugin->logger(), new Payload_Builder(), $carts, null, null, $coupons );
		$dispatcher->set_event_filter( array( self::class, 'event_on' ) );
		$dispatcher->register();

		if ( Modules::enabled( 'abandoned_cart' ) ) {
			$tracker = new Cart_Tracker( $carts, $dispatcher, $this->plugin->repository(), $this->plugin->jobs(), $this->plugin->logger(), new Rate_Limiter(), $coupons );
			$tracker->register();
		}

		( new Privacy( $carts ) )->register();

		$tab = new Settings_Tab( $this->plugin->client(), $carts, Modules::enabled( 'order_emails' ), Modules::enabled( 'abandoned_cart' ) );
		if ( is_admin() ) {
			$tab->register();
		} else {
			// Abilities save settings over REST / WP-CLI.
			add_action( 'cfbe_load_settings_sanitizers', array( $tab, 'register' ) );
		}
	}

	/**
	 * Adds the settings area.
	 *
	 * @param array $areas Areas.
	 */
	public function add_area( $areas ): array {
		$areas   = (array) $areas;
		$areas[] = self::AREA;
		return $areas;
	}

	/**
	 * Area defaults.
	 *
	 * @param array $defaults Defaults.
	 */
	public function defaults( $defaults ): array {
		return array_merge( self::DEFAULTS, (array) $defaults );
	}
}
