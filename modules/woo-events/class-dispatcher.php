<?php
/**
 * Turns store events into queued bluefox emails and sends them in background jobs.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

use CFBE\Bluefox_Client;
use CFBE\Jobs;
use CFBE\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Event hooks only queue Action Scheduler jobs (never a bluefox call in the checkout / admin request).
 * Jobs are idempotent: an order email is sent at most once per rule (order meta `_cfbe_sent_<rule>`),
 * a cart reminder only while the cart is still in status `sent` (not ordered meanwhile).
 */
class Dispatcher {

	const JOB_ORDER = 'cfbe_woo_events_send';
	const JOB_CART  = 'cfbe_woo_events_send_cart';

	const STATUS_OPTION = 'cfbe_woo_events_status';

	const MAX_ATTEMPTS = 3;

	/**
	 * Retry delays in seconds after attempt 1, 2.
	 */
	const BACKOFF = array(
		1 => 300,
		2 => 1800,
	);

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

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
	 * Payload builder.
	 *
	 * @var Payload_Builder
	 */
	private $builder;

	/**
	 * Cart storage.
	 *
	 * @var Cart_Repository
	 */
	private $carts;

	/**
	 * Recovery coupons (null = feature unavailable, e.g. in tests).
	 *
	 * @var Recovery_Coupon|null
	 */
	private $coupons;

	/**
	 * Loads an order by ID.
	 *
	 * @var callable
	 */
	private $order_loader;

	/**
	 * Queries order IDs (`wc_get_orders()` args).
	 *
	 * @var callable
	 */
	private $order_query;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null  $client       Client.
	 * @param Jobs                 $jobs         Jobs.
	 * @param Logger               $logger       Logger.
	 * @param Payload_Builder      $builder      Payload builder.
	 * @param Cart_Repository      $carts        Cart storage.
	 * @param callable|null        $order_loader fn( int ): ?WC_Order (tests).
	 * @param callable|null        $order_query  fn( array $args ): int[] (tests).
	 * @param Recovery_Coupon|null $coupons      Recovery coupons for abandoned cart reminders.
	 */
	public function __construct( ?Bluefox_Client $client, Jobs $jobs, Logger $logger, Payload_Builder $builder, Cart_Repository $carts, ?callable $order_loader = null, ?callable $order_query = null, ?Recovery_Coupon $coupons = null ) {
		$this->coupons      = $coupons;
		$this->client       = $client;
		$this->jobs         = $jobs;
		$this->logger       = $logger;
		$this->builder      = $builder;
		$this->carts        = $carts;
		$this->order_loader = $order_loader ? $order_loader : static function ( $id ) {
			$order = wc_get_order( $id );
			return $order instanceof \WC_Order ? $order : null;
		};
		$this->order_query  = $order_query ? $order_query : static function ( array $args ) {
			return (array) wc_get_orders( $args );
		};
	}

	/**
	 * Decides whether an event is switched on (null = every event).
	 *
	 * @var callable|null
	 */
	private $event_filter = null;

	/**
	 * Limits the rules to the events whose switch is on.
	 *
	 * @param callable|null $filter Receives the event, returns bool.
	 */
	public function set_event_filter( ?callable $filter ): void {
		$this->event_filter = $filter;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_order_status_changed', array( $this, 'on_status_changed' ), 20, 4 );
		add_action( 'woocommerce_new_customer_note', array( $this, 'on_customer_note' ), 20, 1 );
		add_action( self::JOB_ORDER, array( $this, 'send_order' ), 10, 1 );
		add_action( self::JOB_CART, array( $this, 'send_cart' ), 10, 1 );
	}

