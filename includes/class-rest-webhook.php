<?php
/**
 * REST endpoint: POST /wp-json/cfbe/v1/bluefox-hook (bluefox project webhook or „Contact added” automation).
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates, then only enqueues a reward check (which re-reads the status from bluefox).
 *
 * - Project webhook: `Authorization: Bearer <project API key>` (bluefox sends one of the project's API keys),
 *   payload { type, ... }. Every authenticated project event is handed to `cfbe_webhook_received` first
 *   (listeners must only queue work). Subscription events carry
 *   { type, subscription: { email, status, subscriberList: { _id } } }; only subscribe/resubscribe with status
 *   active on the list of the email's local signup row (default list for old rows) can lead to a confirmation.
 *   Such events also record the bluefox status locally (unsubscribe / pause / resubscribe).
 * - Automation webhook node: `X-CFBE-Secret` header, payload { automationId, contactId, contact: { email, ... } }.
 */
final class Rest_Webhook {

	const MAX_BODY   = 65536;
	const RATE_LIMIT = 60;

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
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private $limiter;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Reward_Service        $rewards    Reward service.
	 * @param Subscriber_Repository $repository Repository.
	 * @param Rate_Limiter          $limiter    Rate limiter.
	 * @param Logger                $logger     Logger.
	 */
	public function __construct( Reward_Service $rewards, Subscriber_Repository $repository, Rate_Limiter $limiter, Logger $logger ) {
		$this->rewards    = $rewards;
		$this->repository = $repository;
		$this->limiter    = $limiter;
		$this->logger     = $logger;
	}

