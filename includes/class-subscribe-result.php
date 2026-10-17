<?php
/**
 * Outcome of a signup attempt.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Value object returned by Subscription_Service and mapped to JSON by the REST layer.
 */
final class Subscribe_Result {

	const PENDING      = 'pending';
	const RESENT       = 'resent';
	const ALREADY      = 'already_active';
	const QUEUED       = 'queued';
	const INVALID      = 'invalid';
	const REJECTED     = 'rejected';
	const RATE_LIMITED = 'rate_limited';
	const SUPPRESSED   = 'suppressed'; // bluefox refused the address (bounced / complained / suppressed).

	const SUCCESS_CODES = array( self::PENDING, self::RESENT, self::ALREADY, self::QUEUED, self::SUPPRESSED );

	/**
	 * Result code.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Visitor-facing message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Field => error message.
	 *
	 * @var array<string, string>
	 */
	public $field_errors;

	/**
	 * HTTP status for the REST response.
	 *
	 * @var int
	 */
	public $http_status;

	/**
	 * Constructor.
	 *
	 * @param string $code         Result code.
	 * @param string $message      Visitor-facing message.
	 * @param array  $field_errors Field errors.
	 * @param int    $http_status  HTTP status.
	 */
	public function __construct( string $code, string $message, array $field_errors = array(), int $http_status = 200 ) {
		$this->code         = $code;
		$this->message      = $message;
		$this->field_errors = $field_errors;
		$this->http_status  = $http_status;
	}

	/**
	 * Whether the visitor should see a success state.
	 */
	public function is_success(): bool {
		return in_array( $this->code, self::SUCCESS_CODES, true );
	}

	/**
	 * What a visitor may learn: every success looks like "check your inbox", so the form never tells anyone
	 * whether an address is subscribed, waiting for confirmation or suppressed. With $reveal_active the
	 * "already subscribed" message is kept (Form tab setting).
	 *
	 * @param string $pending_message Message of a new signup.
	 * @param bool   $reveal_active   Whether to tell visitors an address is already subscribed.
	 */
	public function for_visitor( string $pending_message, bool $reveal_active = false ): self {
		if ( ! $this->is_success() || self::PENDING === $this->code || ( self::ALREADY === $this->code && $reveal_active ) ) {
			return $this;
		}
		return new self( self::PENDING, $pending_message );
	}

	/**
	 * JSON payload.
	 */
	public function to_array(): array {
		$out = array(
			'success' => $this->is_success(),
			'code'    => $this->code,
			'message' => $this->message,
		);
		if ( $this->field_errors ) {
			$out['fields'] = $this->field_errors;
		}
		return $out;
	}
}
