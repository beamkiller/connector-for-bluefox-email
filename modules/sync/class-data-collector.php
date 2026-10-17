<?php
/**
 * Contact sync: computes the field values of one email address.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Reads WordPress user data and (when WooCommerce is active) order statistics of an email address.
 * Order access goes through wc_get_orders() / WC_Customer only, so it works with HPOS.
 */
class Data_Collector {

	/**
	 * Most recent paid orders read per contact (guest stats are aggregated from these).
	 */
	const ORDER_LIMIT = 100;

	/**
	 * Most recent orders scanned for product categories.
	 */
	const CATEGORY_ORDERS = 20;

	/**
	 * Maximum number of category slugs.
	 */
	const CATEGORY_MAX = 20;

	/**
	 * Maximum length of the comma-separated category list.
	 */
	const CATEGORY_CHARS = 250;

	/**
	 * Returns whether WooCommerce is active.
	 *
	 * @var callable
	 */
	private $woo_active;

	/**
	 * Builds a WC_Customer for a user ID.
	 *
	 * @var callable
	 */
	private $customer_factory;

	/**
	 * Constructor.
	 *
	 * @param callable|null $woo_active       Returns bool (null = Modules::woocommerce_active()).
	 * @param callable|null $customer_factory int $user_id => WC_Customer|null (tests).
	 */
	public function __construct( ?callable $woo_active = null, ?callable $customer_factory = null ) {
		$this->woo_active       = $woo_active ? $woo_active : array( Modules::class, 'woocommerce_active' );
		$this->customer_factory = $customer_factory ? $customer_factory : static function ( int $user_id ) {
			try {
				return new \WC_Customer( $user_id );
			} catch ( \Exception $e ) {
				return null;
			}
		};
	}

	/**
	 * Values of an email address, keyed by field key (see Fields::DEFINITIONS). Empty values are left out.
	 *
	 * @param string        $email  Email address.
	 * @param \WP_User|null $user   The WordPress user with this email, if any.
	 * @param string[]|null $wanted Field keys to compute (null = all); skips expensive lookups.
	 * @return array{values: array, has_user: bool, paid_orders: int}
	 */
	public function collect( string $email, $user, ?array $wanted = null ): array {
		$values = array();
		$paid   = 0;

		if ( is_object( $user ) && ! empty( $user->ID ) ) {
			$user_id                = (int) $user->ID;
			$values['firstName']    = (string) get_user_meta( $user_id, 'first_name', true );
			$values['lastName']     = (string) get_user_meta( $user_id, 'last_name', true );
			$values['wpUserId']     = $user_id;
			$roles                  = array_values( (array) ( $user->roles ?? array() ) );
			$values['wpRole']       = isset( $roles[0] ) ? (string) $roles[0] : null;
			$values['registeredAt'] = self::date_from_mysql( (string) ( $user->user_registered ?? '' ) );
		} else {
			$user = null;
		}

		if ( call_user_func( $this->woo_active ) && function_exists( 'wc_get_orders' ) ) {
			$woo    = $this->woo_values( $email, $user, $wanted );
			$paid   = $woo['paid'];
			$values = self::fill( $values, $woo['values'] );
		}

		$values['name'] = self::full_name( $values, $user );

		return array(
			'values'      => array_filter(
				$values,
				static function ( $value ) {
					return null !== $value && '' !== $value;
				}
			),
			'has_user'    => null !== $user,
			'paid_orders' => $paid,
		);
	}

