<?php
/**
 * User roles that never get the welcome coupon (e.g. wholesale customers, resellers).
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Coupon tab “Excluded user roles”. The members of those roles are usually a small set, so their account and billing
 * addresses are loaded with one query and cached as mailboxes (aliases and Gmail dots collapse). Used at three points:
 * no coupon after the confirmation (Reward_Service), no expiry reminder (Coupon_Reminder), and the coupon is rejected
 * in the cart when it is locked to such an address or the shopper is logged in with such a role (coupons issued
 * before the role was given).
 */
class Excluded_Roles {

	const CACHE     = 'cfbe_excluded_mailboxes';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Hooks the cart check and the cache invalidation.
	 */
	public function register(): void {
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon' ), 10, 2 );
		foreach ( array( 'set_user_role', 'add_user_role', 'remove_user_role', 'profile_update', 'user_register', 'delete_user' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}
		foreach ( array( 'added_user_meta', 'updated_user_meta', 'deleted_user_meta' ) as $hook ) {
			add_action( $hook, array( $this, 'on_user_meta' ), 10, 3 );
		}
	}

	/**
	 * Excluded role slugs from the Coupon tab.
	 *
	 * @return string[]
	 */
	public static function roles(): array {
		return array_values( array_filter( array_map( 'strval', (array) Settings::get( 'coupon', 'excluded_roles' ) ) ) );
	}

	/**
	 * Cart message: the Coupon tab text, or the translated default.
	 */
	public static function message(): string {
		$message = trim( (string) Settings::get( 'coupon', 'excluded_message' ) );
		return '' !== $message ? $message : __( 'The welcome coupon cannot be used with this account.', 'connector-for-bluefox-email' );
	}

	/**
	 * Whether an email address belongs to a user with an excluded role (account or billing email, same mailbox).
	 *
	 * @param string $email Email address.
	 */
	public function email_excluded( string $email ): bool {
		if ( '' === $email || ! self::roles() ) {
			return false;
		}
		return isset( $this->mailboxes()[ Subscriber_Repository::mailbox( $email ) ] );
	}

	/**
	 * Whether the logged-in user has an excluded role.
	 */
	public function current_user_excluded(): bool {
		$roles = self::roles();
		if ( ! $roles || ! is_user_logged_in() ) {
			return false;
		}
		return (bool) array_intersect( $roles, (array) wp_get_current_user()->roles );
	}

	/**
	 * Cart / checkout: a welcome coupon is not valid for excluded roles. Throwing is how WooCommerce shows a custom
	 * message (WC_Discounts::is_coupon_valid catches it).
	 *
	 * @param bool       $valid  Valid so far.
	 * @param \WC_Coupon $coupon Coupon.
	 * @return bool
	 * @throws \Exception When the coupon is a welcome coupon and the shopper or its address is excluded.
	 */
	public function validate_coupon( $valid, $coupon ) {
		if ( ! $valid || ! is_object( $coupon ) || ! self::roles() ) {
			return $valid;
		}
		$owner = (string) get_post_meta( (int) $coupon->get_id(), Coupon_Factory::META, true );
		if ( '' === $owner ) {
			return $valid; // Not a welcome coupon.
		}
		if ( $this->current_user_excluded() || $this->email_excluded( $owner ) ) {
			throw new \Exception( esc_html( self::message() ) );
		}
		return $valid;
	}

	/**
	 * A changed billing email clears the cache.
	 *
	 * @param mixed  $meta_id  Meta ID(s).
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 */
	public function on_user_meta( $meta_id, $user_id, $meta_key ): void {
		if ( 'billing_email' === $meta_key ) {
			self::flush();
		}
	}

	/**
	 * Clears the cached mailboxes.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * Mailboxes of the excluded users, as keys (cached; the cache also records which roles it was built for).
	 *
	 * @return array<string, true>
	 */
	private function mailboxes(): array {
		$roles  = self::roles();
		$key    = md5( implode( ',', $roles ) );
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) && ( $cached['roles'] ?? '' ) === $key && is_array( $cached['mailboxes'] ?? null ) ) {
			return $cached['mailboxes'];
		}

		$mailboxes = array();
		$users     = get_users(
			array(
				'role__in'    => $roles,
				'fields'      => array( 'ID', 'user_email' ),
				'count_total' => false,
			)
		);
		foreach ( (array) $users as $user ) {
			foreach ( array( (string) $user->user_email, (string) get_user_meta( (int) $user->ID, 'billing_email', true ) ) as $email ) {
				if ( '' !== trim( $email ) ) {
					$mailboxes[ Subscriber_Repository::mailbox( $email ) ] = true;
				}
			}
		}
		set_transient(
			self::CACHE,
			array(
				'roles'     => $key,
				'mailboxes' => $mailboxes,
			),
			self::CACHE_TTL
		);
		return $mailboxes;
	}
}
