<?php
/**
 * Abandoned cart recovery coupon.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Coupon_Data;
use CFBE\Coupon_Factory;
use CFBE\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * One single-use, email-locked coupon per abandoned cart, created when the reminder is sent and applied
 * automatically when the cart is restored from the email. Kept apart from welcome coupons (own meta key),
 * so a recovery coupon never blocks or counts as the one welcome coupon per email.
 */
class Recovery_Coupon {

	const META_CART  = '_cfbe_cart_id';
	const META_EMAIL = '_cfbe_cart_email';

	/**
	 * Creates WC_Coupon objects: fn( int $id = 0 ): WC_Coupon.
	 *
	 * @var callable
	 */
	private $make;

	/**
	 * Finds the coupon of a cart: fn( int $cart_id ): int.
	 *
	 * @var callable
	 */
	private $finder;

	/**
	 * Recovery coupons of an email issued since a date: fn( string $email, string $since_gmt ): int[] (newest first).
	 *
	 * @var callable
	 */
	private $recent;

	/**
	 * Code generator.
	 *
	 * @var Coupon_Factory
	 */
	private $codes;

	/**
	 * Constructor.
	 *
	 * @param Coupon_Factory|null $codes  Code generator (unique codes).
	 * @param callable|null       $make   fn( int $id = 0 ): WC_Coupon.
	 * @param callable|null       $finder fn( int $cart_id ): int coupon ID or 0.
	 * @param callable|null       $recent fn( string $email, string $since_gmt ): int[] coupon IDs, newest first.
	 */
	public function __construct( ?Coupon_Factory $codes = null, ?callable $make = null, ?callable $finder = null, ?callable $recent = null ) {
		$this->codes  = $codes ? $codes : new Coupon_Factory();
		$this->make   = $make ? $make : static fn( int $id = 0 ) => new \WC_Coupon( $id );
		$this->finder = $finder ? $finder : array( self::class, 'find_by_meta' );
		$this->recent = $recent ? $recent : array( self::class, 'recent_by_email' );
	}

	/**
	 * Default lookup of an email's recovery coupons issued since a UTC date, newest first.
	 *
	 * @param string $email     Email address.
	 * @param string $since_gmt UTC datetime (Y-m-d H:i:s).
	 * @return int[]
	 */
	public static function recent_by_email( string $email, string $since_gmt ): array {
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type = 'shop_coupon' AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				AND m.meta_key = %s AND m.meta_value = %s AND p.post_date_gmt >= %s ORDER BY p.ID DESC LIMIT 20",
				self::META_EMAIL,
				strtolower( $email ),
				$since_gmt
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Whether a coupon can still be redeemed (not expired, usage left).
	 *
	 * @param int      $id  Coupon ID.
	 * @param int|null $now Unix timestamp (tests).
	 */
	public function usable( int $id, ?int $now = null ): bool {
		$coupon  = call_user_func( $this->make, $id );
		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < ( $now ?? time() ) ) {
			return false;
		}
		return (int) $coupon->get_usage_count() < max( 1, (int) $coupon->get_usage_limit() );
	}

	/**
	 * Whether the recovery coupon is switched on.
	 *
	 * @param array $settings `woo_events` settings.
	 */
	public static function enabled( array $settings ): bool {
		return ! empty( $settings['cart_coupon'] ) && (float) ( $settings['cart_coupon_amount'] ?? 0 ) > 0;
	}

	/**
	 * ID of the coupon issued for a cart, or 0.
	 *
	 * @param int $cart_id Cart ID.
	 */
	public function find( int $cart_id ): int {
		return $cart_id > 0 ? (int) call_user_func( $this->finder, $cart_id ) : 0;
	}

