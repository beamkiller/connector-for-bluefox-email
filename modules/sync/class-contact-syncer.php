<?php
/**
 * Contact sync: writes one email's data to its bluefox contact.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Bluefox_Client;
use CFBE\Logger;
use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the payload for an email and updates (or, in `all` scope, creates) the bluefox contact.
 * Creating a contact never subscribes it to a list.
 */
class Contact_Syncer {

	const INVALID        = 'invalid';
	const NOT_CONFIGURED = 'not_configured';
	const NO_DATA        = 'no_data';
	const NOT_CONTACT    = 'not_contact';
	const CREATED        = 'created';
	const UPDATED        = 'updated';
	const UNCHANGED      = 'unchanged';
	const ERROR          = 'error';

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Data collector.
	 *
	 * @var Data_Collector
	 */
	private $collector;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null $client    Client.
	 * @param Data_Collector      $collector Data collector.
	 * @param Logger              $logger    Logger.
	 */
	public function __construct( ?Bluefox_Client $client, Data_Collector $collector, Logger $logger ) {
		$this->client    = $client;
		$this->collector = $collector;
		$this->logger    = $logger;
	}

	/**
	 * Syncs one email address.
	 *
	 * @param string $email   Email address.
	 * @param bool   $dry_run Compute only, never write (created/updated then mean "would be").
	 * @return array{outcome: string, payload: array}
	 */
	public function sync( string $email, bool $dry_run = false ): array {
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return self::result( self::INVALID );
		}
		if ( null === $this->client ) {
			return self::result( self::NOT_CONFIGURED );
		}

		$settings = Fields::settings();
		$map      = Fields::active_map( $settings, Modules::woocommerce_active() );
		$user     = get_user_by( 'email', $email );
		$user     = is_object( $user ) ? $user : null;
		$data     = $this->collector->collect( $email, $user, array_keys( $map ) );

		if ( ! $data['has_user'] && 0 === $data['paid_orders'] ) {
			return self::result( self::NO_DATA );
		}

		$fields = array();
		foreach ( $map as $key => $name ) {
			if ( array_key_exists( $key, $data['values'] ) ) {
				$fields[ $name ] = $data['values'][ $key ];
			}
		}

		/**
		 * Filters the custom field values sent to the bluefox contact (keys = bluefox field names).
		 *
		 * @param array         $fields Field values.
		 * @param string        $email  Email address.
		 * @param \WP_User|null $user   The WordPress user, if any.
		 */
		$fields = (array) apply_filters( 'cfbe_sync_contact_data', $fields, $email, $user );
		unset( $fields['email'], $fields['tags'] );

		$add_tags = array();
		if ( ! empty( $settings['tag_wp_user'] ) && $data['has_user'] ) {
			$add_tags[] = Fields::TAG_WP_USER;
		}
		if ( ! empty( $settings['tag_customer'] ) && $data['paid_orders'] > 0 ) {
			$add_tags[] = Fields::TAG_CUSTOMER;
		}

		$contact = $this->client->get_contact( $email );
		if ( is_wp_error( $contact ) ) {
			if ( 'cfbe_bluefox_not_found' !== $contact->get_error_code() ) {
				$this->logger->error(
					'Contact sync: reading the contact failed',
					array(
						'email' => $email,
						'error' => $contact->get_error_message(),
					)
				);
				return self::result( self::ERROR );
			}
			if ( 'all' !== $settings['scope'] ) {
				return self::result( self::NOT_CONTACT );
			}
			$payload = array( 'email' => $email ) + $fields;
			if ( $add_tags ) {
				$payload['tags'] = $add_tags;
			}
			if ( ! $dry_run ) {
				$created = $this->client->create_contact( $payload );
				if ( is_wp_error( $created ) ) {
					$this->logger->error(
						'Contact sync: creating the contact failed',
						array(
							'email' => $email,
							'error' => $created->get_error_message(),
						)
					);
					return self::result( self::ERROR, $payload );
				}
			}
			return self::result( self::CREATED, $payload );
		}

		$payload = self::changed_fields( $fields, (array) $contact );
		if ( isset( $payload['name'] ) && '' === trim( (string) $payload['name'] ) && '' !== trim( (string) ( $contact['name'] ?? '' ) ) ) {
			// Never blank out an existing bluefox name.
			unset( $payload['name'] );
		}
		$existing = isset( $contact['tags'] ) && is_array( $contact['tags'] ) ? $contact['tags'] : array();
		$merged   = self::merge_tags( $existing, $add_tags );
		if ( count( $merged ) !== count( array_unique( array_map( 'strval', $existing ) ) ) ) {
			// PATCH replaces the tag list, so always send the merged list.
			$payload['tags'] = $merged;
		}
		if ( ! $payload ) {
			return self::result( self::UNCHANGED );
		}
		if ( ! $dry_run ) {
			$updated = $this->client->update_contact( $email, $payload );
			if ( is_wp_error( $updated ) ) {
				$this->logger->error(
					'Contact sync: updating the contact failed',
					array(
						'email' => $email,
						'error' => $updated->get_error_message(),
					)
				);
				return self::result( self::ERROR, $payload );
			}
		}
		return self::result( self::UPDATED, $payload );
	}

	/**
	 * Existing tags plus new ones (no duplicates, original order kept).
	 *
	 * @param array $existing Current tags.
	 * @param array $add      Tags to add.
	 * @return string[]
	 */
	public static function merge_tags( array $existing, array $add ): array {
		$out = array();
		foreach ( array_merge( $existing, $add ) as $tag ) {
			if ( ! is_scalar( $tag ) ) {
				continue;
			}
			$tag = trim( (string) $tag );
			if ( '' !== $tag && ! in_array( $tag, $out, true ) ) {
				$out[] = $tag;
			}
		}
		return $out;
	}

	/**
	 * Fields whose value differs from the contact's current value.
	 *
	 * @param array $fields  New values.
	 * @param array $contact Current contact.
	 */
	public static function changed_fields( array $fields, array $contact ): array {
		$changed = array();
		foreach ( $fields as $name => $value ) {
			if ( ! array_key_exists( $name, $contact ) || ! self::same( $value, $contact[ $name ] ) ) {
				$changed[ $name ] = $value;
			}
		}
		return $changed;
	}

	/**
	 * Loose equality that tolerates number formatting and ISO date-times for Y-m-d dates.
	 *
	 * @param mixed $value   New value.
	 * @param mixed $current Current value.
	 */
	private static function same( $value, $current ): bool {
		if ( is_numeric( $value ) && is_numeric( $current ) ) {
			return abs( (float) $value - (float) $current ) < 0.005;
		}
		if ( ! is_scalar( $value ) || ! is_scalar( $current ) ) {
			return false;
		}
		$value   = (string) $value;
		$current = (string) $current;
		if ( 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return 0 === strpos( $current, $value );
		}
		return $value === $current;
	}

	/**
	 * Result array.
	 *
	 * @param string $outcome Outcome.
	 * @param array  $payload Payload.
	 */
	private static function result( string $outcome, array $payload = array() ): array {
		return array(
			'outcome' => $outcome,
			'payload' => $payload,
		);
	}
}
