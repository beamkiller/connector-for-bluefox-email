<?php
/**
 * Email rules: events, sanitizing, matching, delays.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

defined( 'ABSPATH' ) || exit;

/**
 * A rule = event + email type (transactional | triggered) + bluefox email ID + optional delay + enabled
 * (+ replace_wc: switch off WooCommerce's own email for the event).
 * Stored in the `woo_events` settings area under `rules`.
 */
final class Rules {

	const TYPE_TRANSACTIONAL = 'transactional';
	const TYPE_TRIGGERED     = 'triggered';
	const TYPES              = array( self::TYPE_TRANSACTIONAL, self::TYPE_TRIGGERED );

	const EVENT_FIRST_ORDER    = 'first_order';
	const EVENT_CUSTOMER_NOTE  = 'order_note_customer';
	const EVENT_ABANDONED_CART = 'abandoned_cart';
	const EVENT_REVIEW_REQUEST = 'review_request';
	const STATUS_PREFIX        = 'order_status_';

	const UNITS = array(
		'minutes' => 60,
		'hours'   => 3600,
		'days'    => 86400,
	);

	const MAX_RULES = 50;
	const MAX_DELAY = 31536000; // One year.

	/**
	 * Events that may only use triggered emails (marketing: bluefox sends them to active subscribers only).
	 */
	const TRIGGERED_ONLY = array( self::EVENT_ABANDONED_CART );

	/**
	 * Events WooCommerce already covers with a customer email: event => WooCommerce email ID. A transactional rule
	 * with `replace_wc` switches that WooCommerce email off, so the customer gets only the bluefox email.
	 */
	const WC_EMAILS = array(
		self::STATUS_PREFIX . 'processing' => 'customer_processing_order',
		self::STATUS_PREFIX . 'completed'  => 'customer_completed_order',
		self::STATUS_PREFIX . 'on-hold'    => 'customer_on_hold_order',
		self::STATUS_PREFIX . 'refunded'   => 'customer_refunded_order',
		self::STATUS_PREFIX . 'cancelled'  => 'customer_cancelled_order',
		self::STATUS_PREFIX . 'failed'     => 'customer_failed_order',
		self::EVENT_CUSTOMER_NOTE          => 'customer_note',
	);

	/**
	 * Selectable events: event => label.
	 *
	 * @param array $statuses Order statuses (`wc_get_order_statuses()`: `wc-processing` => label).
	 * @return array<string, string>
	 */
	public static function events( array $statuses ): array {
		$events = array();
		foreach ( $statuses as $status => $label ) {
			$slug = self::status_slug( (string) $status );
			if ( '' === $slug ) {
				continue;
			}
			/* translators: %s: order status name */
			$events[ self::STATUS_PREFIX . $slug ] = sprintf( __( 'Order status: %s', 'connector-for-bluefox-email' ), wp_strip_all_tags( (string) $label ) );
		}
		$events[ self::EVENT_FIRST_ORDER ]    = __( 'First paid order of a customer', 'connector-for-bluefox-email' );
		$events[ self::EVENT_CUSTOMER_NOTE ]  = __( 'Note to customer added', 'connector-for-bluefox-email' );
		$events[ self::EVENT_REVIEW_REQUEST ] = __( 'Review request (after the order is completed)', 'connector-for-bluefox-email' );
		$events[ self::EVENT_ABANDONED_CART ] = __( 'Abandoned cart', 'connector-for-bluefox-email' );
		return $events;
	}

	/**
	 * Names of the WooCommerce customer emails a rule can replace: event => email name.
	 *
	 * @return array<string, string>
	 */
	public static function wc_email_titles(): array {
		return array(
			self::STATUS_PREFIX . 'processing' => __( 'Processing order', 'connector-for-bluefox-email' ),
			self::STATUS_PREFIX . 'completed'  => __( 'Completed order', 'connector-for-bluefox-email' ),
			self::STATUS_PREFIX . 'on-hold'    => __( 'Order on-hold', 'connector-for-bluefox-email' ),
			self::STATUS_PREFIX . 'refunded'   => __( 'Refunded order', 'connector-for-bluefox-email' ),
			self::STATUS_PREFIX . 'cancelled'  => __( 'Cancelled order', 'connector-for-bluefox-email' ),
			self::STATUS_PREFIX . 'failed'     => __( 'Failed order', 'connector-for-bluefox-email' ),
			self::EVENT_CUSTOMER_NOTE          => __( 'Customer note', 'connector-for-bluefox-email' ),
		);
	}

	/**
	 * WooCommerce email IDs switched off by enabled, complete transactional rules with `replace_wc`.
	 *
	 * @param array $rules Rules.
	 * @return string[]
	 */
	public static function replaced_wc_emails( array $rules ): array {
		$ids = array();
		foreach ( $rules as $rule ) {
			$event = is_array( $rule ) ? (string) ( $rule['event'] ?? '' ) : '';
			if ( isset( self::WC_EMAILS[ $event ] ) && ! empty( $rule['replace_wc'] ) && self::TYPE_TRANSACTIONAL === ( $rule['type'] ?? '' ) && self::matching( array( $rule ), $event ) ) {
				$ids[ self::WC_EMAILS[ $event ] ] = true;
			}
		}
		return array_keys( $ids );
	}

