<?php
/**
 * Contact sync: bulk backfill in self-healing background batches.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Jobs;
use CFBE\Logger;
use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Syncs every user (then every guest customer) in short background batches, built to survive big lists and
 * shared hosting:
 *
 * - A batch stops after BATCH_SIZE contacts or TIME_BUDGET seconds (well below common PHP time limits) and saves
 *   its cursor after every contact, so a killed request loses at most one contact.
 * - Before working, a batch schedules a watchdog run of itself; if the request dies, the watchdog continues from
 *   the saved cursor. A finished batch moves on, so the old watchdog becomes a no-op.
 * - Temporary bluefox errors (429, 5xx, network) are retried with growing pauses instead of being counted; a
 *   rejected API key (401/403) or a long run of temporary errors pauses the run with the reason.
 * - Progress, the last error and the last ERRORS_KEEP failures live in `cfbe_sync_progress` for the live view.
 *
 * Each job carries the run ID and batch number, so a stale or duplicated job is a no-op.
 */
class Backfill {

	const HOOK = 'cfbe_sync_batch';

	const OPTION = 'cfbe_sync_progress';

	/**
	 * Most contacts per batch.
	 */
	const BATCH_SIZE = 50;

	/**
	 * Seconds of work per batch (filter: cfbe_sync_batch_seconds).
	 */
	const TIME_BUDGET = 20;

	/**
	 * Seconds between two batches.
	 */
	const BATCH_GAP = 5;

	/**
	 * Seconds after which the watchdog continues a batch that did not finish.
	 */
	const WATCHDOG = 180;

	/**
	 * Seconds without any activity after which a running sync counts as stalled.
	 */
	const STALL_AFTER = 300;

	/**
	 * Waits after temporary bluefox errors, in seconds (the last one repeats).
	 */
	const BACKOFF = array( 30, 60, 120, 300, 600 );

	/**
	 * Temporary errors in a row after which the run pauses.
	 */
	const MAX_RETRIES = 8;

	/**
	 * Failures kept for the live view.
	 */
	const ERRORS_KEEP = 20;

	/**
	 * Default pause between two contacts, in milliseconds (each contact costs one or two API calls).
	 */
	const CONTACT_PAUSE_MS = 250;

	/**
	 * Syncer.
	 *
	 * @var Contact_Syncer
	 */
	private $syncer;

	/**
	 * Email source.
	 *
	 * @var Contact_Source
	 */
	private $source;

	/**
	 * Background jobs.
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
	 * Pause between contacts in ms (null = filterable default).
	 *
	 * @var int|null
	 */
	private $pause_ms;

	/**
	 * Seconds of work per batch (null = filterable default).
	 *
	 * @var float|null
	 */
	private $budget;

	/**
	 * Constructor.
	 *
	 * @param Contact_Syncer $syncer   Syncer.
	 * @param Contact_Source $source   Email source.
	 * @param Jobs           $jobs     Background jobs.
	 * @param Logger         $logger   Logger.
	 * @param int|null       $pause_ms Pause between contacts in ms (tests pass 0).
	 * @param float|null     $budget   Seconds of work per batch (tests).
	 */
	public function __construct( Contact_Syncer $syncer, Contact_Source $source, Jobs $jobs, Logger $logger, ?int $pause_ms = null, ?float $budget = null ) {
		$this->syncer   = $syncer;
		$this->source   = $source;
		$this->jobs     = $jobs;
		$this->logger   = $logger;
		$this->pause_ms = $pause_ms;
		$this->budget   = $budget;
	}

	/**
	 * Current progress (status idle when never started).
	 */
	public static function progress(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge(
			array(
				// idle | running | waiting (after a temporary error) | paused | cancelled | done.
				'status'      => 'idle',
				'run_id'      => '',
				'batch'       => 0,
				'phase'       => 'users',
				'offset'      => 0,
				'page'        => 1,
				'page_pos'    => 0,
				'total'       => 0,
				'guest_total' => 0,
				'processed'   => 0,
				'counts'      => array(),
				'started'     => 0,
				'updated'     => 0,
				'finished'    => 0,
				'claimed_at'  => 0,
				'next_at'     => 0,
				'retries'     => 0,
				'reason'      => '',
				'last_error'  => array(),
				'errors'      => array(),
			),
			$stored
		);
	}