	/**
	 * Current rules.
	 *
	 * @return array[]
	 */
	public function rules(): array {
		$rules = (array) ( Module::settings()['rules'] ?? array() );
		if ( null === $this->event_filter ) {
			return $rules;
		}
		$filter = $this->event_filter;
		return array_values(
			array_filter(
				$rules,
				static function ( $rule ) use ( $filter ): bool {
					return is_array( $rule ) && (bool) $filter( (string) ( $rule['event'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * Order status changed: status rule, first paid order, review request.
	 *
	 * @param int            $order_id Order ID.
	 * @param string         $from     Old status.
	 * @param string         $to       New status.
	 * @param \WC_Order|null $order    Order.
	 */
	public function on_status_changed( $order_id, $from = '', $to = '', $order = null ): void {
		if ( ! is_object( $order ) ) {
			$order = call_user_func( $this->order_loader, (int) $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$to = Rules::status_slug( (string) $to );

		$this->queue_order_event( Rules::STATUS_PREFIX . $to, $order );

		$paid = function_exists( 'wc_get_is_paid_statuses' ) ? (array) wc_get_is_paid_statuses() : array( 'processing', 'completed' );
		if ( in_array( $to, $paid, true ) && Rules::matching( $this->rules(), Rules::EVENT_FIRST_ORDER ) && $this->is_first_order( $order, $paid ) ) {
			$this->queue_order_event( Rules::EVENT_FIRST_ORDER, $order );
		}
		if ( 'completed' === $to ) {
			$this->queue_order_event( Rules::EVENT_REVIEW_REQUEST, $order );
		}
	}

	/**
	 * Customer note added to an order.
	 *
	 * @param array $args order_id, customer_note.
	 */
	public function on_customer_note( $args ): void {
		$args  = is_array( $args ) ? $args : array();
		$order = call_user_func( $this->order_loader, (int) ( $args['order_id'] ?? 0 ) );
		$note  = (string) ( $args['customer_note'] ?? '' );
		if ( ! $order || '' === $note ) {
			return;
		}
		$this->queue_order_event( Rules::EVENT_CUSTOMER_NOTE, $order, self::note_hash( $note ) );
	}

	/**
	 * Dedupe discriminator of a customer note (every note is a separate email).
	 *
	 * @param string $note Note text.
	 */
	public static function note_hash( string $note ): string {
		return substr( md5( $note ), 0, 10 );
	}

	/**
	 * Whether this is the customer's first paid order (by billing email, guests included; and by customer ID).
	 *
	 * @param \WC_Order $order Order.
	 * @param string[]  $paid  Paid statuses.
	 */
	public function is_first_order( $order, array $paid ): bool {
		$email = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( '' === $email ) {
			return false;
		}
		// Two results are enough: one of them may be this order.
		$base        = array(
			'status' => $paid,
			'limit'  => 2,
			'return' => 'ids',
			'type'   => 'shop_order',
		);
		$order_id    = (int) $order->get_id();
		$has_another = function ( array $query ) use ( $order_id ): bool {
			foreach ( (array) call_user_func( $this->order_query, $query ) as $id ) {
				if ( (int) $id !== $order_id ) {
					return true;
				}
			}
			return false;
		};
		if ( $has_another( $base + array( 'billing_email' => $email ) ) ) {
			return false;
		}
		$customer_id = (int) $order->get_customer_id();
		return ! ( $customer_id > 0 && $has_another( $base + array( 'customer_id' => $customer_id ) ) );
	}

	/**
	 * Queues the matching rules of an order event (skips rules already sent for this order).
	 *
	 * @param string    $event  Event.
	 * @param \WC_Order $order  Order.
	 * @param string    $suffix Dedupe discriminator.
	 * @return int Number of queued jobs.
	 */
	public function queue_order_event( string $event, $order, string $suffix = '' ): int {
		$queued = 0;
		foreach ( Rules::matching( $this->rules(), $event ) as $rule ) {
			if ( $order->get_meta( Rules::sent_meta_key( $rule['id'], $suffix ) ) ) {
				continue;
			}
			$this->schedule(
				Rules::delay_seconds( $rule ),
				self::JOB_ORDER,
				array(
					'rule'    => $rule['id'],
					'order'   => (int) $order->get_id(),
					'event'   => $event,
					'suffix'  => $suffix,
					'attempt' => 1,
				)
			);
			++$queued;
		}
		return $queued;
	}

	/**
	 * Claims an idle cart (open → sent) and queues its reminder rules.
	 *
	 * @param array $cart Cart row.
	 * @return int Number of queued jobs.
	 */
	public function queue_abandoned( array $cart ): int {
		$rules = Rules::matching( $this->rules(), Rules::EVENT_ABANDONED_CART );
		if ( ! $rules || ! $this->carts->transition( $cart, Cart_Repository::STATUS_SENT ) ) {
			return 0;
		}
		foreach ( $rules as $rule ) {
			$this->schedule(
				Rules::delay_seconds( $rule ),
				self::JOB_CART,
				array(
					'rule'    => $rule['id'],
					'cart'    => (int) $cart['id'],
					'attempt' => 1,
				)
			);
		}
		return count( $rules );
	}

	/**
	 * Job: sends one order email.
	 *
	 * @param array $payload rule, order, event, suffix, attempt.
	 */
	public function send_order( $payload ): void {
		$payload = is_array( $payload ) ? $payload : array();
		$rule    = Rules::find( $this->rules(), (string) ( $payload['rule'] ?? '' ) );
		if ( ! $rule || empty( $rule['enabled'] ) ) {
			return;
		}
		$order = call_user_func( $this->order_loader, (int) ( $payload['order'] ?? 0 ) );
		if ( ! $order ) {
			return;
		}
		$suffix = (string) ( $payload['suffix'] ?? '' );
		$key    = Rules::sent_meta_key( $rule['id'], $suffix );
		if ( $order->get_meta( $key ) ) {
			return;
		}
		$email = (string) $order->get_billing_email();
		if ( ! is_email( $email ) ) {
			$this->record( $rule['id'], false, __( 'The order has no valid billing email.', 'connector-for-bluefox-email' ) );
			return;
		}

		$event = (string) ( $payload['event'] ?? $rule['event'] );
		$extra = array();
		if ( Rules::EVENT_CUSTOMER_NOTE === $event ) {
			$extra['note'] = $this->find_note( $order, $suffix );
		}
		$result = $this->deliver( $rule, $email, $this->builder->for_order( $order, $event, $extra ) );
		if ( is_wp_error( $result ) ) {
			$this->failed( self::JOB_ORDER, $payload, $rule, $result, array( 'order' => (int) $order->get_id() ) );
			return;
		}

		$order->update_meta_data( $key, time() );
		$order->save();
		$this->record( $rule['id'], true );
		$this->logger->info(
			'Woo emails: sent',
			array(
				'rule'  => $rule['id'],
				'event' => $event,
				'order' => (int) $order->get_id(),
			)
		);
	}

	/**
	 * Job: sends one abandoned cart reminder.
	 *
	 * @param array $payload rule, cart, attempt.
	 */
	public function send_cart( $payload ): void {
		$payload = is_array( $payload ) ? $payload : array();
		$rule    = Rules::find( $this->rules(), (string) ( $payload['rule'] ?? '' ) );
		if ( ! $rule || ! Rules::matching( array( $rule ), Rules::EVENT_ABANDONED_CART ) ) {
			return;
		}
		$cart = $this->carts->find( (int) ( $payload['cart'] ?? 0 ) );
		// Ordered / recovered / expired meanwhile: no reminder.
		if ( ! $cart || Cart_Repository::STATUS_SENT !== ( $cart['status'] ?? '' ) || ! is_email( (string) $cart['email'] ) ) {
			return;
		}
		$result = $this->deliver( $rule, (string) $cart['email'], $this->builder->for_cart( $cart ) + $this->cart_coupon_data( $cart ) );
		if ( is_wp_error( $result ) ) {
			$this->failed( self::JOB_CART, $payload, $rule, $result, array( 'cart' => (int) $cart['id'] ) );
			return;
		}
		$this->record( $rule['id'], true );
		$this->logger->info(
			'Woo emails: abandoned cart reminder sent',
			array(
				'rule' => $rule['id'],
				'cart' => (int) $cart['id'],
			)
		);
	}

	/**
	 * Recovery coupon data for the reminder (couponCode, couponDiscount, couponExpiry, couponMinSpend), or none.
	 * A failure to create the coupon never blocks the reminder; the template should use {{#if couponCode}}.
	 *
	 * @param array $cart Cart row.
	 */
	private function cart_coupon_data( array $cart ): array {
		$settings = Module::settings();
		if ( null === $this->coupons || ! Recovery_Coupon::enabled( $settings ) ) {
			return array();
		}
		$id = $this->coupons->for_cart( $cart, $settings );
		if ( is_wp_error( $id ) ) {
			// The cooldown is the anti-abuse rule doing its job: the reminder simply goes out without a coupon.
			$level = 'cfbe_cart_coupon_cooldown' === $id->get_error_code() ? 'info' : 'warning';
			$this->logger->$level(
				'Woo emails: recovery coupon not created',
				array(
					'cart'  => (int) ( $cart['id'] ?? 0 ),
					'error' => $id->get_error_message(),
				)
			);
			return array();
		}
		return $this->coupons->email_data( (int) $id );
	}

	/**
	 * Sends via the rule's email type.
	 *
	 * @param array  $rule  Rule.
	 * @param string $email Recipient.
	 * @param array  $data  Template data.
	 * @return array|\WP_Error
	 */
	private function deliver( array $rule, string $email, array $data ) {
		if ( null === $this->client ) {
			return new \WP_Error( 'cfbe_not_configured', __( 'The bluefox connection is not configured.', 'connector-for-bluefox-email' ) );
		}
		if ( Rules::TYPE_TRIGGERED === $rule['type'] ) {
			return $this->client->send_triggered( $rule['email_id'], array( $email ), $data );
		}
		return $this->client->send_transactional( $rule['email_id'], $email, $data );
	}

	/**
	 * Records a failed attempt and re-queues it with backoff when the error is transient.
	 *
	 * @param string    $hook    Job hook.
	 * @param array     $payload Job payload.
	 * @param array     $rule    Rule.
	 * @param \WP_Error $error   Error.
	 * @param array     $context Log context.
	 */
	private function failed( string $hook, array $payload, array $rule, \WP_Error $error, array $context ): void {
		$attempt = max( 1, (int) ( $payload['attempt'] ?? 1 ) );
		$context = array(
			'rule'    => $rule['id'],
			'attempt' => $attempt,
			'error'   => $error->get_error_message(),
		) + $context;
		$this->record( $rule['id'], false, $error->get_error_message() );

		if ( self::is_transient( $error ) && $attempt < self::MAX_ATTEMPTS ) {
			$payload['attempt'] = $attempt + 1;
			$this->jobs->schedule( self::BACKOFF[ $attempt ] ?? 3600, $hook, array( $payload ) );
			$this->logger->warning( 'Woo emails: send failed, retrying', $context );
			return;
		}
		$this->logger->error( 'Woo emails: send failed', $context );
	}

	/**
	 * Whether an error is worth retrying (network, rate limit, server error, invalid response).
	 *
	 * @param \WP_Error $error Error.
	 */
	public static function is_transient( \WP_Error $error ): bool {
		$code = (string) $error->get_error_code();
		if ( in_array( $code, array( 'http_request_failed', 'cfbe_bluefox_invalid_json' ), true ) ) {
			return true;
		}
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		return 408 === $status || 429 === $status || $status >= 500;
	}

	/**
	 * Queues a job now or after a delay.
	 *
	 * @param int    $delay   Seconds.
	 * @param string $hook    Hook.
	 * @param array  $payload Payload (passed as the single job argument).
	 */
	private function schedule( int $delay, string $hook, array $payload ): void {
		if ( $delay > 0 ) {
			$this->jobs->schedule( $delay, $hook, array( $payload ) );
		} else {
			$this->jobs->enqueue( $hook, array( $payload ) );
		}
	}

	/**
	 * Text of the customer note matching a hash ('' if not found).
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $hash  Note hash.
	 */
	private function find_note( $order, string $hash ): string {
		if ( ! function_exists( 'wc_get_order_notes' ) ) {
			return '';
		}
		$notes = wc_get_order_notes(
			array(
				'order_id' => (int) $order->get_id(),
				'type'     => 'customer',
			)
		);
		foreach ( (array) $notes as $note ) {
			$content = is_object( $note ) && isset( $note->content ) ? (string) $note->content : '';
			if ( '' !== $content && self::note_hash( $content ) === $hash ) {
				return Payload_Builder::text( $content );
			}
		}
		return '';
	}

	/**
	 * Per-rule status (last sent / last error).
	 *
	 * @return array<string, array>
	 */
	public static function statuses(): array {
		$status = get_option( self::STATUS_OPTION, array() );
		return is_array( $status ) ? $status : array();
	}

	/**
	 * Records the outcome of a send.
	 *
	 * @param string $rule_id Rule ID.
	 * @param bool   $ok      Success.
	 * @param string $error   Error message.
	 */
	private function record( string $rule_id, bool $ok, string $error = '' ): void {
		$all  = self::statuses();
		$row  = isset( $all[ $rule_id ] ) && is_array( $all[ $rule_id ] ) ? $all[ $rule_id ] : array();
		$time = time();
		if ( $ok ) {
			$row['last_sent'] = $time;
			$row['count']     = (int) ( $row['count'] ?? 0 ) + 1;
		} else {
			$row['last_error']    = function_exists( 'mb_substr' ) ? mb_substr( $error, 0, 200 ) : substr( $error, 0, 200 );
			$row['last_error_at'] = $time;
		}
		$all[ $rule_id ] = $row;
		update_option( self::STATUS_OPTION, $all, false );
	}
}
