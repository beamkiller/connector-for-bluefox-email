<?php
/**
 * Site-timezone date helpers.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the site timezone from `timezone_string` / `gmt_offset` and formats dates in the site's date format.
 */
final class Dates {

	/**
	 * Site timezone.
	 */
	public static function site_timezone(): \DateTimeZone {
		$tz = (string) get_option( 'timezone_string' );
		if ( '' !== $tz ) {
			try {
				return new \DateTimeZone( $tz );
			} catch ( \Exception $e ) {
				unset( $e ); // Fall through to the offset.
			}
		}
		$offset  = (float) get_option( 'gmt_offset', 0 );
		$seconds = (int) round( $offset * 3600 );
		$sign    = $seconds < 0 ? '-' : '+';
		$seconds = abs( $seconds );
		return new \DateTimeZone( sprintf( '%s%02d:%02d', $sign, intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ) ) );
	}

	/**
	 * End of the day (23:59:59 site time) `$days` days after `$now`.
	 *
	 * @param int      $days Days.
	 * @param int|null $now  Unix timestamp (tests).
	 */
	public static function end_of_day_in( int $days, ?int $now = null ): int {
		$date = ( new \DateTimeImmutable( '@' . ( null === $now ? time() : $now ) ) )
			->setTimezone( self::site_timezone() )
			->modify( '+' . $days . ' days' )
			->setTime( 23, 59, 59 );
		return $date->getTimestamp();
	}

	/**
	 * Date in the site's date format and timezone (Settings → General), localized by wp_date().
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	public static function local_date( int $timestamp ): string {
		$format = (string) get_option( 'date_format' );
		$format = '' !== $format ? $format : 'F j, Y';
		if ( function_exists( 'wp_date' ) ) {
			$date = wp_date( $format, $timestamp, self::site_timezone() );
			if ( is_string( $date ) && '' !== $date ) {
				return $date;
			}
		}
		return ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( self::site_timezone() )->format( $format );
	}

	/**
	 * ISO 8601 UTC with milliseconds, as bluefox stores dates.
	 *
	 * @param int $timestamp Unix timestamp.
	 */
	public static function iso_utc( int $timestamp ): string {
		return gmdate( 'Y-m-d\TH:i:s.000\Z', $timestamp );
	}
}