	/**
	 * WooCommerce values: order statistics, billing address, categories, customer type.
	 *
	 * @param string        $email  Email address.
	 * @param \WP_User|null $user   User.
	 * @param string[]|null $wanted Wanted keys.
	 * @return array{values: array, paid: int}
	 */
	private function woo_values( string $email, $user, ?array $wanted ): array {
		$query = array(
			'status'  => self::paid_statuses(),
			'type'    => 'shop_order',
			'limit'   => self::ORDER_LIMIT,
			'orderby' => 'date',
			'order'   => 'DESC',
		);
		if ( null !== $user ) {
			$query['customer_id'] = (int) $user->ID;
		} else {
			$query['billing_email'] = $email;
		}
		$orders = (array) wc_get_orders( $query );
		if ( null === $user ) {
			// Guests only: orders placed by a registered account belong to that account.
			$orders = array_values(
				array_filter(
					$orders,
					static function ( $order ) {
						return is_object( $order ) && 0 === (int) $order->get_customer_id();
					}
				)
			);
			if ( ! $orders ) {
				return array(
					'values' => array(),
					'paid'   => 0,
				);
			}
		}

		$values = array();
		$latest = $orders[0] ?? null;
		$paid   = count( $orders );

		if ( null !== $user ) {
			$customer = call_user_func( $this->customer_factory, (int) $user->ID );
			if ( is_object( $customer ) ) {
				$values['orderCount']     = (int) $customer->get_order_count();
				$values['totalSpent']     = round( (float) $customer->get_total_spent(), 2 );
				$values['billingCountry'] = (string) $customer->get_billing_country();
				$values['billingCity']    = (string) $customer->get_billing_city();
				$values['firstName']      = (string) $customer->get_billing_first_name();
				$values['lastName']       = (string) $customer->get_billing_last_name();
			}
			$values['customerType'] = 'customer';
		} else {
			$total = 0.0;
			foreach ( $orders as $order ) {
				$total += (float) $order->get_total() - (float) $order->get_total_refunded();
			}
			$values['orderCount']   = $paid;
			$values['totalSpent']   = round( $total, 2 );
			$values['customerType'] = 'guest';
		}

		if ( null !== $latest ) {
			$values['lastOrderDate']  = self::order_date( $latest );
			$values['lastOrderTotal'] = round( (float) $latest->get_total(), 2 );
			if ( empty( $values['billingCountry'] ) ) {
				$values['billingCountry'] = (string) $latest->get_billing_country();
			}
			if ( empty( $values['billingCity'] ) ) {
				$values['billingCity'] = (string) $latest->get_billing_city();
			}
			if ( empty( $values['firstName'] ) ) {
				$values['firstName'] = (string) $latest->get_billing_first_name();
			}
			if ( empty( $values['lastName'] ) ) {
				$values['lastName'] = (string) $latest->get_billing_last_name();
			}

			$oldest = end( $orders );
			if ( count( $orders ) >= self::ORDER_LIMIT ) {
				$first  = (array) wc_get_orders(
					array_merge(
						$query,
						array(
							'limit' => 1,
							'order' => 'ASC',
						)
					)
				);
				$oldest = $first ? $first[0] : $oldest;
			}
			$values['firstOrderDate'] = self::order_date( $oldest );

			if ( null === $wanted || in_array( 'purchasedCategories', $wanted, true ) ) {
				$values['purchasedCategories'] = $this->categories( array_slice( $orders, 0, self::CATEGORY_ORDERS ) );
			}
		}

		return array(
			'values' => $values,
			'paid'   => $paid,
		);
	}

	/**
	 * Comma-separated product category slugs of the given orders (capped).
	 *
	 * @param array $orders Orders.
	 */
	private function categories( array $orders ): string {
		$product_ids = array();
		foreach ( $orders as $order ) {
			foreach ( (array) $order->get_items() as $item ) {
				if ( is_object( $item ) && is_callable( array( $item, 'get_product_id' ) ) ) {
					$product_ids[ (int) $item->get_product_id() ] = true;
				}
			}
		}
		unset( $product_ids[0] );

		$slugs = array();
		foreach ( array_keys( $product_ids ) as $product_id ) {
			$terms = function_exists( 'wc_get_product_terms' )
				? wc_get_product_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) )
				: wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $slug ) {
				$slugs[ (string) $slug ] = true;
			}
		}

		$out = array();
		$len = 0;
		foreach ( array_keys( $slugs ) as $slug ) {
			if ( count( $out ) >= self::CATEGORY_MAX || $len + strlen( $slug ) + 1 > self::CATEGORY_CHARS ) {
				break;
			}
			$out[] = $slug;
			$len  += strlen( $slug ) + 1;
		}
		return implode( ',', $out );
	}

	/**
	 * First + last name (already falling back to billing names); a user's display name otherwise.
	 *
	 * @param array         $values Values.
	 * @param \WP_User|null $user   User.
	 */
	private static function full_name( array $values, $user ): ?string {
		$name = trim( preg_replace( '/\s+/', ' ', trim( (string) ( $values['firstName'] ?? '' ) . ' ' . (string) ( $values['lastName'] ?? '' ) ) ) );
		if ( '' === $name && null !== $user ) {
			$name = trim( (string) ( $user->display_name ?? '' ) );
		}
		return '' === $name ? null : $name;
	}

	/**
	 * Paid order statuses (without the wc- prefix).
	 *
	 * @return string[]
	 */
	public static function paid_statuses(): array {
		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? (array) wc_get_is_paid_statuses() : array();
		return $statuses ? array_values( $statuses ) : array( 'processing', 'completed' );
	}

	/**
	 * Order creation date as Y-m-d (site time zone for WC_DateTime).
	 *
	 * @param object $order Order.
	 */
	private static function order_date( $order ): ?string {
		$date = is_object( $order ) ? $order->get_date_created() : null;
		if ( ! $date instanceof \DateTimeInterface ) {
			return null;
		}
		return method_exists( $date, 'date' ) ? (string) $date->date( 'Y-m-d' ) : $date->format( 'Y-m-d' );
	}

	/**
	 * Y-m-d part of a MySQL datetime.
	 *
	 * @param string $datetime `Y-m-d H:i:s`.
	 */
	private static function date_from_mysql( string $datetime ): ?string {
		return 1 === preg_match( '/^(\d{4}-\d{2}-\d{2})/', $datetime, $m ) && '0000-00-00' !== $m[1] ? $m[1] : null;
	}

	/**
	 * Fills empty values of $base from $extra (non-empty $base values win).
	 *
	 * @param array $base  Values.
	 * @param array $extra Additional values.
	 */
	private static function fill( array $base, array $extra ): array {
		foreach ( $extra as $key => $value ) {
			if ( ! isset( $base[ $key ] ) || '' === $base[ $key ] ) {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}
}
