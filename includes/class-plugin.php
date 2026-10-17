<?php
/**
 * Plugin bootstrap.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin on `plugins_loaded`: core services always, optional features only when their module is on.
 * Modules hook into `cfbe_init` / `cfbe_register_modules` and use the shared services.
 */
final class Plugin {

	const TEXT_DOMAIN = 'connector-for-bluefox-email';

	/**
	 * Feature modules living in modules/<slug>/: module slug => entry class.
	 * Each entry class takes the Plugin in its constructor and hooks itself (frontend + admin tab) in register().
	 */
	const FEATURE_MODULES = array(
		'sync'         => Sync\Module::class,
		'mailer'       => Mailer\Module::class,
		'woo_events'   => Woo_Events\Module::class,
		'integrations' => Integrations\Module::class,
		'webhooks'     => Webhooks\Module::class,
	);

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns whether WooCommerce is active (null = class_exists check).
	 *
	 * @var callable|null
	 */
	private $wc_check;

	/**
	 * Settings service.
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Subscribers repository.
	 *
	 * @var Subscriber_Repository|null
	 */
	private $repository = null;

	/**
	 * Logger.
	 *
	 * @var Logger|null
	 */
	private $logger = null;

	/**
	 * Background jobs.
	 *
	 * @var Jobs|null
	 */
	private $jobs = null;

	/**
	 * Signup service.
	 *
	 * @var Subscription_Service|null
	 */
	private $subscription = null;

	/**
	 * Confirmation / reward service.
	 *
	 * @var Reward_Service|null
	 */
	private $rewards = null;

	/**
	 * Whether the settings tabs are registered.
	 *
	 * @var bool
	 */
	private $tabs_registered = false;

	/**
	 * The bluefox client (null while not configured).
	 *
	 * @var Bluefox_Client|null
	 */
	private $client = null;

