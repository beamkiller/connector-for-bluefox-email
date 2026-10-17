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

	/**
	 * Counts a hit; false when the subject is over the limit for the current window.
	 *
	 * @param string $bucket Bucket name (ip, email).
	 * @param string $key    Subject.
	 * @param int    $limit  Max hits per window.
	 * @param int    $window Window length in seconds.
	 */
	public function hit( string $bucket, string $key, int $limit, int $window ): bool {
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
	 * Visitor IP (REMOTE_ADDR), filterable for proxies / Cloudflare.
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		/**
		 * Filters the client IP used for rate limiting and consent records.
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'cfbe_client_ip', $ip );
	}
}
