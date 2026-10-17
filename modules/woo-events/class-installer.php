<?php
/**
 * Abandoned cart table install / upgrade.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades `{prefix}cfbe_carts` via dbDelta (version option `cfbe_woo_events_db_version`).
 */
final class Installer {

	const DB_VERSION = '1';
	const OPTION     = 'cfbe_woo_events_db_version';
	const TABLE      = 'cfbe_carts';

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
		update_option( self::OPTION, self::DB_VERSION );
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
  email varchar(190) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  session_key varchar(100) NOT NULL DEFAULT '',
  cart longtext DEFAULT NULL,
  cart_total decimal(19,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT '',
  token char(32) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  updated_at datetime NOT NULL,
  sent_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token (token),
  KEY email (email),
  KEY session_key (session_key),
  KEY status_updated (status,updated_at)
) {$collate};";
	}
}
