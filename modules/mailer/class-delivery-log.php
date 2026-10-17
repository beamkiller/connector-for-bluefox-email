<?php
/**
 * Small delivery log of the mailer (last 50 sends).
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in option `cfbe_mailer_log` (not autoloaded). Recipients are masked, subjects truncated,
 * message bodies are never stored.
 */
final class Delivery_Log {

	const OPTION = 'cfbe_mailer_log';

	const SIZE = 50;

	const SUBJECT_LENGTH = 60;

	/**
	 * Appends an entry.
	 *
	 * @param string[] $recipients Recipients.
	 * @param string   $subject    Subject.
	 * @param string   $result     sent | failed | fallback | test.
	 * @param string   $note       Short note (error message), emails are masked.
	 */
	public static function add( array $recipients, string $subject, string $result, string $note = '' ): void {
		$entries   = self::entries();
		$first     = $recipients ? Logger::mask_email( (string) reset( $recipients ) ) : '-';
		$entries[] = array(
			'time'      => time(),
			'recipient' => count( $recipients ) > 1 ? $first . ' +' . ( count( $recipients ) - 1 ) : $first,
			'subject'   => self::truncate( $subject, self::SUBJECT_LENGTH ),
			'result'    => $result,
			'note'      => self::truncate( self::mask_emails( $note ), 200 ),
		);
		update_option( self::OPTION, array_slice( $entries, -self::SIZE ), false );
	}

	/**
	 * Entries, oldest first.
	 *
	 * @return array[]
	 */
	public static function entries(): array {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? array_values( $entries ) : array();
	}

	/**
	 * Masks every email address in a text.
	 *
	 * @param string $text Text.
	 */
	public static function mask_emails( string $text ): string {
		return (string) preg_replace_callback(
			'/[^\s<>"\'(),;:]+@[^\s<>"\'(),;:]+/',
			static function ( $found ) {
				return Logger::mask_email( $found[0] );
			},
			$text
		);
	}

	/**
	 * Truncates to a number of characters with an ellipsis.
	 *
	 * @param string $text   Text.
	 * @param int    $length Max characters.
	 */
	public static function truncate( string $text, int $length ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $text ) > $length ? mb_substr( $text, 0, $length - 1 ) . '…' : $text;
		}
		return strlen( $text ) > $length ? substr( $text, 0, $length - 1 ) . '…' : $text;
	}
}
