<?php
/**
 * [cfbe_promo] – compact teaser that opens the signup form in a dialog.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A slim promo strip (optional offer badge, one line of copy, one button). The full form lives in a
 * native <dialog>; browsers without showModal() get it expanded inline instead (handled in cfbe-form.js).
 */
final class Promo_Shortcode {

	const TAG = 'cfbe_promo';

	// Accepted attributes (documented on the Shortcodes admin tab).
	const ATTRS = array( 'style', 'corners', 'action', 'target', 'heading', 'text', 'button', 'form_variant', 'place', 'list', 'tags' );

	/**
	 * Per-request counter for unique IDs.
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Renderer.
	 *
	 * @var Form_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Form_Renderer $renderer Renderer.
	 */
	public function __construct( Form_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Registers the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback. Attributes: heading, text, button, style (light|dark|bar), form_variant (card|banner|plain), place,
	 * list (bluefox list ID) and tags ("a,b") for the form in the dialog,
	 * action (dialog|scroll), target (CSS selector of the form to scroll to; default: the last inline form on the page).
	 *
	 * @param array|string $atts Attributes.
	 */
	public function render( $atts ): string {
		$atts  = is_array( $atts ) ? $atts : array();
		$form  = Settings::get( 'form' );
		$n     = ++self::$counter;
		$style = in_array( $atts['style'] ?? '', array( 'dark', 'bar' ), true ) ? $atts['style'] : 'light';

		$heading = $this->fill( (string) ( $atts['heading'] ?? $form['promo_heading'] ) );
		$text    = $this->fill( (string) ( $atts['text'] ?? $form['promo_text'] ) );
		$button  = $this->fill( '' !== (string) ( $atts['button'] ?? '' ) ? (string) $atts['button'] : (string) $form['promo_button'] );
		$dialog  = 'cfbe-dialog-' . $n;
		$title   = 'cfbe-dialog-title-' . $n;
		$action  = (string) ( $atts['action'] ?? $form['promo_action'] );
		$action  = 'scroll' === $action ? 'scroll' : 'dialog';
		$target  = trim( (string) preg_replace( '/[<>{}"]/', '', (string) ( $atts['target'] ?? '' ) ) );

		$round = 'bar' === $style && 'round' === ( $atts['corners'] ?? '' ) ? ' cfbe-promo--round' : '';

		$html = '<div class="cfbe-promo cfbe-promo--' . esc_attr( $style ) . $round . '" data-cfbe-promo';
		if ( 'scroll' === $action ) {
			$html .= ' data-cfbe-action="scroll"' . ( '' !== $target ? ' data-cfbe-target="' . esc_attr( substr( $target, 0, 100 ) ) . '"' : '' );
		}
		$html .= '>';
		$html .= '<div class="cfbe-promo__body">';
		$badge = $this->fill( (string) $form['badge_text'] );
		if ( ! empty( $form['badge_enabled'] ) && '' !== trim( $badge ) ) {
			$html .= Form_Renderer::badge( $badge, (string) $form['badge_label'] );
		}
		$html .= '<div class="cfbe-promo__copy">';
		$html .= '<p class="cfbe-promo__heading">' . esc_html( $heading ) . '</p>';
		if ( '' !== $text ) {
			$html .= '<p class="cfbe-promo__text">' . esc_html( $text ) . '</p>';
		}
		$html .= '</div>';
		// Scroll mode: a plain button (JS adds the dialog semantics only when it falls back to the dialog).
		$aria  = 'scroll' === $action ? '' : ' aria-haspopup="dialog" aria-controls="' . esc_attr( $dialog ) . '"';
		$html .= '<button type="button" class="cfbe-promo__open"' . $aria . '>' . esc_html( $button ) . '</button>';
		$html .= '</div>';

		$form_html = $this->renderer->render(
			array(
				'source'      => 'promo',
				'variant'     => in_array( $atts['form_variant'] ?? '', Form_Renderer::VARIANTS, true ) ? $atts['form_variant'] : 'card',
				'heading_tag' => 'h2',
				'heading_id'  => $title,
				'place'       => (string) ( $atts['place'] ?? '' ),
				'list'        => (string) ( $atts['list'] ?? '' ),
				'tags'        => (string) ( $atts['tags'] ?? '' ),
			)
		);
		$html     .= Dialog_Renderer::render( $form_html, $dialog, $title ) . '</div>';

		Assets::enqueue();
		return $html;
	}

	/**
	 * {discount} placeholder.
	 *
	 * @param string $text Text.
	 */
	private function fill( string $text ): string {
		if ( false === strpos( $text, '{discount}' ) ) {
			return $text;
		}
		$coupon = Settings::get( 'coupon' );
		return str_replace( '{discount}', \CFBE\Coupon_Data::discount_label( (string) $coupon['discount_type'], (float) $coupon['amount'] ), $text );
	}
}
