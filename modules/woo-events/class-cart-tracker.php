<?php
/**
 * Abandoned cart capture, restore link and background scans.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Jobs;
use CFBE\Logger;
use CFBE\Rate_Limiter;
use CFBE\Subscriber_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Capture: logged-in shoppers on every cart change; guests once they type their email at checkout
 * (classic checkout: small script → `POST cfbe/v1/cart-email`; block checkout: Store API customer update).
 * A recurring job finds idle carts and hands them to the Dispatcher; `?cfbe_cart_<token>` restores a cart.
 */
class Cart_Tracker {

	const QUERY_VAR        = 'cfbe_cart';
	const ROUTE_NS         = 'cfbe/v1';
	const ROUTE            = '/cart-email';
	const SESSION_EMAIL    = 'cfbe_cart_email';
	const SESSION_RESTORED = 'cfbe_restored_cart';
	const JOB_SCAN         = 'cfbe_woo_events_cart_scan';
	const JOB_CLEANUP      = 'cfbe_woo_events_cart_cleanup';
	const SCAN_INTERVAL    = 900;
	const SCAN_BATCH       = 50;
	const MIN_IDLE_MINUTES = 15;

	/**
	 * Local signup statuses that count as opted in.
	 */
	const SUBSCRIBED = array( Subscriber_Repository::STATUS_CONFIRMED, Subscriber_Repository::STATUS_REWARDED, Subscriber_Repository::STATUS_EXISTING );

	/**
	 * Cart storage.
	 *
	 * @var Cart_Repository
	 */
	private $carts;

	/**
	 * Dispatcher.
	 *
	 * @var Dispatcher
	 */
	private $dispatcher;

	/**
	 * Local signups.
	 *
	 * @var Subscriber_Repository
	 */
	private $subscribers;

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
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private $limiter;

	/**
	 * Recovery coupons (applied when a cart is restored from the reminder).
	 *
	 * @var Recovery_Coupon|null
	 */
	private $coupons;

	/**
	 * Whether the cart changed in this request (written once at shutdown).
	 *
	 * @var bool
	 */
	private $dirty = false;

	/**
	 * Whether an order was placed in this request (the cart is still full until payment: never store it again).
	 *
	 * @var bool
	 */
	private $ordered = false;

