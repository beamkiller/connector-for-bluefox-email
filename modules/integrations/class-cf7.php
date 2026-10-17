<?php
/**
 * Contact Form 7 integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `wpcf7_mail_sent( WPCF7_ContactForm $contact_form )` and reads the posted data from
 * `WPCF7_Submission::get_instance()->get_posted_data()`. Field identifiers are CF7 field names.
 */
class Cf7 extends Integration {

	/**
	 * Slug.
	 */
	public function slug(): string {
		return 'cf7';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Contact Form 7';
	}

	/**
	 * Detection.
	 */
	public function is_active(): bool {
		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wpcf7_mail_sent', array( $this, 'on_mail_sent' ), 10, 1 );
	}

	/**
	 * Field help.
	 */
	public function field_help(): string {
		return __( 'Use the CF7 field names, e.g. your-email, your-name, acceptance-newsletter.', 'connector-for-bluefox-email' );
	}

	/**
	 * Contact forms (id => title).
	 */
	public function forms(): ?array {
		if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
			return null;
		}
		$forms = array();
		foreach ( (array) \WPCF7_ContactForm::find( array( 'posts_per_page' => 100 ) ) as $form ) {
			if ( is_object( $form ) && method_exists( $form, 'id' ) ) {
				$forms[ (string) $form->id() ] = (string) $form->title();
			}
		}
		return $forms;
	}

	/**
	 * `wpcf7_mail_sent` callback.
	 *
	 * @param mixed $contact_form WPCF7_ContactForm.
	 */
	public function on_mail_sent( $contact_form ): void {
		$this->guard(
			function () use ( $contact_form ) {
				if ( ! is_object( $contact_form ) || ! method_exists( $contact_form, 'id' ) || ! class_exists( 'WPCF7_Submission' ) ) {
					return;
				}
				if ( ! $this->rules_for( (string) $contact_form->id() ) ) {
					return;
				}
				$submission = \WPCF7_Submission::get_instance();
				if ( ! is_object( $submission ) ) {
					return;
				}
				$this->handle( $this->submission( $contact_form, (array) $submission->get_posted_data() ) );
			}
		);
	}

	/**
	 * Normalizes a CF7 submission.
	 *
	 * @param object $contact_form WPCF7_ContactForm.
	 * @param array  $posted       Posted data (field name => value).
	 */
	public function submission( $contact_form, array $posted ): Submission {
		$emails = array();
		$labels = array();
		if ( method_exists( $contact_form, 'scan_form_tags' ) ) {
			foreach ( (array) $contact_form->scan_form_tags( array( 'basetype' => 'email' ) ) as $tag ) {
				if ( ! empty( $tag->name ) ) {
					$emails[] = (string) $tag->name;
				}
			}
			foreach ( (array) $contact_form->scan_form_tags( array( 'basetype' => 'acceptance' ) ) as $tag ) {
				if ( empty( $tag->name ) ) {
					continue;
				}
				$label = ! empty( $tag->content ) ? (string) $tag->content : (string) ( is_array( $tag->values ?? null ) ? reset( $tag->values ) : '' );
				if ( '' !== trim( $label ) ) {
					$labels[ (string) $tag->name ] = $label;
				}
			}
		}
		$title = method_exists( $contact_form, 'title' ) ? (string) $contact_form->title() : '';
		return new Submission( (string) $contact_form->id(), $title, $posted, $emails, $labels, $this->ip() );
	}
}
