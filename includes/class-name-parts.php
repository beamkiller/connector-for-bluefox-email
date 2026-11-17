<?php
/**
 * First / last name handling shared by the signup form, the checkout and the subscription service.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * The signup form asks either for one Name or for First name + Last name (`form.name_mode`). The full name is always
 * built too (`{{contact.name}}`), in the order of `form.name_order`: `auto` follows the site language (family name
 * first for Hungarian, Japanese, Chinese, Korean and Vietnamese).
 */
final class Name_Parts {

	const MODES  = array( 'single', 'split' );
	const ORDERS = array( 'auto', 'first_last', 'last_first' );

	/**
	 * Languages that write the family name first.
	 */
	const FAMILY_FIRST = array( 'hu', 'ja', 'zh', 'ko', 'vi' );

	/**
	 * Most characters of one name part.
	 */
	const MAX = 100;

	/**
	 * `single` or `split`.
	 *
	 * @param array $form `form` settings.
	 */
	public static function mode( array $form ): string {
		return 'split' === ( $form['name_mode'] ?? '' ) ? 'split' : 'single';
	}

	/**
	 * Whether the family name comes first (form field order and the full name).
	 *
	 * @param array       $form   `form` settings.
	 * @param string|null $locale Locale (null = the site's).
	 */
	public static function last_first( array $form, ?string $locale = null ): bool {
		$order = in_array( $form['name_order'] ?? '', self::ORDERS, true ) ? $form['name_order'] : 'auto';
		if ( 'auto' !== $order ) {
			return 'last_first' === $order;
		}
		$locale = null === $locale ? ( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() ) : $locale;
		return in_array( strtolower( substr( (string) $locale, 0, 2 ) ), self::FAMILY_FIRST, true );
	}

	/**
	 * Full name from the parts.
	 *
	 * @param string $first      First name.
	 * @param string $last       Last name.
	 * @param bool   $last_first Family name first.
	 */
	public static function compose( string $first, string $last, bool $last_first ): string {
		$parts = $last_first ? array( $last, $first ) : array( $first, $last );
		return trim( implode( ' ', array_filter( array_map( 'trim', $parts ), 'strlen' ) ) );
	}

	/**
	 * The bluefox fields for the parts, named like the First name / Last name rows of the Contact sync table
	 * (`firstName` / `lastName` unless renamed there). Empty parts are left out.
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return array<string, string>
	 */
	public static function bluefox_fields( string $first, string $last ): array {
		$names = self::field_names();
		$out   = array();
		if ( '' !== trim( $first ) ) {
			$out[ $names['first'] ] = trim( $first );
		}
		if ( '' !== trim( $last ) ) {
			$out[ $names['last'] ] = trim( $last );
		}
		return $out;
	}

	/**
	 * The bluefox field names of the first and last name.
	 *
	 * @return array{first: string, last: string}
	 */
	public static function field_names(): array {
		$fields = Sync\Fields::settings()['fields'];
		return array(
			'first' => (string) ( $fields['firstName']['name'] ?? 'firstName' ),
			'last'  => (string) ( $fields['lastName']['name'] ?? 'lastName' ),
		);
	}
}
