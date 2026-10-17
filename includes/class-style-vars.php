<?php
/**
 * CSS custom properties from the appearance settings.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the `:root{--cfbe-*}` block printed with the form stylesheet.
 */
final class Style_Vars {

	/**
	 * Setting key => default colour (neutral, theme-agnostic; all overridable on the Form tab).
	 */
	const DEFAULT_COLORS = array(
		'color_primary'       => '#1b3a57',
		'color_primary_hover' => '#132b42',
		'color_button_text'   => '#ffffff',
		'color_accent'        => '#d9603b',
		'color_text'          => '#1c2026',
		'color_muted'         => '#5b6270',
		'color_background'    => '#ffffff',
		'color_border'        => '#d5d9e0',
		'color_error'         => '#b42318',
		'color_success'       => '#1a7f52',
		'color_card'          => '#f5f6f8',
	);

	/**
	 * `#rgb` / `#rrggbb` (lowercased) or null.
	 *
	 * @param mixed $value Value.
	 */
	public static function hex( $value ): ?string {
		$value = strtolower( trim( (string) $value ) );
		return 1 === preg_match( '/^#([0-9a-f]{3}){1,2}$/', $value ) ? $value : null;
	}

	/**
	 * Radius clamped to 0–30 px.
	 *
	 * @param mixed $value Value.
	 */
	public static function radius( $value ): int {
		return min( 30, max( 0, (int) $value ) );
	}

	/**
	 * CSS declaration block for the given `form` settings (invalid values fall back to the defaults).
	 *
	 * @param array $form `form` settings.
	 */
	public static function css( array $form ): string {
		return ':root{' . self::declarations( $form ) . '}';
	}

	/**
	 * `--cfbe-x:value;…` declarations (also used inline on the admin preview wrapper).
	 *
	 * @param array $form `form` settings.
	 */
	public static function declarations( array $form ): string {
		$out = '';
		foreach ( self::vars( $form ) as $name => $value ) {
			$out .= $name . ':' . $value . ';';
		}
		return $out;
	}

	/**
	 * Variable name => value.
	 *
	 * @param array $form `form` settings.
	 * @return array<string, string>
	 */
	public static function vars( array $form ): array {
		$vars = array();
		foreach ( self::DEFAULT_COLORS as $key => $default ) {
			$color                          = self::hex( $form[ $key ] ?? '' );
			$vars[ self::var_name( $key ) ] = null !== $color ? $color : $default;
		}
		$vars['--cfbe-radius-button'] = self::radius( $form['radius_button'] ?? 10 ) . 'px';
		$vars['--cfbe-radius-input']  = self::radius( $form['radius_input'] ?? 8 ) . 'px';
		return $vars;
	}

	/**
	 * CSS variable for a setting key: color_primary_hover → --cfbe-primary-hover.
	 *
	 * @param string $key Setting key.
	 */
	public static function var_name( string $key ): string {
		return '--cfbe-' . str_replace( '_', '-', (string) preg_replace( '/^color_/', '', $key ) );
	}
}
