<?php
/**
 * Shared <dialog> wrapper (promo teaser and popup).
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Native <dialog> with a close button around already-rendered form HTML. Behaviour lives in cfbe-form.js
 * (`window.cfbeDialog`).
 */
final class Dialog_Renderer {

	/**
	 * Wraps the form.
	 *
	 * @param string $form_html  Escaped form markup (Form_Renderer output).
	 * @param string $id         Dialog id.
	 * @param string $labelledby Id of the heading that names the dialog.
	 * @param string $classes    Extra classes.
	 */
	public static function render( string $form_html, string $id, string $labelledby, string $classes = '' ): string {
		$classes = trim( 'cfbe-dialog ' . $classes );

		$html  = '<dialog class="' . esc_attr( $classes ) . '" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $labelledby ) . '">';
		$html .= '<button type="button" class="cfbe-dialog__close" aria-label="' . esc_attr__( 'Close', 'connector-for-bluefox-email' ) . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg></button>';
		$html .= $form_html;
		$html .= '</dialog>';
		return $html;
	}
}