	/**
	 * Default finder: one indexed postmeta lookup (always fresh, no query filters).
	 *
	 * @param int $cart_id Cart ID.
	 */
	public static function find_by_meta( int $cart_id ): int {
		global $wpdb;
		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type = 'shop_coupon' AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				AND m.meta_key = %s AND m.meta_value = %s ORDER BY p.ID ASC LIMIT 1",
				self::META_CART,
				(string) $cart_id
			)
		);
		return $id ? (int) $id : 0;
	}

	/**
	 * The cart's coupon: the existing one, or a new one (idempotent per cart).
	 *
	 * @param array    $cart     Cart row (id, email).
	 * @param array    $settings `woo_events` settings.
	 * @param int|null $now      Unix timestamp (tests).
	 * @return int|\WP_Error Coupon ID.
	 */
	public function for_cart( array $cart, array $settings, ?int $now = null ) {
		$cart_id = (int) ( $cart['id'] ?? 0 );
		$email   = strtolower( (string) ( $cart['email'] ?? '' ) );
		if ( $cart_id <= 0 || '' === $email ) {
			return new \WP_Error( 'cfbe_cart_coupon', 'Cart without ID or email.' );
		}
		$existing = $this->find( $cart_id );
		if ( $existing > 0 ) {
			return $existing;
		}

		// Abuse guard: within the cooldown an email gets one recovery coupon. A still usable one is offered again;
		// an expired or redeemed one means no new coupon until the cooldown has passed.
		$cooldown = (int) ( $settings['cart_coupon_cooldown'] ?? 30 );
		if ( $cooldown > 0 ) {
			$since  = gmdate( 'Y-m-d H:i:s', ( $now ?? time() ) - $cooldown * DAY_IN_SECONDS );
			$recent = (array) call_user_func( $this->recent, $email, $since );
			if ( $recent ) {
				$latest = (int) $recent[0];
				if ( $this->usable( $latest, $now ) ) {
					// Link the reused coupon to this cart too, so restoring this cart applies it.
					$coupon = call_user_func( $this->make, $latest );
					$coupon->add_meta_data( self::META_CART, (string) $cart_id );
					$coupon->save();
					return $latest;
				}
				return new \WP_Error( 'cfbe_cart_coupon_cooldown', 'A recovery coupon was already issued to this email within the cooldown.' );
			}
		}

		$code = $this->codes->generate_code( (string) ( $settings['cart_coupon_prefix'] ?? 'CART-' ) );
		if ( null === $code ) {
			return new \WP_Error( 'cfbe_cart_coupon', 'Could not generate a unique coupon code.' );
		}

		$coupon = call_user_func( $this->make, 0 );
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' === ( $settings['cart_coupon_type'] ?? '' ) ? 'fixed_cart' : 'percent' );
		$coupon->set_amount( (float) ( $settings['cart_coupon_amount'] ?? 0 ) );
		$coupon->set_individual_use( ! empty( $settings['cart_coupon_individual'] ) );
		$coupon->set_exclude_sale_items( ! empty( $settings['cart_coupon_exclude_sale'] ) );
		if ( (float) ( $settings['cart_coupon_min'] ?? 0 ) > 0 ) {
			$coupon->set_minimum_amount( (float) $settings['cart_coupon_min'] );
		}
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( $email ) );
		$coupon->set_date_expires( Dates::end_of_day_in( max( 1, (int) ( $settings['cart_coupon_days'] ?? 7 ) ), $now ) );
		/* translators: %s: customer email */
		$coupon->set_description( sprintf( __( 'Abandoned cart coupon – %s', 'connector-for-bluefox-email' ), $email ) );
		$coupon->update_meta_data( self::META_CART, (string) $cart_id );
		$coupon->update_meta_data( self::META_EMAIL, $email );

		$id = (int) $coupon->save();
		return $id > 0 ? $id : new \WP_Error( 'cfbe_cart_coupon', 'WooCommerce did not save the coupon.' );
	}

	/**
	 * Template data of a coupon: couponCode, couponDiscount, couponExpiry, couponMinSpend.
	 *
	 * @param int $id Coupon ID.
	 */
	public function email_data( int $id ): array {
		$coupon  = call_user_func( $this->make, $id );
		$expires = $coupon->get_date_expires();
		$minimum = (float) $coupon->get_minimum_amount();
		return array(
			// WooCommerce stores codes lowercase; codes are case-insensitive, so show them uppercase.
			'couponCode'     => strtoupper( (string) $coupon->get_code() ),
			'couponDiscount' => Coupon_Data::discount_label( (string) $coupon->get_discount_type(), (float) $coupon->get_amount() ),
			'couponExpiry'   => $expires ? Dates::local_date( (int) $expires->getTimestamp() ) : '',
			'couponMinSpend' => $minimum > 0 ? Coupon_Data::money( $minimum ) : '',
		);
	}

	/**
	 * Code of a cart's coupon while it can still be used ('' otherwise).
	 *
	 * @param int $cart_id Cart ID.
	 */
	public function usable_code( int $cart_id ): string {
		$id = $this->find( $cart_id );
		if ( $id <= 0 || ! $this->usable( $id ) ) {
			return '';
		}
		return (string) call_user_func( $this->make, $id )->get_code();
	}
}
