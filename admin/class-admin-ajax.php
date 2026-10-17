<?php
/**
 * Admin AJAX actions of the Connection tab.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Bluefox_Client;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * `cfbe_test_connection`, `cfbe_load_lists`, `cfbe_regen_secret`. Nonce + capability on every call.
 */
final class Admin_Ajax {

	const NONCE = 'cfbe_admin';

	/**
	 * Builds a client from (project_id, api_key).
	 *
	 * @var callable
	 */
	private $client_factory;

	/**
	 * Constructor.
	 *
	 * @param callable|null $client_factory fn( string $project_id, string $api_key ): Bluefox_Client (tests).
	 */
	public function __construct( ?callable $client_factory = null ) {
		$this->client_factory = $client_factory ? $client_factory : static function ( string $project_id, string $api_key ) {
			return new Bluefox_Client( $project_id, $api_key );
		};
	}

	/**
	 * Hooks the AJAX actions.
	 */
	public function register(): void {
		add_action( 'wp_ajax_cfbe_test_connection', array( $this, 'test_connection' ) );
		add_action( 'wp_ajax_cfbe_load_lists', array( $this, 'load_lists' ) );
		add_action( 'wp_ajax_cfbe_regen_secret', array( $this, 'regen_secret' ) );
	}

	/**
	 * Tests the connection with the values currently typed in the form (falling back to stored ones).
	 */
	public function test_connection(): void {
		$client = $this->client_from_request();
		if ( null === $client ) {
			return;
		}

		$project = $client->get_project();
		if ( is_wp_error( $project ) ) {
			wp_send_json_error( array( 'message' => $project->get_error_message() ), 400 );
			return;
		}

		$name   = (string) ( $project['name'] ?? '' );
		$status = (string) ( $project['status'] ?? '' );

		// bluefox contacts have no built-in name: it only persists via a custom field called "name".
		$warning = '';
		$fields  = $client->get_contact_fields();
		if ( ! is_wp_error( $fields ) ) {
			$required = array( 'name' );
			if ( ! empty( Settings::get( 'coupon', 'store_code_in_bluefox' ) ) ) {
				$required = array_keys( Coupon_Tab::REQUIRED_FIELDS );
			}
			$missing = array_diff( $required, array_column( $fields, 'name' ) );
			if ( $missing ) {
				/* translators: %s: comma separated field names */
				$warning = sprintf( __( 'Warning: missing bluefox contact fields: %s. Create them on the Coupon tab ("Create bluefox fields"), otherwise the name / coupon data is not stored (without "name" not even the subscribers\' names are kept).', 'connector-for-bluefox-email' ), implode( ', ', $missing ) );
			}
		}

		wp_send_json_success(
			array(
				'name'    => $name,
				'status'  => $status,
				/* translators: 1: bluefox project name, 2: project status (e.g. sandbox) */
				'message' => sprintf( __( 'Connected: %1$s (%2$s)', 'connector-for-bluefox-email' ), $name, $status ),
				'warning' => $warning,
			)
		);
	}

	/**
	 * Loads the subscriber lists and caches id → name for the list_id whitelist.
	 */
	public function load_lists(): void {
		$client = $this->client_from_request();
		if ( null === $client ) {
			return;
		}

		$lists = $client->get_lists();
		if ( is_wp_error( $lists ) ) {
			wp_send_json_error( array( 'message' => $lists->get_error_message() ), 400 );
			return;
		}

		$cache = array();
		$out   = array();
		foreach ( $lists as $list ) {
			$id = (string) ( $list['_id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$cache[ $id ] = (string) ( $list['name'] ?? $id );
			$out[]        = array(
				'id'   => $id,
				'name' => $cache[ $id ],
				'doi'  => ! empty( $list['doubleOptIn']['active'] ),
			);
		}
		Settings::remember_lists( $cache );

		wp_send_json_success( array( 'lists' => $out ) );
	}

	/**
	 * Generates and stores a new webhook secret.
	 */
	public function regen_secret(): void {
		if ( ! $this->authorized() ) {
			return;
		}

		$secret = Settings::generate_secret();
		Settings::update( 'api', array( 'webhook_secret' => $secret ) );

		wp_send_json_success( array( 'secret' => $secret ) );
	}

	/**
	 * Verifies nonce + capability; sends a 403 JSON error otherwise.
	 */
	private function authorized(): bool {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) || ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return false;
		}
		return true;
	}

	/**
	 * Client from POSTed project_id / api_key, falling back to stored settings. Sends the JSON error itself.
	 */
	private function client_from_request(): ?Bluefox_Client {
		if ( ! $this->authorized() ) {
			return null;
		}

		// Nonce verified in authorized().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$project_id = isset( $_POST['project_id'] ) ? preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) ) : '';
		$api_key    = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		// phpcs:enable

		if ( '' === $project_id ) {
			$project_id = (string) Settings::get( 'api', 'project_id' );
		}
		if ( Settings::api_key_from_constant() ) {
			$api_key = (string) CFBE_BLUEFOX_API_KEY;
		} elseif ( '' === $api_key ) {
			$api_key = (string) Settings::get( 'api', 'api_key' );
		}

		if ( '' === $project_id || '' === $api_key ) {
			wp_send_json_error( array( 'message' => __( 'Enter the Project ID and the API key.', 'connector-for-bluefox-email' ) ), 400 );
			return null;
		}

		return call_user_func( $this->client_factory, $project_id, $api_key );
	}
}
