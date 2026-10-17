<?php
/**
 * Database schema install / upgrade.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the `{prefix}cfbe_subscribers` table via dbDelta.
 */
final class Installer {

	const DB_VERSION = '4';
	const OPTION     = 'cfbe_db_version';
	const TABLE      = 'cfbe_subscribers';

	/**
	 * Full table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Runs install when the stored schema version differs.
	 */
	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( self::OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Creates or upgrades the table (dbDelta is idempotent).
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema() );
		self::backfill_mailboxes();
		update_option( self::OPTION, self::DB_VERSION );
	}

	/**
	 * Fills the canonical mailbox of rows stored before the column existed.
	 */
	private static function backfill_mailboxes(): void {
		global $wpdb;
		$table = self::table();
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off schema upgrade.
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, email FROM %i WHERE mailbox = %s LIMIT 500', $table, '' ), ARRAY_A );
			$count = count( $rows );
			foreach ( $rows as $row ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off schema upgrade.
				$wpdb->update( $table, array( 'mailbox' => Subscriber_Repository::mailbox( (string) $row['email'] ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
			}
		} while ( 500 === $count );
	}

	/**
	 * CREATE TABLE statement in dbDelta's strict format.
	 */
	public static function schema(): string {
		global $wpdb;
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email varchar(190) NOT NULL,
  mailbox varchar(190) NOT NULL DEFAULT '',
  name varchar(190) DEFAULT NULL,
  source varchar(20) NOT NULL DEFAULT '',
  list_id varchar(64) NOT NULL DEFAULT '',
  tags text DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  consent_text text DEFAULT NULL,
  consent_ip varchar(45) DEFAULT NULL,
  consent_at datetime DEFAULT NULL,
  confirmed_at datetime DEFAULT NULL,
  coupon_id bigint(20) unsigned DEFAULT NULL,
  order_id bigint(20) unsigned DEFAULT NULL,
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  last_error varchar(255) DEFAULT NULL,
  list_status varchar(20) DEFAULT NULL,
  list_status_at datetime DEFAULT NULL,
  merge_pending tinyint(1) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY status_created (status,created_at),
  KEY mailbox (mailbox)
) {$collate};";
	}
}
