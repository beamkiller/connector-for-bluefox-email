<?php
/**
 * Preview of a template email with sample data, rendered in WordPress.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * The bluefox test sends ignore template data, so the Email templates tab previews the email here instead. Renders the
 * small Handlebars subset the templates use: {{x}}, {{{x}}}, {{a.b}}, {{DEFAULT a.b "fallback"}},
 * {{#each items}}…{{/each}} and {{#if x}}…{{/if}}. Values are HTML-escaped except in triple braces (URLs).
 */
final class Email_Preview {

	/**
	 * The template rendered with sample data, as a complete HTML document.
	 *
	 * @param string $key   Template key.
	 * @param array  $texts Texts (unsaved texts from the card work too).
	 * @param string $type  Email type ('' = the template's default).
	 */
	public static function html( string $key, array $texts, string $type = '' ): string {
		$email                   = Email_Templates::render( $key, $texts, $type );
		$data                    = Email_Templates::sample_data( $key );
		$data['unsubscribeLink'] = '#';
		return self::render( (string) $email['document'], $data );
	}

	/**
	 * Renders the Handlebars subset.
	 *
	 * @param string $template Template.
	 * @param array  $data     Data.
	 */
	public static function render( string $template, array $data ): string {
		$template = (string) preg_replace_callback(
			'/\{\{#each ([\w.]+)\}\}(.*?)\{\{\/each\}\}/s',
			static function ( array $m ) use ( $data ): string {
				$out = '';
				foreach ( (array) self::value( $data, $m[1] ) as $item ) {
					$out .= self::render( $m[2], array_merge( $data, is_array( $item ) ? $item : array( 'this' => $item ) ) );
				}
				return $out;
			},
			$template
		);
		$template = (string) preg_replace_callback(
			'/\{\{#if ([\w.]+)\}\}(.*?)\{\{\/if\}\}/s',
			static function ( array $m ) use ( $data ): string {
				$value = self::value( $data, $m[1] );
				return ! empty( $value ) ? self::render( $m[2], $data ) : '';
			},
			$template
		);
		$template = (string) preg_replace_callback(
			'/\{\{DEFAULT ([\w.]+) "([^"]*)"\}\}/',
			static function ( array $m ) use ( $data ): string {
				$value = self::scalar( $data, $m[1] );
				return esc_html( '' !== $value ? $value : $m[2] );
			},
			$template
		);
		$template = (string) preg_replace_callback(
			'/\{\{\{([\w.]+)\}\}\}/',
			static function ( array $m ) use ( $data ): string {
				return esc_url( self::scalar( $data, $m[1] ) );
			},
			$template
		);
		return (string) preg_replace_callback(
			'/\{\{([\w.]+)\}\}/',
			static function ( array $m ) use ( $data ): string {
				return esc_html( self::scalar( $data, $m[1] ) );
			},
			$template
		);
	}

	/**
	 * Value at a dotted path.
	 *
	 * @param array  $data Data.
	 * @param string $path Path, e.g. billing.firstName.
	 * @return mixed
	 */
	private static function value( array $data, string $path ) {
		$value = $data;
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return null;
			}
			$value = $value[ $part ];
		}
		return $value;
	}

	/**
	 * Scalar value at a dotted path as a string ('' otherwise).
	 *
	 * @param array  $data Data.
	 * @param string $path Path.
	 */
	private static function scalar( array $data, string $path ): string {
		$value = self::value( $data, $path );
		return is_scalar( $value ) ? (string) $value : '';
	}
}
