<?php
/**
 * Module registry: which features are switched on.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Every optional feature is a module with an on/off switch (General tab, option `cfbe_modules`).
 * WooCommerce modules are only available while WooCommerce is active; a form plugin integration only while
 * its form plugin is active. Switches of one group load the same code module (`Plugin::FEATURE_MODULES`).
 */
final class Modules {

	const OPTION = 'cfbe_modules';

	/**
	 * Built-in modules: slug => [ woocommerce (requires Woo), default (on after install), group ].
	 */
	const FLAGS = array(
		'forms'                    => array( false, true, '' ),
		'checkout'                 => array( true, false, '' ),
		'coupon'                   => array( true, false, '' ),
		'sync'                     => array( false, false, '' ),
		'mailer'                   => array( false, false, '' ),
		'order_emails'             => array( true, false, 'woo_events' ),
		'abandoned_cart'           => array( true, false, 'woo_events' ),
		'integration_cf7'          => array( false, false, 'integrations' ),
		'integration_wpforms'      => array( false, false, 'integrations' ),
		'integration_fluentforms'  => array( false, false, 'integrations' ),
		'integration_gravityforms' => array( false, false, 'integrations' ),
		'integration_elementor'    => array( false, false, 'integrations' ),
		'preferences'              => array( false, true, '' ),
		'webhooks'                 => array( false, true, '' ),
		'abilities'                => array( false, false, '' ),
	);

	/**
	 * Third-party plugin a module needs: slug => [ constants, classes, functions ] (any one of them is enough).
	 */
	const REQUIRES = array(
		'integration_cf7'          => array( array( 'WPCF7_VERSION' ), array( 'WPCF7_ContactForm' ), array() ),
		'integration_wpforms'      => array( array(), array(), array( 'wpforms' ) ),
		'integration_fluentforms'  => array( array( 'FLUENTFORM' ), array(), array( 'wpFluentForm' ) ),
		'integration_gravityforms' => array( array(), array( 'GFForms' ), array() ),
		'integration_elementor'    => array( array( 'ELEMENTOR_PRO_VERSION' ), array(), array() ),
		'abilities'                => array( array(), array(), array( 'wp_register_ability' ) ),
	);

	/**
	 * Module definitions: slug => label, description, woocommerce (bool), default (bool), group.
	 * Before `init` (when the modules are wired) labels are not translated yet: only the flags are reliable.
	 */
	public static function all(): array {
		$texts = did_action( 'init' ) ? self::texts() : array();

		$modules = array();
		foreach ( self::FLAGS as $slug => $flags ) {
			$modules[ $slug ] = array(
				'label'       => $texts[ $slug ][0] ?? $slug,
				'description' => $texts[ $slug ][1] ?? '',
				'woocommerce' => $flags[0],
				'default'     => $flags[1],
				'group'       => $flags[2],
			);
		}

		/**
		 * Filters the module definitions.
		 *
		 * @param array $modules Module definitions.
		 */
		return (array) apply_filters( 'cfbe_modules', $modules );
	}

	/**
	 * Translated label and description per module.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function texts(): array {
		return array(
			'forms'                    => array( __( 'Signup forms', 'connector-for-bluefox-email' ), __( 'Signup form block, shortcode and popup with double opt-in.', 'connector-for-bluefox-email' ) ),
			'checkout'                 => array( __( 'Checkout opt-in', 'connector-for-bluefox-email' ), __( 'Newsletter checkbox on the WooCommerce checkout (classic and block checkout).', 'connector-for-bluefox-email' ) ),
			'coupon'                   => array( __( 'Welcome coupon', 'connector-for-bluefox-email' ), __( 'Single-use WooCommerce coupon for new subscribers, sent after they confirm.', 'connector-for-bluefox-email' ) ),
			'sync'                     => array( __( 'Contact sync', 'connector-for-bluefox-email' ), __( 'Keep bluefox contacts up to date with user and customer data (orders, total spent, …).', 'connector-for-bluefox-email' ) ),
			'mailer'                   => array( __( 'Send WordPress emails via bluefox', 'connector-for-bluefox-email' ), __( 'Route wp_mail() (WordPress and WooCommerce system emails) through the bluefox transactional API.', 'connector-for-bluefox-email' ) ),
			'order_emails'             => array( __( 'Order emails', 'connector-for-bluefox-email' ), __( 'Send your own bluefox emails on order events: status changes, first paid order, note to customer, review request.', 'connector-for-bluefox-email' ) ),
			'abandoned_cart'           => array( __( 'Abandoned cart emails', 'connector-for-bluefox-email' ), __( 'Remind shoppers of carts they left, with a restore link and an optional recovery coupon.', 'connector-for-bluefox-email' ) ),
			'integration_cf7'          => array( 'Contact Form 7', '' ),
			'integration_wpforms'      => array( 'WPForms', '' ),
			'integration_fluentforms'  => array( 'Fluent Forms', '' ),
			'integration_gravityforms' => array( 'Gravity Forms', '' ),
			'integration_elementor'    => array( 'Elementor Pro Forms', '' ),
			'preferences'              => array( __( 'Subscription preferences page', 'connector-for-bluefox-email' ), __( 'Your own unsubscribe / pause page on this site instead of the bluefox hosted page.', 'connector-for-bluefox-email' ) ),
			'webhooks'                 => array( __( 'Inbound webhooks', 'connector-for-bluefox-email' ), __( 'Receive bluefox events (subscribe, unsubscribe, bounce, complaint, …) and act on them in WordPress.', 'connector-for-bluefox-email' ) ),
			'abilities'                => array( __( 'AI agent access (experimental)', 'connector-for-bluefox-email' ), __( 'Let AI agents read and change these settings through the WordPress Abilities API, e.g. with the MCP Adapter plugin. Administrators only; the API key is never exposed.', 'connector-for-bluefox-email' ) ),
		);
	}

	/**
	 * Overridden WooCommerce detection (tests); null = class_exists check.
	 *
	 * @var callable|null
	 */
	private static $wc_check = null;

