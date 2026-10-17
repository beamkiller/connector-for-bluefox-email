<?php
/**
 * Contact sync: field definitions, settings defaults and sanitizer.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * The fields the sync can write to bluefox contacts and the `sync` settings area.
 */
final class Fields {

	const AREA = 'sync';

	const OPTION = 'cfbe_sync';

	const TAG_WP_USER = 'wp-user';

	const TAG_CUSTOMER = 'customer';

	/**
	 * Keys that are never accepted as custom field names (built-in contact properties).
	 */
	const RESERVED = array( 'email', 'tags', '_id', '_lists', 'createdAt', 'updatedAt', 'accountId', 'projectId' );

	/**
	 * Field definitions: key => type (bluefox field type), woo (needs WooCommerce), on (enabled by default).
	 * The key is also the default bluefox field name; `fixed` fields always use it.
	 */
	const DEFINITIONS = array(
		// Built-in-like `name` field used by templates as {{contact.name}}: its bluefox name cannot be changed.
		'name'                => array(
			'type'  => 'string',
			'woo'   => false,
			'on'    => true,
			'fixed' => true,
		),
		'firstName'           => array(
			'type' => 'string',
			'woo'  => false,
			'on'   => true,
		),
		'lastName'            => array(
			'type' => 'string',
			'woo'  => false,
			'on'   => true,
		),
		'wpUserId'            => array(
			'type' => 'number',
			'woo'  => false,
			'on'   => true,
		),
		'wpRole'              => array(
			'type' => 'string',
			'woo'  => false,
			'on'   => true,
		),
		'registeredAt'        => array(
			'type' => 'date',
			'woo'  => false,
			'on'   => true,
		),
		'orderCount'          => array(
			'type' => 'number',
			'woo'  => true,
			'on'   => true,
		),
		'totalSpent'          => array(
			'type' => 'number',
			'woo'  => true,
			'on'   => true,
		),
		'lastOrderDate'       => array(
			'type' => 'date',
			'woo'  => true,
			'on'   => true,
		),
		'lastOrderTotal'      => array(
			'type' => 'number',
			'woo'  => true,
			'on'   => true,
		),
		'firstOrderDate'      => array(
			'type' => 'date',
			'woo'  => true,
			'on'   => true,
		),
		'billingCountry'      => array(
			'type' => 'string',
			'woo'  => true,
			'on'   => true,
		),
		'billingCity'         => array(
			'type' => 'string',
			'woo'  => true,
			'on'   => true,
		),
		'purchasedCategories' => array(
			'type' => 'string',
			'woo'  => true,
			'on'   => false,
		),
		'customerType'        => array(
			'type' => 'string',
			'woo'  => true,
			'on'   => true,
		),
	);

	/**
	 * Human labels of the fields.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'name'                => __( 'Full name (first + last name)', 'connector-for-bluefox-email' ),
			'firstName'           => __( 'First name', 'connector-for-bluefox-email' ),
			'lastName'            => __( 'Last name', 'connector-for-bluefox-email' ),
			'wpUserId'            => __( 'WordPress user ID', 'connector-for-bluefox-email' ),
			'wpRole'              => __( 'WordPress role', 'connector-for-bluefox-email' ),
			'registeredAt'        => __( 'Registration date', 'connector-for-bluefox-email' ),
			'orderCount'          => __( 'Number of orders', 'connector-for-bluefox-email' ),
			'totalSpent'          => __( 'Total spent', 'connector-for-bluefox-email' ),
			'lastOrderDate'       => __( 'Last order date', 'connector-for-bluefox-email' ),
			'lastOrderTotal'      => __( 'Last order total', 'connector-for-bluefox-email' ),
			'firstOrderDate'      => __( 'First order date', 'connector-for-bluefox-email' ),
			'billingCountry'      => __( 'Billing country', 'connector-for-bluefox-email' ),
			'billingCity'         => __( 'Billing city', 'connector-for-bluefox-email' ),
			'purchasedCategories' => __( 'Purchased product categories (comma-separated slugs)', 'connector-for-bluefox-email' ),
			'customerType'        => __( 'Customer type (customer / guest)', 'connector-for-bluefox-email' ),
		);
	}

	/**
	 * Defaults of the `sync` area.
	 */
	public static function defaults(): array {
		$fields = array();
		foreach ( self::DEFINITIONS as $key => $definition ) {
			$fields[ $key ] = array(
				'enabled' => $definition['on'],
				'name'    => $key,
			);
		}
		return array(
			// subscribers = only update existing bluefox contacts; all = also create contacts.
			'scope'        => 'subscribers',
			'auto_sync'    => true,
			'tag_wp_user'  => false,
			'tag_customer' => false,
			'fields'       => $fields,
		);
	}

	/**
	 * `cfbe_settings_defaults_sync` callback.
	 *
	 * @param array $defaults Defaults from core (empty for a module area).
	 */
	public static function filter_defaults( $defaults ): array {
		return array_merge( (array) $defaults, self::defaults() );
	}

