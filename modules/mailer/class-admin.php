<?php
/**
 * Mailer admin: "Email delivery" tab, AJAX actions and the misconfiguration notice.
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Admin\Admin_Page;
use CFBE\Bluefox_Client;
use CFBE\Plugin;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX actions (nonce `cfbe_mailer` + capability on each): `cfbe_mailer_load_emails`,
 * `cfbe_mailer_load_identities`, `cfbe_mailer_create_template`, `cfbe_mailer_send_test`.
 */
final class Admin {

	const NONCE = 'cfbe_mailer';

	const TAB = 'mailer';

	const TEMPLATE_NAME = 'WordPress mail (Connector for bluefox.email)';

	/**
	 * Mailer.
	 *
	 * @var Mailer
	 */
	private $mailer;

	/**
	 * Returns the bluefox client or null.
	 *
	 * @var callable
	 */
	private $client_resolver;

	/**
	 * Constructor.
	 *
	 * @param Mailer          $mailer Mailer.
	 * @param Plugin|callable $client Plugin, or fn(): ?Bluefox_Client (tests).
	 */
	public function __construct( Mailer $mailer, $client ) {
		$this->mailer          = $mailer;
		$this->client_resolver = is_callable( $client ) ? $client : static function () use ( $client ) {
			return $client->client();
		};
	}

	/**
	 * Hooks the tab, the assets, the AJAX actions and the notice.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'wp_ajax_cfbe_mailer_load_emails', array( $this, 'ajax_load_emails' ) );
		add_action( 'wp_ajax_cfbe_mailer_load_identities', array( $this, 'ajax_load_identities' ) );
		add_action( 'wp_ajax_cfbe_mailer_create_template', array( $this, 'ajax_create_template' ) );
		add_action( 'wp_ajax_cfbe_mailer_send_test', array( $this, 'ajax_send_test' ) );
	}

	/**
	 * Adds the "Email delivery" tab.
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs              = (array) $tabs;
		$tabs[ self::TAB ] = array(
			'label'    => __( 'Email delivery', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 55,
		);
		return $tabs;
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_values      = Options::all();
		$cfbe_emails      = Options::cached_emails();
		$cfbe_connected   = null !== $this->client();
		$cfbe_configured  = $this->mailer->is_configured();
		$cfbe_active      = $this->mailer->is_active();
		$cfbe_log         = array_reverse( Delivery_Log::entries() );
		$cfbe_option      = Settings::option_name( Options::AREA );
		$cfbe_admin_email = (string) get_option( 'admin_email' );
		include __DIR__ . '/views/tab-mailer.php';
	}

	/**
	 * Enqueues the tab script on the plugin page, mailer tab only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ): void {
		// Read-only navigation parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_' . Admin_Page::SLUG !== $hook || self::TAB !== $tab ) {
			return;
		}
		wp_enqueue_script( 'cfbe-mailer-admin', CFBE_URL . 'modules/mailer/assets/mailer-admin.js', array(), CFBE_VERSION, true );
		wp_localize_script(
			'cfbe-mailer-admin',
			'cfbeMailer',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'working'       => __( 'Working…', 'connector-for-bluefox-email' ),
					'error'         => __( 'Something went wrong.', 'connector-for-bluefox-email' ),
					'loaded'        => __( 'Transactional emails loaded.', 'connector-for-bluefox-email' ),
					'none'          => __( '— Select —', 'connector-for-bluefox-email' ),
					'identities'    => __( 'Choose the sender identity, then create the template.', 'connector-for-bluefox-email' ),
					'noIdentity'    => __( 'No sender identity found in the bluefox project.', 'connector-for-bluefox-email' ),
					'defaultSender' => __( 'The project’s default sender (no verified sender identity yet)', 'connector-for-bluefox-email' ),
				),
			)
		);
	}

	/**
	 * Notice on the Plugins screen when routing is switched on but cannot work.
	 */
	public function notice(): void {
		global $pagenow;
		if ( 'plugins.php' !== $pagenow || ! current_user_can( Admin_Page::capability() ) ) {
			return;
		}
		$values = Options::all();
		if ( empty( $values['enabled'] ) || $this->mailer->is_configured() ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Connector for bluefox.email: sending WordPress emails through bluefox is enabled, but the connection or the transactional email is not set, so WordPress sends emails the normal way.', 'connector-for-bluefox-email' ),
			esc_url( admin_url( 'admin.php?page=' . Admin_Page::SLUG . '&tab=' . self::TAB ) ),
			esc_html__( 'Email delivery settings', 'connector-for-bluefox-email' )
		);
	}

