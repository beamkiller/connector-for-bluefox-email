<?php
/**
 * [cfbe_thankyou] – confirmation page message.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the title + text for the state in `?cfbe=`: none → confirmed (also bluefox's DOI redirect),
 * `expired`, `error`. Styling is left to the page builder.
 */
final class Thankyou_Shortcode {

	const TAG    = 'cfbe_thankyou';
	const STATES = array( 'confirmed', 'expired', 'error' );
	const ATTRS  = array( 'tag', 'show_title' );

	/**
	 * Registers the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Current state from the query string (unknown values → confirmed).
	 */
	public static function state(): string {
		// Read-only display switch, no state change.
		$state = isset( $_GET['cfbe'] ) ? sanitize_key( wp_unslash( $_GET['cfbe'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $state, self::STATES, true ) ? $state : 'confirmed';
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes: tag (h1|h2|h3|h4), show_title (0|1).
	 */
	public function render( $atts ): string {
		$atts  = shortcode_atts(
			array(
				'tag'        => 'h1',
				'show_title' => '1',
			),
			(array) $atts,
			self::TAG
		);
		$tag   = in_array( $atts['tag'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $atts['tag'] : 'h1';
		$state = self::state();
		$title = (string) Settings::get( 'emails', 'thankyou_' . $state . '_title' );
		$text  = (string) Settings::get( 'emails', 'thankyou_' . $state . '_text' );

		$html = '<div class="cfbe-thankyou cfbe-thankyou--' . esc_attr( $state ) . '">';
		if ( '0' !== (string) $atts['show_title'] && '' !== $title ) {
			$html .= sprintf( '<%1$s class="cfbe-thankyou__title">%2$s</%1$s>', $tag, esc_html( $title ) );
		}
		$html .= '<div class="cfbe-thankyou__text">' . wpautop( wp_kses_post( $text ) ) . '</div>';
		return $html . '</div>';
	}
}
