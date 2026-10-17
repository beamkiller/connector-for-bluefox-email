<?php
/**
 * Newsletter opt-in on the WooCommerce checkout (classic and Blocks) and, optionally, on My Account.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Unticked checkbox below the billing e-mail (classic) / in the contact step (Checkout block). Consent is stored on
 * the order; the subscription runs in the background so the checkout never waits for (or fails because of) bluefox.
 */
final class Checkout_Optin {

	const FIELD       = 'cfbe_newsletter';
	const BLOCK_FIELD = 'cfbe/newsletter';
	const JOB         = 'cfbe_checkout_subscribe';
	const ACCOUNT_JOB = 'cfbe_account_subscribe';

	const META_OPTIN  = '_cfbe_optin';
	const META_AT     = '_cfbe_optin_at';
	const META_TEXT   = '_cfbe_optin_text';
	const META_IP     = '_cfbe_optin_ip';
	const META_RESULT = '_cfbe_optin_result';

	// Where the Checkout block stores contact / order fields (CheckoutFields::OTHER_FIELDS_PREFIX).
	const BLOCK_META_PREFIX = '_wc_other/';

	// Markup allowed in the label: the privacy link and the small note.
	const LABEL_TAGS = array(
		'a'     => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
		'br'    => array(),
		'small' => array( 'class' => true ),
	);

	// Fallback priority of billing_email (WooCommerce default); the checkbox goes right after it.
	const EMAIL_PRIORITY = 110;

	// Local statuses that mean "already our subscriber": don't offer the checkbox again.
	const SUBSCRIBED = array( Subscriber_Repository::STATUS_CONFIRMED, Subscriber_Repository::STATUS_REWARDED, Subscriber_Repository::STATUS_EXISTING );

	/**
	 * Signup service.
	 *
	 * @var Subscription_Service
	 */
	private $service;

	/**
	 * Background jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Subscribers repository.
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
	 * Loads an order by ID (wc_get_order; overridable in tests).
	 *
	 * @var callable
	 */
	private $order_loader;

	/**
	 * Reads the Checkout block field from an order: fn( WC_Order ): mixed.
	 *
	 * @var callable
	 */
	private $field_reader;

	/**
	 * Loads a customer by user ID: fn( int ): ?WC_Customer.
	 *
	 * @var callable
	 */
	private $customer_loader;

	/**
	 * Constructor.
	 *
	 * @param Subscription_Service  $service         Signup service.
	 * @param Jobs                  $jobs            Jobs.
	 * @param Subscriber_Repository $repository      Repository.
	 * @param Logger                $logger          Logger.
	 * @param callable|null         $order_loader    Order loader.
	 * @param callable|null         $field_reader    Checkout block field reader.
	 * @param callable|null         $customer_loader Customer loader.
	 */
	public function __construct( Subscription_Service $service, Jobs $jobs, Subscriber_Repository $repository, Logger $logger, ?callable $order_loader = null, ?callable $field_reader = null, ?callable $customer_loader = null ) {
		$this->service         = $service;
		$this->jobs            = $jobs;
		$this->repository      = $repository;
		$this->logger          = $logger;
		$this->order_loader    = $order_loader ? $order_loader : 'wc_get_order';
		$this->field_reader    = $field_reader ? $field_reader : array( self::class, 'read_block_field' );
		$this->customer_loader = $customer_loader ? $customer_loader : array( self::class, 'load_customer' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		// Classic (shortcode) checkout.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_field' ) );
		add_filter( 'woocommerce_form_field_checkbox', array( $this, 'strip_optional' ), 10, 2 );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'queue' ), 10, 1 );

		// Checkout block (Store API).
		if ( did_action( 'woocommerce_init' ) ) {
			$this->register_block_field();
		} else {
			add_action( 'woocommerce_init', array( $this, 'register_block_field' ) );
		}
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_block_order' ), 10, 1 );

		add_action( self::JOB, array( $this, 'process' ), 10, 1 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_order_info' ) );

		if ( ! empty( Settings::get( 'checkout', 'account_optin' ) ) ) {
			add_action( 'woocommerce_edit_account_form', array( $this, 'render_account_field' ) );
			add_action( 'woocommerce_save_account_details', array( $this, 'save_account' ), 10, 1 );
		}
		// Always hooked so jobs queued before the option was turned off still run.
		add_action( self::ACCOUNT_JOB, array( $this, 'process_account' ), 10, 3 );
	}

