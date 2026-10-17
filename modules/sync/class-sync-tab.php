<?php
/**
 * Contact sync: admin tab and AJAX actions.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Admin\Admin_Page;
use CFBE\Bluefox_Client;
use CFBE\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * "Contact sync" tab: settings, "Create missing fields in bluefox", backfill start / progress / cancel.
 */
class Sync_Tab {

	const NONCE = 'cfbe_sync';

	/**
	 * Backfill runner.
	 *
	 * @var Backfill
	 */
	private $backfill;

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Backfill            $backfill Backfill runner.
	 * @param Bluefox_Client|null $client   Client.
	 */
	public function __construct( Backfill $backfill, ?Bluefox_Client $client ) {
		$this->backfill = $backfill;
		$this->client   = $client;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_cfbe_sync_fields', array( $this, 'ajax_fields' ) );
		add_action( 'wp_ajax_cfbe_sync_start', array( $this, 'ajax_start' ) );
		add_action( 'wp_ajax_cfbe_sync_cancel', array( $this, 'ajax_cancel' ) );
		add_action( 'wp_ajax_cfbe_sync_status', array( $this, 'ajax_status' ) );
	}

	/**
	 * Adds the tab (position 50).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs         = (array) $tabs;
		$tabs['sync'] = array(
			'label'    => __( 'Contact sync', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 50,
		);
		return $tabs;
	}

	/**
	 * Enqueues the tab script on the plugin page's Sync tab only.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ): void {
		// Read-only navigation parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_' . Admin_Page::SLUG !== $hook || 'sync' !== $tab ) {
			return;
		}
		wp_enqueue_script( 'cfbe-sync-admin', CFBE_URL . 'modules/sync/assets/sync-admin.js', array(), \CFBE\Plugin::asset_version( 'modules/sync/assets/sync-admin.js' ), true );
		wp_localize_script(
			'cfbe-sync-admin',
			'cfbeSync',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'working'       => __( 'Working…', 'connector-for-bluefox-email' ),
					'error'         => __( 'Something went wrong.', 'connector-for-bluefox-email' ),
					'confirmStart'  => __( 'Sync all existing users and customers now? This runs in the background in batches of 50, one batch per minute.', 'connector-for-bluefox-email' ),
					'confirmCancel' => __( 'Cancel the running sync?', 'connector-for-bluefox-email' ),
				),
			)
		);
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_values     = Fields::settings();
		$cfbe_woo        = Modules::woocommerce_active();
		$cfbe_progress   = Backfill::progress();
		$cfbe_configured = null !== $this->client;
		include __DIR__ . '/views/tab-sync.php';
	}

	/**
	 * Human progress line.
	 *
	 * @param array $progress Progress.
	 */
	public static function progress_text( array $progress ): string {
		$counts = (array) $progress['counts'];
		$detail = sprintf(
			/* translators: 1: updated, 2: created, 3: unchanged, 4: skipped, 5: errors */
			__( 'updated %1$d, created %2$d, unchanged %3$d, skipped %4$d, errors %5$d', 'connector-for-bluefox-email' ),
			(int) ( $counts[ Contact_Syncer::UPDATED ] ?? 0 ),
			(int) ( $counts[ Contact_Syncer::CREATED ] ?? 0 ),
			(int) ( $counts[ Contact_Syncer::UNCHANGED ] ?? 0 ),
			(int) ( $counts[ Contact_Syncer::NOT_CONTACT ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::NO_DATA ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::INVALID ] ?? 0 ),
			(int) ( $counts[ Contact_Syncer::ERROR ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::NOT_CONFIGURED ] ?? 0 )
		);
		switch ( $progress['status'] ) {
			case 'running':
				/* translators: 1: processed emails, 2: number of users, 3: phase, 4: details */
				return sprintf( __( 'Running: %1$d processed (%2$d users in total, now: %3$s) – %4$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], (int) $progress['total'], 'guests' === $progress['phase'] ? __( 'guest customers', 'connector-for-bluefox-email' ) : __( 'users', 'connector-for-bluefox-email' ), $detail );
			case 'done':
				/* translators: 1: processed emails, 2: details */
				return sprintf( __( 'Last sync finished: %1$d processed – %2$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], $detail );
			case 'cancelled':
				/* translators: 1: processed emails, 2: details */
				return sprintf( __( 'Last sync was cancelled after %1$d – %2$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], $detail );
			default:
				return __( 'No sync has run yet.', 'connector-for-bluefox-email' );
		}
	}

	/**
	 * Creates the enabled fields that are missing in bluefox.
	 *
	 * @param Bluefox_Client        $client Client.
	 * @param array<string, string> $map    Field key => bluefox name.
	 * @return array{created: string[], existing: string[], failed: string[]}|\WP_Error
	 */
	public static function ensure_fields( Bluefox_Client $client, array $map ) {
		$fields = $client->get_contact_fields();
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$have = array_map( 'strval', array_column( $fields, 'name' ) );
		$out  = array(
			'created'  => array(),
			'existing' => array(),
			'failed'   => array(),
		);
		foreach ( $map as $key => $name ) {
			if ( in_array( $name, $have, true ) ) {
				$out['existing'][] = $name;
				continue;
			}
			$created = $client->create_contact_field( $name, Fields::DEFINITIONS[ $key ]['type'] ?? 'string' );
			if ( is_wp_error( $created ) ) {
				$out['failed'][] = $name;
				continue;
			}
			$out['created'][] = $name;
			$have[]           = $name;
		}
		return $out;
	}

	/**
	 * AJAX: create missing fields.
	 */
	public function ajax_fields(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		if ( null === $this->client ) {
			wp_send_json_error( array( 'message' => __( 'Connect to bluefox first (General tab).', 'connector-for-bluefox-email' ) ), 400 );
			return;
		}
		$result = self::ensure_fields( $this->client, Fields::active_map() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
			return;
		}
		$message = sprintf(
			/* translators: 1: created field names, 2: existing field names */
			__( 'Created: %1$s. Already existed: %2$s.', 'connector-for-bluefox-email' ),
			$result['created'] ? implode( ', ', $result['created'] ) : '–',
			$result['existing'] ? implode( ', ', $result['existing'] ) : '–'
		);
		if ( $result['failed'] ) {
			/* translators: %s: field names */
			$message .= ' ' . sprintf( __( 'Failed: %s.', 'connector-for-bluefox-email' ), implode( ', ', $result['failed'] ) );
		}
		wp_send_json_success( array( 'message' => $message ) );
	}

	/**
	 * AJAX: start the backfill.
	 */
	public function ajax_start(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		if ( null === $this->client ) {
			wp_send_json_error( array( 'message' => __( 'Connect to bluefox first (General tab).', 'connector-for-bluefox-email' ) ), 400 );
			return;
		}
		$this->backfill->start();
		$this->send_status();
	}

	/**
	 * AJAX: cancel the backfill.
	 */
	public function ajax_cancel(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		$this->backfill->cancel();
		$this->send_status();
	}

	/**
	 * AJAX: progress.
	 */
	public function ajax_status(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		$this->send_status();
	}

	/**
	 * Sends the current progress.
	 */
	private function send_status(): void {
		$progress = Backfill::progress();
		wp_send_json_success(
			array(
				'running' => 'running' === $progress['status'],
				'message' => self::progress_text( $progress ),
			)
		);
	}

	/**
	 * Nonce + capability check; sends a 403 otherwise.
	 */
	private function authorized(): bool {
		if ( false === check_ajax_referer( self::NONCE, 'nonce', false ) || ! current_user_can( Admin_Page::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'connector-for-bluefox-email' ) ), 403 );
			return false;
		}
		return true;
	}
}
