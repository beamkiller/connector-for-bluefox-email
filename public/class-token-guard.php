<?php
/**
 * Keeps bluefox subscriber tokens in URLs out of caches, Referer headers and search indexes.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * After double opt-in, bluefox.email appends `?token=<JWT>` to the redirect URL (thank-you page). The token grants
 * 7 days of read access to that subscriber's contact data, so a page carrying it must not be
 * cached, leak the URL to linked or embedded sites, or be indexed. Runs regardless of modules,
 * because the confirmation may come from a checkout or form-integration signup too.
 */
final class Token_Guard {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'on_template_redirect' ), 1 );
	}

	/**
	 * Whether a query value looks like a JWT (three base64url segments).
	 *
	 * @param mixed $value Raw query value.
	 */
	public static function is_jwt( $value ): bool {
		return is_string( $value )
			&& strlen( $value ) <= 4096
			&& 1 === preg_match( '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/', $value );
	}

	/**
	 * Whether the current request carries a bluefox token.
	 */
	public static function applies(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only pattern-matched, never stored or printed.
		return isset( $_GET['token'] ) && self::is_jwt( wp_unslash( $_GET['token'] ) );
	}

	/**
	 * Sends the protective headers before output.
	 */
	public function on_template_redirect(): void {
		if ( ! self::applies() || ! Plugin_Page::is_current( Settings::thankyou_url(), Thankyou_Shortcode::TAG ) ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared convention honoured by page-cache plugins.
		}
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}
}