	/**
	 * Constructor.
	 *
	 * @param Cart_Repository       $carts       Cart storage.
	 * @param Dispatcher            $dispatcher  Dispatcher.
	 * @param Subscriber_Repository $subscribers Local signups.
	 * @param Jobs                  $jobs        Jobs.
	 * @param Logger                $logger      Logger.
	 * @param Rate_Limiter          $limiter     Rate limiter.
	 * @param Recovery_Coupon|null  $coupons     Recovery coupons.
	 */
	public function __construct( Cart_Repository $carts, Dispatcher $dispatcher, Subscriber_Repository $subscribers, Jobs $jobs, Logger $logger, Rate_Limiter $limiter, ?Recovery_Coupon $coupons = null ) {
		$this->coupons     = $coupons;
		$this->carts       = $carts;
		$this->dispatcher  = $dispatcher;
		$this->subscribers = $subscribers;
		$this->jobs        = $jobs;
		$this->logger      = $logger;
		$this->limiter     = $limiter;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_cart_updated', array( $this, 'mark_dirty' ) );
		add_action( 'shutdown', array( $this, 'flush' ), 5 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'on_store_api_customer' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_classic_order' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_order' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'maybe_restore' ), 5 );
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( self::JOB_SCAN, array( $this, 'scan' ) );
		add_action( self::JOB_CLEANUP, array( $this, 'cleanup' ) );
		add_action( 'admin_init', array( $this, 'schedule' ) );
	}

	/**
	 * Restore link of a cart: `https://shop.example/?cfbe_cart_<token>`.
	 *
	 * The link deliberately contains no `=` or `&`: Handlebars HTML-escapes them in `{{cartUrl}}` (`=` → `&#x3D;`),
	 * and email click tracking that wraps the escaped text would cut the token off at the `#`.
	 *
	 * @param string $token Token.
	 */
	public static function restore_url( string $token ): string {
		return home_url( '/' ) . '?' . self::QUERY_VAR . '_' . preg_replace( '/[^A-Za-z0-9]/', '', $token );
	}

	/**
	 * Token from the current request: `?cfbe_cart_<token>` or the older `?cfbe_cart=<token>` ('' when none).
	 *
	 * @param array $query Query parameters ($_GET).
	 */
	public static function token_from_query( array $query ): string {
		if ( isset( $query[ self::QUERY_VAR ] ) && is_string( $query[ self::QUERY_VAR ] ) ) {
			return (string) preg_replace( '/[^A-Za-z0-9]/', '', $query[ self::QUERY_VAR ] );
		}
		foreach ( array_keys( $query ) as $key ) {
			if ( preg_match( '/^' . self::QUERY_VAR . '_([A-Za-z0-9]{16,64})$/', (string) $key, $m ) ) {
				return $m[1];
			}
		}
		return '';
	}

	/**
	 * Whether carts are tracked: the switch is on and an enabled abandoned cart rule exists.
	 */
	public function enabled(): bool {
		$settings = Module::settings();
		return ! empty( $settings['cart_tracking'] ) && (bool) Rules::matching( (array) $settings['rules'], Rules::EVENT_ABANDONED_CART );
	}

	/**
	 * Schedules the recurring scan and cleanup (idempotent).
	 */
	public function schedule(): void {
		$this->jobs->schedule_recurring( self::JOB_SCAN, self::SCAN_INTERVAL );
		$this->jobs->schedule_recurring( self::JOB_CLEANUP, DAY_IN_SECONDS );
	}

	/**
	 * `woocommerce_cart_updated`: remember to snapshot the cart at the end of the request.
	 */
	public function mark_dirty(): void {
		$this->dirty = true;
	}

	/**
	 * Shutdown: writes the cart once per request.
	 */
	public function flush(): void {
		if ( ! $this->dirty || $this->ordered ) {
			return;
		}
		$this->dirty = false;
		$this->snapshot();
	}

	/**
	 * Saves the current WooCommerce cart for its shopper (needs a known email).
	 *
	 * @param string|null $email Email (null = from the session / logged-in user).
	 * @return int Cart row ID (0 = not stored).
	 */
	public function snapshot( ?string $email = null ): int {
		$wc = $this->enabled() && function_exists( 'WC' ) ? WC() : null;
		if ( ! $wc || empty( $wc->cart ) || empty( $wc->session ) ) {
			return 0;
		}
		$session_key = (string) $wc->session->get_customer_id();
		// Checked out, waiting for (offsite) payment: the cart is emptied on the thank-you page.
		if ( $wc->session->get( 'order_awaiting_payment' ) ) {
			return 0;
		}
		if ( $wc->cart->is_empty() ) {
			$this->carts->delete_open( $session_key );
			return 0;
		}
		$email = null !== $email ? $email : $this->known_email( $wc );
		if ( ! is_email( $email ) ) {
			return 0;
		}
		if ( ! $this->may_track( $email ) ) {
			return 0;
		}

		return $this->carts->save(
			array(
				'email'       => $email,
				'user_id'     => get_current_user_id(),
				'session_key' => $session_key,
				'items'       => self::items( (array) $wc->cart->get_cart() ),
				'cart_total'  => (float) $wc->cart->get_total( 'edit' ),
				'currency'    => (string) get_woocommerce_currency(),
			)
		);
	}

	/**
	 * Email of the shopper: typed at checkout (session), else the logged-in customer's.
	 *
	 * @param object $wc WooCommerce instance.
	 */
	private function known_email( $wc ): string {
		$email = (string) $wc->session->get( self::SESSION_EMAIL );
		if ( '' !== $email ) {
			return $email;
		}
		if ( ! is_user_logged_in() ) {
			return '';
		}
		if ( ! empty( $wc->customer ) && '' !== (string) $wc->customer->get_billing_email() ) {
			return (string) $wc->customer->get_billing_email();
		}
		$user = wp_get_current_user();
		return $user ? (string) $user->user_email : '';
	}

	/**
	 * Whether a shopper's cart may be stored ("only opted-in shoppers" setting): only a confirmed signup counts.
	 * A newsletter box ticked on an unfinished checkout is not consent, and the browser could claim it for anyone.
	 *
	 * @param string $email Email.
	 */
	public function may_track( string $email ): bool {
		if ( empty( Module::settings()['cart_optin_only'] ) ) {
			return true;
		}
		$row = $this->subscribers->find_by_email( $email );
		return $row && in_array( (string) $row['status'], self::SUBSCRIBED, true ) && 'unsubscribed' !== ( $row['list_status'] ?? '' );
	}

	/**
	 * Cart contents → storable item list (with a price and product snapshot for the email).
	 *
	 * @param array $contents `WC_Cart::get_cart()`.
	 * @return array[]
	 */
	public static function items( array $contents ): array {
		$items = array();
		foreach ( $contents as $line ) {
			$product = is_array( $line ) ? ( $line['data'] ?? null ) : null;
			if ( ! is_object( $product ) ) {
				continue;
			}
			$variation = array();
			foreach ( (array) ( $line['variation'] ?? array() ) as $attr => $value ) {
				$variation[ sanitize_text_field( (string) $attr ) ] = sanitize_text_field( (string) $value );
			}
			$items[] = array(
				'product_id'   => absint( $line['product_id'] ?? 0 ),
				'variation_id' => absint( $line['variation_id'] ?? 0 ),
				'quantity'     => max( 1, (int) ( $line['quantity'] ?? 1 ) ),
				'variation'    => $variation,
				'name'         => (string) $product->get_name(),
				'line_total'   => round( (float) ( $line['line_total'] ?? 0 ) + (float) ( $line['line_tax'] ?? 0 ), 4 ),
				'sku'          => (string) $product->get_sku(),
				'image'        => Payload_Builder::image_url( (int) $product->get_image_id() ),
			);
		}
		return $items;
	}

	/**
	 * Block checkout: the Store API updated the customer (email typed).
	 *
	 * @param \WC_Customer     $customer Customer.
	 * @param \WP_REST_Request $request  Request.
	 */
	public function on_store_api_customer( $customer, $request = null ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		if ( ! is_object( $customer ) || ! $this->enabled() ) {
			return;
		}
		$email = sanitize_email( (string) $customer->get_billing_email() );
		if ( ! is_email( $email ) ) {
			return;
		}
		$wc = WC();
		if ( ! empty( $wc->session ) ) {
			$wc->session->set( self::SESSION_EMAIL, $email );
		}
		$this->snapshot( $email );
	}

	/**
	 * Registers `POST cfbe/v1/cart-email` (classic checkout).
	 */
	public function register_route(): void {
		register_rest_route(
			self::ROUTE_NS,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_email' ),
				// Public by design (guests); protected by the wp_rest nonce of the (never cached) checkout page + rate limit.
				'permission_callback' => '__return_true',
				'args'                => array(
					'email' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Stores the email typed on the classic checkout and snapshots the cart.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function rest_email( $request ): \WP_REST_Response {
		if ( ! $this->enabled() ) {
			return new \WP_REST_Response( array( 'stored' => false ), 200 );
		}
		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_REST_Response( array( 'stored' => false ), 403 );
		}
		if ( ! $this->limiter->hit( 'cart_email', Rate_Limiter::client_ip(), 20, 600 ) ) {
			return new \WP_REST_Response( array( 'stored' => false ), 429 );
		}
		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response( array( 'stored' => false ), 400 );
		}
		if ( ! $this->limiter->hit( 'cart_mailbox', \CFBE\Subscriber_Repository::mailbox( $email ), 5, 3600 ) ) {
			return new \WP_REST_Response( array( 'stored' => false ), 429 );
		}

		// WooCommerce does not load the cart / session for REST requests by default.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		$wc = WC();
		if ( empty( $wc->session ) ) {
			return new \WP_REST_Response( array( 'stored' => false ), 200 );
		}
		$wc->session->set( self::SESSION_EMAIL, $email );

		return new \WP_REST_Response( array( 'stored' => $this->snapshot( $email ) > 0 ), 200 );
	}

	/**
	 * Classic checkout order.
	 *
	 * @param int            $order_id Order ID.
	 * @param array          $posted   Posted data.
	 * @param \WC_Order|null $order    Order.
	 */
	public function on_classic_order( $order_id, $posted = array(), $order = null ): void {
		if ( ! is_object( $order ) ) {
			$order = wc_get_order( $order_id );
		}
		if ( is_object( $order ) ) {
			$this->on_order( $order );
		}
	}

	/**
	 * An order was placed: the shopper's active carts become `ordered`, a restored one `recovered`.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function on_order( $order ): void {
		if ( ! is_object( $order ) ) {
			return;
		}
		$wc          = function_exists( 'WC' ) ? WC() : null;
		$session     = $wc && ! empty( $wc->session ) ? $wc->session : null;
		$session_key = $session ? (string) $session->get_customer_id() : '';

		$restored = $session ? (int) $session->get( self::SESSION_RESTORED ) : 0;
		if ( $restored > 0 ) {
			$cart = $this->carts->find( $restored );
			if ( $cart && $this->carts->transition( $cart, Cart_Repository::STATUS_RECOVERED ) ) {
				$this->logger->info(
					'Woo emails: abandoned cart recovered',
					array(
						'cart'  => $restored,
						'order' => (int) $order->get_id(),
					)
				);
			}
			$session->set( self::SESSION_RESTORED, null );
		}
		$this->ordered = true;
		$this->carts->mark_ordered( $session_key, (string) $order->get_billing_email() );
	}

	/**
	 * `?cfbe_cart_<token>` (or `?cfbe_cart=<token>`): restores the cart and redirects to the cart page.
	 */
	public function maybe_restore(): void {
		// Restore links arrive from emails: the 32-char random token is the credential, no nonce possible.
		$token = self::token_from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only [A-Za-z0-9] is kept.
		if ( '' === $token ) {
			return;
		}
		$ok = $this->limiter->hit( 'cart_restore', Rate_Limiter::client_ip(), 30, 600 ) && $this->restore( $token );
		if ( ! $ok && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( __( 'This cart link is no longer valid.', 'connector-for-bluefox-email' ), 'notice' );
		}
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Whether a cart row can be restored.
	 *
	 * @param array|null $cart Cart row.
	 */
	public static function restorable( ?array $cart ): bool {
		return null !== $cart && in_array( (string) ( $cart['status'] ?? '' ), Cart_Repository::ACTIVE, true ) && ! empty( $cart['cart'] );
	}

	/**
	 * Repopulates the WooCommerce cart from a token (only products that are still purchasable and in stock).
	 *
	 * @param string $token Token.
	 * @return bool Whether at least one item was restored.
	 */
	public function restore( string $token ): bool {
		$cart = $this->carts->find_by_token( $token );
		if ( ! self::restorable( $cart ) || ! function_exists( 'WC' ) ) {
			return false;
		}
		$wc = WC();
		if ( empty( $wc->cart ) || empty( $wc->session ) ) {
			return false;
		}

		$wc->cart->empty_cart();
		$added = 0;
		foreach ( $cart['cart'] as $item ) {
			$product_id   = absint( $item['product_id'] ?? 0 );
			$variation_id = absint( $item['variation_id'] ?? 0 );
			$product      = wc_get_product( $variation_id ? $variation_id : $product_id );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}
			if ( $wc->cart->add_to_cart( $product_id, max( 1, (int) ( $item['quantity'] ?? 1 ) ), $variation_id, (array) ( $item['variation'] ?? array() ) ) ) {
				++$added;
			}
		}
		if ( 0 === $added ) {
			return false;
		}
		// The reminder's recovery coupon is applied right away (WooCommerce checks the email restriction at checkout).
		$code = null !== $this->coupons ? $this->coupons->usable_code( (int) $cart['id'] ) : '';
		if ( '' !== $code && ! $wc->cart->has_discount( $code ) ) {
			// The coupon is locked to the cart's email: WooCommerce only accepts it once the session knows an
			// email, so a guest arriving from the reminder gets the checkout email pre-filled with that address.
			if ( ! empty( $wc->customer ) && '' === (string) $wc->customer->get_billing_email() && is_email( (string) $cart['email'] ) ) {
				$wc->customer->set_billing_email( (string) $cart['email'] );
				$wc->customer->save();
			}
			$wc->cart->apply_coupon( $code );
		}
		$wc->session->set( self::SESSION_RESTORED, (int) $cart['id'] );
		$wc->session->set( self::SESSION_EMAIL, (string) $cart['email'] );
		return true;
	}

	/**
	 * Job: hands idle carts to the dispatcher.
	 *
	 * @return int Carts claimed.
	 */
	public function scan(): int {
		if ( ! $this->enabled() ) {
			return 0;
		}
		$minutes = max( self::MIN_IDLE_MINUTES, (int) Module::settings()['cart_idle_minutes'] );
		$claimed = 0;
		foreach ( $this->carts->idle( $minutes, self::SCAN_BATCH ) as $cart ) {
			if ( $this->dispatcher->queue_abandoned( $cart ) > 0 ) {
				++$claimed;
			}
		}
		return $claimed;
	}

	/**
	 * Job: expires old carts and deletes very old rows.
	 */
	public function cleanup(): void {
		$this->carts->cleanup( max( 1, (int) Module::settings()['cart_expiry_days'] ) );
	}

	/**
	 * Classic checkout script that reports the typed billing email.
	 */
	public function enqueue(): void {
		if ( ! $this->enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		wp_enqueue_script( 'cfbe-woo-events-checkout', CFBE_URL . 'modules/woo-events/assets/checkout.js', array(), \CFBE\Plugin::asset_version( 'modules/woo-events/assets/checkout.js' ), true );
		wp_localize_script(
			'cfbe-woo-events-checkout',
			'cfbeCartCapture',
			array(
				'url'   => esc_url_raw( rest_url( self::ROUTE_NS . self::ROUTE ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
