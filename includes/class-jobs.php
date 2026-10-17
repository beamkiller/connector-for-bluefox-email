<?php
/**
 * Background jobs: Action Scheduler when available, WP-Cron otherwise.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Background jobs in group `cfbe`. Action Scheduler (bundled with WooCommerce and many other plugins) is used
 * when it is loaded; otherwise the jobs run on WP-Cron. Callbacks receive the args positionally either way.
 */
class Jobs {

	const GROUP = 'cfbe';

	const CRON_PREFIX = 'cfbe_every_';

	/**
	 * Whether Action Scheduler is loaded (overridable for tests).
	 */
	public static function action_scheduler(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * Queues an action to run as soon as possible.
	 *
	 * @param string $hook Action hook.
	 * @param array  $args Action arguments.
	 * @return int Action ID (1 for WP-Cron, 0 when nothing could be scheduled).
	 */
	public function enqueue( string $hook, array $args = array() ): int {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			return (int) as_enqueue_async_action( $hook, $args, self::GROUP );
		}
		if ( self::action_scheduler() ) {
			return (int) as_schedule_single_action( time(), $hook, $args, self::GROUP );
		}
		return $this->cron_single( time(), $hook, $args );
	}

	/**
	 * Queues an action to run after a delay.
	 *
	 * @param int    $delay Seconds from now.
	 * @param string $hook  Action hook.
	 * @param array  $args  Action arguments.
	 * @return int Action ID (1 for WP-Cron, 0 when nothing could be scheduled).
	 */
	public function schedule( int $delay, string $hook, array $args = array() ): int {
		if ( self::action_scheduler() ) {
			return (int) as_schedule_single_action( time() + $delay, $hook, $args, self::GROUP );
		}
		return $this->cron_single( time() + $delay, $hook, $args );
	}

	/**
	 * Schedules a recurring action once; repeated calls are no-ops.
	 *
	 * @param string $hook     Action hook.
	 * @param int    $interval Interval in seconds.
	 * @param array  $args     Action arguments.
	 * @return bool True if a new schedule was created.
	 */
	public function schedule_recurring( string $hook, int $interval, array $args = array() ): bool {
		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			if ( false !== as_next_scheduled_action( $hook, $args, self::GROUP ) ) {
				return false;
			}
			as_schedule_recurring_action( time() + $interval, $interval, $hook, $args, self::GROUP );
			return true;
		}
		if ( false !== wp_next_scheduled( $hook, $args ) ) {
			return false;
		}
		return true === wp_schedule_event( time() + $interval, self::cron_recurrence( $interval ), $hook, $args, true );
	}

	/**
	 * Cancels every pending run of one hook (any args).
	 *
	 * @param string $hook Action hook.
	 */
	public function cancel_hook( string $hook ): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}
		wp_unschedule_hook( $hook );
	}

	/**
	 * Cancels every pending action of the `cfbe` group and every cfbe_* cron event (deactivation/uninstall).
	 *
	 * @return int Number of cancelled actions.
	 */
	public function cancel_all(): int {
		$count = 0;
		if ( function_exists( 'as_get_scheduled_actions' ) && class_exists( 'ActionScheduler' ) ) {
			$ids = as_get_scheduled_actions(
				array(
					'group'    => self::GROUP,
					'status'   => 'pending',
					'per_page' => -1,
				),
				'ids'
			);
			foreach ( $ids as $id ) {
				\ActionScheduler::store()->cancel_action( $id );
			}
			$count += count( $ids );
		}
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'cfbe_' ) ) {
					$count += max( 0, (int) wp_unschedule_hook( (string) $hook ) );
				}
			}
		}
		return $count;
	}

	/**
	 * Registers the custom WP-Cron intervals used by recurring jobs (`cron_schedules` filter).
	 *
	 * @param array $schedules Schedules.
	 */
	public static function cron_schedules( $schedules ): array {
		$schedules = is_array( $schedules ) ? $schedules : array();
		foreach ( (array) get_option( 'cfbe_cron_intervals', array() ) as $interval ) {
			$interval = (int) $interval;
			if ( $interval >= 60 ) {
				$schedules[ self::CRON_PREFIX . $interval ] = array(
					'interval' => $interval,
					'display'  => sprintf( 'bluefox.email: every %d seconds', $interval ),
				);
			}
		}
		return $schedules;
	}

	/**
	 * Name of the WP-Cron recurrence for an interval (remembered so `cron_schedules` can register it).
	 *
	 * @param int $interval Seconds.
	 */
	private static function cron_recurrence( int $interval ): string {
		$interval  = max( 60, $interval );
		$intervals = array_map( 'intval', (array) get_option( 'cfbe_cron_intervals', array() ) );
		if ( ! in_array( $interval, $intervals, true ) ) {
			$intervals[] = $interval;
			update_option( 'cfbe_cron_intervals', $intervals, true );
		}
		return self::CRON_PREFIX . $interval;
	}

	/**
	 * Schedules a WP-Cron single event. WP-Cron drops an identical event (same hook + args) within 10 minutes
	 * of an existing one; that is fine for our idempotent jobs and still counts as scheduled.
	 *
	 * @param int    $timestamp When.
	 * @param string $hook      Hook.
	 * @param array  $args      Args (passed positionally).
	 */
	private function cron_single( int $timestamp, string $hook, array $args ): int {
		$args = array_values( $args );
		if ( false !== wp_next_scheduled( $hook, $args ) ) {
			return 1;
		}
		return true === wp_schedule_single_event( $timestamp, $hook, $args, true ) ? 1 : 0;
	}
}
