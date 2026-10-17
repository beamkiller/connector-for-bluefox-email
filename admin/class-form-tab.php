<?php
/**
 * Form and Emails settings tabs (subscribe module).
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Settings;
use CFBE\Signup_Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tabs and sanitizes the `form` and `emails` areas.
 */
final class Form_Tab {

	const MAX_INTERESTS = 12;

	/**
	 * Hooks the tabs and sanitizers.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tabs' ) );
		add_filter( 'cfbe_sanitize_settings_form', array( $this, 'sanitize_form' ), 10, 2 );
		add_filter( 'cfbe_sanitize_settings_emails', array( $this, 'sanitize_emails' ), 10, 2 );
	}

	/**
	 * Adds the Form (20) and Emails (60) tabs.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tabs( array $tabs ): array {
		$tabs['form']   = array(
			'label'    => __( 'Form', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render_form' ),
			'position' => 20,
		);
		$tabs['emails'] = array(
			'label'    => __( 'Emails', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render_emails' ),
			'position' => 60,
		);
		return $tabs;
	}

	/**
	 * Sanitizes the subscribe-owned keys of the `form` area.
	 *
	 * @param array $clean Values from the generic sanitizer.
	 * @param array $input Raw input.
	 */
	public function sanitize_form( array $clean, array $input ): array {
		foreach ( array( 'consent_label', 'msg_pending', 'msg_already', 'msg_error' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$value         = sanitize_textarea_field( (string) $input[ $key ] );
				$clean[ $key ] = '' !== trim( $value ) ? $value : Settings::defaults( 'form' )[ $key ];
			}
		}
		// Appearance section.
		$defaults = Settings::defaults( 'form' );
		foreach ( array_keys( \CFBE\Style_Vars::DEFAULT_COLORS ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$color         = \CFBE\Style_Vars::hex( $input[ $key ] );
				$clean[ $key ] = null !== $color ? $color : $defaults[ $key ];
			}
		}
		foreach ( array( 'radius_button', 'radius_input' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = \CFBE\Style_Vars::radius( $input[ $key ] );
			}
		}
		if ( isset( $input['intro'] ) ) {
			$clean['intro'] = sanitize_textarea_field( (string) $input['intro'] );
		}
		$clean['layout']       = 'inline' === ( $input['layout'] ?? '' ) ? 'inline' : 'stacked';
		$clean['promo_action'] = 'scroll' === ( $input['promo_action'] ?? '' ) ? 'scroll' : 'dialog';
		$clean['variant']      = in_array( $input['variant'] ?? '', \CFBE\Frontend\Form_Renderer::VARIANTS, true ) ? $input['variant'] : 'card';
		if ( isset( $input['benefits'] ) ) {
			$clean['benefits'] = sanitize_textarea_field( (string) $input['benefits'] );
		}

		$clean['privacy_url']    = esc_url_raw( (string) ( $input['privacy_url'] ?? '' ) );
		$clean['interests_mode'] = 'checkbox' === ( $input['interests_mode'] ?? '' ) ? 'checkbox' : 'select';

		if ( isset( $input['interests_label'] ) ) {
			$clean['interests_label'] = sanitize_text_field( (string) $input['interests_label'] );
			// The Form tab was submitted: no rows means the admin removed them all.
			$clean['interests'] = self::sanitize_interests( (array) ( $input['interests'] ?? array() ) );
		}
		return $clean;
	}

	/**
	 * Cleans interest rows: label required, tag slugified (from the label when empty) and unique.
	 *
	 * @param array $rows Raw rows (label, tag).
	 * @return array<int, array{label: string, tag: string}>
	 */
	public static function sanitize_interests( array $rows ): array {
		$out  = array();
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
			if ( '' === $label ) {
				continue;
			}
			$tag = Signup_Validator::slugify( '' !== trim( (string) ( $row['tag'] ?? '' ) ) ? (string) $row['tag'] : $label );
			if ( '' === $tag || isset( $seen[ $tag ] ) ) {
				continue;
			}
			$seen[ $tag ] = true;
			$out[]        = array(
				'label' => $label,
				'tag'   => $tag,
			);
			if ( count( $out ) >= self::MAX_INTERESTS ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sanitizes the `emails` area.
	 *
	 * @param array $clean Values from the generic sanitizer.
	 * @param array $input Raw input.
	 */
	public function sanitize_emails( array $clean, array $input ): array {
		$clean['thankyou_url']    = esc_url_raw( (string) ( $input['thankyou_url'] ?? '' ) );
		$clean['preferences_url'] = esc_url_raw( (string) ( $input['preferences_url'] ?? '' ) );

		$defaults = Settings::defaults( 'emails' );
		if ( isset( $input['prefs_title'] ) ) {
			$title                = sanitize_text_field( (string) $input['prefs_title'] );
			$clean['prefs_title'] = '' !== $title ? $title : $defaults['prefs_title'];
		}
		if ( isset( $input['prefs_intro'] ) ) {
			$clean['prefs_intro'] = wp_kses_post( (string) $input['prefs_intro'] );
		}
		foreach ( array( 'confirmed', 'expired', 'error' ) as $state ) {
			$title_key = 'thankyou_' . $state . '_title';
			$text_key  = 'thankyou_' . $state . '_text';
			if ( isset( $input[ $title_key ] ) ) {
				$title               = sanitize_text_field( (string) $input[ $title_key ] );
				$clean[ $title_key ] = '' !== $title ? $title : $defaults[ $title_key ];
			}
			if ( isset( $input[ $text_key ] ) ) {
				$text               = wp_kses_post( (string) $input[ $text_key ] );
				$clean[ $text_key ] = '' !== trim( $text ) ? $text : $defaults[ $text_key ];
			}
		}
		return $clean;
	}

	/**
	 * Renders the Form tab.
	 */
	public function render_form(): void {
		$cfbe_values  = Settings::get( 'form' );
		$cfbe_preview = ( new \CFBE\Frontend\Form_Renderer() )->render( array( 'source' => 'shortcode' ) );
		include __DIR__ . '/views/tab-form.php';
	}

	/**
	 * Renders the Emails tab.
	 */
	public function render_emails(): void {
		$cfbe_values       = Settings::get( 'emails' );
		$cfbe_thankyou_url = Settings::thankyou_url();
		$cfbe_prefs_url    = Settings::preferences_url();
		include __DIR__ . '/views/tab-emails.php';
	}
}
