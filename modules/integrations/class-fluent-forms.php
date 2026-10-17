<?php
/**
 * Fluent Forms integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `fluentform/submission_inserted( $submission_id, $form_data, $form )`.
 * Field identifiers are Fluent Forms input names (`email`, `names.first_name`, `terms-n-condition`).
 */
class Fluent_Forms extends Integration {

	/**
	 * Elements whose input is an email.
	 */
	const EMAIL_ELEMENTS = array( 'input_email' );

	/**
	 * Agreement elements (consent wording lives in settings.tnc_html).
	 */
	const CONSENT_ELEMENTS = array( 'terms_and_condition', 'gdpr_agreement' );

	/**
	 * Slug.
	 */
	public function slug(): string {
		return 'fluentforms';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Fluent Forms';
	}

	/**
	 * Detection.
	 */
	public function is_active(): bool {
		return defined( 'FLUENTFORM' ) || function_exists( 'wpFluentForm' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'fluentform/submission_inserted', array( $this, 'on_inserted' ), 10, 3 );
	}

	/**
	 * Field help.
	 */
	public function field_help(): string {
		return __( 'Use the Fluent Forms input names (Advanced Options → Name Attribute), e.g. email, names.first_name, terms-n-condition.', 'connector-for-bluefox-email' );
	}

	/**
	 * Forms (id => title).
	 */
	public function forms(): ?array {
		$model = '\FluentForm\App\Models\Form';
		if ( ! class_exists( $model ) ) {
			return null;
		}
		try {
			$forms = array();
			foreach ( $model::select( array( 'id', 'title' ) )->orderBy( 'id', 'DESC' )->limit( 200 )->get() as $form ) {
				$forms[ (string) $form->id ] = (string) $form->title;
			}
			return $forms;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * `fluentform/submission_inserted` callback.
	 *
	 * @param mixed $submission_id Submission ID.
	 * @param mixed $form_data     Posted data (input name => value).
	 * @param mixed $form          Form object (id, title, form_fields JSON).
	 */
	public function on_inserted( $submission_id, $form_data, $form ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		$this->guard(
			function () use ( $form_data, $form ) {
				if ( ! is_array( $form_data ) || ! is_object( $form ) || ! isset( $form->id ) ) {
					return;
				}
				if ( $this->rules_for( (string) $form->id ) ) {
					$this->handle( $this->submission( $form_data, $form ) );
				}
			}
		);
	}

	/**
	 * Normalizes a Fluent Forms submission.
	 *
	 * @param array  $form_data Posted data.
	 * @param object $form      Form object.
	 */
	public function submission( array $form_data, $form ): Submission {
		$emails = array();
		$labels = array();
		$schema = json_decode( (string) ( $form->form_fields ?? '' ), true );
		if ( is_array( $schema ) ) {
			$this->scan_schema( $schema, $emails, $labels );
		}
		return new Submission( (string) $form->id, (string) ( $form->title ?? '' ), $form_data, $emails, $labels, $this->ip() );
	}

	/**
	 * Walks the form schema (containers nest fields in columns) collecting email inputs and consent wording.
	 *
	 * @param array    $node   Schema node.
	 * @param string[] $emails Email input names (by reference).
	 * @param array    $labels Input name => label (by reference).
	 */
	private function scan_schema( array $node, array &$emails, array &$labels ): void {
		if ( isset( $node['element'], $node['attributes']['name'] ) && is_string( $node['attributes']['name'] ) ) {
			$name    = $node['attributes']['name'];
			$element = (string) $node['element'];
			if ( in_array( $element, self::EMAIL_ELEMENTS, true ) ) {
				$emails[] = $name;
			}
			$label = in_array( $element, self::CONSENT_ELEMENTS, true ) && ! empty( $node['settings']['tnc_html'] )
				? (string) $node['settings']['tnc_html']
				: (string) ( $node['settings']['label'] ?? '' );
			if ( '' !== trim( $label ) ) {
				$labels[ $name ] = $label;
			}
		}
		foreach ( $node as $child ) {
			if ( is_array( $child ) ) {
				$this->scan_schema( $child, $emails, $labels );
			}
		}
	}
}
