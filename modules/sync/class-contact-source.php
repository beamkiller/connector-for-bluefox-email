<?php
/**
 * Contact sync: pages through the emails to backfill.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Registered users first, then guest customers (paid guest orders, by billing email).
 */
class Contact_Source {

	/**
	 * User emails ordered by ID.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Page size.
	 * @return string[]
	 */
	public function user_emails( int $offset, int $limit ): array {
		$users  = get_users(
			array(
				'fields'  => array( 'ID', 'user_email' ),
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => $limit,
				'offset'  => $offset,
			)
		);
		$emails = array();
		foreach ( (array) $users as $user ) {
			$emails[] = (string) $user->user_email;
		}
		return $emails;
	}

	/**
	 * Guest customers' billing emails from one page of paid guest orders (accounts' emails left out).
	 *
	 * @param int $page  Page (1-based).
	 * @param int $limit Orders per page.
	 * @return array{emails: string[], fetched: int}
	 */
	public function guest_emails( int $page, int $limit ): array {
		if ( ! Modules::woocommerce_active() || ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'emails'  => array(),
				'fetched' => 0,
			);
		}
		$orders = (array) wc_get_orders(
			array(
				'customer_id' => 0,
				'status'      => Data_Collector::paid_statuses(),
				'type'        => 'shop_order',
				'limit'       => $limit,
				'paged'       => max( 1, $page ),
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);
		$emails = array();
		foreach ( $orders as $order ) {
			$email = strtolower( trim( (string) $order->get_billing_email() ) );
			if ( '' !== $email && ! isset( $emails[ $email ] ) && ! email_exists( $email ) ) {
				$emails[ $email ] = true;
			}
		}
		return array(
			'emails'  => array_keys( $emails ),
			'fetched' => count( $orders ),
		);
	}

	/**
	 * Number of registered users.
	 */
	public function user_count(): int {
		$counts = count_users();
		return (int) ( $counts['total_users'] ?? 0 );
	}
}
