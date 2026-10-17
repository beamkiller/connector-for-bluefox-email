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

	const SUCCESS_CODES = array( self::PENDING, self::RESENT, self::ALREADY, self::QUEUED );

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
