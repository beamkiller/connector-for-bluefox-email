<?php
/**
 * Webhooks settings tab + its AJAX actions.
 *
 * @package CFBE
 */

namespace CFBE\Webhooks;

use CFBE\Admin\Admin_Ajax;
use CFBE\Admin\Admin_Page;
use CFBE\Bluefox_Client;
use CFBE\Logger;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tab "Webhooks" (position 70): endpoint URL, auth methods, event selection, register / test / status of the
 * bluefox project webhook, legacy automation secret, last received events.
 * AJAX: `cfbe_webhook_register`, `cfbe_webhook_test`, `cfbe_webhook_status` (nonce + capability).
 */
final class Webhooks_Tab {

	/**
	 * Event types bluefox can send as a test (GET test-webhook?type=).
	 */
	const TEST_TYPES = array( 'subscribe', 'resubscribe', 'unsubscribe', 'pause-subscription', 'bounce', 'complaint', 'open', 'click', 'sent', 'failed' );

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null $client Client.
	 * @param Logger              $logger Logger.
	 */
	public function __construct( ?Bluefox_Client $client, Logger $logger ) {
		$this->client = $client;
		$this->logger = $logger;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_cfbe_webhook_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_cfbe_webhook_test', array( $this, 'ajax_test' ) );
		add_action( 'wp_ajax_cfbe_webhook_status', array( $this, 'ajax_status' ) );
	}

	/**
	 * Adds the tab.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['webhooks'] = array(
			'label'    => __( 'Webhooks', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 70,
		);
		return $tabs;
	}

	/**
	 * The endpoint bluefox posts to.
	 */
	public static function endpoint_url(): string {
		return rest_url( 'cfbe/v1/bluefox-hook' );
	}

	/**
	 * Enqueues the tab script on this tab only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab switch.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'toplevel_page_' . Admin_Page::SLUG !== $hook || 'webhooks' !== $tab ) {
			return;
		}
		wp_enqueue_script( 'cfbe-webhooks', CFBE_URL . 'modules/webhooks/assets/webhooks.js', array( 'cfbe-admin' ), \CFBE\Plugin::asset_version( 'modules/webhooks/assets/webhooks.js' ), true );
		wp_localize_script(
			'cfbe-webhooks',
			'cfbeWebhooks',
			array(
				'i18n' => array(
					'registered' => __( 'Webhook saved in bluefox.', 'connector-for-bluefox-email' ),
					'sent'       => __( 'Test event sent. Reload this page to see it in the list below.', 'connector-for-bluefox-email' ),
				),
			)
		);
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_values       = Settings::get( Module::AREA );
		$cfbe_option       = Settings::option_name( Module::AREA );
		$cfbe_webhook_url  = self::endpoint_url();
		$cfbe_secret       = (string) Settings::get( 'api', 'webhook_secret' );
		$cfbe_events       = self::event_labels();
		$cfbe_test_types   = self::TEST_TYPES;
		$cfbe_log          = Event_Log::entries();
		$cfbe_configured   = null !== $this->client;
		$cfbe_key_constant = Settings::api_key_from_constant();
		include __DIR__ . '/views/tab-webhooks.php';
	}

	/**
	 * Event labels for the checkboxes.
	 */
	public static function event_labels(): array {
		return array(
			'subscription' => __( 'Subscriptions (subscribe, resubscribe, unsubscribe, pause)', 'connector-for-bluefox-email' ),
			'bounce'       => __( 'Bounces', 'connector-for-bluefox-email' ),
			'complaint'    => __( 'Spam complaints', 'connector-for-bluefox-email' ),
			'failed'       => __( 'Failed sends', 'connector-for-bluefox-email' ),
			'sent'         => __( 'Sent emails', 'connector-for-bluefox-email' ),
			'open'         => __( 'Opens', 'connector-for-bluefox-email' ),
			'click'        => __( 'Clicks', 'connector-for-bluefox-email' ),
		);
	}

	/**
	 * The PATCH /webhook body: our URL, the configured API key as the secret, one flag per event.
	 *
	 * @param string[] $events Selected events.
	 */
	public function webhook_payload( array $events ): array {
		$data = array(
			'url'       => self::endpoint_url(),
			'secretKey' => null !== $this->client ? $this->client->api_key() : '',
		);
		foreach ( Module::EVENTS as $event ) {
			$data[ $event ] = in_array( $event, $events, true );
		}
		return $data;
	}

