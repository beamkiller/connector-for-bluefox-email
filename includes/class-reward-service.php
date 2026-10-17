<?php
/**
 * Welcome coupon reward pipeline.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a confirmed subscriber into exactly one welcome coupon + coupon email.
 * Triggers (webhook, WP signal, poller) only enqueue `cfbe_reward`; all logic is in reward().
 */
class Reward_Service {

	const JOB           = 'cfbe_reward';
	const POLL          = 'cfbe_poll_confirmations';
	const USED          = 'cfbe_mark_coupon_used';
	const MAX_ATTEMPTS  = 5;
	const RETRY_DELAY   = 600;
	const POLL_INTERVAL = 900;
	const BATCH         = 25;

	/**
	 * Client (null when not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Coupon factory.
	 *
	 * @var Coupon_Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null   $client     Client.
	 * @param Subscriber_Repository $repository Repository.
	 * @param Jobs                  $jobs       Jobs.
	 * @param Logger                $logger     Logger.
	 * @param Coupon_Factory        $factory    Coupon factory.
	 */
	public function __construct( ?Bluefox_Client $client, Subscriber_Repository $repository, Jobs $jobs, Logger $logger, Coupon_Factory $factory ) {
		$this->client     = $client;
		$this->repository = $repository;
		$this->jobs       = $jobs;
		$this->logger     = $logger;
		$this->factory    = $factory;
	}

