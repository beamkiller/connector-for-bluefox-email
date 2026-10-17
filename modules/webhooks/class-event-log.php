<?php
/**
 * Last received webhook events (for the Webhooks tab).
 *
 * @package CFBE
 */

namespace CFBE\Webhooks;

use CFBE\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Ring buffer of the last 20 events in option `cfbe_webhook_log` (not autoloaded). Emails are stored masked.
 */
final class Event_Log {

	const OPTION = 'cfbe_webhook_log';
	const SIZE   = 20;

	/**
	 * Records an event.
	 *
	 * @param string $type  Event type.
	 * @param string $email Contact email ('' if none).
	 */
	public static function add( string $type, string $email ): void {
		$entries = self::entries();
		array_unshift(
			$entries,
			array(
				'type'  => substr( sanitize_key( $type ), 0, 40 ),
				'email' => '' !== $email ? Logger::mask_email( $email ) : '',
				'time'  => time(),
			)
		);
		update_option( self::OPTION, array_slice( $entries, 0, self::SIZE ), false );
	}

	/**
	 * Stored entries, newest first.
	 *
	 * @return array[] Rows: type, email (masked), time (Unix).
	 */
	public static function entries(): array {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? array_values( array_filter( $entries, 'is_array' ) ) : array();
	}
}
