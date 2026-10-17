<?php
/**
 * Uninstall: removes the plugin's options, tables, user meta and pending background jobs.
 * Issued WooCommerce coupons, order notes and order meta (consent records) are intentionally kept.
 *
 * @package CFBE
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Options: every setting, cache and log of this plugin is named cfbe_*.
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$cfbe_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'cfbe_' ) . '%',
		$wpdb->esc_like( '_transient_cfbe_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cfbe_' ) . '%'
	)
);
foreach ( $cfbe_options as $cfbe_option ) {
	delete_option( $cfbe_option );
}

// User meta written by the plugin (subscription status, bounces).
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'cfbe_' ) . '%' ) );

// Tables.
foreach ( array( 'cfbe_subscribers', 'cfbe_carts' ) as $cfbe_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $cfbe_table ) );
}

// WP-Cron events of this plugin.
foreach ( (array) _get_cron_array() as $cfbe_events ) {
	foreach ( array_keys( (array) $cfbe_events ) as $cfbe_hook ) {
		if ( 0 === strpos( (string) $cfbe_hook, 'cfbe_' ) ) {
			wp_unschedule_hook( (string) $cfbe_hook );
		}
	}
}

// Pending background jobs of the `cfbe` group (Action Scheduler tables are shared with other plugins).
if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler' ) && ActionScheduler::is_initialized() ) {
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
