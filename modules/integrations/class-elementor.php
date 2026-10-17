<?php
/**
 * Elementor Pro Forms integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a "bluefox.email" action for the Elementor Pro Form widget (`elementor_pro/forms/actions/register`).
 * The action class extends an Elementor Pro class, so its file is only loaded once that class exists.
 * Field identifiers are the form fields' custom IDs (Advanced → ID).
 */
class Elementor extends Integration {

	/**
	 * Action name (stored in the widget's submit_actions).
	 */
	const ACTION = 'cfbe_bluefox';

	/**
	 * Slug.
	 */
	public function slug(): string {
		return 'elementor';
	}

	/**
	 * Label.
	 */
	public function label(): string {
		return 'Elementor Forms';
	}

	/**
	 * Detection.
	 */
	public function is_active(): bool {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'register_action' ) );
	}

	/**
	 * Field help.
	 */
	public function field_help(): string {
		return __( 'Configured in each Elementor Form widget: Actions After Submit → bluefox.email. Use the fields’ custom IDs (Advanced → ID), e.g. email, name, newsletter.', 'connector-for-bluefox-email' );
	}

	/**
	 * `elementor_pro/forms/actions/register` callback.
	 *
	 * @param mixed $registrar Form actions registrar.
	 */
	public function register_action( $registrar ): void {
		$this->guard(
			function () use ( $registrar ) {
				if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) || ! is_object( $registrar ) || ! method_exists( $registrar, 'register' ) ) {
					return;
				}
				require_once __DIR__ . '/elementor/class-elementor-form-action.php';
				$registrar->register( new Elementor_Form_Action( $this ) );
			}
		);
	}

	/**
	 * Processes a submitted Elementor form (called by the action's run()).
	 *
	 * @param mixed $record Form record (`get( 'form_settings' )`, `get( 'fields' )`).
	 * @return int Number of queued signups.
	 */
	public function process( $record ): int {
		$queued = 0;
		$this->guard(
			function () use ( $record, &$queued ) {
				if ( ! is_object( $record ) || ! method_exists( $record, 'get' ) || ! $this->is_enabled() ) {
					return;
				}
				$settings = (array) $record->get( 'form_settings' );
				$sub      = $this->submission( $settings, (array) $record->get( 'fields' ) );
				$queued   = $this->handle( $sub, array( self::rule_from_settings( $settings, $sub->form_id ) ) );
			}
		);
		return $queued;
	}

	/**
	 * Normalizes an Elementor form record.
	 *
	 * @param array $settings Form settings.
	 * @param array $fields   Fields: custom ID => { id, type, title, value, raw_value }.
	 */
	public function submission( array $settings, array $fields ): Submission {
		$values = array();
		$emails = array();
		$labels = array();
		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$id            = (string) ( $field['id'] ?? $key );
			$values[ $id ] = $field['raw_value'] ?? ( $field['value'] ?? '' );
			if ( 'email' === ( $field['type'] ?? '' ) ) {
				$emails[] = $id;
			}
			if ( ! empty( $field['title'] ) ) {
				$labels[ $id ] = (string) $field['title'];
			}
		}
		$form_id = (string) ( $settings['id'] ?? '' );
		return new Submission( '' !== $form_id ? $form_id : 'form', (string) ( $settings['form_name'] ?? '' ), $values, $emails, $labels, $this->ip() );
	}

	/**
	 * Rule built from the widget's action controls.
	 *
	 * @param array  $settings Form settings.
	 * @param string $form_id  Form ID.
	 */
	public static function rule_from_settings( array $settings, string $form_id ): array {
		$rule           = Rules::sanitize_rule(
			array(
				'form'            => '' !== $form_id ? $form_id : 'form',
				'list_id'         => $settings['cfbe_list'] ?? '',
				'email_field'     => $settings['cfbe_email_field'] ?? '',
				'name_field'      => $settings['cfbe_name_field'] ?? '',
				'consent_field'   => $settings['cfbe_consent_field'] ?? '',
				'implied_consent' => 'yes' === ( $settings['cfbe_implied_consent'] ?? '' ),
				'tags'            => $settings['cfbe_tags'] ?? '',
			)
		);
		$rule           = null === $rule ? Rules::blank_rule() : $rule;
		$rule['fields'] = Rules::parse_field_map( (string) ( $settings['cfbe_field_map'] ?? '' ) );
		return $rule;
	}
}
