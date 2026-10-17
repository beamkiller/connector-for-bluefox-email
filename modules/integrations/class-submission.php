<?php
/**
 * A third-party form submission, normalized.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin-independent view of one submission: field identifier => posted value.
 */
final class Submission {

	/**
	 * Form ID.
	 *
	 * @var string
	 */
	public $form_id;

	/**
	 * Form title.
	 *
	 * @var string
	 */
	public $form_title;

	/**
	 * Field identifier => value (string or array).
	 *
	 * @var array
	 */
	public $values;

	/**
	 * Identifiers of email-type fields, in form order.
	 *
	 * @var string[]
	 */
	public $email_fields;

	/**
	 * Field identifier => label (when known).
	 *
	 * @var array<string, string>
	 */
	public $labels;

	/**
	 * Visitor IP captured at submission time.
	 *
	 * @var string
	 */
	public $ip;

	/**
	 * Constructor.
	 *
	 * @param string   $form_id      Form ID.
	 * @param string   $form_title   Form title.
	 * @param array    $values       Field identifier => value.
	 * @param string[] $email_fields Email-type field identifiers.
	 * @param array    $labels       Field identifier => label.
	 * @param string   $ip           Visitor IP.
	 */
	public function __construct( string $form_id, string $form_title, array $values, array $email_fields = array(), array $labels = array(), string $ip = '' ) {
		$this->form_id      = $form_id;
		$this->form_title   = $form_title;
		$this->values       = $values;
		$this->email_fields = array_values( array_map( 'strval', $email_fields ) );
		$this->labels       = $labels;
		$this->ip           = $ip;
	}

	/**
	 * Raw value of a field: exact key first, then a nested path (`names.first_name` or `names[first_name]`).
	 *
	 * @param string $field Field identifier.
	 * @return mixed Null when absent.
	 */
	public function raw( string $field ) {
		if ( '' === $field ) {
			return null;
		}
		if ( array_key_exists( $field, $this->values ) ) {
			return $this->values[ $field ];
		}
		$path = preg_split( '/[.\[\]]+/', $field, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $path || count( $path ) < 2 ) {
			return null;
		}
		$value = $this->values;
		foreach ( $path as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}

	/**
	 * Value of a field as a single trimmed string (array parts joined with a space).
	 *
	 * @param string $field Field identifier.
	 */
	public function text( string $field ): string {
		return self::flatten( $this->raw( $field ) );
	}

	/**
	 * Email field to use when none is configured: the first email-type field, else the first value that is an email.
	 */
	public function detect_email_field(): string {
		if ( $this->email_fields ) {
			return $this->email_fields[0];
		}
		foreach ( $this->values as $key => $value ) {
			if ( is_string( $value ) && '' !== $value && false !== is_email( trim( $value ) ) ) {
				return (string) $key;
			}
		}
		return '';
	}

	/**
	 * Flattens a posted value to text.
	 *
	 * @param mixed $value Value.
	 */
	public static function flatten( $value ): string {
		if ( is_array( $value ) ) {
			$parts = array();
			array_walk_recursive(
				$value,
				static function ( $item ) use ( &$parts ) {
					if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
						$parts[] = trim( (string) $item );
					}
				}
			);
			return implode( ' ', $parts );
		}
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
