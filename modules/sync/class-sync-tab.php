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
		add_action( 'wp_ajax_cfbe_sync_resume', array( $this, 'ajax_resume' ) );
		add_action( 'wp_ajax_cfbe_sync_tick', array( $this, 'ajax_tick' ) );
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
		if ( Modules::woocommerce_active() ) {
			// WooCommerce's own AJAX product search (selectWoo): only matching products are loaded, fine for big stores.
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
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
					'confirmStart'  => __( 'Sync all existing users and customers now? This runs in the background in short batches; you can follow it here.', 'connector-for-bluefox-email' ),
					/* translators: 1: contacts per minute, 2: minutes left */
					'stats'         => __( '%1$d contacts per minute, about %2$d minutes left', 'connector-for-bluefox-email' ),
					'confirmCancel' => __( 'Cancel the running sync?', 'connector-for-bluefox-email' ),
					'errorsTitle'   => __( 'Last errors', 'connector-for-bluefox-email' ),
					'colTime'       => __( 'Time', 'connector-for-bluefox-email' ),
					'colEmail'      => __( 'Contact', 'connector-for-bluefox-email' ),
					'colResponse'   => __( 'bluefox response', 'connector-for-bluefox-email' ),
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
		$cfbe_products   = $cfbe_woo ? self::product_names( (array) $cfbe_values['product_tag_ids'] ) : array();
		include __DIR__ . '/views/tab-sync.php';
	}

	/**
	 * Names of the chosen products (only these are loaded; the select searches the rest over AJAX).
	 *
	 * @param array $ids Product IDs.
	 * @return array<int, string> ID => name.
	 */
	public static function product_names( array $ids ): array {
		$out = array();
		foreach ( Fields::clean_product_ids( $ids ) as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			/* translators: %d: product ID */
			$out[ $id ] = is_object( $product ) ? wp_strip_all_tags( (string) $product->get_formatted_name() ) : sprintf( __( 'Deleted product #%d', 'connector-for-bluefox-email' ), $id );
		}
		return $out;
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
				/* translators: 1: processed emails, 2: users, 3: guest orders, 4: phase, 5: details */
				return sprintf( __( 'Running: %1$d processed (%2$d users and up to %3$d guest orders, now: %4$s) – %5$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], (int) $progress['total'], (int) $progress['guest_total'], 'guests' === $progress['phase'] ? __( 'guest customers', 'connector-for-bluefox-email' ) : __( 'users', 'connector-for-bluefox-email' ), $detail );
			case 'done':
				/* translators: 1: processed emails, 2: details */
				return sprintf( __( 'Last sync finished: %1$d processed – %2$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], $detail );
			case 'waiting':
				/* translators: 1: processed emails, 2: details */
				return sprintf( __( 'Waiting for bluefox: %1$d processed – %2$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], $detail );
			case 'paused':
				/* translators: 1: processed emails, 2: details */
				return sprintf( __( 'Paused: %1$d processed – %2$s.', 'connector-for-bluefox-email' ), (int) $progress['processed'], $detail );
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
	 * AJAX: progress, and when the background queue has stopped running the sync, the due batch runs in this request
	 * (short budget) – so an open Contact sync tab keeps a stalled sync going.
	 */
	public function ajax_tick(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		$this->backfill->tick();
		$this->send_status();
	}

	/**
	 * AJAX: resume a paused or stalled backfill.
	 */
	public function ajax_resume(): void {
		if ( ! $this->authorized() ) {
			return;
		}
		$this->backfill->resume();
		$this->send_status();
	}

	/**
	 * Sends the current progress.
	 */
	private function send_status(): void {
		wp_send_json_success( self::status_data( Backfill::progress() ) );
	}

	/**
	 * Everything the live view (and the cfbe/get-sync-status ability) shows about a backfill.
	 *
	 * @param array    $progress Progress.
	 * @param int|null $now      Current time (tests).
	 */
	public static function status_data( array $progress, ?int $now = null ): array {
		$now      = null === $now ? time() : $now;
		$counts   = (array) $progress['counts'];
		$total    = (int) $progress['total'] + (int) $progress['guest_total'];
		$done     = (int) $progress['processed'];
		$health   = Backfill::health( $progress, $now );
		$elapsed  = max( 1, (int) $progress['updated'] - (int) $progress['started'] );
		$per_min  = $done > 0 ? $done / $elapsed * 60 : 0.0;
		$left_min = $per_min > 0 && $total > $done ? (int) ceil( ( $total - $done ) / $per_min ) : 0;
		return array(
			'status'      => (string) $progress['status'],
			'running'     => in_array( $progress['status'], array( 'running', 'waiting' ), true ),
			'message'     => self::progress_text( $progress ),
			'processed'   => $done,
			'total'       => $total,
			// The guest total is an upper bound (orders, not people): a finished run is 100%.
			'percent'     => 'done' === $progress['status'] ? 100 : ( $total > 0 ? min( 99, (int) floor( $done * 100 / $total ) ) : 0 ),
			'phase'       => (string) $progress['phase'],
			'counts'      => array(
				'updated'   => (int) ( $counts[ Contact_Syncer::UPDATED ] ?? 0 ),
				'created'   => (int) ( $counts[ Contact_Syncer::CREATED ] ?? 0 ),
				'unchanged' => (int) ( $counts[ Contact_Syncer::UNCHANGED ] ?? 0 ),
				'skipped'   => (int) ( $counts[ Contact_Syncer::NOT_CONTACT ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::NO_DATA ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::INVALID ] ?? 0 ),
				'errors'    => (int) ( $counts[ Contact_Syncer::ERROR ] ?? 0 ) + (int) ( $counts[ Contact_Syncer::NOT_CONFIGURED ] ?? 0 ),
			),
			'per_minute'  => (int) round( $per_min ),
			'eta_minutes' => $left_min,
			'health'      => array(
				'code' => $health['code'],
				'text' => self::health_text( $progress, $health, $now ),
			),
			'can_resume'  => 'paused' === $progress['status'] || 0 === strpos( $health['code'], 'stalled' ),
			'last_error'  => (array) $progress['last_error'],
			'errors'      => array_values( (array) $progress['errors'] ),
		);
	}

	/**
	 * Explains the health of a running or paused backfill ('' when there is nothing to say).
	 *
	 * @param array $progress Progress.
	 * @param array $health   Backfill::health() result.
	 * @param int   $now      Current time.
	 */
	public static function health_text( array $progress, array $health, int $now ): string {
		$error    = (array) $progress['last_error'];
		$response = '' !== (string) ( $error['message'] ?? '' )
			/* translators: 1: HTTP status, 2: bluefox message */
			? sprintf( __( 'HTTP %1$d: %2$s', 'connector-for-bluefox-email' ), (int) ( $error['status'] ?? 0 ), (string) $error['message'] )
			: '';
		switch ( $health['code'] ) {
			case 'waiting':
				return sprintf(
					/* translators: 1: bluefox response, 2: seconds */
					__( 'bluefox asked to slow down or could not be reached (%1$s). Retrying the same contact in %2$d seconds – nothing is lost.', 'connector-for-bluefox-email' ),
					$response,
					max( 0, (int) $progress['next_at'] - $now )
				);
			case 'paused':
				if ( 'auth' === $progress['reason'] ) {
					/* translators: %s: bluefox response */
					return sprintf( __( 'Paused: bluefox rejected the API key (%s). Check the project ID and API key on the General tab, then click Resume.', 'connector-for-bluefox-email' ), $response );
				}
				if ( 'not_configured' === $progress['reason'] ) {
					return __( 'Paused: bluefox is not connected. Enter the project ID and API key on the General tab, then click Resume.', 'connector-for-bluefox-email' );
				}
				/* translators: 1: number of attempts, 2: bluefox response */
				return sprintf( __( 'Paused after %1$d failed attempts in a row (%2$s). Click Resume to try again later.', 'connector-for-bluefox-email' ), Backfill::MAX_RETRIES, $response );
			case 'stalled_queue':
				/* translators: %d: minutes */
				return sprintf( __( 'No progress for %d minutes: the background queue did not run the next batch. WP-Cron may be disabled on this site, or the site gets too few visitors to trigger it. While this page is open, the sync goes on from here. For a lasting fix, set up a real cron job for wp-cron.php, or run the sync on the command line: wp cfbe sync', 'connector-for-bluefox-email' ), $health['minutes'] );
			case 'stalled_batch':
				/* translators: %d: minutes */
				return sprintf( __( 'No progress for %d minutes: the last batch stopped before it finished (usually the server\'s PHP time limit or an error – see the Logs tab). It goes on from the last saved contact; you can also click Resume.', 'connector-for-bluefox-email' ), $health['minutes'] );
			default:
				return '';
		}
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
