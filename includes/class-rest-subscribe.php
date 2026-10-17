<?php
/**
 * REST endpoint: POST /wp-json/cfbe/v1/subscribe.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Public, nonce-less (pages may be cached). Abuse is handled by a honeypot,
 * a minimum fill time, IP/email rate limits and a body size cap.
 */
final class Rest_Subscribe {

	const ROUTE_NS    = 'cfbe/v1';
	const MIN_ELAPSED = Form_Config::MIN_FILL_SECONDS * 1000; // The form script waits this long (ms) after fetching its token.
	const MAX_BODY    = 4096;
	const SOURCES     = array( 'popup', 'shortcode', 'block', 'promo' );

	const IP_LIMIT    = 5;
	const IP_WINDOW   = 600;
	const SITE_LIMIT  = 30; // Signups per minute for the whole site (filter `cfbe_signup_site_limit`).
	const SITE_WINDOW = 60;

	/**
	 * Service.
	 *
	 * @var Subscription_Service
	 */
	private $service;

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
	 * @param Subscription_Service $service Service.
	 * @param Rate_Limiter         $limiter Rate limiter.
	 * @param Logger               $logger  Logger.
	 */
	public function __construct( Subscription_Service $service, Rate_Limiter $limiter, Logger $logger ) {
		$this->service = $service;
		$this->limiter = $limiter;
		$this->logger  = $logger;
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
			self::ROUTE_NS,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::ROUTE_NS,
			'/form-token',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'token' ),
				'permission_callback' => '__return_true', // Public: the token only proves when a form was shown.
			)
		);
	}

	/**
	 * Fill-time token for the form script (never cached).
	 */
	public function token(): \WP_REST_Response {
		$response = new \WP_REST_Response( array( 'token' => Form_Config::fill_token() ), 200 );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * Handles a signup request.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function handle( $request ): \WP_REST_Response {
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return $this->error( 'payload_too_large', __( 'Request too large.', 'connector-for-bluefox-email' ), 413 );
		}

		$source = sanitize_key( (string) $request->get_param( 'source' ) );
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			return $this->respond(
				new Subscribe_Result(
					Subscribe_Result::INVALID,
					__( 'Invalid request.', 'connector-for-bluefox-email' ),
					array( 'source' => __( 'Unknown source.', 'connector-for-bluefox-email' ) ),
					400
				)
			);
		}

		// Bots: pretend success, store nothing. The fill-time token is signed by this site (see Form_Config).
		$honeypot = (string) $request->get_param( 'cfbe_hp' );
		$token_ok = Form_Config::fill_token_ok( $request->get_param( 'cfbe_token' ) );
		if ( '' !== $honeypot || ! $token_ok ) {
			$this->logger->info(
				'Signup dropped by bot filter',
				array(
					'honeypot' => '' !== $honeypot,
					'token'    => $token_ok,
				)
			);
			return $this->respond( new Subscribe_Result( Subscribe_Result::PENDING, (string) Settings::get( 'form', 'msg_pending' ) ) );
		}

		$ip = Rate_Limiter::client_ip();
		if ( ! $this->limiter->hit( 'ip', $ip, self::IP_LIMIT, self::IP_WINDOW ) ) {
			return $this->rate_limited();
		}
		/**
		 * Filters how many signups per minute the whole site accepts (protects the bluefox account from floods).
		 *
		 * @param int $limit Signups per minute.
		 */
		$site_limit = max( 1, (int) apply_filters( 'cfbe_signup_site_limit', self::SITE_LIMIT ) );
		if ( ! $this->limiter->hit( 'site', 'all', $site_limit, self::SITE_WINDOW ) ) {
			$this->logger->warning( 'Signup rate limited (whole site)', array( 'limit' => $site_limit ) );
			return $this->rate_limited();
		}
		// The per-mailbox limit is applied by the signup service, for every source.

		// List + extra tags only from a form config signed by this site (cached pages, no nonce).
		$target = Form_Config::verify( $request->get_param( Form_Config::FIELD ) );

		$result = $this->service->subscribe(
			array(
				'email'        => (string) $request->get_param( 'email' ),
				'name'         => (string) $request->get_param( 'name' ),
				'interests'    => $request->get_param( 'interests' ),
				'consent'      => $request->get_param( 'consent' ),
				'consent_text' => self::consent_text(),
				'ip'           => $ip,
				'place'        => $target['place'],
				'list_id'      => $target['list_id'],
				'tags'         => $target['tags'],
			),
			$source
		);

		return $this->respond( $result );
	}

	/**
	 * The consent statement exactly as configured (never taken from the request).
	 */
	public static function consent_text(): string {
		$text = wp_strip_all_tags( (string) Settings::get( 'form', 'consent_label' ) );
		$url  = (string) Settings::get( 'form', 'privacy_url' );
		return '' === $url ? $text : $text . ' [' . $url . ']';
	}

	/**
	 * JSON response with no-store caching.
	 *
	 * @param Subscribe_Result $result Result.
	 */
	private function respond( Subscribe_Result $result ): \WP_REST_Response {
		$result   = $result->for_visitor( (string) Settings::get( 'form', 'msg_pending' ), (bool) Settings::get( 'form', 'reveal_subscribed' ) );
		$response = new \WP_REST_Response( $result->to_array(), $result->http_status );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		return $response;
	}

	/**
	 * 429 response.
	 */
	private function rate_limited(): \WP_REST_Response {
		return $this->error( Subscribe_Result::RATE_LIMITED, __( 'Too many attempts, please try again later.', 'connector-for-bluefox-email' ), 429 );
	}

	/**
	 * Error response.
	 *
	 * @param string $code    Code.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 */
	private function error( string $code, string $message, int $status ): \WP_REST_Response {
		return $this->respond( new Subscribe_Result( $code, $message, array(), $status ) );
	}
}
