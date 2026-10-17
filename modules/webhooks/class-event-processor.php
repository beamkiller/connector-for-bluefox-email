<?php
/**
 * Applies one bluefox project webhook event in WordPress (runs as a background job).
 *
 * @package CFBE
 */

namespace CFBE\Webhooks;

use CFBE\Logger;
use CFBE\Rest_Webhook;
use CFBE\Subscriber_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Records the event on the matching WP user (meta) and the local signup row, then fires the developer actions.
 *
 * Payload shapes (https://bluefox.email/docs/integrations/webhooks):
 * - subscription events (subscribe, resubscribe, unsubscribe, pause-subscription):
 *   { type, subscription: { email, status, subscriberList: { _id, name } } }
 * - email events (sent, failed, open, click, bounce, complaint): { type, emailData: { to, ... } }
 *
 * The payload is never trusted for confirmations or coupons: those stay in the core (re-checked via the API).
 */
final class Event_Processor {

	const META_LIST_STATUS = 'cfbe_list_status';
	const META_BOUNCED     = 'cfbe_bounced';
	const META_COMPLAINED  = 'cfbe_complained';

	/**
	 * Subscription event type => list status when the payload has none.
	 */
	const TYPE_STATUS = array(
		'subscribe'          => 'active',
		'resubscribe'        => 'active',
		'unsubscribe'        => 'unsubscribed',
		'pause-subscription' => 'paused',
	);

	/**
	 * Statuses a subscriber can have on a list.
	 */
	const STATUSES = array( 'active', 'paused', 'unsubscribed', 'unverified' );

	/**
	 * Repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Subscriber_Repository $repository Repository.
	 * @param Logger                $logger     Logger.
	 */
	public function __construct( Subscriber_Repository $repository, Logger $logger ) {
		$this->repository = $repository;
		$this->logger     = $logger;
	}

	/**
	 * Applies an event.
	 *
	 * @param string $type    Event type.
	 * @param array  $payload Decoded webhook body.
	 */
	public function process( string $type, array $payload ): void {
		$type  = sanitize_key( $type );
		$email = self::email( $type, $payload );

		if ( '' !== $email ) {
			if ( self::is_subscription( $type ) ) {
				$this->apply_subscription( $type, $email, $payload );
			} elseif ( 'bounce' === $type ) {
				$this->update_user_meta(
					$email,
					self::META_BOUNCED,
					array(
						'at'   => time(),
						'type' => self::bounce_type( $payload ),
					)
				);
			} elseif ( 'complaint' === $type ) {
				$this->update_user_meta( $email, self::META_COMPLAINED, time() );
			}
		}

		/**
		 * Fires for every bluefox project webhook event (in a background job, after the plugin has applied it).
		 *
		 * @param string $type    Event type (subscribe, resubscribe, unsubscribe, pause-subscription, bounce,
		 *                        complaint, click, open, sent, failed).
		 * @param array  $payload Decoded webhook body (untrusted input).
		 */
		do_action( 'cfbe_webhook_event', $type, $payload );

		/**
		 * Fires for one bluefox webhook event type, e.g. `cfbe_webhook_bounce`, `cfbe_webhook_unsubscribe`,
		 * `cfbe_webhook_pause-subscription`.
		 *
		 * @param array $payload Decoded webhook body (untrusted input).
		 */
		do_action( "cfbe_webhook_{$type}", $payload );
	}

	/**
	 * Whether the type is a subscription event.
	 *
	 * @param string $type Event type.
	 */
	public static function is_subscription( string $type ): bool {
		return isset( self::TYPE_STATUS[ $type ] );
	}

	/**
	 * The contact's email: `subscription.email` for subscription events, `emailData.to` otherwise.
	 *
	 * @param string $type    Event type.
	 * @param array  $payload Payload.
	 */
	public static function email( string $type, array $payload ): string {
		if ( self::is_subscription( $type ) || is_array( $payload['subscription'] ?? null ) ) {
			$email = $payload['subscription']['email'] ?? '';
		} else {
			$email = $payload['emailData']['to'] ?? '';
		}
		$email = is_string( $email ) ? sanitize_email( $email ) : '';
		return '' !== $email && is_email( $email ) ? Subscriber_Repository::normalize_email( $email ) : '';
	}

	/**
	 * The list ID of a subscription event.
	 *
	 * @param array $payload Payload.
	 */
	public static function list_id( array $payload ): string {
		$list = $payload['subscription']['subscriberList']['_id'] ?? '';
		return is_string( $list ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', $list ) : '';
	}

	/**
	 * The subscriber's list status: the payload's `subscription.status`, else derived from the event type.
	 *
	 * @param string $type    Event type.
	 * @param array  $payload Payload.
	 */
	public static function list_status( string $type, array $payload ): string {
		$status = $payload['subscription']['status'] ?? '';
		if ( is_string( $status ) && in_array( $status, self::STATUSES, true ) ) {
			return $status;
		}
		return self::TYPE_STATUS[ $type ] ?? '';
	}

	/**
	 * Bounce type if bluefox sends one (not documented yet; looks at the usual SES-style keys).
	 *
	 * @param array $payload Payload.
	 */
	public static function bounce_type( array $payload ): string {
		$candidates = array(
			$payload['bounceType'] ?? null,
			$payload['bounce']['bounceType'] ?? null,
			$payload['emailData']['bounceType'] ?? null,
		);
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				return sanitize_key( $candidate );
			}
		}
		return '';
	}

	/**
	 * Records a subscription change on the user and on the local signup row (only for the row's own list).
	 *
	 * @param string $type    Event type.
	 * @param string $email   Email address.
	 * @param array  $payload Payload.
	 */
	private function apply_subscription( string $type, string $email, array $payload ): void {
		$list   = self::list_id( $payload );
		$status = self::list_status( $type, $payload );
		if ( '' === $list || '' === $status ) {
			return;
		}

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$statuses          = get_user_meta( $user->ID, self::META_LIST_STATUS, true );
			$statuses          = is_array( $statuses ) ? $statuses : array();
			$statuses[ $list ] = $status;
			update_user_meta( $user->ID, self::META_LIST_STATUS, $statuses );
		}

		// Idempotent with the core endpoint, which records the same status synchronously.
		if ( in_array( $status, Subscriber_Repository::LIST_STATUSES, true ) ) {
			$row = $this->repository->find_by_email( $email );
			if ( null !== $row && Rest_Webhook::row_list_id( $row ) === $list ) {
				$this->repository->set_list_status( $email, $status );
			}
		}
	}

	/**
	 * Sets a meta value on the user with this email, if there is one.
	 *
	 * @param string $email Email address.
	 * @param string $key   Meta key.
	 * @param mixed  $value Value.
	 */
	private function update_user_meta( string $email, string $key, $value ): void {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return;
		}
		update_user_meta( $user->ID, $key, $value );
		$this->logger->info( 'Webhook event recorded on user', array( 'meta' => $key ) );
	}
}