	/**
	 * Stored settings merged over the defaults (per field as well).
	 */
	public static function settings(): array {
		$stored   = get_option( self::OPTION, array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$defaults = self::defaults();
		$values   = array_merge( $defaults, $stored );

		$fields        = array();
		$stored_fields = isset( $stored['fields'] ) && is_array( $stored['fields'] ) ? $stored['fields'] : array();
		foreach ( $defaults['fields'] as $key => $default ) {
			$row            = isset( $stored_fields[ $key ] ) && is_array( $stored_fields[ $key ] ) ? $stored_fields[ $key ] : array();
			$fields[ $key ] = array(
				'enabled' => array_key_exists( 'enabled', $row ) ? (bool) $row['enabled'] : $default['enabled'],
				'name'    => ! self::is_fixed( $key ) && isset( $row['name'] ) && '' !== (string) $row['name'] ? (string) $row['name'] : $default['name'],
			);
		}
		$values['fields'] = $fields;
		$values['scope']  = 'all' === $values['scope'] ? 'all' : 'subscribers';
		return $values;
	}

	/**
	 * Enabled fields available on this site: key => bluefox field name.
	 *
	 * @param array|null $settings Settings (null = stored).
	 * @param bool|null  $woo      WooCommerce active (null = detect).
	 * @return array<string, string>
	 */
	public static function active_map( ?array $settings = null, ?bool $woo = null ): array {
		$settings = null === $settings ? self::settings() : $settings;
		$woo      = null === $woo ? Modules::woocommerce_active() : $woo;
		$map      = array();
		foreach ( self::DEFINITIONS as $key => $definition ) {
			if ( $definition['woo'] && ! $woo ) {
				continue;
			}
			$row = $settings['fields'][ $key ] ?? null;
			if ( is_array( $row ) && ! empty( $row['enabled'] ) ) {
				$map[ $key ] = ! self::is_fixed( $key ) && '' !== (string) ( $row['name'] ?? '' ) ? (string) $row['name'] : $key;
			}
		}
		return $map;
	}

	/**
	 * Whether a field's bluefox name is fixed (not editable).
	 *
	 * @param string $key Field key.
	 */
	public static function is_fixed( string $key ): bool {
		return ! empty( self::DEFINITIONS[ $key ]['fixed'] );
	}

	/**
	 * Cleans a bluefox field name: `[A-Za-z0-9_]`, max 64 characters.
	 *
	 * @param string $name Raw name.
	 */
	public static function sanitize_name( string $name ): string {
		return substr( (string) preg_replace( '/[^A-Za-z0-9_]/', '', $name ), 0, 64 );
	}

	/**
	 * `cfbe_sanitize_settings_sync` callback.
	 *
	 * @param array $clean    Generic-sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public static function sanitize( $clean, $input, $previous = array() ): array {
		$clean    = is_array( $clean ) ? $clean : array();
		$input    = is_array( $input ) ? $input : array();
		$previous = is_array( $previous ) ? $previous : array();

		$clean['scope']        = 'all' === ( $input['scope'] ?? '' ) ? 'all' : 'subscribers';
		$clean['auto_sync']    = ! empty( $input['auto_sync'] );
		$clean['tag_wp_user']  = ! empty( $input['tag_wp_user'] );
		$clean['tag_customer'] = ! empty( $input['tag_customer'] );

		$raw_fields  = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
		$prev_fields = isset( $previous['fields'] ) && is_array( $previous['fields'] ) ? $previous['fields'] : array();
		$fields      = array();
		$used        = array( 'name' => true );
		foreach ( self::DEFINITIONS as $key => $definition ) {
			$row = isset( $raw_fields[ $key ] ) && is_array( $raw_fields[ $key ] ) ? $raw_fields[ $key ] : null;
			if ( null === $row || empty( $row['present'] ) ) {
				// Not rendered (e.g. a WooCommerce field while WooCommerce is off): keep what was stored.
				$prev    = isset( $prev_fields[ $key ] ) && is_array( $prev_fields[ $key ] ) ? $prev_fields[ $key ] : array();
				$enabled = array_key_exists( 'enabled', $prev ) ? (bool) $prev['enabled'] : $definition['on'];
				$name    = self::sanitize_name( (string) ( $prev['name'] ?? $key ) );
			} else {
				$enabled = ! empty( $row['enabled'] );
				$name    = self::sanitize_name( is_scalar( $row['name'] ?? null ) ? (string) $row['name'] : '' );
			}
			if ( self::is_fixed( $key ) ) {
				$name = $key;
			} elseif ( '' === $name || in_array( $name, self::RESERVED, true ) || isset( $used[ $name ] ) ) {
				$name = $key;
			}
			$used[ $name ]  = true;
			$fields[ $key ] = array(
				'enabled' => $enabled,
				'name'    => $name,
			);
		}
		$clean['fields'] = $fields;
		return $clean;
	}
}
