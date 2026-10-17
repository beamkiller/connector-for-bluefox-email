<?php
/**
 * Gravity Forms integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `gform_after_submission( $entry, $form )` (also runs for spam entries, which are skipped).
 * Field identifiers are Gravity Forms field IDs (`3`) or input IDs (`1.3` = first name).
 */
class Gravity_Forms extends Integration {

	/**
	 * Slug.
	 */
	public function slug(): string {
		return 'gravityforms';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Gravity Forms';
	}

	/**
	 * Detection.
	 */
	public function is_active(): bool {
		return class_exists( 'GFForms' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'gform_after_submission', array( $this, 'on_after_submission' ), 10, 2 );
	}

	/**
	 * Field help.
	 */
	public function field_help(): string {
		return __( 'Use the Gravity Forms field IDs, e.g. 3; a single input as 1.3 (first name) or 1.6 (last name). A Name field ID (1) gives the full name, a Consent field ID gives its checkbox.', 'connector-for-bluefox-email' );
	}

	/**
	 * Forms (id => title).
	 */
	public function forms(): ?array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return null;
		}
		$forms = array();
		foreach ( (array) \GFAPI::get_forms() as $form ) {
			if ( isset( $form['id'] ) ) {
				$forms[ (string) $form['id'] ] = (string) ( $form['title'] ?? '' );
			}
		}
		return $forms;
	}

	/**
	 * `gform_after_submission` callback.
	 *
	 * @param mixed $entry Entry object (array).
	 * @param mixed $form  Form object (array).
	 */
	public function on_after_submission( $entry, $form ): void {
		$this->guard(
			function () use ( $entry, $form ) {
				if ( ! is_array( $entry ) || ! is_array( $form ) || 'spam' === (string) self::rgar( $entry, 'status' ) ) {
					return;
				}
				if ( $this->rules_for( (string) self::rgar( $form, 'id' ) ) ) {
					$this->handle( $this->submission( $entry, $form ) );
				}
			}
		);
	}

	/**
	 * Normalizes a Gravity Forms entry. Composite fields (name, address, checkbox) also get a value under the
	 * field ID: their non-empty inputs joined; a consent field's value is its checkbox input (`<id>.1`).
	 *
	 * @param array $entry Entry.
	 * @param array $form  Form.
	 */
	public function submission( array $entry, array $form ): Submission {
		$values = array();
		foreach ( $entry as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$values[ (string) $key ] = $value;
			}
		}

		$emails = array();
		$labels = array();
		foreach ( (array) self::rgar( $form, 'fields' ) as $field ) {
			$id     = (string) self::prop( $field, 'id' );
			$type   = (string) self::prop( $field, 'type' );
			$inputs = self::prop( $field, 'inputs' );
			if ( '' === $id ) {
				continue;
			}
			if ( 'email' === $type ) {
				$emails[] = $id;
			}
			$label = (string) self::prop( $field, 'label' );
			if ( 'consent' === $type ) {
				$values[ $id ] = self::rgar( $entry, $id . '.1' );
				$text          = (string) self::prop( $field, 'checkboxLabel' );
				$label         = '' !== trim( $text ) ? $text : $label;
			} elseif ( is_array( $inputs ) && $inputs && '' === Submission::flatten( $values[ $id ] ?? '' ) ) {
				$parts = array();
				foreach ( $inputs as $input ) {
					$part = Submission::flatten( self::rgar( $entry, (string) ( is_array( $input ) ? ( $input['id'] ?? '' ) : '' ) ) );
					if ( '' !== $part ) {
						$parts[] = $part;
					}
				}
				$values[ $id ] = 'checkbox' === $type ? $parts : implode( ' ', $parts );
			}
			if ( '' !== trim( $label ) ) {
				$labels[ $id ] = $label;
			}
		}

		return new Submission( (string) self::rgar( $form, 'id' ), (string) self::rgar( $form, 'title' ), $values, $emails, $labels, $this->ip() );
	}

	/**
	 * `rgar()` when Gravity Forms is loaded, else the same plain lookup.
	 *
	 * @param array  $data Array.
	 * @param string $key  Key.
	 * @return mixed
	 */
	private static function rgar( array $data, string $key ) {
		if ( function_exists( 'rgar' ) ) {
			return rgar( $data, $key );
		}
		return $data[ $key ] ?? '';
	}

	/**
	 * Property of a GF_Field object (or array in tests / old exports).
	 *
	 * @param mixed  $field Field.
	 * @param string $key   Property.
	 * @return mixed
	 */
	private static function prop( $field, string $key ) {
		if ( is_array( $field ) ) {
			return $field[ $key ] ?? '';
		}
		return is_object( $field ) && isset( $field->$key ) ? $field->$key : '';
	}
}
