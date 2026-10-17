<?php
/**
 * WP-CLI: wp cfbe poll | status <email> | resend-coupon <email>.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Support commands for the newsletter / welcome coupon flow.
 */
final class CLI_Command {

	/**
	 * Reward service.
	 *
	 * @var Reward_Service
	 */
	private $rewards;

	/**
	 * Repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Client (null when not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Reward_Service        $rewards    Reward service.
	 * @param Subscriber_Repository $repository Repository.
	 * @param Bluefox_Client|null   $client     Client.
	 */
	public function __construct( Reward_Service $rewards, Subscriber_Repository $repository, ?Bluefox_Client $client ) {
		$this->rewards    = $rewards;
		$this->repository = $repository;
		$this->client     = $client;
	}

	/**
	 * Runs the confirmation poller now (expires stale signups, queues rewards).
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfbe poll
	 */
	public function poll(): void {
		$queued = $this->rewards->poll();
		\WP_CLI::success( sprintf( '%d reward job(s) queued.', $queued ) );
	}

	/**
	 * Shows the local row, the bluefox status and the coupon of an email.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Subscriber email.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfbe status user@example.com
	 *
	 * @param array $args Positional args.
	 */
	public function status( array $args ): void {
		$email = Subscriber_Repository::normalize_email( (string) ( $args[0] ?? '' ) );
		$row   = $this->repository->find_by_email( $email );
		\WP_CLI::log( 'Local: ' . ( $row ? wp_json_encode( array_intersect_key( $row, array_flip( array( 'status', 'source', 'tags', 'attempts', 'last_error', 'created_at', 'confirmed_at', 'coupon_id' ) ) ) ) : 'no row' ) );

		if ( $this->client ) {
			// The list the person signed up to on this site, else the default list.
			$list = $row && '' !== (string) ( $row['list_id'] ?? '' ) ? (string) $row['list_id'] : (string) Settings::get( 'api', 'list_id' );
			$sub  = $this->client->get_subscriber( $list, $email );
			\WP_CLI::log( 'bluefox (list ' . $list . '): ' . ( is_wp_error( $sub ) ? $sub->get_error_code() : (string) ( $sub['status'] ?? '?' ) ) );
		}
		if ( $row && ! empty( $row['coupon_id'] ) && function_exists( 'wc_get_coupon_code_by_id' ) ) {
			\WP_CLI::log( 'Coupon: ' . wc_get_coupon_code_by_id( (int) $row['coupon_id'] ) );
		}
	}

	/**
	 * Re-sends the welcome coupon email (same code).
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Subscriber email.
	 *
	 * @subcommand resend-coupon
	 *
	 * @param array $args Positional args.
	 */
	public function resend_coupon( array $args ): void {
		$result = $this->rewards->resend( (string) ( $args[0] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( 'Coupon email re-sent.' );
	}
}
