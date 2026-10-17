<?php
/**
 * Contact sync module entry.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

use CFBE\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps bluefox contacts enriched with WordPress user and WooCommerce customer data
 * (custom fields + optional tags) so they can be used in bluefox segments.
 *
 * Deleting a WordPress user does not delete its bluefox contact, and a changed account email
 * does not rename the contact: the new address is synced as its own contact.
 */
final class Module {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Syncer.
	 *
	 * @var Contact_Syncer|null
	 */
	private $syncer = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks the module.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_areas', array( $this, 'add_area' ) );
		add_filter( 'cfbe_settings_defaults_' . Fields::AREA, array( Fields::class, 'filter_defaults' ) );
		add_filter( 'cfbe_sanitize_settings_' . Fields::AREA, array( Fields::class, 'sanitize' ), 10, 3 );

		$this->syncer = new Contact_Syncer( $this->plugin->client(), new Data_Collector(), $this->plugin->logger() );
		$source       = new Contact_Source();
		$backfill     = new Backfill( $this->syncer, $source, $this->plugin->jobs(), $this->plugin->logger() );

		add_action( Triggers::JOB, array( $this, 'run_contact_job' ) );
		add_action( Backfill::HOOK, array( $backfill, 'run_batch' ), 10, 2 );

		if ( ! empty( Fields::settings()['auto_sync'] ) ) {
			( new Triggers( $this->plugin->jobs() ) )->register();
		}

		if ( is_admin() ) {
			( new Sync_Tab( $backfill, $this->plugin->client() ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cfbe sync', new Cli_Command( $this->syncer, $source ) );
		}
	}

	/**
	 * Adds the `sync` settings area.
	 *
	 * @param array $areas Areas.
	 */
	public function add_area( $areas ): array {
		$areas   = (array) $areas;
		$areas[] = Fields::AREA;
		return $areas;
	}

	/**
	 * Job callback: syncs one email.
	 *
	 * @param string $email Email address.
	 */
	public function run_contact_job( $email ): void {
		if ( null !== $this->syncer ) {
			$this->syncer->sync( (string) $email );
		}
	}
}
