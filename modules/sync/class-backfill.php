<?php
/**
 * Contact sync: bulk backfill in chained background batches.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Jobs;
use CFBE\Logger;
use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Syncs every user (then every guest customer) in batches of 50; each batch schedules the next one
 * a minute later to stay well below the bluefox rate limits. Progress lives in `cfbe_sync_progress`.
 * Each job carries the run ID and batch number, so a stale or duplicated job is a no-op.
 */
class Backfill {

	const HOOK = 'cfbe_sync_batch';

	const OPTION = 'cfbe_sync_progress';

	const BATCH_SIZE = 50;

	/**
	 * Seconds between batches.
	 */
	const BATCH_GAP = 60;

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
	 * Constructor.
	 *
	 * @param Contact_Syncer $syncer   Syncer.
	 * @param Contact_Source $source   Email source.
	 * @param Jobs           $jobs     Background jobs.
	 * @param Logger         $logger   Logger.
	 * @param int|null       $pause_ms Pause between contacts in ms (tests pass 0).
	 */
	public function __construct( Contact_Syncer $syncer, Contact_Source $source, Jobs $jobs, Logger $logger, ?int $pause_ms = null ) {
		$this->syncer   = $syncer;
		$this->source   = $source;
		$this->jobs     = $jobs;
		$this->logger   = $logger;
		$this->pause_ms = $pause_ms;
	}

	/**
	 * Current progress (status idle when never started).
	 */
	public static function progress(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge(
			array(
				'status'    => 'idle',
				'run_id'    => '',
				'batch'     => 0,
				'phase'     => 'users',
				'offset'    => 0,
				'page'      => 1,
				'total'     => 0,
				'processed' => 0,
				'counts'    => array(),
				'started'   => 0,
				'updated'   => 0,
				'finished'  => 0,
			),
			$stored
		);
	}

	/**
	 * Whether a backfill is running.
	 */
	public static function is_running(): bool {
		return 'running' === self::progress()['status'];
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
		$progress = array(
			'status'    => 'running',
			'run_id'    => wp_generate_password( 12, false, false ),
			'batch'     => 1,
			'phase'     => 'users',
			'offset'    => 0,
			'page'      => 1,
			'total'     => $this->source->user_count(),
			'processed' => 0,
			'counts'    => array(),
			'started'   => time(),
			'updated'   => time(),
			'finished'  => 0,
		);
		self::save( $progress );
		$this->jobs->enqueue( self::HOOK, array( $progress['run_id'], 1 ) );
		$this->logger->info( 'Contact sync: backfill started', array( 'users' => $progress['total'] ) );
		return true;
	}

	/**
	 * Cancels a running backfill; pending batch jobs become no-ops.
	 */
	public function cancel(): bool {
		$progress = self::progress();
		if ( 'running' !== $progress['status'] ) {
			return false;
		}
		$progress['status']   = 'cancelled';
		$progress['finished'] = time();
		self::save( $progress );
		( new \CFBE\Jobs() )->cancel_hook( self::HOOK );
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
		if ( 'running' !== $progress['status'] || (string) $run_id !== $progress['run_id'] || (int) $batch !== (int) $progress['batch'] ) {
			return;
		}

		$emails = array();
		$done   = false;
		if ( 'users' === $progress['phase'] ) {
			$emails              = $this->source->user_emails( (int) $progress['offset'], self::BATCH_SIZE );
			$progress['offset'] += count( $emails );
			if ( count( $emails ) < self::BATCH_SIZE ) {
				if ( Modules::woocommerce_active() ) {
					$progress['phase'] = 'guests';
					$progress['page']  = 1;
				} else {
					$done = true;
				}
			}
		} else {
			$page             = $this->source->guest_emails( (int) $progress['page'], self::BATCH_SIZE );
			$emails           = $page['emails'];
			$progress['page'] = (int) $progress['page'] + 1;
			$done             = $page['fetched'] < self::BATCH_SIZE;
		}

		foreach ( $emails as $i => $email ) {
			if ( $i > 0 ) {
				$this->pause();
			}
			try {
				$outcome = $this->syncer->sync( (string) $email )['outcome'];
			} catch ( \Throwable $e ) {
				$outcome = Contact_Syncer::ERROR;
				$this->logger->error( 'Contact sync: backfill item failed', array( 'error' => $e->getMessage() ) );
			}
			$progress['counts'][ $outcome ] = (int) ( $progress['counts'][ $outcome ] ?? 0 ) + 1;
			++$progress['processed'];
		}

		$progress['updated'] = time();
		if ( $done ) {
			$progress['status']   = 'done';
			$progress['finished'] = time();
			$this->logger->info( 'Contact sync: backfill finished', array( 'counts' => $progress['counts'] ) );
		} else {
			++$progress['batch'];
		}
		self::save( $progress );

		if ( ! $done ) {
			$this->jobs->schedule( self::BATCH_GAP, self::HOOK, array( $progress['run_id'], $progress['batch'] ) );
		}
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