	/**
	 * Constructor.
	 *
	 * @param callable|null $wc_check Overridable WooCommerce detection (tests).
	 */
	public function __construct( ?callable $wc_check = null ) {
		$this->wc_check = $wc_check;
	}

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Entry point called from the main plugin file.
	 */
	public static function boot(): void {
		add_action( 'plugins_loaded', array( self::instance(), 'on_plugins_loaded' ) );
		add_filter( 'cron_schedules', array( Jobs::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Intervals are >= 60 s.
	}

	/**
	 * Activation hook: creates/upgrades the tables.
	 */
	public static function activate(): void {
		Installer::install();
		do_action( 'cfbe_activate' );
	}

	/**
	 * Deactivation hook: cancels pending background jobs (recurring ones are re-scheduled on admin_init).
	 */
	public static function deactivate(): void {
		( new Jobs() )->cancel_all();
	}

	/**
	 * Upgrades the schema and initialises the core services and enabled modules.
	 */
	public function on_plugins_loaded(): void {
		Modules::set_woocommerce_check( $this->wc_check );
		Installer::maybe_upgrade();

		$this->init_services();

		/**
		 * Fires once the core services and built-in modules exist.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'cfbe_init', $this );
	}

	/**
	 * Version string of a bundled asset: the plugin version plus the file's modification time, so browsers and
	 * page caches fetch a changed file even when the plugin version stays the same.
	 *
	 * @param string $path Path relative to the plugin folder.
	 */
	public static function asset_version( string $path ): string {
		$mtime = defined( 'CFBE_DIR' ) ? @filemtime( CFBE_DIR . $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing file falls back to the plugin version.
		$base  = defined( 'CFBE_VERSION' ) ? CFBE_VERSION : '0';
		return false !== $mtime ? $base . '.' . $mtime : $base;
	}

	/**
	 * Settings service (available after `cfbe_init`).
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Subscribers repository (available after `cfbe_init`).
	 */
	public function repository(): Subscriber_Repository {
		return $this->repository;
	}

	/**
	 * Logger (available after `cfbe_init`).
	 */
	public function logger(): Logger {
		return $this->logger;
	}

	/**
	 * Background jobs (available after `cfbe_init`).
	 */
	public function jobs(): Jobs {
		return $this->jobs;
	}

	/**
	 * Signup service (available after `cfbe_init`).
	 */
	public function subscription(): Subscription_Service {
		return $this->subscription;
	}

	/**
	 * Confirmation / reward service (available after `cfbe_init`).
	 */
	public function rewards(): Reward_Service {
		return $this->rewards;
	}

	/**
	 * The bluefox API client, or null while the connection is not configured.
	 */
	public function client(): ?Bluefox_Client {
		return $this->client;
	}

	/**
	 * Creates the shared services, the enabled modules and the admin UI.
	 */
	private function init_services(): void {
		global $wpdb;
		$this->settings   = new Settings();
		$this->repository = new Subscriber_Repository( $wpdb );
		$this->logger     = new Logger();
		$this->jobs       = new Jobs();

		$client       = Bluefox_Client::from_settings();
		$this->client = is_wp_error( $client ) ? null : $client;

		// Core: signups (shared by forms, checkout and integrations) and double opt-in confirmation tracking.
		$this->subscription = new Subscription_Service( $this->client, $this->repository, $this->jobs, $this->logger );
		$this->subscription->register();
		( new Rest_Subscribe( $this->subscription, new Rate_Limiter(), $this->logger ) )->register();

		$this->rewards = new Reward_Service( $this->client, $this->repository, $this->jobs, $this->logger, new Coupon_Factory() );
		$this->rewards->register();
		( new Rest_Webhook( $this->rewards, $this->repository, new Rate_Limiter(), $this->logger ) )->register();
		if ( class_exists( Privacy::class ) ) {
			( new Privacy( $this->repository ) )->register();
		}
		( new Consent_Ip( $this->repository, $this->jobs ) )->register();

		if ( Modules::enabled( 'forms' ) ) {
			( new Frontend\Assets() )->register();
			( new Frontend\Thankyou_Shortcode() )->register();
			( new Frontend\Newsletter_Shortcode( new Frontend\Form_Renderer() ) )->register();
			( new Frontend\Promo_Shortcode( new Frontend\Form_Renderer() ) )->register();
			( new Frontend\Popup( new Frontend\Form_Renderer() ) )->register();
			if ( class_exists( Frontend\Form_Block::class ) ) {
				( new Frontend\Form_Block( new Frontend\Form_Renderer() ) )->register();
			}
		}
		if ( Modules::enabled( 'preferences' ) ) {
			( new Frontend\Preferences_Page( $this->repository, new Rate_Limiter(), $this->logger ) )->register();
		}
		if ( Modules::enabled( 'checkout' ) ) {
			( new Checkout_Optin( $this->subscription, $this->jobs, $this->repository, $this->logger ) )->register();
		}

		foreach ( self::FEATURE_MODULES as $slug => $class ) {
			if ( ( Modules::enabled( $slug ) || Modules::group_enabled( $slug ) ) && class_exists( $class ) ) {
				( new $class( $this ) )->register();
			}
		}

		/**
		 * Fires after the built-in modules are registered; add-ons hook in here.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'cfbe_register_modules', $this );

		if ( Modules::enabled( 'abilities' ) ) {
			( new Abilities( $this->settings, array( $this, 'client' ) ) )->register();
		}

		if ( is_admin() ) {
			( new Admin\Admin_Page( $this->settings, $this->repository ) )->register();
			( new Admin\Admin_Ajax() )->register();
			( new Admin\Email_Templates_Tab( array( $this, 'client' ) ) )->register();
			$this->register_settings_tabs();
		} else {
			// Abilities save settings over REST / WP-CLI: load the settings screens' sanitizers on demand.
			add_action( 'cfbe_load_settings_sanitizers', array( $this, 'register_settings_tabs' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'cfbe', new CLI_Command( $this->rewards, $this->repository, $this->client ) );
		}
	}

	/**
	 * Registers the settings tabs (their sanitizers) and the cache purge on settings changes, once.
	 */
	public function register_settings_tabs(): void {
		if ( $this->tabs_registered ) {
			return;
		}
		$this->tabs_registered = true;

		( new Cache_Purge() )->register();
		if ( Modules::enabled( 'forms' ) ) {
			( new Admin\Form_Tab() )->register();
			( new Admin\Popup_Tab() )->register();
			( new Admin\Shortcodes_Tab() )->register();
		}
		if ( Modules::enabled( 'checkout' ) ) {
			( new Admin\Checkout_Tab() )->register();
		}
		if ( Modules::enabled( 'coupon' ) ) {
			( new Admin\Coupon_Tab( $this->rewards ) )->register();
		}
	}
}