	/**
	 * Hooks jobs, triggers and order usage tracking.
	 */
	public function register(): void {
		add_action( self::JOB, array( $this, 'reward' ), 10, 1 );
		add_action( self::POLL, array( $this, 'poll' ) );
		add_action( self::USED, array( $this, 'mark_used' ), 10, 4 );
		// Same statuses as WooCommerce's own coupon usage count (wc_update_coupon_usage_counts).
		foreach ( array( 'pending', 'on-hold', 'processing', 'completed', 'cancelled' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'on_order_paid' ), 10, 2 );
		}
		add_action( 'admin_init', array( $this, 'schedule_poll' ) );
	}

	/**
	 * Schedules the recurring poller (idempotent).
	 */
	public function schedule_poll(): void {
		$this->jobs->schedule_recurring( self::POLL, self::POLL_INTERVAL );
	}

	/**
	 * Queues a reward check for an email.
	 *
	 * @param string $email Email address.
	 */
	public function enqueue( $email ): void {
		$this->jobs->enqueue( self::JOB, array( 'email' => Subscriber_Repository::normalize_email( (string) $email ) ) );
	}

	/**
	 * Reward job: verify confirmation, lock, create (or reuse) the coupon, email it.
	 *
	 * @param string $email Email address.
	 */
	public function reward( $email ): void {
		$email = Subscriber_Repository::normalize_email( (string) $email );

		$row = $this->repository->find_by_email( $email );
		if ( ! $row || ! in_array( $row['status'], array( Subscriber_Repository::STATUS_PENDING, Subscriber_Repository::STATUS_CONFIRMED ), true ) ) {
			return; // Unknown (import / foreign form), existing, already rewarded, expired or failed.
		}

		$list_id = Subscription_Service::list_id( (string) ( $row['list_id'] ?? '' ) );
		if ( null === $this->client || '' === $list_id ) {
			$this->logger->error( 'Reward skipped: bluefox is not configured', array( 'email' => $email ) );
			return;
		}

		$subscriber = $this->client->get_subscriber( $list_id, $email );
		if ( is_wp_error( $subscriber ) ) {
			$this->retry_later( $email, (int) $row['attempts'], 'bluefox: ' . $subscriber->get_error_message(), false );
			return;
		}
		if ( 'active' !== ( $subscriber['status'] ?? '' ) ) {
			$this->repository->touch( $email ); // Not confirmed yet; the poller checks again.
			return;
		}

		if ( Subscriber_Repository::STATUS_PENDING === $row['status'] && $this->repository->mark_confirmed( $email ) ) {
			/**
			 * Fires once when a signup made through this plugin is confirmed (double opt-in done, contact active).
			 *
			 * @param string $email   Email address.
			 * @param string $list_id bluefox list ID.
			 * @param array  $row     Local signup row.
			 */
			do_action( 'cfbe_signup_confirmed', $email, $list_id, $row );
		}

		if ( ! self::coupon_enabled_for( $list_id ) ) {
			return;
		}

		if ( Settings::get( 'coupon', 'one_per_mailbox' ) && $this->repository->mailbox_rewarded( $email ) ) {
			$this->repository->skip_reward( $email, 'Another address of this mailbox already received a welcome coupon.' );
			$this->logger->info( 'Welcome coupon skipped: alias of a rewarded address', array( 'email' => $email ) );
			return;
		}

		if ( ! $this->repository->claim_for_reward( $email ) ) {
			return; // Another worker owns it.
		}

		$coupon_id = $this->factory->find_existing( $email );
		if ( 0 === $coupon_id ) {
			$created = $this->factory->create( $email, (string) $row['source'], Settings::get( 'coupon' ) );
			if ( is_wp_error( $created ) ) {
				$this->retry_later( $email, (int) $row['attempts'], $created->get_error_message(), true );
				return;
			}
			$coupon_id = (int) $created;
		}
		$this->repository->set_coupon( $email, $coupon_id );

		$sent = $this->send_coupon_email( $email, $coupon_id, (string) $row['name'] );
		if ( is_wp_error( $sent ) ) {
			$this->retry_later( $email, (int) $row['attempts'], $sent->get_error_message(), true );
			return;
		}

		$this->sync_contact_fields( $email, $coupon_id );
		$this->logger->info(
			'Welcome coupon sent',
			array(
				'email'     => $email,
				'coupon_id' => $coupon_id,
			)
		);
	}

	/**
	 * Whether confirmed signups on this list get a welcome coupon (module on, WooCommerce active, list matches).
	 *
	 * @param string $list_id bluefox list ID.
	 */
	public static function coupon_enabled_for( string $list_id ): bool {
		if ( ! Modules::enabled( 'coupon' ) ) {
			return false;
		}
		$coupon_list = (string) Settings::get( 'coupon', 'list_id' );
		return '' === $coupon_list || $coupon_list === $list_id;
	}

	/**
	 * Transactional email ID of the coupon email in bluefox (Coupon tab; before 1.0 it was stored with the emails).
	 */
	public static function coupon_email_id(): string {
		$id = (string) Settings::get( 'coupon', 'email_id' );
		if ( '' !== $id ) {
			return $id;
		}
		$legacy = get_option( Settings::option_name( 'emails' ), array() );
		return is_array( $legacy ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $legacy['coupon_email_id'] ?? '' ) ) : '';
	}

	/**
	 * Re-sends the coupon email of a rewarded subscriber (admin / WP-CLI).
	 *
	 * @param string $email Email address.
	 * @return true|\WP_Error
	 */
	public function resend( string $email ) {
		$row = $this->repository->find_by_email( $email );
		if ( ! $row || empty( $row['coupon_id'] ) ) {
			return new \WP_Error( 'cfbe_no_coupon', __( 'No coupon has been created for this email address yet.', 'connector-for-bluefox-email' ) );
		}
		return $this->send_coupon_email( (string) $row['email'], (int) $row['coupon_id'], (string) $row['name'] );
	}

	/**
	 * Poller: expire stale pending rows, enqueue rewards for confirmed ones.
	 *
	 * @return int Number of rewards enqueued.
	 */
	public function poll(): int {
		$days = Settings::pending_expiry_days();
		$this->repository->expire_older_than( $days );

		if ( null === $this->client ) {
			return 0;
		}

		$queued = 0;
		foreach ( $this->repository->pending_batch( self::BATCH, $days ) as $row ) {
			$list_id = Subscription_Service::list_id( (string) ( $row['list_id'] ?? '' ) );
			if ( '' === $list_id ) {
				continue;
			}
			$subscriber = $this->client->get_subscriber( $list_id, (string) $row['email'] );
			if ( ! is_wp_error( $subscriber ) && 'active' === ( $subscriber['status'] ?? '' ) ) {
				$this->enqueue( (string) $row['email'] );
				++$queued;
			} else {
				$this->repository->touch( (string) $row['email'] );
			}
		}
		return $queued;
	}

	/**
	 * Order status change: mirror WooCommerce's coupon usage in bluefox couponUsed. WooCommerce counts the coupon
	 * once the order is placed (pending / on-hold / processing / completed) and gives it back when the order is
	 * cancelled, so a cancelled order sets couponUsed back to false (the expiry reminder can go out again).
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order (passed by the status hooks; loaded with wc_get_order() otherwise).
	 */
	public function on_order_paid( $order_id, $order = null ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_coupon_codes' ) ) {
			$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		}
		if ( ! $order ) {
			return;
		}
		$used = ! $order->has_status( 'cancelled' );
		foreach ( (array) $order->get_coupon_codes() as $code ) {
			$coupon_id = (int) wc_get_coupon_id_by_code( (string) $code );
			$email     = $coupon_id ? $this->factory->welcome_email( $coupon_id ) : '';
			if ( '' === $email || $this->factory->used_synced( $coupon_id ) === $used ) {
				continue; // Not a welcome coupon, or bluefox already has this value.
			}
			$this->jobs->enqueue(
				self::USED,
				array(
					'email'     => $email,
					'coupon_id' => $coupon_id,
					'attempt'   => 1,
					'used'      => $used,
				)
			);
		}
	}

	/**
	 * Job: sets couponUsed on the bluefox contact (true when an order uses the coupon, false when it is cancelled).
	 *
	 * @param string $email     Email address.
	 * @param int    $coupon_id Coupon ID.
	 * @param int    $attempt   Attempt (1-based).
	 * @param bool   $used      Value to write (jobs queued before 0.10.3 have no flag: true).
	 */
	public function mark_used( $email, $coupon_id, $attempt = 1, $used = true ): void {
		if ( empty( Settings::get( 'coupon', 'store_code_in_bluefox' ) ) || null === $this->client ) {
			return;
		}
		$used   = (bool) $used;
		$result = $this->client->update_contact( (string) $email, array( 'couponUsed' => $used ) );
		if ( ! is_wp_error( $result ) ) {
			$this->factory->set_used_synced( (int) $coupon_id, $used );
			return;
		}
		if ( (int) $attempt < 3 ) {
			$this->jobs->schedule(
				self::RETRY_DELAY,
				self::USED,
				array(
					'email'     => (string) $email,
					'coupon_id' => (int) $coupon_id,
					'attempt'   => (int) $attempt + 1,
					'used'      => $used,
				)
			);
		} else {
			$this->logger->warning( 'couponUsed sync failed', array( 'email' => (string) $email ) );
		}
	}

	/**
	 * Sends the coupon transactional email.
	 *
	 * @param string $email     Email address.
	 * @param int    $coupon_id Coupon ID.
	 * @param string $name      Subscriber name.
	 * @return true|\WP_Error
	 */
	private function send_coupon_email( string $email, int $coupon_id, string $name ) {
		$email_id = self::coupon_email_id();
		if ( '' === $email_id ) {
			return new \WP_Error( 'cfbe_no_coupon_email', 'No coupon email configured (Coupon tab).' );
		}
		if ( null === $this->client ) {
			return new \WP_Error( 'cfbe_not_configured', 'bluefox is not configured.' );
		}
		$data   = Coupon_Data::email_data( $this->factory->details( $coupon_id ), $name );
		$result = $this->client->send_transactional( $email_id, $email, $data );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Writes couponCode / couponExpires / couponUsed to the bluefox contact (best effort).
	 *
	 * @param string $email     Email address.
	 * @param int    $coupon_id Coupon ID.
	 */
	private function sync_contact_fields( string $email, int $coupon_id ): void {
		if ( empty( Settings::get( 'coupon', 'store_code_in_bluefox' ) ) ) {
			return;
		}
		$result = $this->client->update_contact( $email, Coupon_Data::contact_fields( $this->factory->details( $coupon_id ) ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->warning(
				'Coupon fields not stored in bluefox',
				array(
					'email' => $email,
					'error' => $result->get_error_code(),
				)
			);
		}
	}

	/**
	 * Records a failure and retries in 10 minutes, or gives up after MAX_ATTEMPTS.
	 *
	 * @param string $email    Email address.
	 * @param int    $attempts Attempts so far.
	 * @param string $error    Error message.
	 * @param bool   $claimed  Whether the reward lock is held (then it is released).
	 */
	private function retry_later( string $email, int $attempts, string $error, bool $claimed ): void {
		if ( $claimed ) {
			$this->repository->release_claim( $email, $error );
		} else {
			$this->repository->record_attempt( $email, $error );
		}

		if ( $attempts + 1 >= self::MAX_ATTEMPTS ) {
			$this->repository->mark_failed( $email, $error );
			$this->logger->error(
				'Welcome coupon failed after retries',
				array(
					'email' => $email,
					'error' => $error,
				)
			);
			return;
		}

		$this->jobs->schedule( self::RETRY_DELAY, self::JOB, array( 'email' => $email ) );
		$this->logger->warning(
			'Welcome coupon retry scheduled',
			array(
				'email' => $email,
				'error' => $error,
			)
		);
	}
}
