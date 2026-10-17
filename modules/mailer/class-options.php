<?php
/**
 * Settings area `mailer` (option `cfbe_mailer`).
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults, sanitizer and the cached list of transactional emails.
 */
final class Options {

	const AREA = 'mailer';

	const EMAILS_CACHE = 'cfbe_mailer_emails';

	const DEFAULTS = array(
		// Route wp_mail() through bluefox (the module switch only loads the module; this goes live).
		'enabled'           => false,
		'transactional_id'  => '',
		// fallback = let WordPress send it the normal way; fail = wp_mail() returns false.
		'failure_mode'      => 'fallback',
		'max_attachment_mb' => 7,
	);

	const FAILURE_MODES = array( 'fallback', 'fail' );

	const MAX_ATTACHMENT_MB = 40;

	/**
	 * Hooks the settings area.
	 */
	public static function register(): void {
		add_filter( 'cfbe_settings_areas', array( self::class, 'add_area' ) );
		add_filter( 'cfbe_settings_defaults_' . self::AREA, array( self::class, 'defaults' ) );
		add_filter( 'cfbe_sanitize_settings_' . self::AREA, array( self::class, 'sanitize' ), 10, 3 );
	}

	/**
	 * Adds the `mailer` area.
	 *
	 * @param array $areas Areas.
	 */
	public static function add_area( $areas ): array {
		$areas   = (array) $areas;
		$areas[] = self::AREA;
		return $areas;
	}

	/**
	 * Area defaults.
	 *
	 * @param array $defaults Defaults from other sources.
	 */
	public static function defaults( $defaults ): array {
		return array_merge( (array) $defaults, self::DEFAULTS );
	}

	/**
	 * Current values (stored over the defaults).
	 */
	public static function all(): array {
		$stored = get_option( Settings::option_name( self::AREA ), array() );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Sanitizes the area.
	 *
	 * @param array $clean    Generic-sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public static function sanitize( $clean, $input, $previous ): array {
		$clean    = (array) $clean;
		$input    = (array) $input;
		$previous = (array) $previous;

		$clean['enabled'] = ! empty( $input['enabled'] );

		$id    = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['transactional_id'] ?? '' ) );
		$known = get_transient( self::EMAILS_CACHE );
		if ( '' !== $id && is_array( $known ) && $known && ! isset( $known[ $id ] ) && ( $previous['transactional_id'] ?? '' ) !== $id ) {
			$id = (string) ( $previous['transactional_id'] ?? '' );
		}
		$clean['transactional_id'] = $id;

		$mode                  = (string) ( $input['failure_mode'] ?? '' );
		$clean['failure_mode'] = in_array( $mode, self::FAILURE_MODES, true ) ? $mode : self::DEFAULTS['failure_mode'];

		$mb                         = isset( $input['max_attachment_mb'] ) ? absint( $input['max_attachment_mb'] ) : self::DEFAULTS['max_attachment_mb'];
		$clean['max_attachment_mb'] = max( 1, min( self::MAX_ATTACHMENT_MB, $mb ) );

		return $clean;
	}

	/**
	 * Cached transactional emails: id => name (refreshed from the tab).
	 *
	 * @return array<string, string>
	 */
	public static function cached_emails(): array {
		$cache = get_transient( self::EMAILS_CACHE );
		return is_array( $cache ) ? $cache : array();
	}
}
