<?php
/**
 * “Your coupon expires soon” reminder.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * An hourly job finds unused welcome coupons that expire within the Coupon tab's “Reminder before expiry (days)”
 * and sends each one the bluefox triggered (marketing) email, once. bluefox only delivers triggered emails to active
 * subscribers and adds the unsubscribe link, so unsubscribed or paused readers never get it. Emails go out in the
 * daytime only (site time).
 */
class Coupon_Reminder {

	const HOOK       = 'cfbe_coupon_reminders';
	const INTERVAL   = HOUR_IN_SECONDS;
	const BATCH      = 25;
	const SENT_META  = '_cfbe_reminder_sent';
	const TRIES_META = '_cfbe_reminder_tries';
	const MAX_TRIES  = 3;
	const FROM_HOUR  = 9; // Site time: no reminders at night.
	const UNTIL_HOUR = 20;

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
	 * Coupon factory (coupon details).
	 *
	 * @var Coupon_Factory
	 */
	private $factory;

	/**
	 * Finds due coupon IDs: fn( int $now, int $until, int $limit ): int[] (overridable in tests).
	 *
	 * @var callable
	 */
	private $finder;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null   $client     Client (null when not configured).
	 * @param Subscriber_Repository $repository Repository.
	 * @param Jobs                  $jobs       Jobs.
	 * @param Logger                $logger     Logger.
	 * @param Coupon_Factory        $factory    Coupon factory.
	 * @param callable|null         $finder     Due coupon finder.
	 */
	public function __construct( ?Bluefox_Client $client, Subscriber_Repository $repository, Jobs $jobs, Logger $logger, Coupon_Factory $factory, ?callable $finder = null ) {
		$this->client     = $client;
		$this->repository = $repository;
		$this->jobs       = $jobs;
		$this->logger     = $logger;
		$this->factory    = $factory;
		$this->finder     = $finder ?? array( $this, 'find_due' );
	}

	/**
	 * Hooks the job.
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
	}

	/**
	 * Schedules the hourly job (idempotent).
	 */
	public function schedule(): void {
		$this->jobs->schedule_recurring( self::HOOK, self::INTERVAL );
	}

	/**
	 * Whether reminders are configured: a reminder email is set and bluefox is connected.
	 */
	public function enabled(): bool {
		return null !== $this->client && '' !== (string) Settings::get( 'coupon', 'reminder_email_id' );
	}

	/**
	 * Job: sends the due reminders (only in the daytime unless forced).
	 *
	 * @param bool     $force Ignore the daytime window (WP-CLI).
	 * @param int|null $now   Unix time (tests).
	 * @return int Reminders sent.
	 */
	public function run( $force = false, ?int $now = null ): int {
		if ( ! $this->enabled() ) {
			return 0;
		}
		$now = $now ?? time();
		if ( true !== $force && ! self::daytime( $now ) ) {
			return 0;
		}
		$days = max( 1, (int) Settings::get( 'coupon', 'reminder_days' ) );
		$sent = 0;
		// Calendar days on the site's clock: a coupon is due once its last day is at most $days days away.
		$until = Dates::end_of_day_in( $days, $now );
		foreach ( (array) call_user_func( $this->finder, $now, $until, self::BATCH ) as $coupon_id ) {
			if ( $this->remind( (int) $coupon_id, $now ) ) {
				++$sent;
			}
		}
		return $sent;
	}

	/**
	 * Sends the reminder of one coupon.
	 *
	 * @param int $coupon_id Coupon ID.
	 * @param int $now       Unix time.
	 * @return bool Whether it was sent.
	 */
	public function remind( int $coupon_id, int $now ): bool {
		$email = Subscriber_Repository::normalize_email( (string) get_post_meta( $coupon_id, Coupon_Factory::META, true ) );
		if ( '' === $email || '' !== (string) get_post_meta( $coupon_id, self::SENT_META, true ) ) {
			return false;
		}
		$details = $this->factory->details( $coupon_id );
		if ( empty( $details['expires'] ) || $details['expires'] <= $now ) {
			return false;
		}
		$row              = $this->repository->find_by_email( $email );
		$data             = Coupon_Data::email_data( $details, $row ? (string) $row['name'] : '' );
		$data['daysLeft'] = self::days_left( (int) $details['expires'], $now );

		$result = $this->client->send_triggered( (string) Settings::get( 'coupon', 'reminder_email_id' ), array( $email ), $data );
		if ( is_wp_error( $result ) ) {
			$tries = (int) get_post_meta( $coupon_id, self::TRIES_META, true ) + 1;
			update_post_meta( $coupon_id, self::TRIES_META, $tries );
			if ( $tries >= self::MAX_TRIES ) {
				update_post_meta( $coupon_id, self::SENT_META, 'failed' ); // Stop retrying this coupon.
			}
			$this->logger->warning(
				'Coupon reminder failed',
				array(
					'email'     => $email,
					'coupon_id' => $coupon_id,
					'try'       => $tries,
					'error'     => $result->get_error_message(),
				)
			);
			return false;
		}

		update_post_meta( $coupon_id, self::SENT_META, (string) $now );
		$this->logger->info(
			'Coupon reminder sent',
			array(
				'email'     => $email,
				'coupon_id' => $coupon_id,
			)
		);
		return true;
	}

	/**
	 * Unused welcome coupons expiring in (now, until] without a reminder yet, soonest first.
	 *
	 * @param int $now   Unix time.
	 * @param int $until Unix time.
	 * @param int $limit Max coupons.
	 * @return int[]
	 */
	public function find_due( int $now, int $until, int $limit ): array {
		$ids = get_posts(
			array(
				'post_type'        => 'shop_coupon',
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'posts_per_page'   => $limit,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'meta_value_num',
				'meta_key'         => 'date_expires', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'order'            => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Hourly background job, small batch.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => Coupon_Factory::META,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'date_expires',
						'value'   => array( $now + 1, $until ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => self::SENT_META,
						'compare' => 'NOT EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => 'usage_count',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'usage_count',
							'value'   => 0,
							'compare' => '=',
							'type'    => 'NUMERIC',
						),
					),
				),
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Calendar days from today to the coupon's last day, on the site's clock (at least 1): a coupon that expires at
	 * the end of the 21st has 3 days left on the 18th, whatever the hour.
	 *
	 * @param int $expires Expiry (Unix time).
	 * @param int $now     Unix time.
	 */
	public static function days_left( int $expires, int $now ): int {
		$tz    = Dates::site_timezone();
		$today = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( 0, 0 );
		$last  = ( new \DateTimeImmutable( '@' . $expires ) )->setTimezone( $tz )->setTime( 0, 0 );
		return max( 1, (int) $today->diff( $last )->format( '%r%a' ) );
	}

	/**
	 * Whether it is daytime on the site's clock.
	 *
	 * @param int $now Unix time.
	 */
	public static function daytime( int $now ): bool {
		$hour = (int) ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( Dates::site_timezone() )->format( 'G' );
		return $hour >= self::FROM_HOUR && $hour < self::UNTIL_HOUR;
	}
}
