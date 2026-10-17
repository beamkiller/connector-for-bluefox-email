<?php
/**
 * Clears the page cache when settings that change the frontend are saved.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * The {discount} value, texts and colours are baked into cached HTML (WP Rocket). Saving the Coupon or
 * Form tab purges the whole page cache so visitors see the new values right away.
 */
final class Cache_Purge {

	const OPTIONS = array( 'cfbe_coupon', 'cfbe_form', 'cfbe_popup' );

	/**
	 * Hooks option updates.
	 */
	public function register(): void {
		foreach ( self::OPTIONS as $option ) {
			add_action( 'update_option_' . $option, array( $this, 'purge' ), 10, 2 );
		}
	}

	/**
	 * Purges supported page caches when the value really changed.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public function purge( $old_value, $new_value ): void {
		if ( $old_value === $new_value ) {
			return;
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'rocket_clean_minify' ) ) {
			rocket_clean_minify( 'css' ); // Inline --cfbe-* variables live in the (possibly combined) CSS.
		}

		/**
		 * Fires after the plugin asked the page cache to purge (hook for other cache plugins).
		 */
		do_action( 'cfbe_purge_page_cache' );
	}
}
