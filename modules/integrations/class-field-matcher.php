<?php
/**
 * Matches submitted form fields to Contact sync fields by name.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Sync\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A submitted field whose identifier (or the last part of a nested one, e.g. `names.first_name`) matches a
 * Contact sync field that is switched on is sent to bluefox under that field's bluefox name. Matching ignores
 * case, `_`, `-` and spaces (`first_name` = `firstName`) and knows a few common aliases (`phone`, `zip`, …).
 * Only fields a visitor may state about themselves can match; store data (order count, total spent, role, …) never.
 */
final class Field_Matcher {

	/**
	 * Contact sync field key => accepted identifiers (normalized: lowercase letters and digits only).
	 * The key itself and its bluefox name (Contact sync tab) always match too.
	 */
	const MATCHABLE = array(
		'name'            => array( 'fullname', 'yourname' ),
		'firstName'       => array( 'firstname', 'fname', 'givenname' ),
		'lastName'        => array( 'lastname', 'lname', 'surname', 'familyname' ),
		'billingPhone'    => array( 'phone', 'phonenumber', 'tel', 'telephone' ),
		'billingCompany'  => array( 'company', 'companyname' ),
		'billingAddress'  => array( 'address', 'streetaddress', 'addressline1', 'address1' ),
		'billingCity'     => array( 'city' ),
		'billingPostcode' => array( 'postcode', 'zip', 'zipcode', 'postalcode' ),
		'billingState'    => array( 'state' ),
		'billingCountry'  => array( 'country' ),
	);

	/**
	 * Longest value sent for a matched field.
	 */
	const MAX_LENGTH = 255;

	/**
	 * Normalized identifier: lowercase letters and digits.
	 *
	 * @param string $id Identifier.
	 */
	public static function normalize( string $id ): string {
		return (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $id ) );
	}

	/**
	 * Normalized identifier => Contact sync field key, for the fields that are switched on.
	 *
	 * @param array<string, string>|null $active Enabled fields: key => bluefox name (null = Contact sync settings).
	 * @return array<string, string>
	 */
	public static function lookup( ?array $active = null ): array {
		$active = null === $active ? Fields::active_map() : $active;
		$lookup = array();
		foreach ( self::MATCHABLE as $key => $aliases ) {
			if ( ! isset( $active[ $key ] ) ) {
				continue;
			}
			foreach ( array_merge( array( $key, (string) $active[ $key ] ), $aliases ) as $alias ) {
				$alias = self::normalize( $alias );
				if ( '' !== $alias && ! isset( $lookup[ $alias ] ) ) {
					$lookup[ $alias ] = $key;
				}
			}
		}
		return $lookup;
	}

	/**
	 * Matched values: Contact sync field key => value (first match wins, empty values are skipped).
	 *
	 * @param Submission                 $sub    Submission.
	 * @param string[]                   $skip   Identifiers not to match (email and consent fields).
	 * @param array<string, string>|null $active Enabled fields (null = Contact sync settings).
	 * @return array<string, string>
	 */
	public static function match( Submission $sub, array $skip = array(), ?array $active = null ): array {
		$lookup = self::lookup( $active );
		if ( ! $lookup ) {
			return array();
		}
		$skip    = array_filter( array_map( array( __CLASS__, 'normalize' ), $skip ) );
		$matched = array();
		foreach ( self::leaves( $sub->values ) as $path => $value ) {
			$parts = explode( '.', (string) $path );
			$top   = self::normalize( $parts[0] );
			$full  = self::normalize( (string) $path );
			if ( in_array( $top, $skip, true ) || in_array( $full, $skip, true ) ) {
				continue;
			}
			$key = $lookup[ $full ] ?? ( $lookup[ self::normalize( (string) end( $parts ) ) ] ?? null );
			if ( null === $key || isset( $matched[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( $value );
			if ( '' !== $value ) {
				$matched[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_LENGTH ) : substr( $value, 0, self::MAX_LENGTH );
			}
		}
		return $matched;
	}

	/**
	 * Scalar values by dotted path (`names.first_name`), two levels deep at most.
	 *
	 * @param array  $values Values.
	 * @param string $prefix Path prefix.
	 * @param int    $depth  Current depth.
	 * @return array<string, string>
	 */
	private static function leaves( array $values, string $prefix = '', int $depth = 0 ): array {
		$out = array();
		foreach ( $values as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;
			if ( is_array( $value ) ) {
				if ( $depth < 2 ) {
					$out += self::leaves( $value, $path, $depth + 1 );
				}
			} elseif ( is_scalar( $value ) && ! is_bool( $value ) ) {
				$out[ $path ] = trim( (string) $value );
			}
		}
		return $out;
	}
}
