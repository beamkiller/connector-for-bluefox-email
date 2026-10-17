<?php
/**
 * Contact sync: hooks that queue a sync of one email.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Turns user / order / signup events into one deferred sync job per email.
 * A short transient deduplicates: many hooks in one request (or in quick succession) create one job,
 * and since the job reads fresh data when it runs, nothing is lost.
 */
class Triggers {

	const JOB = 'cfbe_sync_contact';

	/**
	 * Seconds until the queued job runs.
	 */
	const DELAY = 30;

	/**
	 * Dedup window; shorter than DELAY so a change after the window always gets a job that runs after it.
	 */
	const LOCK_TTL = 25;

	/**
	 * Background jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Emails queued during this request.
	 *
	 * @var array<string, bool>
	 */
	private $queued = array();

	/**
	 * Constructor.
	 *
	 * @param Jobs $jobs Background jobs.
	 */
	public function __construct( Jobs $jobs ) {
		$this->jobs = $jobs;
	}

	/**
	 * Hooks the triggers.
	 */
	public function register(): void {
		add_action( 'user_register', array( $this, 'on_user' ) );
		add_action( 'profile_update', array( $this, 'on_user' ) );
		add_action( 'woocommerce_customer_save_address', array( $this, 'on_user' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order' ) );
		add_action( 'cfbe_signup_confirmed', array( $this, 'on_signup_confirmed' ) );
	}

	/**
	 * User created / updated / address saved.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		if ( is_object( $user ) && ! empty( $user->user_email ) ) {
			$this->queue( (string) $user->user_email );
		}
	}

	/**
	 * Order paid / completed / refunded.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order( $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( (int) $order_id );
		if ( is_object( $order ) && is_callable( array( $order, 'get_billing_email' ) ) ) {
			$this->queue( (string) $order->get_billing_email() );
		}
	}

	/**
	 * Signup confirmed (double opt-in done): the contact exists now, so enrich it.
	 *
	 * @param string $email Email address.
	 */
	public function on_signup_confirmed( $email ): void {
		$this->queue( (string) $email );
	}

	/**
	 * Queues a sync job for an email unless one is already pending.
	 *
	 * @param string $email Email address.
	 * @return bool Whether a job was queued.
	 */
	public function queue( string $email ): bool {
		$email = strtolower( trim( $email ) );
		if ( '' === $email || ! is_email( $email ) || isset( $this->queued[ $email ] ) ) {
			return false;
		}
		$this->queued[ $email ] = true;

		$lock = 'cfbe_sync_q_' . md5( $email );
		if ( false !== get_transient( $lock ) ) {
			return false;
		}
		set_transient( $lock, 1, self::LOCK_TTL );
		return $this->jobs->schedule( self::DELAY, self::JOB, array( $email ) ) > 0;
	}
}
