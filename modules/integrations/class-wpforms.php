<?php
/**
 * WPForms integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `wpforms_process_complete( $fields, $entry, $form_data, $entry_id )` (fires only for successful entries).
 * Field identifiers are WPForms field IDs; name sub-values as `3.first` / `3.last`.
 */
class Wpforms extends Integration {

	/**
	 * Slug.
	 */
	public function slug(): string {
		return 'wpforms';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'WPForms';
	}

	/**
	 * Detection.
	 */
	public function is_active(): bool {
		return function_exists( 'wpforms' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wpforms_process_complete', array( $this, 'on_complete' ), 10, 4 );
	}

	/**
	 * Field help.
	 */
	public function field_help(): string {
		return __( 'Use the WPForms field IDs (shown in the form builder), e.g. 1, 2; name parts as 1.first / 1.last.', 'connector-for-bluefox-email' );
	}

	/**
	 * Forms (id => title).
	 */
	public function forms(): ?array {
		if ( ! function_exists( 'wpforms' ) ) {
			return null;
		}
		$wpforms = wpforms();
		$handler = is_object( $wpforms ) && method_exists( $wpforms, 'obj' ) ? $wpforms->obj( 'form' ) : ( $wpforms->form ?? null );
		if ( ! is_object( $handler ) || ! method_exists( $handler, 'get' ) ) {
			return null;
		}
		$forms = array();
		foreach ( (array) $handler->get( '', array( 'posts_per_page' => 100 ) ) as $post ) {
			if ( is_object( $post ) && isset( $post->ID ) ) {
				$forms[ (string) $post->ID ] = (string) $post->post_title;
			}
		}
		return $forms;
	}

	/**
	 * `wpforms_process_complete` callback.
	 *
	 * @param mixed $fields    Processed fields (id => field data).
	 * @param mixed $entry     Original $_POST.
	 * @param mixed $form_data Form data.
	 * @param mixed $entry_id  Entry ID (0 in Lite).
	 */
	public function on_complete( $fields, $entry, $form_data, $entry_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassAfterLastUsed
		$this->guard(
			function () use ( $fields, $form_data ) {
				if ( ! is_array( $fields ) || ! is_array( $form_data ) ) {
					return;
				}
				$sub = $this->submission( $fields, $form_data );
				if ( $this->rules_for( $sub->form_id ) ) {
					$this->handle( $sub );
				}
			}
		);
	}

	/**
	 * Normalizes a WPForms submission.
	 *
	 * @param array $fields    Processed fields.
	 * @param array $form_data Form data.
	 */
	public function submission( array $fields, array $form_data ): Submission {
		$values = array();
		$emails = array();
		$labels = array();
		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$id            = (string) ( $field['id'] ?? $key );
			$type          = (string) ( $field['type'] ?? '' );
			$value         = $field['value'] ?? '';
			$values[ $id ] = $value;
			if ( 'email' === $type ) {
				$emails[] = $id;
			}
			if ( 'name' === $type ) {
				foreach ( array( 'first', 'middle', 'last' ) as $part ) {
					if ( isset( $field[ $part ] ) ) {
						$values[ $id . '.' . $part ] = $field[ $part ];
					}
				}
			}
			$name = (string) ( $field['name'] ?? '' );
			// For checkboxes the posted value is the ticked choice text - the actual consent wording.
			$labels[ $id ] = in_array( $type, array( 'checkbox', 'gdpr-checkbox' ), true ) && is_string( $value ) && '' !== trim( $value ) ? $value : $name;
		}
		$title = (string) ( $form_data['settings']['form_title'] ?? '' );
		return new Submission( (string) ( $form_data['id'] ?? '' ), $title, $values, $emails, $labels, $this->ip() );
	}
}
