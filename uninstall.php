<?php
/**
 * Uninstall: removes the plugin's options, tables, user meta and pending background jobs, on every site of a
 * multisite network. Issued WooCommerce coupons, order notes and order meta (consent records) are intentionally kept.
 *
 * @package CFBE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the data of the current site (blog).
 *
 * @param bool $use_api Whether the Action Scheduler API may be used (only for the site it was loaded for).
 */
function cfbe_uninstall_site( bool $use_api ): void {
	global $wpdb;

	// Options: every setting, cache and log of this plugin is named cfbe_*.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$options = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( 'cfbe_' ) . '%',
			$wpdb->esc_like( '_transient_cfbe_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_cfbe_' ) . '%'
		)
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Tables.
	foreach ( array( 'cfbe_subscribers', 'cfbe_carts' ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
	}

	// WP-Cron events of this plugin.
	foreach ( (array) _get_cron_array() as $events ) {
		foreach ( array_keys( (array) $events ) as $hook ) {
			if ( 0 === strpos( (string) $hook, 'cfbe_' ) ) {
				wp_unschedule_hook( (string) $hook );
			}
		}
	}

	// Pending background jobs of the `cfbe` group (Action Scheduler tables are shared with other plugins).
	if ( $use_api && function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && ActionScheduler::is_initialized() ) {
		as_unschedule_all_actions( '', array(), 'cfbe' );
	} elseif ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'actionscheduler_actions' ) ) ) {
		$wpdb->query(
			$wpdb->prepare(
				'DELETE a FROM %i a INNER JOIN %i g ON a.group_id = g.group_id WHERE g.slug = %s AND a.status = %s',
				$wpdb->prefix . 'actionscheduler_actions',
				$wpdb->prefix . 'actionscheduler_groups',
				'cfbe',
				'pending'
			)
		);
	}
	// phpcs:enable
}

if ( is_multisite() ) {
	$cfbe_current = get_current_blog_id();
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $cfbe_site_id ) {
		switch_to_blog( (int) $cfbe_site_id );
		cfbe_uninstall_site( (int) $cfbe_site_id === $cfbe_current );
		restore_current_blog();
	}
} else {
	cfbe_uninstall_site( true );
}

// User meta written by the plugin (subscription status, bounces). The users table is shared by the whole network,
// so this runs once; on a network every site uses the same cfbe_* keys for the same users.
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'cfbe_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
