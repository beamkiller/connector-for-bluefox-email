<?php
/**
 * IP address stored with a consent record: full, anonymised or not at all, with limited retention.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Every consent IP the plugin stores (signup rows, checkout order meta, queued jobs) passes through prepare().
 * The site owner chooses the mode on the Privacy tab; a daily job erases stored IPs after the retention period.
 */
class Consent_Ip {

	const MODE_FULL       = 'full';
	const MODE_ANONYMIZED = 'anonymized';
	const MODE_OFF        = 'off';
	const MODES           = array( self::MODE_FULL, self::MODE_ANONYMIZED, self::MODE_OFF );

	const SETTING_MODE      = 'ip_mode';
	const SETTING_RETENTION = 'ip_retention_months';

	const DEFAULT_MODE      = self::MODE_ANONYMIZED;
	const DEFAULT_RETENTION = 24;

	const RETENTION_HOOK = 'cfbe_ip_retention';
	const REWRITE_HOOK   = 'cfbe_ip_rewrite';
	const REWRITE_BATCH  = 200;
	const ORDER_META     = '_cfbe_optin_ip';
	const ORDER_BATCH    = 100;

	/**
	 * Subscribers repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Background jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Constructor.
	 *
	 * @param Subscriber_Repository $repository Repository.
	 * @param Jobs                  $jobs       Jobs.
	 */
	public function __construct( Subscriber_Repository $repository, Jobs $jobs ) {
		$this->repository = $repository;
		$this->jobs       = $jobs;
	}

	/**
	 * Hooks the daily retention job.
	 */
	public function register(): void {
		add_action( self::RETENTION_HOOK, array( $this, 'purge' ) );
		add_action( self::REWRITE_HOOK, array( $this, 'rewrite' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
	}

	/**
	 * Schedules the daily retention job (idempotent).
	 */
	public function schedule(): void {
		$this->jobs->schedule_recurring( self::RETENTION_HOOK, DAY_IN_SECONDS );
	}

	/**
	 * Configured mode.
	 */
	public static function mode(): string {
		$mode = (string) Settings::get( 'api', self::SETTING_MODE );
		return in_array( $mode, self::MODES, true ) ? $mode : self::DEFAULT_MODE;
	}

	/**
	 * Retention in months (0 = keep while the record exists).
	 */
	public static function retention_months(): int {
		$months = Settings::get( 'api', self::SETTING_RETENTION );
		return null === $months ? self::DEFAULT_RETENTION : max( 0, (int) $months );
	}

	/**
	 * The value to store for an IP according to the mode: the IP, its anonymised form (last IPv4 octet /
	 * last 80 IPv6 bits zeroed, like wp_privacy_anonymize_ip()) or '' when IPs are not stored.
	 *
	 * @param string $ip Raw IP address.
	 */
	public static function prepare( string $ip ): string {
		$ip = trim( $ip );
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		switch ( self::mode() ) {
			case self::MODE_OFF:
				return '';
			case self::MODE_FULL:
				return $ip;
			default:
				return self::anonymize( $ip );
		}
	}

	/**
	 * Anonymised IP (always, regardless of the mode) – e.g. for log entries.
	 *
	 * @param string $ip IP address.
	 */
	public static function anonymize( string $ip ): string {
		if ( function_exists( 'wp_privacy_anonymize_ip' ) ) {
			return (string) wp_privacy_anonymize_ip( $ip );
		}
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return (string) preg_replace( '/\.\d+$/', '.0', $ip );
		}
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input returns false.
		return false === $packed ? '' : (string) inet_ntop( substr( $packed, 0, 6 ) . str_repeat( "\0", 10 ) );
	}

	/**
	 * Queues the rewrite of stored IPs after the mode became stricter (full → anonymised / off).
	 *
	 * @param string $old_mode Previous mode.
	 * @param string $new_mode New mode.
	 */
	public function mode_changed( string $old_mode, string $new_mode ): void {
		$strictness = array(
			self::MODE_FULL       => 0,
			self::MODE_ANONYMIZED => 1,
			self::MODE_OFF        => 2,
		);
		if ( ( $strictness[ $new_mode ] ?? 1 ) > ( $strictness[ $old_mode ] ?? 1 ) ) {
			$this->jobs->enqueue( self::REWRITE_HOOK );
		}
	}

	/**
	 * Job: applies the current mode to IPs stored earlier (a batch per run, re-queued until done).
	 *
	 * @return int Records changed in this run.
	 */
	public function rewrite(): int {
		$changed = 0;
		foreach ( $this->repository->consent_ips( self::REWRITE_BATCH, self::MODE_OFF !== self::mode() ) as $id => $ip ) {
			$value = self::prepare( (string) $ip );
			if ( $value !== $ip ) {
				$this->repository->set_consent_ip( (int) $id, $value );
				++$changed;
			}
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = (array) wc_get_orders(
				array(
					'limit'      => self::REWRITE_BATCH,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Batched background job.
						array(
							'key'     => self::ORDER_META,
							'compare' => 'EXISTS',
						),
					),
				)
			);
			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
					continue;
				}
				$ip    = (string) $order->get_meta( self::ORDER_META );
				$value = self::prepare( $ip );
				if ( $value === $ip ) {
					continue;
				}
				if ( '' === $value ) {
					$order->delete_meta_data( self::ORDER_META );
				} else {
					$order->update_meta_data( self::ORDER_META, $value );
				}
				$order->save();
				++$changed;
			}
		}

		// Unchanged rows stay in the next batch only if nothing changed at all, so stop then.
		if ( $changed > 0 ) {
			$this->jobs->enqueue( self::REWRITE_HOOK );
		}
		return $changed;
	}

	/**
	 * Job: erases consent IPs older than the retention period (signup rows and checkout order meta).
	 *
	 * @param int|null $now Unix timestamp (tests).
	 * @return int Records cleaned.
	 */
	public function purge( ?int $now = null ): int {
		$months = self::retention_months();
		if ( $months <= 0 ) {
			return 0;
		}
		$cutoff  = ( $now ?? time() ) - $months * MONTH_IN_SECONDS;
		$cleaned = $this->repository->clear_consent_ip_before( gmdate( 'Y-m-d H:i:s', $cutoff ) );

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = (array) wc_get_orders(
				array(
					'limit'        => self::ORDER_BATCH,
					'date_created' => '<' . $cutoff,
					'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small daily batch.
						array(
							'key'     => self::ORDER_META,
							'compare' => 'EXISTS',
						),
					),
				)
			);
			foreach ( $orders as $order ) {
				if ( is_object( $order ) && method_exists( $order, 'delete_meta_data' ) ) {
					$order->delete_meta_data( self::ORDER_META );
					$order->save();
					++$cleaned;
				}
			}
		}
		return $cleaned;
	}
}
