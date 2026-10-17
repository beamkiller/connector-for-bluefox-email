<?php
/**
 * "Order emails" and "Abandoned carts" settings tabs.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Admin\Admin_Page;
use CFBE\Bluefox_Client;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tabs (positions 62 / 63, one per switch), sanitizer of the shared `woo_events` area, cached email lists +
 * refresh (AJAX). Each tab saves only its own part of the area: its rules and (cart tab) the cart settings.
 */
class Settings_Tab {

	const SECTION_ORDERS = 'orders';
	const SECTION_CART   = 'cart';
	const CART_TAB       = 'abandoned_cart';

	const EMAILS_OPTION = 'cfbe_woo_events_emails';
	const AJAX_ACTION   = 'cfbe_woo_events_refresh';
	const NONCE         = 'cfbe_woo_events';

	const MIN_IDLE = 15;
	const MAX_IDLE = 10080;

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Cart storage.
	 *
	 * @var Cart_Repository
	 */
	private $carts;

	/**
	 * Whether the order emails tab is shown.
	 *
	 * @var bool
	 */
	private $orders_on;

	/**
	 * Whether the abandoned carts tab is shown.
	 *
	 * @var bool
	 */
	private $cart_on;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null $client    Client.
	 * @param Cart_Repository     $carts     Cart storage.
	 * @param bool                $orders_on Order emails switch.
	 * @param bool                $cart_on   Abandoned cart switch.
	 */
	public function __construct( ?Bluefox_Client $client, Cart_Repository $carts, bool $orders_on = true, bool $cart_on = true ) {
		$this->client    = $client;
		$this->carts     = $carts;
		$this->orders_on = $orders_on;
		$this->cart_on   = $cart_on;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_filter( 'cfbe_sanitize_settings_' . Module::AREA, array( $this, 'sanitize' ), 10, 3 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_refresh' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Adds the tab.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs = (array) $tabs;
		if ( $this->orders_on ) {
			$tabs[ Module::AREA ] = array(
				'label'    => __( 'Order emails', 'connector-for-bluefox-email' ),
				'render'   => array( $this, 'render_orders' ),
				'position' => 62,
			);
		}
		if ( $this->cart_on ) {
			$tabs[ self::CART_TAB ] = array(
				'label'    => __( 'Abandoned carts', 'connector-for-bluefox-email' ),
				'render'   => array( $this, 'render_cart' ),
				'position' => 63,
			);
		}
		return $tabs;
	}

	/**
	 * Selectable events.
	 */
	public static function events(): array {
		return Rules::events( function_exists( 'wc_get_order_statuses' ) ? (array) wc_get_order_statuses() : array() );
	}

	/**
	 * Events of a tab section.
	 *
	 * @param string $section Section.
	 */
	public static function section_events( string $section ): array {
		$events = self::events();
		foreach ( array_keys( $events ) as $event ) {
			if ( ( Rules::EVENT_ABANDONED_CART === $event ) !== ( self::SECTION_CART === $section ) ) {
				unset( $events[ $event ] );
			}
		}
		return $events;
	}

	/**
	 * Whether a rule belongs to a tab section.
	 *
	 * @param mixed  $rule    Rule.
	 * @param string $section Section.
	 */
	public static function in_section( $rule, string $section ): bool {
		return is_array( $rule ) && ( Rules::EVENT_ABANDONED_CART === ( $rule['event'] ?? '' ) ) === ( self::SECTION_CART === $section );
	}

	/**
	 * Sanitizes the area. A tab posts `_section`: only that section's rules (and, for the cart tab, the cart
	 * settings) come from the input, everything else keeps its stored value.
	 *
	 * @param array $clean    Generic-sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public function sanitize( $clean, $input, $previous = array() ): array {
		$clean    = (array) $clean;
		$input    = is_array( $input ) ? $input : array();
		$previous = array_merge( Module::DEFAULTS, is_array( $previous ) ? $previous : array() );
		$section  = in_array( $input['_section'] ?? '', array( self::SECTION_ORDERS, self::SECTION_CART ), true ) ? (string) $input['_section'] : '';
		$cache    = self::cached_emails();
		$known    = array(
			Rules::TYPE_TRANSACTIONAL => $cache['transactional'],
			Rules::TYPE_TRIGGERED     => $cache['triggered'],
		);
		unset( $clean['_section'] );

		if ( '' === $section ) {
			$clean['rules'] = Rules::sanitize( $input['rules'] ?? array(), self::events(), $known );
		} else {
			$kept           = array_filter(
				(array) $previous['rules'],
				static function ( $rule ) use ( $section ): bool {
					return is_array( $rule ) && ! self::in_section( $rule, $section );
				}
			);
			$clean['rules'] = array_slice( array_merge( array_values( $kept ), Rules::sanitize( $input['rules'] ?? array(), self::section_events( $section ), $known ) ), 0, Rules::MAX_RULES );
			if ( self::SECTION_ORDERS === $section ) {
				foreach ( Module::DEFAULTS as $key => $default ) {
					if ( 'rules' !== $key ) {
						$clean[ $key ] = $previous[ $key ];
					}
				}
				return $clean;
			}
		}
		$clean['cart_tracking']     = ! empty( $input['cart_tracking'] );
		$clean['cart_optin_only']   = ! empty( $input['cart_optin_only'] );
		$clean['cart_idle_minutes'] = min( self::MAX_IDLE, max( self::MIN_IDLE, absint( $input['cart_idle_minutes'] ?? Module::DEFAULTS['cart_idle_minutes'] ) ) );
		$clean['cart_expiry_days']  = min( 365, max( 1, absint( $input['cart_expiry_days'] ?? Module::DEFAULTS['cart_expiry_days'] ) ) );

		$clean['cart_coupon']              = ! empty( $input['cart_coupon'] );
		$clean['cart_coupon_type']         = 'fixed_cart' === ( $input['cart_coupon_type'] ?? '' ) ? 'fixed_cart' : 'percent';
		$amount                            = max( 0.0, self::number( $input['cart_coupon_amount'] ?? Module::DEFAULTS['cart_coupon_amount'] ) );
		$clean['cart_coupon_amount']       = 'percent' === $clean['cart_coupon_type'] ? min( 100.0, $amount ) : $amount;
		$clean['cart_coupon_days']         = min( 365, max( 1, absint( $input['cart_coupon_days'] ?? Module::DEFAULTS['cart_coupon_days'] ) ) );
		$clean['cart_coupon_min']          = max( 0.0, self::number( $input['cart_coupon_min'] ?? 0 ) );
		$prefix                            = strtoupper( (string) preg_replace( '/[^A-Za-z0-9-]/', '', (string) ( $input['cart_coupon_prefix'] ?? '' ) ) );
		$clean['cart_coupon_prefix']       = '' !== $prefix ? substr( $prefix, 0, 12 ) : Module::DEFAULTS['cart_coupon_prefix'];
		$clean['cart_coupon_individual']   = ! empty( $input['cart_coupon_individual'] );
		$clean['cart_coupon_exclude_sale'] = ! empty( $input['cart_coupon_exclude_sale'] );
		$clean['cart_coupon_cooldown']     = min( 365, absint( $input['cart_coupon_cooldown'] ?? Module::DEFAULTS['cart_coupon_cooldown'] ) );
		return $clean;
	}

	/**
	 * Decimal number from a form field (comma or dot as separator).
	 *
	 * @param mixed $value Raw value.
	 */
	private static function number( $value ): float {
		return is_scalar( $value ) ? (float) str_replace( ',', '.', trim( (string) $value ) ) : 0.0;
	}

	/**
	 * Cached email lists: transactional / triggered => [ id => name ], fetched_at.
	 */
	public static function cached_emails(): array {
		$cache = get_option( self::EMAILS_OPTION, array() );
		$cache = is_array( $cache ) ? $cache : array();
		return array(
			'transactional' => isset( $cache['transactional'] ) && is_array( $cache['transactional'] ) ? $cache['transactional'] : array(),
			'triggered'     => isset( $cache['triggered'] ) && is_array( $cache['triggered'] ) ? $cache['triggered'] : array(),
			'fetched_at'    => (int) ( $cache['fetched_at'] ?? 0 ),
		);
	}

	/**
	 * Loads both email lists from bluefox and caches them.
	 *
	 * @return array|\WP_Error Cache.
	 */
	public function refresh_emails() {
		if ( null === $this->client ) {
			return new \WP_Error( 'cfbe_not_configured', __( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ) );
		}
		$cache = array( 'fetched_at' => time() );
		foreach ( array(
			'transactional' => 'get_transactional_emails',
			'triggered'     => 'get_triggered_emails',
		) as $type => $method ) {
			$items = $this->client->$method();
			if ( is_wp_error( $items ) ) {
				return $items;
			}
			$cache[ $type ] = self::map_emails( (array) $items );
		}
		update_option( self::EMAILS_OPTION, $cache, false );
		return self::cached_emails();
	}

	/**
	 * API items → [ id => name ].
	 *
	 * @param array $items Items.
	 * @return array<string, string>
	 */
	public static function map_emails( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$id = is_array( $item ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $item['_id'] ?? $item['id'] ?? '' ) ) : '';
			if ( '' === $id ) {
				continue;
			}
			$name       = sanitize_text_field( (string) ( $item['name'] ?? '' ) );
			$out[ $id ] = '' !== $name ? $name : $id;
		}
		asort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	/**
	 * AJAX: refreshes the cached lists (nonce + capability).
	 */
	public function ajax_refresh(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return;
		}
		$cache = $this->refresh_emails();
		if ( is_wp_error( $cache ) ) {
			wp_send_json_error( array( 'message' => $cache->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success( $cache );
	}

	/**
	 * Whether the current admin screen is this tab.
	 *
	 * @param string $hook Admin page hook.
	 */
	private static function is_tab( string $hook ): bool {
		// Read-only navigation parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'toplevel_page_' . Admin_Page::SLUG === $hook && in_array( $tab, array( Module::AREA, self::CART_TAB ), true );
	}

	/**
	 * Enqueues the tab assets.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ): void {
		if ( ! self::is_tab( (string) $hook ) ) {
			return;
		}
		wp_enqueue_style( 'cfbe-woo-events-admin', CFBE_URL . 'modules/woo-events/assets/admin.css', array(), CFBE_VERSION );
		wp_enqueue_script( 'cfbe-woo-events-admin', CFBE_URL . 'modules/woo-events/assets/admin.js', array(), CFBE_VERSION, true );
		$cache = self::cached_emails();
		wp_localize_script(
			'cfbe-woo-events-admin',
			'cfbeWooEvents',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'action'        => self::AJAX_ACTION,
				'nonce'         => wp_create_nonce( self::NONCE ),
				'emails'        => array(
					'transactional' => (object) $cache['transactional'],
					'triggered'     => (object) $cache['triggered'],
				),
				'triggeredOnly' => Rules::TRIGGERED_ONLY,
				'i18n'          => array(
					'working' => __( 'Loading…', 'connector-for-bluefox-email' ),
					'loaded'  => __( 'Email lists refreshed.', 'connector-for-bluefox-email' ),
					'error'   => __( 'Something went wrong.', 'connector-for-bluefox-email' ),
					'choose'  => __( '— Select an email —', 'connector-for-bluefox-email' ),
					'unknown' => __( '(not found in bluefox)', 'connector-for-bluefox-email' ),
					'remove'  => __( 'Remove this rule?', 'connector-for-bluefox-email' ),
				),
			)
		);
	}

	/**
	 * Renders the order emails tab.
	 */
	public function render_orders(): void {
		$this->render( self::SECTION_ORDERS );
	}

	/**
	 * Renders the abandoned carts tab.
	 */
	public function render_cart(): void {
		$this->render( self::SECTION_CART );
	}

	/**
	 * Renders a tab.
	 *
	 * @param string $section Section.
	 */
	public function render( string $section = self::SECTION_ORDERS ): void {
		$cfbe_section = $section;
		$cfbe_values  = Module::settings();
		$cfbe_cache   = self::cached_emails();
		if ( 0 === $cfbe_cache['fetched_at'] && null !== $this->client ) {
			$fresh      = $this->refresh_emails();
			$cfbe_cache = is_wp_error( $fresh ) ? $cfbe_cache : $fresh;
		}
		$cfbe_events    = self::section_events( $section );
		$cfbe_rules     = array_values( array_filter( (array) $cfbe_values['rules'], static fn( $rule ) => self::in_section( $rule, $section ) ) );
		$cfbe_statuses  = Dispatcher::statuses();
		$cfbe_carts     = self::SECTION_CART === $section ? $this->carts->recent( 20 ) : array();
		$cfbe_option    = Settings::option_name( Module::AREA );
		$cfbe_connected = null !== $this->client;
		include __DIR__ . '/views/tab-woo-events.php';
	}
}
