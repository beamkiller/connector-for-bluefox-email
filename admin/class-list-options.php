<?php
/**
 * List choices (bluefox lists) for module settings.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Cached bluefox lists (General tab → “Load lists”) for list selects, and the matching sanitizer.
 */
final class List_Options {

	/**
	 * Cached lists (id => name), plus the stored ID when the cache no longer has it.
	 *
	 * @param string $current Stored list ID.
	 */
	public static function lists( string $current = '' ): array {
		$lists = get_transient( Settings::LISTS_CACHE );
		$lists = is_array( $lists ) ? $lists : array();
		if ( '' !== $current && ! isset( $lists[ $current ] ) ) {
			$lists[ $current ] = $current;
		}
		return $lists;
	}

	/**
	 * List ID from a form: empty or a cached bluefox list; an unknown ID keeps the stored value.
	 *
	 * @param string $raw      Submitted value.
	 * @param string $previous Stored value.
	 */
	public static function sanitize( string $raw, string $previous ): string {
		$list_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', $raw );
		$known   = get_transient( Settings::LISTS_CACHE );
		if ( '' !== $list_id && is_array( $known ) && $known && ! isset( $known[ $list_id ] ) ) {
			return $previous;
		}
		return $list_id;
	}
}
