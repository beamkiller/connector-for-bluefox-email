<?php
/**
 * Frontend form assets.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Settings;
use CFBE\Style_Vars;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the form CSS/JS; enqueued on demand (shortcode / popup) or everywhere (setting).
 */
final class Assets {

	const HANDLE = 'cfbe-form';

	/**
	 * Hooks registration.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Registers (and optionally enqueues) the assets.
	 */
	public function register_assets(): void {
		wp_register_style( self::HANDLE, CFBE_URL . 'assets/css/cfbe-form.css', array(), \CFBE\Plugin::asset_version( 'assets/css/cfbe-form.css' ) );
		wp_add_inline_style( self::HANDLE, Style_Vars::css( Settings::get( 'form' ) ) );

		wp_register_script( self::HANDLE, CFBE_URL . 'assets/js/cfbe-form.js', array(), \CFBE\Plugin::asset_version( 'assets/js/cfbe-form.js' ), true );
		wp_localize_script( self::HANDLE, 'cfbeForm', self::config() );

		if ( ! empty( Settings::get( 'form', 'assets_everywhere' ) ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueues the assets (safe to call repeatedly, also after wp_head: WP prints them in the footer).
	 */
	public static function enqueue(): void {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Static, cache-safe client config (no nonces, nothing per visitor).
	 */
	public static function config(): array {
		return array(
			'restUrl'    => esc_url_raw( rest_url( 'cfbe/v1/subscribe' ) ),
			'minElapsed' => \CFBE\Rest_Subscribe::MIN_ELAPSED,
			'i18n'       => array(
				'emailInvalid'      => __( 'Please enter a valid email address.', 'connector-for-bluefox-email' ),
				'nameRequired'      => __( 'Please enter your name.', 'connector-for-bluefox-email' ),
				'interestsRequired' => __( 'Please choose at least one topic.', 'connector-for-bluefox-email' ),
				'consentRequired'   => __( 'Please accept the consent to subscribe.', 'connector-for-bluefox-email' ),
				'fixFields'         => __( 'Please correct the highlighted fields.', 'connector-for-bluefox-email' ),
				'sending'           => __( 'Sending…', 'connector-for-bluefox-email' ),
				'error'             => (string) Settings::get( 'form', 'msg_error' ),
			),
		);
	}
}
