<?php
/**
 * Popup settings tab.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Form_Config;
use CFBE\Frontend\Form_Renderer;
use CFBE\Frontend\Popup_Targeting;
use CFBE\Settings;
use CFBE\Style_Vars;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the tab and sanitizes the `popup` area.
 */
final class Popup_Tab {

	const SIZES   = array( 'sm', 'md', 'lg' );
	const PAGES   = array( 'all', 'include', 'exclude' );
	const DEVICES = array( 'all', 'desktop', 'mobile' );

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_filter( 'cfbe_sanitize_settings_popup', array( $this, 'sanitize' ), 10, 3 );
	}

	/**
	 * Adds the Popup tab (position 30).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['popup'] = array(
			'label'    => __( 'Popup', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 30,
		);
		return $tabs;
	}

	/**
	 * Sanitizes the `popup` area.
	 *
	 * @param array $clean    Generic-sanitized values (booleans already handled).
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public function sanitize( array $clean, array $input, array $previous = array() ): array {
		$defaults = Settings::defaults( 'popup' );

		foreach ( array( 'enabled', 'show_interests', 'trigger_scroll', 'trigger_delay', 'trigger_exit', 'trigger_pageviews', 'exclude_woo', 'close_on_backdrop', 'animate' ) as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] );
		}

		$clean['form_variant'] = self::pick( $input['form_variant'] ?? '', Form_Renderer::VARIANTS, 'card' );
		$clean['combine']      = self::pick( $input['combine'] ?? '', array( 'any', 'all' ), 'any' );
		$clean['pages']        = self::pick( $input['pages'] ?? '', self::PAGES, 'all' );
		$clean['device']       = self::pick( $input['device'] ?? '', self::DEVICES, 'all' );
		$clean['size']         = self::pick( $input['size'] ?? '', self::SIZES, 'md' );

		$clean['heading']        = sanitize_text_field( (string) ( $input['heading'] ?? '' ) );
		$clean['intro']          = sanitize_textarea_field( (string) ( $input['intro'] ?? '' ) );
		$clean['scroll_percent'] = self::clamp( $input['scroll_percent'] ?? $defaults['scroll_percent'], 10, 100 );
		$clean['delay_seconds']  = self::clamp( $input['delay_seconds'] ?? $defaults['delay_seconds'], 1, 300 );
		$clean['pageviews']      = self::clamp( $input['pageviews'] ?? $defaults['pageviews'], 1, 20 );
		$clean['dismiss_days']   = self::clamp( $input['dismiss_days'] ?? $defaults['dismiss_days'], 0, 365 );
		$clean['page_ids']       = implode( ',', Popup_Targeting::page_ids( (string) ( $input['page_ids'] ?? '' ) ) );
		$clean['url_contains']   = implode( "\n", Popup_Targeting::url_fragments( (string) ( $input['url_contains'] ?? '' ) ) );

		$color                    = Style_Vars::hex( $input['overlay_color'] ?? '' );
		$clean['overlay_color']   = null !== $color ? $color : $defaults['overlay_color'];
		$opacity                  = (float) str_replace( ',', '.', (string) ( $input['overlay_opacity'] ?? $defaults['overlay_opacity'] ) );
		$clean['overlay_opacity'] = round( max( 0.0, min( 1.0, $opacity ) ), 2 );

		$list_id          = Form_Config::clean_list_id( (string) ( $input['list_id'] ?? '' ) );
		$known            = Form_Config::known_lists();
		$clean['list_id'] = ( '' === $list_id || ! $known || isset( $known[ $list_id ] ) ) ? $list_id : (string) ( $previous['list_id'] ?? '' );
		$clean['tags']    = implode( ', ', Form_Config::parse_tags( sanitize_text_field( (string) ( $input['tags'] ?? '' ) ) ) );

		$selector                     = trim( sanitize_text_field( (string) ( $input['cookie_bar_selector'] ?? '' ) ) );
		$clean['cookie_bar_selector'] = substr( (string) preg_replace( '/[<>{}]/', '', $selector ), 0, 200 );

		$version          = max( 1, (int) ( $previous['version'] ?? $defaults['version'] ) );
		$clean['version'] = ! empty( $input['bump_version'] ) ? $version + 1 : $version;

		$triggers = array( 'trigger_scroll', 'trigger_delay', 'trigger_exit', 'trigger_pageviews' );
		if ( ! array_filter( array_intersect_key( $clean, array_flip( $triggers ) ) ) ) {
			$clean['trigger_scroll'] = true;
			if ( $clean['enabled'] && function_exists( 'add_settings_error' ) ) {
				add_settings_error( Settings::option_name( 'popup' ), 'cfbe_popup_trigger', __( 'Turn on at least one display trigger. The scroll trigger has been re-enabled.', 'connector-for-bluefox-email' ) );
			}
		}
		return $clean;
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_values      = Settings::get( 'popup' );
		$cfbe_lists       = Form_Config::known_lists();
		$cfbe_preview_url = add_query_arg( 'cfbe_popup_preview', '1', home_url( '/' ) );
		include __DIR__ . '/views/tab-popup.php';
	}

	/**
	 * Whitelisted value.
	 *
	 * @param mixed    $value    Raw.
	 * @param string[] $allowed  Allowed values.
	 * @param string   $fallback Fallback.
	 */
	private static function pick( $value, array $allowed, string $fallback ): string {
		return in_array( (string) $value, $allowed, true ) ? (string) $value : $fallback;
	}

	/**
	 * Integer clamped to a range.
	 *
	 * @param mixed $value Raw.
	 * @param int   $min   Min.
	 * @param int   $max   Max.
	 */
	private static function clamp( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}
}