	/**
	 * Whether a backfill is running (also while it waits after a temporary error).
	 */
	public static function is_running(): bool {
		return in_array( self::progress()['status'], array( 'running', 'waiting' ), true );
	}

	/**
	 * Starts a backfill (no-op while one is running).
	 *
	 * @return bool Whether a run was started.
	 */
	public function start(): bool {
		if ( self::is_running() ) {
			return false;
		}
		$progress = array_merge(
			self::progress(),
			array(
				'status'      => 'running',
				'run_id'      => wp_generate_password( 12, false, false ),
				'batch'       => 1,
				'phase'       => 'users',
				'offset'      => 0,
				'page'        => 1,
				'page_pos'    => 0,
				'total'       => $this->source->user_count(),
				'guest_total' => Modules::woocommerce_active() ? $this->source->guest_order_count() : 0,
				'processed'   => 0,
				'counts'      => array(),
				'started'     => time(),
				'updated'     => time(),
				'finished'    => 0,
				'claimed_at'  => 0,
				'next_at'     => time(),
				'retries'     => 0,
				'reason'      => '',
				'last_error'  => array(),
				'errors'      => array(),
			)
		);
		self::save( $progress );
		$this->jobs->enqueue( self::HOOK, array( $progress['run_id'], 1 ) );
		$this->logger->info(
			'Contact sync: backfill started',
			array(
				'users'        => $progress['total'],
				'guest_orders' => $progress['guest_total'],
			)
		);
		return true;
	}

	/**
	 * Continues a running, waiting, paused or stalled backfill from its saved position.
	 *
	 * @return bool Whether it was resumed.
	 */
	public function resume(): bool {
		$progress = self::progress();
		if ( ! in_array( $progress['status'], array( 'running', 'waiting', 'paused' ), true ) || '' === $progress['run_id'] ) {
			return false;
		}
		$progress['status']     = 'running';
		$progress['claimed_at'] = 0;
		$progress['retries']    = 0;
		$progress['reason']     = '';
		$progress['next_at']    = time();
		self::save( $progress );
		$this->jobs->enqueue( self::HOOK, array( $progress['run_id'], (int) $progress['batch'] ) );
		$this->logger->info( 'Contact sync: backfill resumed', array( 'processed' => $progress['processed'] ) );
		return true;
	}

	/**
	 * Runs the due batch in this request when the background queue does not (called while the Contact sync tab is
	 * open), with a shorter time budget so the request stays well below PHP limits.
	 *
	 * @return bool Whether a batch ran.
	 */
	public function tick(): bool {
		$progress = self::progress();
		if ( ! in_array( self::health( $progress )['code'], array( 'stalled_queue', 'stalled_batch' ), true ) ) {
			return false;
		}
		$budget       = $this->budget;
		$this->budget = min( 10.0, $this->budget() );
		$this->run_batch( $progress['run_id'], (int) $progress['batch'] );
		$this->budget = $budget;
		return true;
	}

	/**
	 * Cancels a backfill; pending batch jobs become no-ops.
	 */
	public function cancel(): bool {
		$progress = self::progress();
		if ( ! in_array( $progress['status'], array( 'running', 'waiting', 'paused' ), true ) ) {
			return false;
		}
		$progress['status']   = 'cancelled';
		$progress['finished'] = time();
		$progress['next_at']  = 0;
		self::save( $progress );
		$this->jobs->cancel_hook( self::HOOK );
		return true;
	}

