<?php
/**
 * Email templates tab: ready-made bluefox emails, created and updated with one click.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Bluefox_Client;
use CFBE\Email_Design;
use CFBE\Email_Template_Sync;
use CFBE\Email_Templates;
use CFBE\Form_Config;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the template cards and handles their buttons (admin-post, nonce + capability).
 */
final class Email_Templates_Tab {

	const TAB        = 'email_templates';
	const ACTION     = 'cfbe_email_template';
	const DESIGN     = 'cfbe_email_design';
	const NOTICE     = 'cfbe_template_notice_';
	const IDENTITIES = 'cfbe_sender_identities';

	/**
	 * Returns the bluefox client (null while not configured).
	 *
	 * @var callable
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param callable $client Returns ?Bluefox_Client.
	 */
	public function __construct( callable $client ) {
		$this->client = $client;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::DESIGN, array( $this, 'handle_design' ) );
	}

	/**
	 * Adds the tab (General group).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs              = (array) $tabs;
		$tabs[ self::TAB ] = array(
			'label'    => __( 'Email templates', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 12,
			'group'    => 'general',
		);
		return $tabs;
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$client     = call_user_func( $this->client );
		$cfbe_ready = $client instanceof Bluefox_Client;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: which card to open after a redirect.
		$cfbe_open  = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		$cfbe_saved = isset( $_GET['design'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$cfbe_notice     = get_transient( self::NOTICE . get_current_user_id() );
		$cfbe_templates  = Email_Templates::available();
		$cfbe_identities = $cfbe_ready ? $this->identities( $client ) : array();
		$cfbe_lists      = Form_Config::known_lists();
		$cfbe_default    = (string) Settings::get( 'api', 'list_id' );
		$cfbe_action     = self::ACTION;
		$cfbe_design     = self::DESIGN;
		delete_transient( self::NOTICE . get_current_user_id() );
		include __DIR__ . '/views/tab-email-templates.php';
	}

	/**
	 * Handles Create / Update / Overwrite / Send test.
	 */
	public function handle(): void {
		$key = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $key );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), '', array( 'response' => 403 ) );
		}
		if ( ! isset( Email_Templates::definitions()[ $key ] ) ) {
			wp_die( esc_html__( 'Unknown email template.', 'connector-for-bluefox-email' ), '', array( 'response' => 400 ) );
		}

		$client = call_user_func( $this->client );
		if ( ! $client instanceof Bluefox_Client ) {
			$this->finish( $key, 'error', __( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ) );
			return;
		}

		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$texts    = Email_Templates::clean_texts( $key, isset( $_POST['texts'] ) && is_array( $_POST['texts'] ) ? wp_unslash( $_POST['texts'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field in clean_texts().
		$identity = isset( $_POST['identity'] ) ? (string) preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['identity'] ) ) ) : '';
		$list_id  = isset( $_POST['list_id'] ) ? Form_Config::clean_list_id( sanitize_text_field( wp_unslash( $_POST['list_id'] ) ) ) : '';
		$sync     = new Email_Template_Sync( $client );

		switch ( $do ) {
			case 'create':
				$result = $sync->create( $key, $texts, $identity, $list_id, ! empty( $_POST['assign'] ) );
				if ( is_wp_error( $result ) ) {
					$this->finish( $key, 'error', $result->get_error_message() );
					return;
				}
				$this->finish( $key, 'success', trim( __( 'The email was created in bluefox.', 'connector-for-bluefox-email' ) . ' ' . implode( ' ', $result['notes'] ) ) );
				return;

			case 'update':
			case 'overwrite':
				$result = $sync->update( $key, $texts, 'overwrite' === $do );
				if ( is_wp_error( $result ) ) {
					$this->finish( $key, 'cfbe_template_changed' === $result->get_error_code() ? 'conflict' : 'error', $result->get_error_message(), $texts );
					return;
				}
				$this->finish( $key, 'success', __( 'The email was updated in bluefox.', 'connector-for-bluefox-email' ) );
				return;

			case 'test':
				$user   = wp_get_current_user();
				$result = $sync->test( $key, (string) $user->user_email );
				$this->finish(
					$key,
					is_wp_error( $result ) ? 'error' : 'success',
					is_wp_error( $result )
						? $result->get_error_message()
						/* translators: %s: email address */
						: sprintf( __( 'A test was sent to %s with sample data.', 'connector-for-bluefox-email' ), $user->user_email )
				);
				return;
		}
		$this->finish( $key, 'error', __( 'Unknown action.', 'connector-for-bluefox-email' ) );
	}

	/**
	 * Saves the design source (WooCommerce emails or the Form tab).
	 */
	public function handle_design(): void {
		check_admin_referer( self::DESIGN );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), '', array( 'response' => 403 ) );
		}
		Email_Design::set_source( isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'    => self::TAB,
					'design' => 'saved',
				),
				admin_url( 'admin.php?page=' . Admin_Page::SLUG )
			)
		);
		exit;
	}

	/**
	 * Stores the result notice and goes back to the card.
	 *
	 * @param string $key     Template key.
	 * @param string $type    success | error | conflict.
	 * @param string $message Message.
	 * @param array  $texts   Unsaved texts to show again (conflict).
	 */
	private function finish( string $key, string $type, string $message, array $texts = array() ): void {
		set_transient(
			self::NOTICE . get_current_user_id(),
			array(
				'template' => $key,
				'type'     => $type,
				'message'  => $message,
				'texts'    => $texts,
			),
			10 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'      => self::TAB,
					'template' => $key,
				),
				admin_url( 'admin.php?page=' . Admin_Page::SLUG )
			) . '#cfbe-template-' . $key
		);
		exit;
	}

	/**
	 * Sender identities (cached for an hour): id => "Name <email>".
	 *
	 * @param Bluefox_Client $client Client.
	 */
	private function identities( Bluefox_Client $client ): array {
		$cached = get_transient( self::IDENTITIES );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$items = $client->get_sender_identities();
		$out   = array();
		foreach ( is_wp_error( $items ) ? array() : $items as $item ) {
			$id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $item['_id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			$email      = (string) ( $item['email'] ?? '' );
			$name       = (string) ( $item['name'] ?? '' );
			$out[ $id ] = '' !== $name ? $name . ' <' . $email . '>' : $email;
		}
		if ( ! is_wp_error( $items ) ) {
			set_transient( self::IDENTITIES, $out, HOUR_IN_SECONDS );
		}
		return $out;
	}
}
