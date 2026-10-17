<?php
/**
 * Signup form markup shared by the shortcode and the popup.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Form_Config;
use CFBE\Settings;
use CFBE\Signup_Validator;

defined( 'ABSPATH' ) || exit;

/**
 * Pure renderer: `form` settings + args → HTML. No per-visitor state (cache-safe).
 */
final class Form_Renderer {

	const SOURCES  = array( 'shortcode', 'block', 'promo', 'popup' );
	const VARIANTS = array( 'card', 'banner', 'plain' );

	/**
	 * Per-request counter for unique field IDs (deterministic, so tests and cached pages stay stable).
	 *
	 * @var int
	 */
	private static $counter = 0;

	/**
	 * Renders a form.
	 *
	 * @param array $args source, variant, layout, heading, heading_tag (h2|h3|h4), intro, benefits, badge, button, offer,
	 *                    show_name, show_interests, class, place, list (bluefox list ID), tags (comma-separated or array).
	 */
	public function render( array $args = array() ): string {
		$form = Settings::get( 'form' );
		$args = $this->resolve_args( $args, $form );
		$n    = ++self::$counter;
		$id   = static function ( string $field ) use ( $n ): string {
			return 'cfbe-' . $field . '-' . $n;
		};

		$classes = array( 'cfbe-form', 'cfbe-form--' . $args['variant'], 'cfbe-form--' . $args['layout'], 'cfbe-form--src-' . $args['source'] );
		if ( '' !== $args['class'] ) {
			$classes[] = $args['class'];
		}

		if ( $args['badge'] ) {
			$classes[] = 'cfbe-form--has-badge';
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<div class="cfbe-form__header">';
		if ( $args['badge'] ) {
			$html .= self::badge( $args['badge_text'], $args['badge_label'] );
		}
		$html .= '<div class="cfbe-form__headings">';
		if ( '' !== $args['heading'] ) {
			$heading_id = '' !== $args['heading_id'] ? ' id="' . esc_attr( $args['heading_id'] ) . '"' : '';
			$html      .= sprintf( '<%1$s class="cfbe-form__heading"%3$s>%2$s</%1$s>', $args['heading_tag'], esc_html( $args['heading'] ), $heading_id );
		}
		if ( '' !== $args['intro'] ) {
			$html .= '<p class="cfbe-form__intro">' . nl2br( esc_html( $args['intro'] ) ) . '</p>';
		}
		$html .= '</div></div>';

		if ( $args['benefits'] ) {
			$html .= '<ul class="cfbe-form__benefits">';
			foreach ( $args['benefits'] as $benefit ) {
				$html .= '<li>' . esc_html( $benefit ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '<form class="cfbe-form__form" novalidate data-cfbe-source="' . esc_attr( $args['source'] ) . '"' . ( '' !== $args['place'] ? ' data-cfbe-place="' . esc_attr( $args['place'] ) . '"' : '' ) . '>';
		$html .= '<div class="cfbe-form__fields">';

		if ( $args['show_name'] ) {
			$html .= $this->text_field( $id( 'name' ), 'name', 'text', __( 'Name', 'connector-for-bluefox-email' ), ! empty( $form['name_required'] ), 'name' );
		}
		$html .= $this->text_field( $id( 'email' ), 'email', 'email', __( 'Email address', 'connector-for-bluefox-email' ), true, 'email' );

		if ( $args['show_interests'] && ! empty( $form['interests'] ) ) {
			$html .= $this->interests_field( $id( 'interests' ), $form );
		}

		$html .= '</div>';

		// Consent sits before the button so the tab / reading order is: fields → consent → submit.
		$html .= $this->consent_field( $id( 'consent' ), $form );

		// Honeypot: hidden from people and assistive tech, bots fill it.
		$html .= '<div class="cfbe-hp" aria-hidden="true"><label>' . esc_html__( 'Website', 'connector-for-bluefox-email' ) . '<input type="text" name="cfbe_hp" value="" tabindex="-1" autocomplete="off"></label></div>';

		// Signed list + tags (verified by the REST endpoint; unsigned values are ignored there).
		$config = Form_Config::sign( $args['list'], $args['tags'] );
		if ( '' !== $config ) {
			$html .= '<input type="hidden" name="' . esc_attr( Form_Config::FIELD ) . '" value="' . esc_attr( $config ) . '">';
		}

		$html .= '<button type="submit" class="cfbe-form__submit"><span class="cfbe-form__submit-text">' . esc_html( $args['button'] ) . '</span></button>';

		if ( '' !== $args['offer'] ) {
			$html .= '<p class="cfbe-form__offer">' . esc_html( $args['offer'] ) . '</p>';
		}
		$html .= '<div class="cfbe-form__status" role="status" aria-live="polite" tabindex="-1"></div>';
		$html .= '</form></div>';

		return $html;
	}

	/**
	 * Merges args with settings; show_* can only switch fields OFF (the server validates against settings).
	 *
	 * @param array $args Raw args.
	 * @param array $form `form` settings.
	 */
	private function resolve_args( array $args, array $form ): array {
		$off     = static function ( $value ): bool {
			return in_array( strtolower( (string) $value ), array( '0', 'false', 'no', 'off' ), true );
		};
		$source  = (string) ( $args['source'] ?? 'shortcode' );
		$variant = (string) ( $args['variant'] ?? $form['variant'] );
		$variant = in_array( $variant, self::VARIANTS, true ) ? $variant : 'card';
		$fill    = function ( $text ): string {
			return $this->placeholders( (string) $text );
		};
		// One benefit per line (settings) or separated by | (shortcode attributes can't hold line breaks).
		$benefits = array_values( array_filter( array_map( 'trim', (array) preg_split( '/\r\n|\r|\n|\|/', (string) ( $args['benefits'] ?? $form['benefits'] ) ) ) ) );

		return array(
			'source'         => in_array( $source, self::SOURCES, true ) ? $source : 'shortcode',
			'variant'        => $variant,
			'layout'         => 'inline' === ( $args['layout'] ?? $form['layout'] ) ? 'inline' : 'stacked',
			'badge'          => 'plain' !== $variant && ! empty( $form['badge_enabled'] ) && ! $off( $args['badge'] ?? '1' ) && '' !== trim( $fill( $form['badge_text'] ) ),
			'badge_text'     => $fill( $form['badge_text'] ),
			'badge_label'    => (string) $form['badge_label'],
			'benefits'       => array_slice( array_map( $fill, $benefits ), 0, 5 ),
			'heading'        => $fill( $args['heading'] ?? $form['heading'] ),
			'heading_id'     => sanitize_html_class( (string) ( $args['heading_id'] ?? '' ) ),
			// h2 by default: the page title is the h1 (Lighthouse heading-order).
			'heading_tag'    => in_array( $args['heading_tag'] ?? '', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading_tag'] : 'h2',
			'intro'          => $fill( $args['intro'] ?? $form['intro'] ),
			'button'         => $fill( '' !== (string) ( $args['button'] ?? '' ) ? (string) $args['button'] : (string) $form['button'] ),
			'offer'          => $fill( $args['offer'] ?? $form['offer'] ),
			'show_name'      => ! empty( $form['name_enabled'] ) && ! $off( $args['show_name'] ?? '1' ),
			'show_interests' => ! empty( $form['interests_enabled'] ) && ! $off( $args['show_interests'] ?? '1' ),
			'place'          => Signup_Validator::place( (string) ( $args['place'] ?? '' ) ),
			'class'          => implode( ' ', array_filter( array_map( 'sanitize_html_class', explode( ' ', (string) ( $args['class'] ?? '' ) ) ) ) ),
			'list'           => Form_Config::clean_list_id( (string) ( $args['list'] ?? '' ) ),
			'tags'           => is_array( $args['tags'] ?? null ) ? Signup_Validator::clean_tags( $args['tags'] ) : Form_Config::parse_tags( (string) ( $args['tags'] ?? '' ) ),
		);
	}

	/**
	 * Replaces {discount} with the configured coupon value (e.g. "10%" or "$20").
	 *
	 * @param string $text Text.
	 */
	private function placeholders( string $text ): string {
		if ( false === strpos( $text, '{discount}' ) ) {
			return $text;
		}
		$coupon = Settings::get( 'coupon' );
		return str_replace( '{discount}', \CFBE\Coupon_Data::discount_label( (string) $coupon['discount_type'], (float) $coupon['amount'] ), $text );
	}

	/**
	 * Optional offer badge: a small pill with a value and a label (e.g. "10%" + "coupon").
	 *
	 * @param string $value Value, e.g. "10%".
	 * @param string $label Optional label next to it.
	 */
	public static function badge( string $value, string $label ): string {
		return '<p class="cfbe-badge">'
			. '<span class="cfbe-badge__value">' . esc_html( $value ) . '</span>'
			. ( '' !== $label ? ' <span class="cfbe-badge__label">' . esc_html( $label ) . '</span>' : '' )
			. '</p>';
	}

	/**
	 * Label + input + error slot.
	 *
	 * @param string $id           Field ID.
	 * @param string $name         Input name.
	 * @param string $type         Input type.
	 * @param string $label        Label.
	 * @param bool   $required     Required.
	 * @param string $autocomplete Autocomplete token.
	 */
	private function text_field( string $id, string $name, string $type, string $label, bool $required, string $autocomplete ): string {
		$extra = 'email' === $type ? ' inputmode="email"' : '';
		return '<div class="cfbe-field cfbe-field--' . esc_attr( $name ) . '">'
			. '<label class="cfbe-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $this->required_mark( $required ) . '</label>'
			. '<input class="cfbe-field__input" id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" autocomplete="' . esc_attr( $autocomplete ) . '"' . $extra . ( $required ? ' required aria-required="true"' : '' ) . ' aria-describedby="' . esc_attr( $id ) . '-err">'
			. '<p class="cfbe-field__error" id="' . esc_attr( $id ) . '-err" hidden></p>'
			. '</div>';
	}

	/**
	 * Interests: select (one) or checkbox group (several).
	 *
	 * @param string $id   Field ID.
	 * @param array  $form `form` settings.
	 */
	private function interests_field( string $id, array $form ): string {
		$required = ! empty( $form['interests_required'] );
		$label    = (string) $form['interests_label'];

		if ( 'checkbox' === $form['interests_mode'] ) {
			$html = '<fieldset class="cfbe-field cfbe-field--interests cfbe-field--checkboxes" aria-describedby="' . esc_attr( $id ) . '-err"><legend class="cfbe-field__label">' . esc_html( $label ) . $this->required_mark( $required ) . '</legend><div class="cfbe-field__options">';
			foreach ( $form['interests'] as $i => $interest ) {
				$opt   = $id . '-' . $i;
				$html .= '<label class="cfbe-check" for="' . esc_attr( $opt ) . '"><input type="checkbox" id="' . esc_attr( $opt ) . '" name="interests[]" value="' . esc_attr( $interest['tag'] ) . '"> <span>' . esc_html( $interest['label'] ) . '</span></label>';
			}
			return $html . '</div><p class="cfbe-field__error" id="' . esc_attr( $id ) . '-err" hidden></p></fieldset>';
		}

		$html = '<div class="cfbe-field cfbe-field--interests"><label class="cfbe-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . $this->required_mark( $required ) . '</label>'
			. '<select class="cfbe-field__input" id="' . esc_attr( $id ) . '" name="interests"' . ( $required ? ' required aria-required="true"' : '' ) . ' aria-describedby="' . esc_attr( $id ) . '-err">'
			. '<option value="">' . esc_html__( '– Select –', 'connector-for-bluefox-email' ) . '</option>';
		foreach ( $form['interests'] as $interest ) {
			$html .= '<option value="' . esc_attr( $interest['tag'] ) . '">' . esc_html( $interest['label'] ) . '</option>';
		}
		return $html . '</select><p class="cfbe-field__error" id="' . esc_attr( $id ) . '-err" hidden></p></div>';
	}

	/**
	 * Consent checkbox with the privacy link.
	 *
	 * @param string $id   Field ID.
	 * @param array  $form `form` settings.
	 */
	private function consent_field( string $id, array $form ): string {
		$link = '';
		if ( '' !== (string) $form['privacy_url'] ) {
			$link = ' <a href="' . esc_url( $form['privacy_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy policy', 'connector-for-bluefox-email' ) . '</a>';
		}
		return '<div class="cfbe-field cfbe-field--consent">'
			. '<input class="cfbe-field__checkbox" type="checkbox" id="' . esc_attr( $id ) . '" name="consent" value="1" aria-required="true" aria-describedby="' . esc_attr( $id ) . '-err">'
			. '<label class="cfbe-field__consent-label" for="' . esc_attr( $id ) . '">' . esc_html( $form['consent_label'] ) . $link . '</label>'
			. '<p class="cfbe-field__error" id="' . esc_attr( $id ) . '-err" hidden></p>'
			. '</div>';
	}

	/**
	 * Visual required marker (the input carries `required` for assistive tech).
	 *
	 * @param bool $required Required.
	 */
	private function required_mark( bool $required ): string {
		return $required ? ' <span class="cfbe-req" aria-hidden="true">*</span>' : '';
	}
}