	/**
	 * Job callback: processes one batch and schedules the next.
	 *
	 * @param string $run_id Run ID.
	 * @param int    $batch  Batch number.
	 */
	public function run_batch( $run_id, $batch ): void {
		$progress = self::progress();
		if ( ! in_array( $progress['status'], array( 'running', 'waiting' ), true ) || (string) $run_id !== $progress['run_id'] || (int) $batch !== (int) $progress['batch'] ) {
			return;
		}
		$now = time();
		if ( 'waiting' === $progress['status'] && $now < (int) $progress['next_at'] - 5 ) {
			// A watchdog fired during the wait after a temporary error: keep the wait.
			$this->jobs->schedule( (int) $progress['next_at'] - $now, self::HOOK, array( $progress['run_id'], (int) $batch ) );
			return;
		}
		if ( (int) $progress['claimed_at'] > 0 && $now - (int) $progress['claimed_at'] < self::WATCHDOG - 10 ) {
			// This batch is being worked on right now (a duplicate or early run): check again later.
			$this->jobs->schedule( self::WATCHDOG, self::HOOK, array( $progress['run_id'], (int) $batch ) );
			return;
		}

		// Claim the batch and leave a watchdog behind in case this request dies.
		$progress['status']     = 'running';
		$progress['claimed_at'] = $now;
		$progress['next_at']    = $now + self::WATCHDOG;
		self::save( $progress );
		$this->jobs->schedule( self::WATCHDOG, self::HOOK, array( $progress['run_id'], (int) $batch ) );

		$deadline = microtime( true ) + $this->budget();
		$list     = $this->work_list( $progress );
		$done     = false;
		$handled  = 0;

		foreach ( $list['emails'] as $i => $email ) {
			if ( $i > 0 ) {
				$this->pause();
			}
			try {
				$result = $this->syncer->sync( (string) $email );
			} catch ( \Throwable $e ) {
				$result = array(
					'outcome' => Contact_Syncer::ERROR,
					'error'   => array(
						'code'    => 'exception',
						'status'  => 0,
						'message' => $e->getMessage(),
					),
				);
			}
			$outcome = (string) ( $result['outcome'] ?? Contact_Syncer::ERROR );
			$error   = is_array( $result['error'] ?? null ) ? $result['error'] : array();
			$kind    = Contact_Syncer::NOT_CONFIGURED === $outcome ? 'config' : self::error_kind( $error );

			if ( 'temporary' === $kind ) {
				$this->wait( $progress, $error, (string) $email );
				return;
			}
			if ( 'auth' === $kind || 'config' === $kind ) {
				$this->pause_run( $progress, 'auth' === $kind ? 'auth' : 'not_configured', $error, (string) $email );
				return;
			}

			// Done with this contact: count it and move the cursor before anything else can fail.
			$progress['counts'][ $outcome ] = (int) ( $progress['counts'][ $outcome ] ?? 0 ) + 1;
			++$progress['processed'];
			$progress['retries'] = 0;
			$progress['updated'] = time();
			if ( Contact_Syncer::ERROR === $outcome ) {
				$this->remember_error( $progress, $error, (string) $email );
			}
			$this->advance( $progress );
			self::save( $progress );
			++$handled;

			if ( microtime( true ) >= $deadline ) {
				break;
			}
		}

		if ( count( $list['emails'] ) === $handled ) {
			// The whole chunk is done (also when it was empty).
			if ( $list['last'] ) {
				$done = $this->phase_finished( $progress );
			} elseif ( 'guests' === $progress['phase'] ) {
				// The next batch reads the next page of guest orders.
				$progress['page']     = (int) $progress['page'] + 1;
				$progress['page_pos'] = 0;
			}
		}

		$progress['updated']    = time();
		$progress['claimed_at'] = 0;
		if ( $done ) {
			$progress['status']   = 'done';
			$progress['finished'] = time();
			$progress['next_at']  = 0;
			self::save( $progress );
			$this->logger->info( 'Contact sync: backfill finished', array( 'counts' => $progress['counts'] ) );
			return;
		}
		++$progress['batch'];
		$progress['next_at'] = time() + self::BATCH_GAP;
		self::save( $progress );
		$this->jobs->schedule( self::BATCH_GAP, self::HOOK, array( $progress['run_id'], (int) $progress['batch'] ) );
	}

