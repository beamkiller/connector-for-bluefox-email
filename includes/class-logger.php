<?php
/**
 * Logger wrapping wc_get_logger().
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to the WooCommerce log (source `cfbe`), masking emails and redacting secrets.
 */
class Logger {

	const SOURCE = 'cfbe';

	const BUFFER_OPTION = 'cfbe_log';

	const BUFFER_SIZE = 200;

	const SECRET_KEY_PATTERN = '/key|token|secret|password|authorization/i';

	/**
	 * WooCommerce logger, resolved lazily.
	 *
	 * @var \WC_Logger_Interface|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param \WC_Logger_Interface|null $logger Injected logger (tests); defaults to wc_get_logger().
	 */
	public function __construct( $logger = null ) {
		$this->logger = $logger;
	}

	/**
	 * Logs an info message.
	 *
	 * @param string $message Message.
	 * @param array  $context Context (redacted before writing).
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Logs a warning.
	 *
	 * @param string $message Message.
	 * @param array  $context Context (redacted before writing).
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Logs an error.
	 *
	 * @param string $message Message.
	 * @param array  $context Context (redacted before writing).
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Masks an email address: `user@example.com` → `u***@example.com`.
	 *
	 * @param string $email Email address.
	 */
	public static function mask_email( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return '***';
		}
		return substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * Redacts secrets and masks emails recursively.
	 *
	 * @param array $context Context.
	 */
	public static function redact( array $context ): array {
		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && preg_match( self::SECRET_KEY_PATTERN, $key ) ) {
				$context[ $key ] = '[redacted]';
			} elseif ( is_array( $value ) ) {
				$context[ $key ] = self::redact( $value );
			} elseif ( is_string( $value ) && false !== strpos( $value, '@' ) && preg_match( '/^[^@\s]+@[^@\s]+$/', $value ) ) {
				$context[ $key ] = self::mask_email( $value );
			}
		}
		return $context;
	}

	/**
	 * Writes a log line.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private function log( string $level, string $message, array $context ): void {
		if ( null === $this->logger && function_exists( 'wc_get_logger' ) ) {
			$this->logger = wc_get_logger();
		}
		if ( $context ) {
			$message .= ' ' . wp_json_encode( self::redact( $context ) );
		}
		// The admin Logs tab shows the recent entries: warnings/errors always, everything without WooCommerce.
		if ( null === $this->logger || 'info' !== $level ) {
			self::buffer( $level, $message );
		}
		if ( null !== $this->logger ) {
			$this->logger->log( $level, $message, array( 'source' => self::SOURCE ) );
		}
	}

	/**
	 * Appends to the ring buffer option (not autoloaded).
	 *
	 * @param string $level   Level.
	 * @param string $message Message (already redacted).
	 */
	private static function buffer( string $level, string $message ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}
		$entries   = self::entries();
		$entries[] = array(
			'time'    => time(),
			'level'   => $level,
			'message' => function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 1000 ) : substr( $message, 0, 1000 ),
		);
		update_option( self::BUFFER_OPTION, array_slice( $entries, -self::BUFFER_SIZE ), false );
	}

	/**
	 * Recent log entries, oldest first.
	 *
	 * @return array[] time, level, message.
	 */
	public static function entries(): array {
		$entries = get_option( self::BUFFER_OPTION, array() );
		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Empties the ring buffer.
	 */
	public static function clear(): void {
		delete_option( self::BUFFER_OPTION );
	}
}
