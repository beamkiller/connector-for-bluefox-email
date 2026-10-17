<?php
/**
 * Admin page: WooCommerce → bluefox.email.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Logger;
use CFBE\Modules;
use CFBE\Settings;
use CFBE\Subscriber_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Tabbed settings page. Modules add tabs via the `cfbe_settings_tabs` filter:
 * `slug => array( 'label' => string, 'render' => callable, 'position' => int, 'group' => string (optional) )`.
 */
final class Admin_Page {

	const SLUG = 'cfbe-newsletter';

	/**
	 * Capability for the settings page and admin actions (filterable, e.g. `manage_woocommerce` for shop managers).
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to manage the plugin.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'cfbe_capability', 'manage_options' );
	}

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Subscribers repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Constructor.
	 *
	 * @param Settings              $settings   Settings.
	 * @param Subscriber_Repository $repository Subscribers repository.
	 */
	public function __construct( Settings $settings, Subscriber_Repository $repository ) {
		$this->settings   = $settings;
		$this->repository = $repository;
	}

	/**
	 * Hooks the page.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 60 );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_cfbe_clear_log', array( $this, 'clear_log' ) );
		foreach ( Settings::areas() as $area ) {
			// options.php checks manage_options by default; use our (filterable) capability.
			add_filter(
				'option_page_capability_' . Settings::option_name( $area ),
				static function () {
					return self::capability();
				}
			);
		}
	}

	/**
	 * Adds the top-level menu.
	 */
	public function add_menu(): void {
		$this->hook = (string) add_menu_page(
			__( 'bluefox.email', 'connector-for-bluefox-email' ),
			__( 'bluefox.email', 'connector-for-bluefox-email' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-email-alt',
			58
		);
	}

	/**
	 * Enqueues admin assets on this page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( string $hook ): void {
		if ( '' === $this->hook || $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'cfbe-admin', CFBE_URL . 'admin/assets/admin.css', array(), \CFBE\Plugin::asset_version( 'admin/assets/admin.css' ) );
		// Frontend form CSS for the Form tab live preview (variables are set on the preview wrapper).
		wp_enqueue_style( 'cfbe-form', CFBE_URL . 'assets/css/cfbe-form.css', array(), \CFBE\Plugin::asset_version( 'assets/css/cfbe-form.css' ) );
		wp_enqueue_script( 'cfbe-admin', CFBE_URL . 'admin/assets/admin.js', array(), \CFBE\Plugin::asset_version( 'admin/assets/admin.js' ), true );
		wp_localize_script(
			'cfbe-admin',
			'cfbeAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Admin_Ajax::NONCE ),
				'i18n'    => array(
					'working' => __( 'Working…', 'connector-for-bluefox-email' ),
					'error'   => __( 'Something went wrong.', 'connector-for-bluefox-email' ),
					'copied'  => __( 'Copied!', 'connector-for-bluefox-email' ),
					'loaded'  => __( 'Lists loaded.', 'connector-for-bluefox-email' ),
					'doi'     => __( 'double opt-in', 'connector-for-bluefox-email' ),
					'confirm' => __( 'Generate a new webhook secret? You will also have to update the header in the bluefox automation!', 'connector-for-bluefox-email' ),
				),
			)
		);
	}

	/**
	 * Registered tabs ordered by position.
	 *
	 * @return array<string, array{label: string, render: callable, position: int}>
	 */
	public function tabs(): array {
		$tabs = array(
			'api'         => array(
				'label'    => __( 'General', 'connector-for-bluefox-email' ),
				'render'   => array( $this, 'render_api_tab' ),
				'position' => 10,
			),
			'subscribers' => array(
				'label'    => __( 'Subscribers', 'connector-for-bluefox-email' ),
				'render'   => array( $this, 'render_subscribers_tab' ),
				'position' => 100,
			),
			'logs'        => array(
				'label'    => __( 'Logs', 'connector-for-bluefox-email' ),
				'render'   => array( $this, 'render_logs_tab' ),
				'position' => 110,
			),
		);

		/**
		 * Filters the settings tabs.
		 *
		 * @param array $tabs Tabs.
		 */
		$tabs = (array) apply_filters( 'cfbe_settings_tabs', $tabs );

		uasort(
			$tabs,
			static function ( $a, $b ) {
				return ( $a['position'] ?? 50 ) <=> ( $b['position'] ?? 50 );
			}
		);
		return $tabs;
	}

	/**
	 * Tab groups shown as the main tabs: group => tab slugs. A tab can name its group with a `group` key;
	 * tabs in no group (e.g. from add-ons) go to "More".
	 */
	const GROUPS = array(
		'general'      => array( 'api', 'email_templates' ),
		'forms'        => array( 'form', 'popup', 'emails', 'shortcodes' ),
		'woocommerce'  => array( 'checkout', 'coupon', 'woo_events', 'abandoned_cart' ),
		'integrations' => array( 'sync', 'mailer', 'integrations', 'webhooks' ),
		'subscribers'  => array( 'subscribers', 'privacy', 'logs' ),
	);

	/**
	 * Group labels.
	 *
	 * @return array<string, string>
	 */
	private static function group_labels(): array {
		return array(
			'general'      => __( 'General', 'connector-for-bluefox-email' ),
			'forms'        => __( 'Signup forms', 'connector-for-bluefox-email' ),
			'woocommerce'  => __( 'WooCommerce', 'connector-for-bluefox-email' ),
			'integrations' => __( 'Integrations', 'connector-for-bluefox-email' ),
			'subscribers'  => __( 'Subscribers', 'connector-for-bluefox-email' ),
			'more'         => __( 'More', 'connector-for-bluefox-email' ),
		);
	}

	/**
	 * The registered tabs grouped, in group order and tab position order; empty groups are left out.
	 *
	 * @param array $tabs Tabs (ordered).
	 * @return array<string, string[]> group => tab slugs
	 */
	public static function grouped( array $tabs ): array {
		$out = array_fill_keys( array_keys( self::group_labels() ), array() );
		foreach ( array_keys( $tabs ) as $slug ) {
			$group = (string) ( $tabs[ $slug ]['group'] ?? '' );
			if ( ! isset( $out[ $group ] ) ) {
				$group = 'more';
				foreach ( self::GROUPS as $key => $slugs ) {
					if ( in_array( $slug, $slugs, true ) ) {
						$group = $key;
						break;
					}
				}
			}
			$out[ $group ][] = (string) $slug;
		}
		return array_filter( $out );
	}

	/**
	 * Current tab slug (falls back to the first tab).
	 *
	 * @param array $tabs Tabs.
	 */
	public function current_tab( array $tabs ): string {
		// Read-only navigation parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Renders the page.
	 */
	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$tabs    = $this->tabs();
		$current = $this->current_tab( $tabs );

		echo '<div class="wrap cfbe-admin"><h1>' . esc_html__( 'bluefox.email', 'connector-for-bluefox-email' ) . '</h1>';
		settings_errors();
		$groups = self::grouped( $tabs );
		$labels = self::group_labels();
		$active = array();
		echo '<nav class="nav-tab-wrapper cfbe-nav">';
		foreach ( $groups as $group => $slugs ) {
			$is_active = in_array( $current, $slugs, true );
			if ( $is_active ) {
				$active = $slugs;
			}
			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( add_query_arg( 'tab', $slugs[0], admin_url( 'admin.php?page=' . self::SLUG ) ) ),
				$is_active ? ' nav-tab-active' : '',
				$is_active ? ' aria-current="page"' : '',
				esc_html( $labels[ $group ] )
			);
		}
		echo '</nav>';
		if ( count( $active ) > 1 ) {
			$links = array();
			foreach ( $active as $slug ) {
				$links[] = sprintf(
					'<li><a href="%s"%s>%s</a></li>',
					esc_url( add_query_arg( 'tab', $slug, admin_url( 'admin.php?page=' . self::SLUG ) ) ),
					$slug === $current ? ' class="current" aria-current="page"' : '',
					esc_html( $tabs[ $slug ]['label'] )
				);
			}
			echo '<ul class="subsubsub cfbe-subnav">' . implode( '<li class="cfbe-subnav__sep" aria-hidden="true">|</li>', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		}
		echo '<div class="cfbe-tab cfbe-tab-' . esc_attr( $current ) . '">';
		call_user_func( $tabs[ $current ]['render'] );
		echo '</div></div>';
	}

	/**
	 * Connection tab.
	 */
	public function render_api_tab(): void {
		$cfbe_values       = Settings::get( 'api' );
		$cfbe_key_constant = Settings::api_key_from_constant();
		$cfbe_lists        = get_transient( Settings::LISTS_CACHE );
		$cfbe_lists        = is_array( $cfbe_lists ) ? $cfbe_lists : array();
		$cfbe_webhook_url  = rest_url( 'cfbe/v1/bluefox-hook' );
		$cfbe_modules      = Modules::all();
		$cfbe_states       = Modules::states();
		include __DIR__ . '/views/tab-api.php';
	}

	/**
	 * Logs tab: recent entries of the plugin's own ring buffer.
	 */
	public function render_logs_tab(): void {
		$cfbe_entries = array_reverse( Logger::entries() );
		include __DIR__ . '/views/tab-logs.php';
	}

	/**
	 * Clears the log (admin-post, nonce + capability).
	 */
	public function clear_log(): void {
		check_admin_referer( 'cfbe_clear_log' );
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), 403 );
		}
		Logger::clear();
		wp_safe_redirect( add_query_arg( 'tab', 'logs', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Subscribers tab (read-only log).
	 */
	public function render_subscribers_tab(): void {
		// Read-only filters of a list table.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$cfbe_filters = array(
			'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'source'   => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'page'     => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
			'per_page' => 20,
		);
		// phpcs:enable
		$cfbe_result = $this->repository->paginate( $cfbe_filters );
		include __DIR__ . '/views/tab-subscribers.php';
	}
}