	/**
	 * Emails still to do in the current chunk of the current phase.
	 *
	 * @param array $progress Progress.
	 * @return array{emails: string[], last: bool} last = this is the final chunk of the phase.
	 */
	private function work_list( array $progress ): array {
		if ( 'users' === $progress['phase'] ) {
			$emails = $this->source->user_emails( (int) $progress['offset'], self::BATCH_SIZE );
			return array(
				'emails' => array_values( $emails ),
				'last'   => count( $emails ) < self::BATCH_SIZE,
			);
		}
		$page = $this->source->guest_emails( (int) $progress['page'], self::BATCH_SIZE );
		return array(
			'emails' => array_slice( array_values( $page['emails'] ), (int) $progress['page_pos'] ),
			'last'   => (int) $page['fetched'] < self::BATCH_SIZE,
		);
	}

	/**
	 * Moves the cursor past the contact just done.
	 *
	 * @param array $progress Progress (changed).
	 */
	private function advance( array &$progress ): void {
		if ( 'users' === $progress['phase'] ) {
			++$progress['offset'];
		} else {
			++$progress['page_pos'];
		}
	}

	/**
	 * The last chunk of a phase is done: users → guests (with WooCommerce), guests → finished.
	 *
	 * @param array $progress Progress (changed).
	 * @return bool Whether the whole backfill is finished.
	 */
	private function phase_finished( array &$progress ): bool {
		if ( 'users' === $progress['phase'] && Modules::woocommerce_active() ) {
			$progress['phase']    = 'guests';
			$progress['page']     = 1;
			$progress['page_pos'] = 0;
			return false;
		}
		return true;
	}

	/**
	 * A temporary bluefox error: wait and retry the same contact, or pause after too many in a row.
	 *
	 * @param array  $progress Progress.
	 * @param array  $error    Error details.
	 * @param string $email    Contact.
	 */
	private function wait( array $progress, array $error, string $email ): void {
		++$progress['retries'];
		$progress['last_error'] = self::error_entry( $error, $email );
		$progress['claimed_at'] = 0;
		$progress['updated']    = time();
		if ( $progress['retries'] >= self::MAX_RETRIES ) {
			$this->pause_run( $progress, 'unavailable', $error, $email );
			return;
		}
		$delay               = self::BACKOFF[ min( $progress['retries'], count( self::BACKOFF ) ) - 1 ];
		$progress['status']  = 'waiting';
		$progress['next_at'] = time() + $delay;
		self::save( $progress );
		$this->jobs->schedule( $delay, self::HOOK, array( $progress['run_id'], (int) $progress['batch'] ) );
		$this->logger->warning(
			'Contact sync: bluefox temporarily unavailable, waiting',
			array(
				'seconds' => $delay,
				'error'   => $progress['last_error']['message'],
			)
		);
	}

	/**
	 * Pauses the run with a reason; Resume continues it.
	 *
	 * @param array  $progress Progress.
	 * @param string $reason   auth | not_configured | unavailable.
	 * @param array  $error    Error details.
	 * @param string $email    Contact.
	 */
	private function pause_run( array $progress, string $reason, array $error, string $email ): void {
		$progress['status']     = 'paused';
		$progress['reason']     = $reason;
		$progress['last_error'] = self::error_entry( $error, $email );
		$progress['claimed_at'] = 0;
		$progress['next_at']    = 0;
		$progress['updated']    = time();
		self::save( $progress );
		$this->jobs->cancel_hook( self::HOOK );
		$this->logger->error(
			'Contact sync: backfill paused',
			array(
				'reason' => $reason,
				'error'  => $progress['last_error']['message'],
			)
		);
	}

	/**
	 * Keeps the failure for the live view (newest first, ERRORS_KEEP at most).
	 *
	 * @param array  $progress Progress (changed).
	 * @param array  $error    Error details.
	 * @param string $email    Contact.
	 */
	private function remember_error( array &$progress, array $error, string $email ): void {
		$entry                  = self::error_entry( $error, $email );
		$progress['last_error'] = $entry;
		array_unshift( $progress['errors'], $entry );
		$progress['errors'] = array_slice( $progress['errors'], 0, self::ERRORS_KEEP );
	}