	/**
	 * Adds the checkbox to the billing fields, right after billing_email.
	 *
	 * @param array $fields Checkout fields.
	 */
	public function add_field( $fields ) {
		if ( ! is_array( $fields ) || ! $this->should_offer( $this->customer_email() ) ) {
			return $fields;
		}
		$settings = Settings::get( 'checkout' );
		$priority = (int) ( $fields['billing']['billing_email']['priority'] ?? self::EMAIL_PRIORITY );

		$fields['billing'][ self::FIELD ] = array(
			'type'     => 'checkbox',
			'label'    => self::label_html( $settings ),
			'required' => false,
			'class'    => array( 'form-row-wide', 'cfbe-checkout-optin' ),
			'priority' => $priority + 1,
		);
		return $fields;
	}

	/**
	 * Removes WooCommerce's "(optional)" marker from our checkbox: a newsletter opt-in is optional by nature.
	 *
	 * @param string $field Field HTML.
	 * @param string $key   Field key.
	 */
	public function strip_optional( $field, $key ) {
		if ( self::FIELD !== $key ) {
			return $field;
		}
		return (string) preg_replace( '#(&nbsp;)?\s*<span class="optional">.*?</span>#s', '', (string) $field );
	}

	/**
	 * Registers the checkbox with the Additional Checkout Fields API (Checkout block), unless the logged-in
	 * customer is already subscribed. The API takes a plain-text label, so the privacy link is not part of it.
	 */
	public function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! $this->should_offer( $this->customer_email() ) ) {
			return;
		}
		$label = self::label_text( Settings::get( 'checkout' ) );
		try {
			woocommerce_register_additional_checkout_field(
				array(
					'id'                => self::BLOCK_FIELD,
					'label'             => $label,
					'optionalLabel'     => $label, // No "(optional)" suffix: an opt-in is optional by nature.
					'location'          => 'contact',
					'type'              => 'checkbox',
					'required'          => false,
					'sanitize_callback' => static function ( $value ) {
						return (bool) $value;
					},
				)
			);
		} catch ( \Throwable $e ) {
			$this->logger->warning( 'Checkout block field not registered', array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Whether the checkbox is shown.
	 *
	 * @param string $email Logged-in customer's e-mail ('' for guests).
	 */
	public function should_offer( string $email ): bool {
		if ( '' === $email ) {
			return true;
		}
		$row = $this->repository->find_by_email( $email );
		if ( $row && 'unsubscribed' === ( $row['list_status'] ?? '' ) ) {
			return true; // Left the list since: may join again (bluefox re-sends its DOI email).
		}
		return ! ( $row && in_array( (string) $row['status'], self::SUBSCRIBED, true ) );
	}

	/**
	 * Escaped label: the configured text ({discount} filled) + privacy link + small print. WooCommerce prints it raw.
	 *
	 * @param array $settings `checkout` settings.
	 */
	public static function label_html( array $settings ): string {
		$html = esc_html( self::label_text( $settings ) );
		$url  = (string) Settings::get( 'form', 'privacy_url' );
		if ( '' !== $url ) {
			$html .= ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy policy', 'connector-for-bluefox-email' ) . '</a>';
		}
		$note = trim( (string) ( $settings['description'] ?? '' ) );
		if ( '' !== $note ) {
			$html .= '<br><small class="cfbe-checkout-optin__note">' . esc_html( $note ) . '</small>';
		}
		return $html;
	}

	/**
	 * Plain label text with {discount} filled.
	 *
	 * @param array $settings `checkout` settings.
	 */
	public static function label_text( array $settings ): string {
		$text = (string) ( $settings['label'] ?? '' );
		if ( false !== strpos( $text, '{discount}' ) ) {
			$coupon = Settings::get( 'coupon' );
			$text   = str_replace( '{discount}', Coupon_Data::discount_label( (string) $coupon['discount_type'], (float) $coupon['amount'] ), $text );
		}
		return $text;
	}

	/**
	 * Classic checkout: stores the consent on the order (only when ticked).
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Posted checkout data.
	 */
	public function save_meta( $order_id, $data ): void {
		if ( empty( $data[ self::FIELD ] ) ) {
			return;
		}
		$order = call_user_func( $this->order_loader, (int) $order_id );
		if ( $order ) {
			$this->store_consent( $order );
		}
	}

	/**
	 * Classic checkout: queues the subscription; nothing else happens inside the checkout request.
	 *
	 * @param int $order_id Order ID.
	 */
	public function queue( $order_id ): void {
		$order = call_user_func( $this->order_loader, (int) $order_id );
		if ( $order && 'yes' === $order->get_meta( self::META_OPTIN ) ) {
			$this->jobs->enqueue( self::JOB, array( (int) $order_id ) );
		}
	}

	/**
	 * Checkout block: reads the field from the order, stores the consent and queues the subscription.
	 * The Store API can run this more than once for the same order; consent is stored once, the job is idempotent.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function process_block_order( $order ): void {
		if ( ! is_object( $order ) ) {
			return;
		}
		try {
			if ( ! call_user_func( $this->field_reader, $order ) ) {
				return;
			}
			if ( 'yes' !== $order->get_meta( self::META_OPTIN ) ) {
				$this->store_consent( $order );
			}
			if ( '' === (string) $order->get_meta( self::META_RESULT ) ) {
				$this->jobs->enqueue( self::JOB, array( (int) $order->get_id() ) );
			}
			$this->forget_customer_choice( $order );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Checkout block opt-in failed',
				array(
					'order' => is_callable( array( $order, 'get_id' ) ) ? (int) $order->get_id() : 0,
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Background job: subscribes the buyer once per order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function process( $order_id ): void {
		try {
			$order = call_user_func( $this->order_loader, (int) $order_id );
			if ( ! $order || 'yes' !== $order->get_meta( self::META_OPTIN ) || '' !== (string) $order->get_meta( self::META_RESULT ) ) {
				return;
			}
			$result = $this->service->subscribe(
				array(
					'email'          => (string) $order->get_billing_email(),
					'name'           => self::order_name( $order ),
					'consent'        => true,
					'consent_text'   => (string) $order->get_meta( self::META_TEXT ),
					'ip'             => (string) $order->get_meta( self::META_IP ),
					'order_id'       => (int) $order->get_id(),
					'list_id'        => (string) Settings::get( 'checkout', 'list_id' ),
					'tags'           => self::extra_tags(),
					'owner_verified' => self::ordered_by_owner( $order ),
				),
				'checkout'
			);
			$order->update_meta_data( self::META_RESULT, $result->code );
			$order->save();
			$order->add_order_note( self::note( $result->code ) );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Checkout subscription failed',
				array(
					'order' => (int) $order_id,
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * My Account → Account details: the opt-in checkbox (only when not subscribed yet).
	 */
	public function render_account_field(): void {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID || ! $this->should_offer( strtolower( (string) $user->user_email ) ) ) {
			return;
		}
		printf(
			'<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide cfbe-account-optin"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox"><input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="%1$s" value="1"> <span>%2$s</span></label></p>',
			esc_attr( self::FIELD ),
			wp_kses( self::label_html( Settings::get( 'checkout' ) ), self::LABEL_TAGS )
		);
	}

	/**
	 * My Account → Account details saved (WooCommerce verified its nonce before this action): queue the signup.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_account( $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks save-account-details-nonce before firing this action.
		if ( empty( $_POST[ self::FIELD ] ) ) {
			return;
		}
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! $this->should_offer( strtolower( (string) $user->user_email ) ) ) {
			return;
		}
		$url  = (string) Settings::get( 'form', 'privacy_url' );
		$text = self::label_text( Settings::get( 'checkout' ) ) . ( '' !== $url ? ' [' . $url . ']' : '' );
		$this->jobs->enqueue( self::ACCOUNT_JOB, array( (int) $user_id, $text, self::client_ip() ) );
	}

	/**
	 * Background job: subscribes a customer who ticked the box on My Account.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $consent_text Consent text shown.
	 * @param string $ip           Client IP at consent time.
	 */
	public function process_account( $user_id, $consent_text = '', $ip = '' ): void {
		try {
			$user = get_userdata( (int) $user_id );
			if ( ! $user || '' === (string) $user->user_email ) {
				return;
			}
			$result = $this->service->subscribe(
				array(
					'email'           => (string) $user->user_email,
					'name'            => trim( $user->first_name . ' ' . $user->last_name ),
					'consent'         => true,
					'consent_text'    => (string) $consent_text,
					'ip'              => (string) $ip,
					'list_id'         => (string) Settings::get( 'checkout', 'list_id' ),
					'tags'            => self::extra_tags(),
					'skip_form_rules' => true,
					'owner_verified'  => true, // Signed-in user, their own account address.
				),
				'account'
			);
			$this->logger->info(
				'My Account opt-in processed',
				array(
					'user'   => (int) $user_id,
					'result' => $result->code,
				)
			);
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'My Account subscription failed',
				array(
					'user'  => (int) $user_id,
					'error' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Private order note for a result code.
	 *
	 * @param string $code Subscribe_Result code.
	 */
	public static function note( string $code ): string {
		switch ( $code ) {
			case Subscribe_Result::PENDING:
			case Subscribe_Result::RESENT:
				return __( 'Newsletter: the customer subscribed, the confirmation email has been sent.', 'connector-for-bluefox-email' );
			case Subscribe_Result::ALREADY:
				return __( 'Newsletter: the customer is already a subscriber (no new coupon).', 'connector-for-bluefox-email' );
			case Subscribe_Result::QUEUED:
				return __( 'Newsletter: bluefox is unavailable right now, the subscription will be retried later.', 'connector-for-bluefox-email' );
			default:
				return __( 'Newsletter: the subscription failed (details: bluefox.email → Logs).', 'connector-for-bluefox-email' );
		}
	}

	/**
	 * Consent proof on the order edit screen (HPOS and legacy order screens).
	 *
	 * @param \WC_Order $order Order.
	 */
	public function admin_order_info( $order ): void {
		if ( ! is_object( $order ) || 'yes' !== $order->get_meta( self::META_OPTIN ) ) {
			return;
		}
		$at     = (string) $order->get_meta( self::META_AT );
		$result = (string) $order->get_meta( self::META_RESULT );
		$format = trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		printf(
			'<p class="cfbe-order-optin"><strong>%1$s</strong> %2$s%3$s</p>',
			esc_html__( 'Newsletter:', 'connector-for-bluefox-email' ),
			esc_html(
				sprintf(
					/* translators: %s: date and time of the consent */
					__( 'subscribed at checkout (%s)', 'connector-for-bluefox-email' ),
					'' !== $at ? get_date_from_gmt( $at, '' !== $format ? $format : 'Y-m-d H:i' ) : '–'
				)
			),
			'' !== $result ? '<br>' . esc_html( self::note( $result ) ) : ''
		);
	}

	/**
	 * Extra tags from the Checkout tab (comma separated) as clean slugs.
	 *
	 * @return string[]
	 */
	public static function extra_tags(): array {
		return Signup_Validator::clean_tags( explode( ',', (string) Settings::get( 'checkout', 'tags' ) ) );
	}

	/**
	 * Subscriber name from the billing name ("First Last"; filterable for locales that put the family name first).
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_name( $order ): string {
		$first = (string) $order->get_billing_first_name();
		$last  = (string) $order->get_billing_last_name();
		/**
		 * Filters the subscriber name built from the order's billing name.
		 *
		 * @param string    $name  "First Last".
		 * @param string    $first Billing first name.
		 * @param string    $last  Billing last name.
		 * @param \WC_Order $order Order.
		 */
		return trim( (string) apply_filters( 'cfbe_checkout_subscriber_name', trim( $first . ' ' . $last ), $first, $last, $order ) );
	}

	/**
	 * Default Checkout block field reader (CheckoutFields service; the order meta as a fallback).
	 *
	 * @param \WC_Order $order Order.
	 * @return mixed
	 */
	public static function read_block_field( $order ) {
		$service = 'Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';
		if ( class_exists( $service ) ) {
			// WooCommerce 9.x/10.x: Blocks container; newer versions: the main WooCommerce container.
			$containers = array();
			if ( function_exists( 'wc_get_container' ) ) {
				$containers[] = static fn() => wc_get_container();
			}
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
				$containers[] = static fn() => \Automattic\WooCommerce\Blocks\Package::container();
			}
			foreach ( $containers as $container ) {
				try {
					return $container()->get( $service )->get_field_from_object( self::BLOCK_FIELD, $order, 'other' );
				} catch ( \Throwable $e ) {
					continue; // Not registered in this container.
				}
			}
		}
		return $order->get_meta( self::BLOCK_META_PREFIX . self::BLOCK_FIELD );
	}

	/**
	 * Default customer loader: the session customer when it is the same user, else a fresh WC_Customer.
	 *
	 * @param int $user_id User ID.
	 * @return \WC_Customer|null
	 */
	public static function load_customer( int $user_id ) {
		if ( function_exists( 'WC' ) && WC()->customer && (int) WC()->customer->get_id() === $user_id ) {
			return WC()->customer;
		}
		return class_exists( '\WC_Customer' ) ? new \WC_Customer( $user_id ) : null;
	}

	/**
	 * Stores the consent proof on the order (HPOS-safe CRUD).
	 *
	 * @param \WC_Order $order Order.
	 */
	private function store_consent( $order ): void {
		$url = (string) Settings::get( 'form', 'privacy_url' );
		$order->update_meta_data( self::META_OPTIN, 'yes' );
		$order->update_meta_data( self::META_AT, gmdate( 'Y-m-d H:i:s' ) );
		$order->update_meta_data( self::META_TEXT, self::label_text( Settings::get( 'checkout' ) ) . ( '' !== $url ? ' [' . $url . ']' : '' ) );
		$order->update_meta_data( self::META_IP, self::client_ip() );
		$order->save();
	}

	/**
	 * The Checkout block also saves contact fields on the customer, which would show the box pre-ticked next
	 * time. Consent is given per order, so the customer copy is removed once the order has it.
	 *
	 * @param \WC_Order $order Order.
	 */
	private function forget_customer_choice( $order ): void {
		$key = self::BLOCK_META_PREFIX . self::BLOCK_FIELD;

		// Session customer (guests and logged-in shoppers alike): the value the next checkout is pre-filled from.
		if ( function_exists( 'WC' ) && WC()->customer instanceof \WC_Customer ) {
			WC()->customer->delete_meta_data( $key );
			WC()->customer->save();
		}

		$user_id = is_callable( array( $order, 'get_customer_id' ) ) ? (int) $order->get_customer_id() : 0;
		if ( $user_id <= 0 ) {
			return;
		}
		$customer = call_user_func( $this->customer_loader, $user_id );
		if ( ! is_object( $customer ) ) {
			return;
		}
		$customer->delete_meta_data( $key );
		$customer->save();
	}

	/**
	 * Client IP for the consent record: the same source as everywhere else in the plugin (WooCommerce's own helper
	 * trusts X-Forwarded-For, which anyone can send).
	 */
	private static function client_ip(): string {
		return Consent_Ip::prepare( Rate_Limiter::client_ip() );
	}

	/**
	 * Whether a signed-in customer ordered with their own account address (then an existing contact may be updated).
	 *
	 * @param object $order Order.
	 */
	private static function ordered_by_owner( $order ): bool {
		$user_id = is_callable( array( $order, 'get_customer_id' ) ) ? (int) $order->get_customer_id() : 0;
		if ( $user_id <= 0 || ! function_exists( 'get_userdata' ) ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && '' !== (string) $user->user_email && strtolower( (string) $user->user_email ) === strtolower( (string) $order->get_billing_email() );
	}

	/**
	 * Logged-in customer's billing e-mail ('' for guests); the account e-mail when there is no billing e-mail.
	 */
	private function customer_email(): string {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return '';
		}
		if ( function_exists( 'WC' ) && WC()->customer ) {
			$email = (string) WC()->customer->get_billing_email();
			if ( '' !== $email ) {
				return strtolower( $email );
			}
		}
		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;
		return $user ? strtolower( (string) $user->user_email ) : '';
	}
}
