<?php
/**
 * Elementor Pro form action "bluefox.email".
 *
 * Loaded only from Elementor::register_action() after checking that Elementor Pro's Action_Base exists.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribes the submitter to bluefox (in the background) when the form's "Actions After Submit" include it.
 */
class Elementor_Form_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

	/**
	 * Integration.
	 *
	 * @var Elementor
	 */
	private $integration;

	/**
	 * Constructor.
	 *
	 * @param Elementor $integration Integration.
	 */
	public function __construct( Elementor $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Action name.
	 */
	public function get_name(): string {
		return Elementor::ACTION;
	}

	/**
	 * Action label.
	 */
	public function get_label(): string {
		return esc_html__( 'bluefox.email', 'connector-for-bluefox-email' );
	}

	/**
	 * Action controls.
	 *
	 * @param \Elementor\Widget_Base $widget Form widget.
	 */
	public function register_settings_section( $widget ): void {
		$widget->start_controls_section(
			'section_cfbe_bluefox',
			array(
				'label'     => esc_html__( 'bluefox.email', 'connector-for-bluefox-email' ),
				'condition' => array( 'submit_actions' => $this->get_name() ),
			)
		);

		$lists   = get_transient( Settings::LISTS_CACHE );
		$options = array( '' => esc_html__( 'Default list', 'connector-for-bluefox-email' ) );
		foreach ( is_array( $lists ) ? $lists : array() as $id => $name ) {
			$options[ (string) $id ] = (string) $name;
		}
		$widget->add_control(
			'cfbe_list',
			array(
				'label'   => esc_html__( 'List', 'connector-for-bluefox-email' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $options,
				'default' => '',
			)
		);
		$widget->add_control(
			'cfbe_email_field',
			array(
				'label'       => esc_html__( 'Email field ID', 'connector-for-bluefox-email' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Empty = the first email field.', 'connector-for-bluefox-email' ),
			)
		);
		$widget->add_control(
			'cfbe_name_field',
			array(
				'label' => esc_html__( 'Name field ID (optional)', 'connector-for-bluefox-email' ),
				'type'  => \Elementor\Controls_Manager::TEXT,
			)
		);
		$widget->add_control(
			'cfbe_consent_field',
			array(
				'label'       => esc_html__( 'Consent field ID', 'connector-for-bluefox-email' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'An Acceptance or Checkbox field. The visitor is only subscribed when it is ticked.', 'connector-for-bluefox-email' ),
			)
		);
		$widget->add_control(
			'cfbe_implied_consent',
			array(
				'label'       => esc_html__( 'This form itself is the newsletter signup', 'connector-for-bluefox-email' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Consent is implied by submitting. Only switch this on if the form clearly says that submitting it subscribes to the newsletter. Without a consent field and without this switch, nobody is subscribed.', 'connector-for-bluefox-email' ),
			)
		);
		$widget->add_control(
			'cfbe_tags',
			array(
				'label'       => esc_html__( 'Extra tags', 'connector-for-bluefox-email' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => esc_html__( 'Comma separated.', 'connector-for-bluefox-email' ),
			)
		);
		$widget->add_control(
			'cfbe_field_map',
			array(
				'label'       => esc_html__( 'Custom fields', 'connector-for-bluefox-email' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'placeholder' => 'phone=phone',
				'description' => esc_html__( 'One per line: form field ID=bluefox contact field name.', 'connector-for-bluefox-email' ),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Runs after a successful submission: queues the signup, never blocks or fails the form.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler Ajax handler.
	 */
	public function run( $record, $ajax_handler ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->integration->process( $record );
	}

	/**
	 * Strips site-specific settings (the list ID) from exported templates.
	 *
	 * @param array $element Element data.
	 */
	public function on_export( $element ): array {
		unset( $element['settings']['cfbe_list'] );
		return $element;
	}
}
