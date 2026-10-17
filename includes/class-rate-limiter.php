<?php
/**
 * Fixed-window rate limiting with transients.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Keys are HMACs of the subject (IP / email), so no raw personal data ends up in option names.
 */
class Rate_Limiter {

	// Where the visitor IP comes from (Privacy tab): the direct connection, or a header set by a trusted proxy.
	const SETTING_HEADER = 'ip_header';
	const HEADERS        = array(
		''           => '',
		'cloudflare' => 'HTTP_CF_CONNECTING_IP',
		'forwarded'  => 'HTTP_X_FORWARDED_FOR',
		'real_ip'    => 'HTTP_X_REAL_IP',
	);

	/**
	 * Counts a hit; false when the subject is over the limit for the current window.
	 *
	 * @param string $bucket Bucket name (ip, email).
	 * @param string $key    Subject.
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window length in seconds.
	 */
	public function hit( string $bucket, string $key, int $limit, int $window ): bool {
		$key       = self::subject( $key );
		$transient = 'cfbe_rl_' . $bucket . '_' . substr( hash_hmac( 'sha256', $key, wp_salt( 'nonce' ) ), 0, 24 );
		$now       = time();
		$state     = get_transient( $transient );

		if ( ! is_array( $state ) || (int) ( $state['until'] ?? 0 ) <= $now ) {
			$state = array(
				'n'     => 0,
				'until' => $now + $window,
			);
		}
		if ( (int) $state['n'] >= $limit ) {
			return false;
		}

		++$state['n'];
		set_transient( $transient, $state, max( 1, (int) $state['until'] - $now ) );
		return true;
	}

	/**
	 * IPv6 visitors usually own a whole /64 range: they are counted per range, not per address.
	 *
	 * @param string $key Subject.
	 */
	public static function subject( string $key ): string {
		if ( false === filter_var( $key, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $key;
		}
		$packed = inet_pton( $key );
		return false === $packed ? $key : inet_ntop( substr( $packed, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
	}

	/**
	 * Visitor IP: REMOTE_ADDR, or the header of a trusted proxy chosen on the Privacy tab; filterable.
	 */
	public static function client_ip(): string {
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$header = self::HEADERS[ (string) Settings::get( 'api', self::SETTING_HEADER ) ] ?? '';
		if ( '' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			// X-Forwarded-For lists every hop; the last one was added by the proxy in front of this server.
			$parts     = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) );
			$forwarded = (string) end( $parts );
			if ( false !== filter_var( $forwarded, FILTER_VALIDATE_IP ) ) {
				$ip = $forwarded;
			}
		}
		$ip = false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		/**
		 * Filters the client IP used for rate limiting and consent records.
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'cfbe_client_ip', $ip );
	}
}
