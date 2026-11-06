<?php
/**
 * Detects the plugin's own front-end pages (thank-you page, preferences page).
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Lets page-level side effects (no-cache, Referrer-Policy) apply to the plugin's own pages only, never to unrelated
 * pages that happen to carry a `token` parameter.
 */
final class Plugin_Page {

	/**
	 * Whether the current request shows the given plugin page: a singular post/page containing the shortcode, or the
	 * page at the configured URL (for page builders that keep the shortcode outside post_content).
	 *
	 * @param string $url       Configured page URL.
	 * @param string $shortcode Shortcode tag.
	 */
	public static function is_current( string $url, string $shortcode ): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
			return true;
		}
		$path = self::path( $url );
		return '' !== $path && self::path( (string) get_permalink( $post ) ) === $path;
	}

	/**
	 * Normalised path of a URL ('' when it has none).
	 *
	 * @param string $url URL.
	 */
	public static function path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return '' === trim( $path, '/' ) ? '' : '/' . strtolower( trim( $path, '/' ) ) . '/';
	}
}