	/**
	 * `wc-processing` → `processing` (lowercase, safe characters only).
	 *
	 * @param string $status Status with or without the `wc-` prefix.
	 */
	public static function status_slug( string $status ): string {
		$status = strtolower( $status );
		if ( 0 === strpos( $status, 'wc-' ) ) {
			$status = substr( $status, 3 );
		}
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', $status );
	}

	/**
	 * Sanitizes the rule rows from the settings form.
	 *
	 * @param mixed $input  Raw rows.
	 * @param array $events Valid events (keys).
	 * @param array $known  Known email IDs per type (`transactional` => [id => name], `triggered` => …); an empty
	 *                      list for a type means "unknown", so any well-formed ID is kept.
	 * @return array[] Clean rules.
	 */
	public static function sanitize( $input, array $events, array $known = array() ): array {
		$rules = array();
		$ids   = array();
		if ( ! is_array( $input ) ) {
			return $rules;
		}
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || count( $rules ) >= self::MAX_RULES ) {
				continue;
			}
			$event = (string) ( $row['event'] ?? '' );
			if ( ! isset( $events[ $event ] ) ) {
				continue;
			}
			$type = in_array( $row['type'] ?? '', self::TYPES, true ) ? (string) $row['type'] : self::TYPE_TRANSACTIONAL;
			if ( in_array( $event, self::TRIGGERED_ONLY, true ) ) {
				$type = self::TYPE_TRIGGERED;
			}

			$email_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $row['email_id'] ?? '' ) );
			// An ID known as the other email type is a mismatch; an unknown ID is kept (the cached lists can be stale).
			$other = self::TYPE_TRIGGERED === $type ? self::TYPE_TRANSACTIONAL : self::TYPE_TRIGGERED;
			if ( '' !== $email_id && ! isset( $known[ $type ][ $email_id ] ) && isset( $known[ $other ][ $email_id ] ) ) {
				$email_id = '';
			}

			$unit  = isset( self::UNITS[ $row['delay_unit'] ?? '' ] ) ? (string) $row['delay_unit'] : 'minutes';
			$delay = min( absint( $row['delay'] ?? 0 ), (int) floor( self::MAX_DELAY / self::UNITS[ $unit ] ) );

			$id = strtolower( (string) ( $row['id'] ?? '' ) );
			if ( ! preg_match( '/^[a-z0-9]{8,16}$/', $id ) || isset( $ids[ $id ] ) ) {
				$id = self::new_id();
			}
			$ids[ $id ] = true;

			$rules[] = array(
				'id'         => $id,
				'event'      => $event,
				'type'       => $type,
				'email_id'   => $email_id,
				'delay'      => $delay,
				'delay_unit' => $unit,
				'enabled'    => ! empty( $row['enabled'] ) && '' !== $email_id,
				// Only a transactional email can stand in for WooCommerce's: a triggered one skips non-subscribers.
				'replace_wc' => ! empty( $row['replace_wc'] ) && isset( self::WC_EMAILS[ $event ] ) && self::TYPE_TRANSACTIONAL === $type,
			);
		}
		return $rules;
	}

	/**
	 * New random rule ID.
	 */
	public static function new_id(): string {
		return strtolower( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Enabled, complete rules for an event.
	 *
	 * @param array  $rules Rules.
	 * @param string $event Event.
	 * @return array[]
	 */
	public static function matching( array $rules, string $event ): array {
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ( $rule['event'] ?? '' ) !== $event || empty( $rule['enabled'] ) || '' === (string) ( $rule['email_id'] ?? '' ) ) {
				continue;
			}
			if ( in_array( $event, self::TRIGGERED_ONLY, true ) && self::TYPE_TRIGGERED !== ( $rule['type'] ?? '' ) ) {
				continue;
			}
			$out[] = $rule;
		}
		return $out;
	}

	/**
	 * A rule by ID.
	 *
	 * @param array  $rules Rules.
	 * @param string $id    Rule ID.
	 */
	public static function find( array $rules, string $id ): ?array {
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && ( $rule['id'] ?? '' ) === $id ) {
				return $rule;
			}
		}
		return null;
	}

	/**
	 * Delay of a rule in seconds.
	 *
	 * @param array $rule Rule.
	 */
	public static function delay_seconds( array $rule ): int {
		$unit = self::UNITS[ $rule['delay_unit'] ?? 'minutes' ] ?? 60;
		return min( absint( $rule['delay'] ?? 0 ) * $unit, self::MAX_DELAY );
	}

	/**
	 * Order meta key that records a rule was sent for an order (dedupe).
	 *
	 * @param string $rule_id Rule ID.
	 * @param string $suffix  Optional discriminator (e.g. a customer note hash).
	 */
	public static function sent_meta_key( string $rule_id, string $suffix = '' ): string {
		return '_cfbe_sent_' . $rule_id . ( '' !== $suffix ? '_' . $suffix : '' );
	}
}
