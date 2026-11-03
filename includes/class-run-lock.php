<?php
/**
 * Cross-process lock for background jobs.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * One row in the options table; INSERT IGNORE makes taking it atomic (add_option() is not: it upserts).
 * A lock older than its TTL belongs to a crashed run and is taken over. Never read via get_option(),
 * so no object cache is involved; uninstall removes it with the other cfbe_* options.
 */
final class Run_Lock {

	/**
	 * Value of the lock this instance holds ('' = none).
	 *
	 * @var string
	 */
	private string $token = '';

	/**
	 * Constructor.
	 *
	 * @param string $name Option name (cfbe_*).
	 * @param int    $ttl  Seconds after which a held lock counts as stale.
	 */
	public function __construct( private string $name, private int $ttl ) {}

	/**
	 * Takes the lock.
	 *
	 * @param int $now Unix time.
	 * @return bool Whether this run holds it now.
	 */
	public function acquire( int $now ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Atomic lock row; caching would defeat it.
		$token = $now . ':' . wp_generate_uuid4();
		$taken = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $this->name, $token ) );
		if ( 1 !== $taken ) {
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $this->name ) );
			if ( $now - (int) $held < $this->ttl ) {
				return false; // Another run is working.
			}
			// Stale: take it over only if no other run did so in the meantime.
			$taken = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, $this->name, $held ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		if ( 1 !== $taken ) {
			return false;
		}
		$this->token = $token;
		return true;
	}

	/**
	 * Releases the lock if this instance holds it (never someone else's).
	 */
	public function release(): void {
		global $wpdb;
		if ( '' === $this->token ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lock row.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $this->name, $this->token ) );
		$this->token = '';
	}
}