	/**
	 * Loads the transactional emails and caches id => name.
	 */
	public function ajax_load_emails(): void {
		$client = $this->authorized_client();
		if ( null === $client ) {
			return;
		}
		$items = $client->get_transactional_emails();
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
			return;
		}
		$emails = array();
		foreach ( $items as $item ) {
			$id = (string) ( $item['_id'] ?? '' );
			if ( '' !== $id ) {
				$emails[ $id ] = (string) ( $item['name'] ?? $id );
			}
		}
		set_transient( Options::EMAILS_CACHE, $emails, DAY_IN_SECONDS );
		wp_send_json_success( array( 'emails' => self::pairs( $emails ) ) );
	}

	/**
	 * Loads the sender identities for the template creation.
	 */
	public function ajax_load_identities(): void {
		$client = $this->authorized_client();
		if ( null === $client ) {
			return;
		}
		$items = $client->get_sender_identities();
		if ( is_wp_error( $items ) ) {
			wp_send_json_error( array( 'message' => $items->get_error_message() ), 400 );
			return;
		}
		$out = array();
		foreach ( $items as $item ) {
			$id = (string) ( $item['_id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$email = (string) ( $item['email'] ?? '' );
			$name  = (string) ( $item['name'] ?? '' );
			$out[] = array(
				'id'   => $id,
				'name' => '' !== $name ? $name . ' <' . $email . '>' : $email,
			);
		}
		wp_send_json_success( array( 'identities' => $out ) );
	}

	/**
	 * Creates the generic "WordPress mail" transactional email and selects it.
	 */
	public function ajax_create_template(): void {
		$client = $this->authorized_client();
		if ( null === $client ) {
			return;
		}
		$identity = isset( $_POST['identity'] ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['identity'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorized_client().
		// Empty = no verified sender identity yet (e.g. sandbox): bluefox uses the project's default sender.
		$created = $client->create_transactional_email( self::template( $identity ) );
		if ( is_wp_error( $created ) ) {
			wp_send_json_error( array( 'message' => $created->get_error_message() ), 400 );
			return;
		}
		$id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $created['_id'] ?? '' ) );
		if ( '' === $id ) {
			wp_send_json_error( array( 'message' => __( 'bluefox did not return the ID of the new transactional email.', 'connector-for-bluefox-email' ) ), 400 );
			return;
		}

		$emails        = Options::cached_emails();
		$emails[ $id ] = self::TEMPLATE_NAME;
		set_transient( Options::EMAILS_CACHE, $emails, DAY_IN_SECONDS );

		$option                     = Settings::option_name( Options::AREA );
		$stored                     = get_option( $option, array() );
		$stored                     = is_array( $stored ) ? $stored : array();
		$stored['transactional_id'] = $id;
		update_option( $option, array_merge( Options::DEFAULTS, $stored ) );

		wp_send_json_success(
			array(
				'id'      => $id,
				'emails'  => self::pairs( $emails ),
				'message' => __( 'The WordPress mail template was created in bluefox and selected.', 'connector-for-bluefox-email' ),
			)
		);
	}

	/**
	 * Sends a test email through bluefox to the entered address.
	 */
	public function ajax_send_test(): void {
		if ( null === $this->authorized_client() ) {
			return;
		}
		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorized_client().
		if ( '' === $to || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter a valid email address.', 'connector-for-bluefox-email' ) ), 400 );
			return;
		}
		$result = $this->mailer->send_test( $to );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		wp_send_json_success( array( 'message' => __( 'Test email accepted by bluefox. Check the inbox (and the spam folder).', 'connector-for-bluefox-email' ) ) );
	}

	/**
	 * Data of the generic transactional email: everything comes from the send's `data`.
	 *
	 * Triple-stash keeps the HTML (and `&` in subjects) unescaped.
	 *
	 * @param string $identity Sender identity ID ('' = the project's default sender).
	 */
	public static function template( string $identity ): array {
		$template = array(
			'name'        => self::TEMPLATE_NAME,
			'subject'     => '{{{subject}}}',
			'previewText' => '{{previewText}}',
			'type'        => 'html',
			'document'    => '{{{html}}}',
		);
		if ( '' !== $identity ) {
			$template['senderIdentity'] = $identity;
		}
		return $template;
	}

	/**
	 * Verifies nonce + capability and returns the client (sends the JSON error otherwise).
	 */
	private function authorized_client(): ?Bluefox_Client {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'The page has expired, please reload it.', 'connector-for-bluefox-email' ) ), 403 );
			return null;
		}
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return null;
		}
		$client = $this->client();
		if ( null === $client ) {
			wp_send_json_error( array( 'message' => __( 'Set up the bluefox connection on the General tab first.', 'connector-for-bluefox-email' ) ), 400 );
		}
		return $client;
	}

	/**
	 * The bluefox client, if configured.
	 */
	private function client(): ?Bluefox_Client {
		$client = call_user_func( $this->client_resolver );
		return $client instanceof Bluefox_Client ? $client : null;
	}

	/**
	 * Id => name map as a JSON-friendly list.
	 *
	 * @param array<string, string> $map Map.
	 */
	private static function pairs( array $map ): array {
		$out = array();
		foreach ( $map as $id => $name ) {
			$out[] = array(
				'id'   => (string) $id,
				'name' => (string) $name,
			);
		}
		return $out;
	}
}