	/**
	 * AJAX: creates / replaces the project webhook in bluefox with the posted event selection (also saved).
	 */
	public function ajax_register(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		// Nonce verified in authorized().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelisted below.
		$posted = isset( $_POST['events'] ) ? (array) wp_unslash( $_POST['events'] ) : array();
		$events = array_values( array_intersect( Module::EVENTS, array_map( 'sanitize_key', array_map( 'strval', $posted ) ) ) );

		$this->save_events( $events );

		$result = $this->client->update_webhook( $this->webhook_payload( $events ) );
		if ( is_wp_error( $result ) ) {
			$this->logger->error( 'Registering the bluefox webhook failed', array( 'error' => $result->get_error_message() ) );
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success( $this->describe( is_array( $result ) ? $result : array() ) );
	}

	/**
	 * AJAX: asks bluefox to send a test event of the posted type.
	 */
	public function ajax_test(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorized().
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'subscribe';
		if ( ! in_array( $type, self::TEST_TYPES, true ) ) {
			$type = 'subscribe';
		}
		$result = $this->client->test_webhook( $type );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success( array( 'type' => $type ) );
	}

	/**
	 * AJAX: the webhook currently configured in bluefox, compared with ours.
	 */
	public function ajax_status(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		$result = $this->client->get_webhook();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success( $this->describe( is_array( $result ) ? $result : array() ) );
	}

	/**
	 * Summary of a bluefox webhook config for the tab (never includes the secret).
	 *
	 * @param array $config Webhook config from bluefox (empty = none).
	 * @return array{configured: bool, url: string, matches: bool, key_matches: bool, events: string[], message: string}
	 */
	public function describe( array $config ): array {
		$url    = isset( $config['url'] ) ? (string) $config['url'] : '';
		$events = array();
		foreach ( Module::EVENTS as $event ) {
			if ( ! empty( $config[ $event ] ) ) {
				$events[] = $event;
			}
		}
		$secret      = isset( $config['secretKey'] ) ? (string) $config['secretKey'] : '';
		$key_matches = '' === $secret || ( null !== $this->client && hash_equals( $this->client->api_key(), $secret ) );
		$matches     = '' !== $url && untrailingslashit( $url ) === untrailingslashit( self::endpoint_url() );

		if ( '' === $url ) {
			$message = __( 'No webhook is configured in bluefox yet.', 'connector-for-bluefox-email' );
		} elseif ( ! $matches ) {
			/* translators: %s: URL configured in bluefox */
			$message = sprintf( __( 'Warning: bluefox sends events to another URL (%s). Click “Register webhook in bluefox” to point it here.', 'connector-for-bluefox-email' ), $url );
		} elseif ( ! $key_matches ) {
			$message = __( 'Warning: bluefox signs the webhook with a different API key than the one configured here, so this site will reject the events. Register the webhook again.', 'connector-for-bluefox-email' );
		} else {
			/* translators: %s: comma separated event list */
			$message = sprintf( __( 'Webhook active for this site. Events: %s.', 'connector-for-bluefox-email' ), $events ? implode( ', ', $events ) : __( 'none', 'connector-for-bluefox-email' ) );
		}

		return array(
			'configured'  => '' !== $url,
			'url'         => $url,
			'matches'     => $matches,
			'key_matches' => $key_matches,
			'events'      => $events,
			'message'     => $message,
		);
	}

	/**
	 * Stores the event selection in the `webhooks` area.
	 *
	 * @param string[] $events Selected events.
	 */
	private function save_events( array $events ): void {
		$values = array();
		foreach ( Module::EVENTS as $event ) {
			$values[ 'event_' . $event ] = in_array( $event, $events, true );
		}
		Settings::update( Module::AREA, $values );
	}

	/**
	 * Nonce + capability + configured client; sends the JSON error itself.
	 */
	private function authorized(): bool {
		if ( false === check_ajax_referer( Admin_Ajax::NONCE, 'nonce', false ) || ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return false;
		}
		if ( null === $this->client ) {
			wp_send_json_error( array( 'message' => __( 'Connect to bluefox first (General tab: Project ID and API key).', 'connector-for-bluefox-email' ) ), 400 );
			return false;
		}
		return true;
	}
}
