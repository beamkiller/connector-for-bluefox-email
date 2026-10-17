<?php
/**
 * Form plugin integrations module.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Admin\Admin_Page;
use CFBE\Plugin;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Subscribes people to bluefox from Contact Form 7, WPForms, Fluent Forms, Gravity Forms and Elementor Pro Forms
 * submissions. Signups always run in a background job through the core signup service.
 */
final class Module {

	const AREA = 'integrations';

	/**
	 * Integration slugs (= signup sources), in display order.
	 */
	const SOURCES = array( 'cf7', 'wpforms', 'fluentforms', 'gravityforms', 'elementor' );

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Dispatcher (created in register()).
	 *
	 * @var Dispatcher|null
	 */
	private $dispatcher = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Whether an integration is configured with rule rows (Elementor is configured in its widget).
	 *
	 * @param string $slug Slug.
	 */
	public static function has_rules( string $slug ): bool {
		return 'elementor' !== $slug;
	}

	/**
	 * Integration instances.
	 *
	 * @param Dispatcher $dispatcher Dispatcher.
	 * @return Integration[] slug => integration.
	 */
	public static function integrations( Dispatcher $dispatcher ): array {
		$list = array(
			new Cf7( $dispatcher ),
			new Wpforms( $dispatcher ),
			new Fluent_Forms( $dispatcher ),
			new Gravity_Forms( $dispatcher ),
			new Elementor( $dispatcher ),
		);
		$out  = array();
		foreach ( $list as $integration ) {
			$out[ $integration->slug() ] = $integration;
		}
		return $out;
	}

	/**
	 * Hooks settings, admin tab, job handler and the active+enabled integrations.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_areas', array( $this, 'add_area' ) );
		add_filter( 'cfbe_settings_defaults_' . self::AREA, array( Rules::class, 'defaults' ) );
		add_filter( 'cfbe_sanitize_settings_' . self::AREA, array( Rules::class, 'sanitize' ), 10, 3 );

		$this->dispatcher = new Dispatcher( $this->plugin->jobs(), $this->plugin->subscription(), $this->plugin->logger() );
		$this->dispatcher->register();

		foreach ( self::integrations( $this->dispatcher ) as $integration ) {
			if ( $integration->is_active() && $integration->is_enabled() ) {
				$integration->register();
			}
		}

		if ( is_admin() ) {
			add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}
	}

	/**
	 * `cfbe_settings_areas` callback.
	 *
	 * @param mixed $areas Areas.
	 */
	public function add_area( $areas ): array {
		$areas   = (array) $areas;
		$areas[] = self::AREA;
		return $areas;
	}

	/**
	 * `cfbe_settings_tabs` callback.
	 *
	 * @param mixed $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs               = (array) $tabs;
		$tabs[ self::AREA ] = array(
			'label'    => __( 'Form integrations', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render_tab' ),
			'position' => 65,
		);
		return $tabs;
	}

	/**
	 * Renders the tab.
	 */
	public function render_tab(): void {
		$cfbe_values       = (array) Settings::get( self::AREA );
		$cfbe_integrations = self::integrations( $this->dispatcher );
		$cfbe_lists        = get_transient( Settings::LISTS_CACHE );
		$cfbe_lists        = is_array( $cfbe_lists ) ? $cfbe_lists : array();
		$cfbe_option       = Settings::option_name( self::AREA );
		include __DIR__ . '/views/tab-integrations.php';
	}

	/**
	 * Enqueues the tab's JS/CSS (only on our page, Form integrations tab).
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue( $hook ): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'toplevel_page_' . Admin_Page::SLUG !== $hook || self::AREA !== $tab ) {
			return;
		}
		wp_enqueue_style( 'cfbe-integrations-admin', CFBE_URL . 'modules/integrations/assets/integrations-admin.css', array(), \CFBE\Plugin::asset_version( 'modules/integrations/assets/integrations-admin.css' ) );
		wp_enqueue_script( 'cfbe-integrations-admin', CFBE_URL . 'modules/integrations/assets/integrations-admin.js', array(), \CFBE\Plugin::asset_version( 'modules/integrations/assets/integrations-admin.js' ), true );
	}
}
