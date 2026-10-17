<?php
/**
 * "bluefox" column in the Users list.
 *
 * @package CFBE
 */

namespace CFBE\Webhooks;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the list statuses, bounce and complaint recorded from webhook events (user meta).
 */
final class Users_Column {

	const COLUMN = 'cfbe_status';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render' ), 10, 3 );
	}

	/**
	 * Adds the column.
	 *
	 * @param array $columns Columns.
	 */
	public function add_column( $columns ): array {
		$columns                 = (array) $columns;
		$columns[ self::COLUMN ] = __( 'bluefox', 'connector-for-bluefox-email' );
		return $columns;
	}

	/**
	 * Column content (escaped HTML).
	 *
	 * @param string $output  Current output.
	 * @param string $column  Column.
	 * @param int    $user_id User ID.
	 */
	public function render( $output, $column, $user_id ): string {
		if ( self::COLUMN !== $column ) {
			return (string) $output;
		}

		$lines    = array();
		$names    = get_transient( Settings::LISTS_CACHE );
		$names    = is_array( $names ) ? $names : array();
		$statuses = get_user_meta( (int) $user_id, Event_Processor::META_LIST_STATUS, true );
		foreach ( is_array( $statuses ) ? $statuses : array() as $list => $status ) {
			$label   = isset( $names[ $list ] ) ? (string) $names[ $list ] : (string) $list;
			$lines[] = esc_html( $label . ': ' . self::status_label( (string) $status ) );
		}
		if ( get_user_meta( (int) $user_id, Event_Processor::META_BOUNCED, true ) ) {
			$lines[] = '<strong>' . esc_html__( 'Bounced', 'connector-for-bluefox-email' ) . '</strong>';
		}
		if ( get_user_meta( (int) $user_id, Event_Processor::META_COMPLAINED, true ) ) {
			$lines[] = '<strong>' . esc_html__( 'Spam complaint', 'connector-for-bluefox-email' ) . '</strong>';
		}

		return $lines ? implode( '<br>', $lines ) : '<span aria-hidden="true">—</span>';
	}

	/**
	 * Translated status.
	 *
	 * @param string $status Status.
	 */
	public static function status_label( string $status ): string {
		switch ( $status ) {
			case 'active':
				return __( 'subscribed', 'connector-for-bluefox-email' );
			case 'paused':
				return __( 'paused', 'connector-for-bluefox-email' );
			case 'unsubscribed':
				return __( 'unsubscribed', 'connector-for-bluefox-email' );
			case 'unverified':
				return __( 'not confirmed', 'connector-for-bluefox-email' );
			default:
				return $status;
		}
	}
}
