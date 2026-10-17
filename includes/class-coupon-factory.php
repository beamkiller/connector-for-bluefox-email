<?php
/**
 * Welcome coupon creation (WC_Coupon CRUD).
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Creates single-use, email-restricted welcome coupons and finds an existing one per email.
 */
class Coupon_Factory {

	const CHARSET   = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No 0/O/1/I.
	const CODE_LEN  = 8;
	const MAX_TRIES = 5;
	const META      = '_cfbe_welcome_email';
	const META_USED = '_cfbe_used_synced';

	/**
	 * Returns a new coupon object (WC_Coupon or a test double).
	 *
	 * @var callable
	 */
	private $make;

	/**
	 * Returns whether a coupon code is taken.
	 *
	 * @var callable
	 */
	private $code_exists;

	/**
	 * Constructor.
	 *
	 * @param callable|null $make        fn( int $id = 0 ): WC_Coupon.
	 * @param callable|null $code_exists fn( string $code ): bool.
	 */
	public function __construct( ?callable $make = null, ?callable $code_exists = null ) {
		$this->make        = $make ? $make : static function ( int $id = 0 ) {
			return new \WC_Coupon( $id );
		};
		$this->code_exists = $code_exists ? $code_exists : static function ( string $code ) {
			return 0 !== (int) wc_get_coupon_id_by_code( $code );
		};
	}

	/**
	 * ID of an existing welcome coupon for this email, or 0.
	 *
	 * @param string $email Email address.
	 */
	public function find_existing( string $email ): int {
		global $wpdb;
		// Direct lookup: one indexed meta query, always fresh (the reward lock relies on it), no query filters.
		$id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				WHERE p.post_type = 'shop_coupon' AND p.post_status NOT IN ( 'trash', 'auto-draft' )
				AND m.meta_key = %s AND m.meta_value = %s ORDER BY p.ID ASC LIMIT 1",
				self::META,
				strtolower( $email )
			)
		);
		return $id ? (int) $id : 0;
	}

	/**
	 * Creates the welcome coupon.
	 *
	 * @param string   $email    Email address (the only allowed billing email).
	 * @param string   $source   Signup source.
	 * @param array    $settings `coupon` settings.
	 * @param int|null $now      Unix timestamp (tests).
	 * @return int|\WP_Error Coupon ID.
	 */
	public function create( string $email, string $source, array $settings, ?int $now = null ) {
		$email = strtolower( $email );
		$code  = $this->generate_code( (string) ( $settings['code_prefix'] ?? '' ) );
		if ( null === $code ) {
			return new \WP_Error( 'cfbe_coupon_code', 'Could not generate a unique coupon code.' );
		}

		$coupon = call_user_func( $this->make, 0 );
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' === ( $settings['discount_type'] ?? '' ) ? 'fixed_cart' : 'percent' );
		$coupon->set_amount( (float) ( $settings['amount'] ?? 0 ) );
		$coupon->set_individual_use( ! empty( $settings['individual_use'] ) );
		$coupon->set_exclude_sale_items( ! empty( $settings['exclude_sale_items'] ) );
		$coupon->set_free_shipping( ! empty( $settings['free_shipping'] ) );
		if ( (float) ( $settings['minimum_amount'] ?? 0 ) > 0 ) {
			$coupon->set_minimum_amount( (float) $settings['minimum_amount'] );
		}
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_email_restrictions( array( $email ) );
		$coupon->set_date_expires( Dates::end_of_day_in( max( 1, (int) ( $settings['expiry_days'] ?? 30 ) ), $now ) );
		/* translators: %s: subscriber email */
		$coupon->set_description( sprintf( __( 'Newsletter welcome coupon – %s', 'connector-for-bluefox-email' ), $email ) );
		$coupon->update_meta_data( self::META, $email );
		$coupon->update_meta_data( '_cfbe_source', $source );

		$id = (int) $coupon->save();
		return $id > 0 ? $id : new \WP_Error( 'cfbe_coupon_save', 'WooCommerce did not save the coupon.' );
	}

	/**
	 * Coupon details for emails and bluefox fields.
	 *
	 * @param int $id Coupon ID.
	 * @return array{code: string, expires: int, discount_type: string, amount: float, minimum_amount: float}
	 */
	public function details( int $id ): array {
		$coupon  = call_user_func( $this->make, $id );
		$expires = $coupon->get_date_expires();
		return array(
			// WooCommerce stores codes lowercase; codes are case-insensitive, so show them uppercase.
			'code'           => strtoupper( (string) $coupon->get_code() ),
			'expires'        => $expires ? (int) $expires->getTimestamp() : 0,
			'discount_type'  => (string) $coupon->get_discount_type(),
			'amount'         => (float) $coupon->get_amount(),
			'minimum_amount' => (float) $coupon->get_minimum_amount(),
			// The issued coupon's own restrictions (not the current settings) – used for the email terms line.
			'individual_use' => (bool) $coupon->get_individual_use(),
			'exclude_sale'   => (bool) $coupon->get_exclude_sale_items(),
			'free_shipping'  => (bool) $coupon->get_free_shipping(),
		);
	}

	/**
	 * Email a welcome coupon was issued to ('' when the coupon is not one of ours).
	 *
	 * @param int $id Coupon ID.
	 */
	public function welcome_email( int $id ): string {
		return $id > 0 ? (string) call_user_func( $this->make, $id )->get_meta( self::META ) : '';
	}

	/**
	 * Whether bluefox currently has couponUsed = true for this coupon.
	 *
	 * @param int $id Coupon ID.
	 */
	public function used_synced( int $id ): bool {
		return $id > 0 && (bool) call_user_func( $this->make, $id )->get_meta( self::META_USED );
	}

	/**
	 * Records the couponUsed value last written to bluefox.
	 *
	 * @param int  $id   Coupon ID.
	 * @param bool $used Value.
	 */
	public function set_used_synced( int $id, bool $used ): void {
		if ( $id <= 0 ) {
			return;
		}
		$coupon = call_user_func( $this->make, $id );
		if ( $used ) {
			$coupon->update_meta_data( self::META_USED, 1 );
		} else {
			$coupon->delete_meta_data( self::META_USED );
		}
		$coupon->save();
	}

	/**
	 * Unique random code, or null after MAX_TRIES collisions.
	 *
	 * @param string $prefix Prefix (e.g. HIR-).
	 */
	public function generate_code( string $prefix ): ?string {
		$prefix = strtoupper( (string) preg_replace( '/[^A-Za-z0-9-]/', '', $prefix ) );
		$max    = strlen( self::CHARSET ) - 1;
		for ( $try = 0; $try < self::MAX_TRIES; $try++ ) {
			$code = $prefix;
			for ( $i = 0; $i < self::CODE_LEN; $i++ ) {
				$code .= self::CHARSET[ random_int( 0, $max ) ];
			}
			if ( ! call_user_func( $this->code_exists, $code ) ) {
				return $code;
			}
		}
		return null;
	}
}