	/**
	 * One stored error: time, masked email, HTTP status, code and message.
	 *
	 * @param array  $error Error details.
	 * @param string $email Contact.
	 */
	private static function error_entry( array $error, string $email ): array {
		return array(
			'time'    => time(),
			'email'   => self::mask( $email ),
			'status'  => (int) ( $error['status'] ?? 0 ),
			'code'    => substr( (string) ( $error['code'] ?? '' ), 0, 60 ),
			'message' => substr( (string) ( $error['message'] ?? '' ), 0, 300 ),
		);
	}

	/**
	 * Whether an error is temporary (retry), an auth problem (pause) or about this contact only (count, go on).
	 *
	 * @param array $error Error details (empty = no error).
	 * @return string none | temporary | auth | contact
	 */
	public static function error_kind( array $error ): string {
		if ( array() === $error ) {
			return 'none';
		}
		$status = (int) ( $error['status'] ?? 0 );
		$code   = (string) ( $error['code'] ?? '' );
		if ( 401 === $status || 403 === $status ) {
			return 'auth';
		}
		if ( 429 === $status || $status >= 500 || 'http_request_failed' === $code || 'cfbe_bluefox_invalid_json' === $code ) {
			return 'temporary';
		}
		return 'contact';
	}

	/**
	 * Masks an email for the live view and logs: a***@example.com.
	 *
	 * @param string $email Email.
	 */
	public static function mask( string $email ): string {
		$at = strpos( $email, '@' );
		if ( false === $at ) {
			return '***';
		}
		return substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * Health of a running sync: ok, or why it is stuck.
	 *
	 * @param array    $progress Progress.
	 * @param int|null $now      Current time (tests).
	 * @return array{code: string, minutes: int} code: ok | waiting | paused | stalled_queue | stalled_batch
	 */
	public static function health( array $progress, ?int $now = null ): array {
		$now     = null === $now ? time() : $now;
		$quiet   = $now - max( (int) $progress['updated'], (int) $progress['claimed_at'] );
		$minutes = (int) floor( $quiet / 60 );
		switch ( $progress['status'] ) {
			case 'paused':
				return array(
					'code'    => 'paused',
					'minutes' => $minutes,
				);
			case 'waiting':
				return array(
					'code'    => $now > (int) $progress['next_at'] + self::STALL_AFTER ? 'stalled_queue' : 'waiting',
					'minutes' => $minutes,
				);
			case 'running':
				if ( $quiet < self::STALL_AFTER ) {
					return array(
						'code'    => 'ok',
						'minutes' => $minutes,
					);
				}
				// A claimed batch that never saved a contact was killed (time limit, fatal error); otherwise the
				// next scheduled run never came (the background queue / WP-Cron does not run).
				return array(
					'code'    => (int) $progress['claimed_at'] > (int) $progress['updated'] ? 'stalled_batch' : 'stalled_queue',
					'minutes' => $minutes,
				);
			default:
				return array(
					'code'    => 'ok',
					'minutes' => 0,
				);
		}
	}

	/**
	 * Seconds of work per batch.
	 */
	private function budget(): float {
		return null !== $this->budget ? $this->budget : max( 5.0, (float) apply_filters( 'cfbe_sync_batch_seconds', self::TIME_BUDGET ) );
	}

	/**
	 * Waits between two contacts.
	 */
	private function pause(): void {
		$ms = null !== $this->pause_ms ? $this->pause_ms : (int) apply_filters( 'cfbe_sync_contact_pause_ms', self::CONTACT_PAUSE_MS );
		if ( $ms > 0 ) {
			usleep( $ms * 1000 );
		}
	}

	/**
	 * Stores the progress (not autoloaded).
	 *
	 * @param array $progress Progress.
	 */
	private static function save( array $progress ): void {
		update_option( self::OPTION, $progress, false );
	}
}
