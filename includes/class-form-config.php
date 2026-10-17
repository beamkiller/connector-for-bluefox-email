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

	/**
	 * Token for a list and tags; '' when there is nothing to target (default list, no tags).
	 *
	 * @param string   $list_id bluefox list ID ('' = default list).
	 * @param string[] $tags    Extra tags.
	 */
	public static function sign( string $list_id, array $tags = array() ): string {
		$list_id = self::clean_list_id( $list_id );
		$tags    = Signup_Validator::clean_tags( $tags );
		if ( '' === $list_id && ! $tags ) {
			return '';
		}
		$payload = self::base64url_encode(
			(string) wp_json_encode(
				array(
					'l' => $list_id,
					't' => $tags,
				)
			)
		);
		return $payload . '.' . self::signature( $payload );
	}

	/**
	 * Verified targeting from a token.
	 *
	 * @param mixed $token Raw value from the request.
	 * @return array{list_id: string, tags: string[]} Empty values when the token is missing or invalid.
	 */
	public static function verify( $token ): array {
		$empty = array(
			'list_id' => '',
			'tags'    => array(),
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
		return array(
			'list_id' => self::clean_list_id( is_string( $data['l'] ?? null ) ? $data['l'] : '' ),
			'tags'    => Signup_Validator::clean_tags( is_array( $data['t'] ?? null ) ? $data['t'] : array() ),
		);
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
