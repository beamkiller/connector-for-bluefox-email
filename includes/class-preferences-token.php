<?php
/**
 * Reads the bluefox subscription-preferences link token.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * The `?token=` of the unsubscribe / pause link is a JWT signed by bluefox. The page only reads its payload
 * (projectId, email, type, optional subscriberListId) and sends the raw string back as the Bearer credential;
 * bluefox verifies it. The token is never stored or logged.
 */
final class Preferences_Token {

	const MAX_LENGTH = 4096;

	const TYPES = array( 'unsubscribe', 'pause-subscription' );

	/**
	 * Raw token.
	 *
	 * @var string
	 */
	public $raw;

	/**
	 * Email address from the payload.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * Project ID from the payload.
	 *
	 * @var string
	 */
	public $project_id;

	/**
	 * Link type: unsubscribe | pause-subscription | ''.
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Constructor.
	 *
	 * @param string $raw        Raw token.
	 * @param string $email      Email.
	 * @param string $project_id Project ID.
	 * @param string $type       Link type.
	 */
	private function __construct( string $raw, string $email, string $project_id, string $type ) {
		$this->raw        = $raw;
		$this->email      = $email;
		$this->project_id = $project_id;
		$this->type       = $type;
	}

	/**
	 * Parses a raw token; null when it isn't a readable JWT with a valid email and project ID.
	 *
	 * @param string $raw Raw token from the request.
	 */
	public static function parse( string $raw ): ?self {
		$raw = trim( $raw );
		if ( '' === $raw || strlen( $raw ) > self::MAX_LENGTH || ! preg_match( '/^[A-Za-z0-9_-]+\.([A-Za-z0-9_-]+)\.[A-Za-z0-9_-]*$/', $raw, $m ) ) {
			return null;
		}
		// Base64url payload segment; reading only, bluefox checks the signature.
		$json    = base64_decode( strtr( $m[1], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $m[1] ) % 4 ) % 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$payload = false !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$email      = sanitize_email( (string) ( $payload['email'] ?? '' ) );
		$project_id = (string) ( $payload['projectId'] ?? '' );
		$type       = (string) ( $payload['type'] ?? '' );
		if ( '' === $email || ! is_email( $email ) || ! preg_match( '/^[A-Za-z0-9]{1,64}$/', $project_id ) ) {
			return null;
		}
		return new self( $raw, $email, $project_id, in_array( $type, self::TYPES, true ) ? $type : '' );
	}
}
