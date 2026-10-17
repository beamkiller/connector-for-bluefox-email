<?php
/**
 * Popup page targeting (server side, cache-safe: page caches store one copy per URL).
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Pure rules: settings + request context → render or not. Device, triggers and frequency are decided in
 * the browser.
 */
final class Popup_Targeting {

	const MAX_LINES = 20;

	/**
	 * Whether the popup markup belongs on this request.
	 *
	 * @param array $settings `popup` settings.
	 * @param array $context  blocked (admin/feed/REST/builder…), preview, is_cart, is_checkout, is_account,
	 *                        post_id, path, thankyou_path.
	 */
	public static function should_render( array $settings, array $context ): bool {
		if ( ! empty( $context['blocked'] ) ) {
			return false;
		}
		if ( ! empty( $context['preview'] ) ) {
			return true;
		}
		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		$path = self::normalize( (string) ( $context['path'] ?? '/' ) );
		if ( '' !== (string) ( $context['thankyou_path'] ?? '' ) && self::normalize( (string) $context['thankyou_path'] ) === $path ) {
			return false;
		}
		if ( ! empty( $settings['exclude_woo'] ) && ( ! empty( $context['is_cart'] ) || ! empty( $context['is_checkout'] ) || ! empty( $context['is_account'] ) ) ) {
			return false;
		}

		$mode = (string) ( $settings['pages'] ?? 'all' );
		if ( 'all' === $mode ) {
			return true;
		}
		$matches = self::matches( $settings, (int) ( $context['post_id'] ?? 0 ), $path );
		return 'include' === $mode ? $matches : ! $matches;
	}

	/**
	 * Page matches an ID or a URL fragment.
	 *
	 * @param array  $settings Settings.
	 * @param int    $post_id  Queried object ID.
	 * @param string $path     Normalized request path.
	 */
	private static function matches( array $settings, int $post_id, string $path ): bool {
		if ( $post_id > 0 && in_array( $post_id, self::page_ids( (string) ( $settings['page_ids'] ?? '' ) ), true ) ) {
			return true;
		}
		$haystack = self::lower( rawurldecode( $path ) );
		foreach ( self::url_fragments( (string) ( $settings['url_contains'] ?? '' ) ) as $fragment ) {
			if ( false !== strpos( $haystack, self::lower( $fragment ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Unique positive page IDs from a comma/space separated list.
	 *
	 * @param string $raw Raw list.
	 * @return int[]
	 */
	public static function page_ids( string $raw ): array {
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', $raw ) ) );
		return array_slice( array_values( array_unique( $ids ) ), 0, 200 );
	}

	/**
	 * URL fragments, one per line (max 20 × 100 chars).
	 *
	 * @param string $raw Raw textarea.
	 * @return string[]
	 */
	public static function url_fragments( string $raw ): array {
		$out = array();
		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			$line = function_exists( 'mb_substr' ) ? mb_substr( $line, 0, 100 ) : substr( $line, 0, 100 );
			if ( '' !== $line && ! in_array( $line, $out, true ) ) {
				$out[] = $line;
			}
		}
		return array_slice( $out, 0, self::MAX_LINES );
	}

	/**
	 * Path with a leading and trailing slash, no query.
	 *
	 * @param string $path Path or URL.
	 */
	public static function normalize( string $path ): string {
		$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		return '/' . trim( $path, '/' ) . ( '' === trim( $path, '/' ) ? '' : '/' );
	}

	/**
	 * Multibyte-safe lowercase.
	 *
	 * @param string $text Text.
	 */
	private static function lower( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
