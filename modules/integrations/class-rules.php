<?php
/**
 * Settings defaults and sanitizer for the `integrations` area.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Settings;
use CFBE\Signup_Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Stored shape (option `cfbe_integrations`):
 * `slug => array( 'rules' => list<rule> )` (Elementor: empty, rules live in the widget). The on/off switch of each
 * form plugin is a module switch on the General tab (`integration_<slug>`).
 * Rule: form, list_id, email_field, name_field, consent_field, implied_consent, tags, fields (list of form_field/bluefox_field).
 */
final class Rules {

	const MAX_RULES  = 50;
	const MAX_FIELDS = 30;

	/**
	 * Defaults for the area.
	 */
	public static function defaults(): array {
		$defaults = array();
		foreach ( Module::SOURCES as $slug ) {
			$defaults[ $slug ] = Module::has_rules( $slug ) ? array( 'rules' => array() ) : array();
		}
		return $defaults;
	}

	/**
	 * `cfbe_sanitize_settings_integrations` callback. An integration missing from the input (e.g. its plugin is
	 * inactive, so the tab printed no fields for it) keeps its stored settings.
	 *
	 * @param array $clean    Generic-sanitized values (arrays come back as defaults).
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public static function sanitize( $clean, $input, $previous ): array {
		$clean    = is_array( $clean ) ? $clean : array();
		$input    = is_array( $input ) ? $input : array();
		$previous = is_array( $previous ) ? $previous : array();
		$lists    = get_transient( Settings::LISTS_CACHE );
		$lists    = is_array( $lists ) ? $lists : array();

		foreach ( Module::SOURCES as $slug ) {
			if ( ! isset( $input[ $slug ] ) || ! is_array( $input[ $slug ] ) ) {
				$clean[ $slug ] = isset( $previous[ $slug ] ) && is_array( $previous[ $slug ] ) ? $previous[ $slug ] : self::defaults()[ $slug ];
				continue;
			}
			$raw  = $input[ $slug ];
			$item = array();
			if ( Module::has_rules( $slug ) ) {
				$item['rules'] = array();
				foreach ( array_slice( (array) ( $raw['rules'] ?? array() ), 0, self::MAX_RULES ) as $rule ) {
					$rule = is_array( $rule ) ? self::sanitize_rule( $rule, $lists ) : null;
					if ( null !== $rule ) {
						$item['rules'][] = $rule;
					}
				}
			}
			$clean[ $slug ] = $item;
		}
		return $clean;
	}

	/**
	 * Sanitizes one rule row; null when it has no form.
	 *
	 * @param array                 $raw   Raw row.
	 * @param array<string, string> $lists Cached lists (id => name); empty = not validated.
	 */
	public static function sanitize_rule( array $raw, array $lists = array() ): ?array {
		$form = self::form_id( $raw['form'] ?? '' );
		if ( '' === $form ) {
			return null;
		}

		$list_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $raw['list_id'] ?? '' ) );
		if ( '' !== $list_id && $lists && ! isset( $lists[ $list_id ] ) ) {
			$list_id = '';
		}

		$tags = Signup_Validator::clean_tags( explode( ',', sanitize_text_field( (string) ( $raw['tags'] ?? '' ) ) ) );

		$fields = array();
		foreach ( (array) ( $raw['fields'] ?? array() ) as $map ) {
			if ( ! is_array( $map ) ) {
				continue;
			}
			$from = self::field_id( $map['form_field'] ?? '' );
			$to   = self::bluefox_field( $map['bluefox_field'] ?? '' );
			if ( '' === $from || '' === $to ) {
				continue;
			}
			$fields[] = array(
				'form_field'    => $from,
				'bluefox_field' => $to,
			);
			if ( count( $fields ) >= self::MAX_FIELDS ) {
				break;
			}
		}

		return array(
			'form'            => $form,
			'list_id'         => $list_id,
			'email_field'     => self::field_id( $raw['email_field'] ?? '' ),
			'name_field'      => self::field_id( $raw['name_field'] ?? '' ),
			'consent_field'   => self::field_id( $raw['consent_field'] ?? '' ),
			'implied_consent' => ! empty( $raw['implied_consent'] ),
			'tags'            => implode( ', ', $tags ),
			'fields'          => $fields,
		);
	}

	/**
	 * Empty rule (admin row template).
	 */
	public static function blank_rule(): array {
		return array(
			'form'            => '',
			'list_id'         => '',
			'email_field'     => '',
			'name_field'      => '',
			'consent_field'   => '',
			'implied_consent' => false,
			'tags'            => '',
			'fields'          => array(),
		);
	}

	/**
	 * Form ID: letters, digits, `-`, `_` (all supported plugins use numeric IDs).
	 *
	 * @param mixed $value Raw value.
	 */
	public static function form_id( $value ): string {
		return is_scalar( $value ) ? substr( (string) preg_replace( '/[^A-Za-z0-9_\-]/', '', sanitize_text_field( (string) $value ) ), 0, 64 ) : '';
	}

	/**
	 * Form field identifier: CF7 names (`your-email`), numeric IDs (`3`, `1.3`), input names / paths
	 * (`names[first_name]`, `names.first_name`), Elementor custom IDs.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function field_id( $value ): string {
		return is_scalar( $value ) ? substr( (string) preg_replace( '/[^A-Za-z0-9_\-.:\[\]]/', '', sanitize_text_field( (string) $value ) ), 0, 100 ) : '';
	}

	/**
	 * Key of a bluefox contact field (flat key; `email`, `tags`, `status` are reserved).
	 *
	 * @param mixed $value Raw value.
	 */
	public static function bluefox_field( $value ): string {
		$key = is_scalar( $value ) ? substr( (string) preg_replace( '/[^A-Za-z0-9_]/', '', trim( (string) $value ) ), 0, 64 ) : '';
		return in_array( $key, array( 'email', 'tags', 'status' ), true ) ? '' : $key;
	}

	/**
	 * Field mapping from Elementor's textarea (`form_field=bluefox_field` per line).
	 *
	 * @param string $text Text.
	 * @return list<array{form_field: string, bluefox_field: string}>
	 */
	public static function parse_field_map( string $text ): array {
		$fields = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			if ( false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $from, $to ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$from              = self::field_id( $from );
			$to                = self::bluefox_field( $to );
			if ( '' !== $from && '' !== $to ) {
				$fields[] = array(
					'form_field'    => $from,
					'bluefox_field' => $to,
				);
			}
			if ( count( $fields ) >= self::MAX_FIELDS ) {
				break;
			}
		}
		return $fields;
	}
}