	/**
	 * Hooks route registration.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			Rest_Subscribe::ROUTE_NS,
			'/bluefox-hook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true', // Authenticated by the shared secret header below.
			)
		);
	}

	/**
	 * Handles a webhook call.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function handle( $request ): \WP_REST_Response {
		$ip = Rate_Limiter::client_ip();
		if ( ! $this->limiter->hit( 'hook', $ip, self::RATE_LIMIT, 60 ) ) {
			return $this->json( array( 'ok' => false ), 429 );
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return $this->json( array( 'ok' => false ), 413 );
		}

		if ( ! $this->authenticated( $request ) ) {
			$this->logger->warning( 'Webhook rejected: bad secret', array( 'ip' => Consent_Ip::anonymize( $ip ) ) );
			return $this->json( array( 'ok' => false ), 401 );
		}

		$type = self::event_type( $request );
		if ( '' !== $type && Modules::enabled( 'webhooks' ) ) {
			$payload = json_decode( (string) $request->get_body(), true );

			/**
			 * Fires for every authenticated bluefox project webhook event, before the core handling, while the
			 * Inbound webhooks feature is on.
			 * Listeners must return quickly (queue a job); bluefox expects a fast 200.
			 *
			 * @param string $type    Event type (subscribe, resubscribe, unsubscribe, pause-subscription, bounce,
			 *                        complaint, click, open, sent, failed).
			 * @param array  $payload Decoded request body (untrusted).
			 */
			do_action( 'cfbe_webhook_received', $type, is_array( $payload ) ? $payload : array() );
		}

		$subscription = $request->get_param( 'subscription' );
		if ( is_array( $subscription ) ) {
			$email = $this->project_event_email( $type, $subscription );
			if ( null === $email ) {
				return $this->ignored();
			}
		} elseif ( '' !== $type ) {
			// Delivery / engagement events: nothing for the core to do.
			return $this->json(
				array(
					'ok'     => true,
					'action' => 'received',
				)
			);
		} else {
			$contact = $request->get_param( 'contact' );
			$email   = is_array( $contact ) ? sanitize_email( (string) ( $contact['email'] ?? '' ) ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				return $this->json( array( 'ok' => false ), 400 );
			}
			if ( null === $this->repository->find_by_email( $email ) ) {
				// Imported or signed up elsewhere: the welcome coupon is only for plugin signups.
				$this->logger->info( 'Webhook for unknown contact ignored', array( 'email' => $email ) );
				return $this->ignored();
			}
		}

		$this->rewards->enqueue( $email );
		return $this->json(
			array(
				'ok'     => true,
				'action' => 'queued',
			)
		);
	}

	/**
	 * Automation secret header, or the project webhook's Bearer API key. Constant-time compares.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private function authenticated( $request ): bool {
		$secret   = (string) Settings::get( 'api', 'webhook_secret' );
		$provided = (string) $request->get_header( 'x_cfbe_secret' );
		if ( '' !== $secret && '' !== $provided ) {
			return hash_equals( $secret, $provided );
		}

		$api_key = Bluefox_Client::configured_api_key();
		$header  = (string) $request->get_header( 'authorization' );
		if ( '' === $header ) {
			$header = self::server_authorization();
		}
		if ( '' === $api_key || 0 !== stripos( $header, 'Bearer ' ) ) {
			return false;
		}
		return hash_equals( $api_key, trim( substr( $header, 7 ) ) );
	}

	/**
	 * Authorization header when WordPress didn't get it: Apache drops it from $_SERVER unless the .htaccess passes it
	 * on (WordPress 5.2's default rules don't), FastCGI setups may expose it as REDIRECT_HTTP_AUTHORIZATION.
	 */
	private static function server_authorization(): string {
		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}
		if ( function_exists( 'apache_request_headers' ) ) {
			foreach ( (array) apache_request_headers() as $name => $value ) {
				if ( 'authorization' === strtolower( (string) $name ) ) {
					return sanitize_text_field( (string) $value );
				}
			}
		}
		return '';
	}

	/**
	 * Project webhook event type (empty for the automation payload, which has none).
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	private static function event_type( $request ): string {
		$type = $request->get_param( 'type' );
		if ( ! is_string( $type ) || strlen( $type ) > 40 ) {
			return '';
		}
		return sanitize_key( $type );
	}

	/**
	 * The list a local signup row belongs to (old rows without one: the default list).
	 *
	 * @param array $row Subscriber row.
	 */
	public static function row_list_id( array $row ): string {
		$list = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $row['list_id'] ?? '' ) );
		return '' !== $list ? $list : (string) Settings::get( 'api', 'list_id' );
	}

	/**
	 * Email from a project webhook subscription event, or null when the event can't lead to a confirmation.
	 * Accepted only for the list of the email's local signup row.
	 *
	 * @param string $type         Event type.
	 * @param array  $subscription Subscription object.
	 */
	private function project_event_email( string $type, array $subscription ): ?string {
		$list   = is_array( $subscription['subscriberList'] ?? null ) ? (string) ( $subscription['subscriberList']['_id'] ?? '' ) : '';
		$status = (string) ( $subscription['status'] ?? '' );
		$email  = sanitize_email( (string) ( $subscription['email'] ?? '' ) );

		$row  = ( '' !== $email && '' !== $list && is_email( $email ) ) ? $this->repository->find_by_email( $email ) : null;
		$ours = null !== $row && self::row_list_id( $row ) === $list;
		if ( $ours && in_array( $status, Subscriber_Repository::LIST_STATUSES, true ) ) {
			$this->repository->set_list_status( $email, $status );
		}

		if ( ! in_array( $type, array( 'subscribe', 'resubscribe' ), true ) || 'active' !== $status || ! $ours ) {
			$this->logger->info(
				'Project webhook event ignored',
				array(
					'type'   => $type,
					'status' => $status,
					'email'  => $email,
				)
			);
			return null;
		}
		return $email;
	}

	/**
	 * 200 "ignored" response.
	 */
	private function ignored(): \WP_REST_Response {
		return $this->json(
			array(
				'ok'     => true,
				'action' => 'ignored',
			)
		);
	}

	/**
	 * JSON response, never cached.
	 *
	 * @param array $data   Body.
	 * @param int   $status HTTP status.
	 */
	private function json( array $data, int $status = 200 ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}
}
