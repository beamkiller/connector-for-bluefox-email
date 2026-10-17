<?php
/**
 * Signed per-form targeting (list + extra tags).
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Forms are rendered into cached pages and submitted to a public, nonce-less endpoint, so the list ID and
 * tags a form targets travel in a hidden field signed with an HMAC of the site's auth salt. A missing or
 * tampered value falls back to the default list without extra tags.
 *
 * Token: base64url( JSON {l: list ID, t: [tags]} ) . "." . HMAC-SHA256 (first 32 hex chars).
 */
final class Form_Config {

	const FIELD     = 'cfbe_cfg';
	const MAX_LEN   = 1024;
	const SIG_CHARS = 32;

	// Fill-time token: seconds a form must be open before it is sent, and how long a token stays valid.
	const MIN_FILL_SECONDS = 3;
	const MAX_FILL_SECONDS = 7200;

	/**
	 * Token for a list and tags; '' when there is nothing to target (default list, no tags).
	 *
	 * @param string   $list_id bluefox list ID ('' = default list).
	 * @param string[] $tags    Extra tags.
	 * @param string   $place   Placement name (becomes the `placement-<place>` tag).
	 */
	public static function sign( string $list_id, array $tags = array(), string $place = '' ): string {
		$list_id = self::clean_list_id( $list_id );
		$tags    = Signup_Validator::clean_tags( $tags );
		$place   = Signup_Validator::place( $place );
		if ( '' === $list_id && ! $tags && '' === $place ) {
			return '';
		}
		$payload = self::base64url_encode(
			(string) wp_json_encode(
				array(
					'l' => $list_id,
					't' => $tags,
					'p' => $place,
				)
			)
		);
		return $payload . '.' . self::signature( $payload );
	}

	/**
	 * Verified targeting from a token.
	 *
	 * @param mixed $token Raw value from the request.
	 * @return array{list_id: string, tags: string[], place: string} Empty values when the token is missing or invalid.
	 */
	public static function verify( $token ): array {
		$empty = array(
			'list_id' => '',
			'tags'    => array(),
			'place'   => '',
		);
		if ( ! is_string( $token ) || '' === $token || strlen( $token ) > self::MAX_LEN || 1 !== substr_count( $token, '.' ) ) {
			return $empty;
		}
		list( $payload, $signature ) = explode( '.', $token );
		if ( '' === $payload || ! hash_equals( self::signature( $payload ), $signature ) ) {
			return $empty;
		}
		$data = json_decode( (string) self::base64url_decode( $payload ), true );
		if ( ! is_array( $data ) ) {
			return $empty;
		}
		$list_id = self::clean_list_id( is_string( $data['l'] ?? null ) ? $data['l'] : '' );
		if ( '' !== $list_id && ! isset( self::allowed_lists()[ $list_id ] ) ) {
			// Signed, but not one of the project's lists an admin has seen (anyone who can preview a shortcode can sign).
			$list_id = '';
		}
		return array(
			'list_id' => $list_id,
			'tags'    => Signup_Validator::clean_tags( is_array( $data['t'] ?? null ) ? $data['t'] : array() ),
			'place'   => is_string( $data['p'] ?? null ) ? Signup_Validator::place( $data['p'] ) : '',
		);
	}

	/**
	 * Lists a signed form may target: the project's lists loaded by an admin, plus the lists chosen in the settings.
	 *
	 * @return array<string, true>
	 */
	public static function allowed_lists(): array {
		$known   = get_option( Settings::KNOWN_LISTS, array() );
		$allowed = array_fill_keys( array_keys( array_merge( is_array( $known ) ? $known : array(), self::known_lists() ) ), true );
		foreach ( array( array( 'api', 'list_id' ), array( 'popup', 'list_id' ), array( 'checkout', 'list_id' ), array( 'coupon', 'list_id' ) ) as $setting ) {
			$id = self::clean_list_id( (string) Settings::get( $setting[0], $setting[1] ) );
			if ( '' !== $id ) {
				$allowed[ $id ] = true;
			}
		}
		return $allowed;
	}

	/**
	 * Fill-time token: when the form was shown, signed by this site. Fetched by the form script, so it works on
	 * cached pages; a bot has to fetch one and wait before it can post.
	 *
	 * @param int|null $now Unix time.
	 */
	public static function fill_token( ?int $now = null ): string {
		$time = (string) ( $now ?? time() );
		return $time . '.' . substr( hash_hmac( 'sha256', 'fill|' . $time, wp_salt( 'nonce' ) ), 0, self::SIG_CHARS );
	}

	/**
	 * Whether a fill-time token is genuine, at least MIN_FILL_SECONDS and at most MAX_FILL_SECONDS old.
	 *
	 * @param mixed    $token Raw value from the request.
	 * @param int|null $now   Unix time.
	 */
	public static function fill_token_ok( $token, ?int $now = null ): bool {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{1,12})\.([a-f0-9]{' . self::SIG_CHARS . '})$/', $token, $m ) ) {
			return false;
		}
		if ( ! hash_equals( substr( hash_hmac( 'sha256', 'fill|' . $m[1], wp_salt( 'nonce' ) ), 0, self::SIG_CHARS ), $m[2] ) ) {
			return false;
		}
		$age = ( $now ?? time() ) - (int) $m[1];
		return $age >= self::MIN_FILL_SECONDS && $age <= self::MAX_FILL_SECONDS;
	}

	/**
	 * Lists cached by the General tab's list picker (ID => name); empty when the cache has expired.
	 *
	 * @return array<string, string>
	 */
	public static function known_lists(): array {
		$lists = get_transient( Settings::LISTS_CACHE );
		$out   = array();
		if ( is_array( $lists ) ) {
			foreach ( $lists as $id => $name ) {
				$id = self::clean_list_id( (string) $id );
				if ( '' !== $id ) {
					$out[ $id ] = (string) $name;
				}
			}
		}
		return $out;
	}

	/**
	 * Comma-separated tags (shortcode / block attribute) → clean slugs.
	 *
	 * @param string $tags Raw list.
	 * @return string[]
	 */
	public static function parse_tags( string $tags ): array {
		return Signup_Validator::clean_tags( explode( ',', $tags ) );
	}

	/**
	 * Keeps only the characters of a bluefox object ID.
	 *
	 * @param string $list_id Raw ID.
	 */
	public static function clean_list_id( string $list_id ): string {
		return substr( (string) preg_replace( '/[^A-Za-z0-9]/', '', $list_id ), 0, 64 );
	}

	/**
	 * Truncated HMAC of the payload.
	 *
	 * @param string $payload Encoded payload.
	 */
	private static function signature( string $payload ): string {
		return substr( hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) ), 0, self::SIG_CHARS );
	}

	/**
	 * URL-safe base64 without padding.
	 *
	 * @param string $data Data.
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport encoding of a signed, non-secret payload.
	}

	/**
	 * Decodes URL-safe base64.
	 *
	 * @param string $data Data.
	 * @return string|false
	 */
	private static function base64url_decode( string $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see base64url_encode().
	}
}