	/**
	 * Overrides WooCommerce detection.
	 *
	 * @param callable|null $check Returns bool; null restores the default.
	 */
	public static function set_woocommerce_check( ?callable $check ): void {
		self::$wc_check = $check;
	}

	/**
	 * Whether WooCommerce is active.
	 */
	public static function woocommerce_active(): bool {
		return null !== self::$wc_check ? (bool) call_user_func( self::$wc_check ) : class_exists( 'WooCommerce' );
	}

	/**
	 * Overridden third-party plugin detection (tests); null = REQUIRES check.
	 *
	 * @var callable|null
	 */
	private static $requirement_check = null;

	/**
	 * Overrides third-party plugin detection.
	 *
	 * @param callable|null $check Receives the module slug, returns bool; null restores the default.
	 */
	public static function set_requirement_check( ?callable $check ): void {
		self::$requirement_check = $check;
	}

	/**
	 * Whether the third-party plugin a module needs is active (true when it needs none).
	 *
	 * @param string $slug Module slug.
	 */
	public static function requirement_active( string $slug ): bool {
		if ( ! isset( self::REQUIRES[ $slug ] ) ) {
			return true;
		}
		if ( null !== self::$requirement_check ) {
			return (bool) call_user_func( self::$requirement_check, $slug );
		}
		list( $constants, $classes, $functions ) = self::REQUIRES[ $slug ];
		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				return true;
			}
		}
		foreach ( $functions as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a module is available on this site (its dependencies are active).
	 *
	 * @param string $slug Module slug.
	 */
	public static function available( string $slug ): bool {
		$modules = self::all();
		if ( ! isset( $modules[ $slug ] ) ) {
			return false;
		}
		return ( empty( $modules[ $slug ]['woocommerce'] ) || self::woocommerce_active() ) && self::requirement_active( $slug );
	}

	/**
	 * Stored switches merged over the defaults. A switch saved before a feature was split (`woo_events`,
	 * `integrations`) is inherited by the new switches of its group.
	 */
	public static function states(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$states = array();
		foreach ( self::all() as $slug => $module ) {
			$group = (string) ( $module['group'] ?? '' );
			if ( array_key_exists( $slug, $stored ) ) {
				$states[ $slug ] = (bool) $stored[ $slug ];
			} elseif ( '' !== $group && array_key_exists( $group, $stored ) ) {
				$states[ $slug ] = (bool) $stored[ $group ] && self::legacy_member_on( $group, $slug );
			} else {
				$states[ $slug ] = (bool) $module['default'];
			}
		}
		return $states;
	}

	/**
	 * Before the split every form plugin had its own "Enabled" box on the integrations tab.
	 *
	 * @param string $group Group.
	 * @param string $slug  Module slug.
	 */
	private static function legacy_member_on( string $group, string $slug ): bool {
		if ( 'integrations' !== $group ) {
			return true;
		}
		$settings = get_option( Settings::option_name( 'integrations' ), array() );
		$source   = substr( $slug, strlen( 'integration_' ) );
		return is_array( $settings ) && ! empty( $settings[ $source ]['enabled'] );
	}

	/**
	 * Whether a module is switched on and available.
	 *
	 * @param string $slug Module slug.
	 */
	public static function enabled( string $slug ): bool {
		$states = self::states();
		return ! empty( $states[ $slug ] ) && self::available( $slug );
	}

	/**
	 * Whether any module of a group is switched on and available.
	 *
	 * @param string $group Group.
	 */
	public static function group_enabled( string $group ): bool {
		foreach ( self::all() as $slug => $module ) {
			if ( ( $module['group'] ?? '' ) === $group && self::enabled( $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sanitizes the switches (Settings API callback).
	 *
	 * @param mixed $input Raw input.
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		foreach ( array_keys( self::all() ) as $slug ) {
			$clean[ $slug ] = ! empty( $input[ $slug ] );
		}
		return $clean;
	}
}
