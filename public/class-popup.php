<?php
/**
 * Automatic signup popup.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Settings;
use CFBE\Style_Vars;

defined( 'ABSPATH' ) || exit;

/**
 * Decides on `wp_enqueue_scripts` whether the popup belongs on the page, enqueues the assets and prints
 * the dialog + JSON config in `wp_footer`. The same HTML for every visitor (page-cache safe).
 */
final class Popup {

	const HANDLE    = 'cfbe-popup';
	const DIALOG_ID = 'cfbe-popup';
	const TITLE_ID  = 'cfbe-popup-title';
	const PREVIEW   = 'cfbe_popup_preview';

	/**
	 * Renderer.
	 *
	 * @var Form_Renderer
	 */
	private $renderer;

	/**
	 * Whether this request shows the popup.
	 *
	 * @var bool
	 */
	private $active = false;

	/**
	 * Constructor.
	 *
	 * @param Form_Renderer $renderer Renderer.
	 */
	public function __construct( Form_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'print_popup' ), 20 );
	}

	/**
	 * Targeting decision + assets.
	 */
	public function maybe_enqueue(): void {
		$settings = Settings::get( 'popup' );
		$context  = self::context();
		/**
		 * Filters whether the popup is printed on this request (e.g. return false inside a page builder's editor).
		 *
		 * @param bool  $render   Targeting decision.
		 * @param array $settings `popup` settings.
		 * @param array $context  Request context (see Popup::context()).
		 */
		$this->active = (bool) apply_filters( 'cfbe_popup_should_render', Popup_Targeting::should_render( $settings, $context ), $settings, $context );
		if ( ! $this->active ) {
			return;
		}
		wp_register_script( self::HANDLE, CFBE_URL . 'assets/js/cfbe-popup.js', array( Assets::HANDLE ), \CFBE\Plugin::asset_version( 'assets/js/cfbe-popup.js' ), true );
		Assets::enqueue();
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_style( Assets::HANDLE, self::backdrop_css( $settings ) );
	}

	/**
	 * Footer output.
	 */
	public function print_popup(): void {
		if ( $this->active ) {
			echo $this->render( Settings::get( 'popup' ), self::is_preview() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render().
		}
	}

	/**
	 * Popup markup and config.
	 *
	 * @param array $settings `popup` settings.
	 * @param bool  $preview  Admin preview (opens at once).
	 */
	public function render( array $settings, bool $preview = false ): string {
		$args = array(
			'source'      => 'popup',
			'variant'     => (string) $settings['form_variant'],
			'heading_tag' => 'h2',
			'heading_id'  => self::TITLE_ID,
			'list'        => (string) ( $settings['list_id'] ?? '' ),
			'tags'        => (string) ( $settings['tags'] ?? '' ),
		);
		if ( '' !== trim( (string) $settings['heading'] ) ) {
			$args['heading'] = (string) $settings['heading'];
		}
		if ( '' !== trim( (string) $settings['intro'] ) ) {
			$args['intro'] = (string) $settings['intro'];
		}
		if ( empty( $settings['show_interests'] ) ) {
			$args['show_interests'] = '0';
		}

		$classes = 'cfbe-dialog--popup cfbe-dialog--' . sanitize_html_class( (string) $settings['size'] );
		if ( empty( $settings['animate'] ) ) {
			$classes .= ' cfbe-dialog--static';
		}

		$html  = '<div class="cfbe-popup" data-cfbe-popup>';
		$html .= Dialog_Renderer::render( $this->renderer->render( $args ), self::DIALOG_ID, self::TITLE_ID, $classes );
		$html .= '<script type="application/json" class="cfbe-popup__config">' . wp_json_encode( self::config( $settings, $preview ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Typed, public client config.
	 *
	 * @param array $settings `popup` settings.
	 * @param bool  $preview  Preview mode.
	 */
	public static function config( array $settings, bool $preview = false ): array {
		return array(
			'version'         => (int) $settings['version'],
			'preview'         => $preview,
			'device'          => (string) $settings['device'],
			'combine'         => (string) $settings['combine'],
			'triggers'        => array(
				'scroll'    => empty( $settings['trigger_scroll'] ) ? null : (int) $settings['scroll_percent'],
				'delay'     => empty( $settings['trigger_delay'] ) ? null : (int) $settings['delay_seconds'],
				'exit'      => ! empty( $settings['trigger_exit'] ),
				'pageviews' => empty( $settings['trigger_pageviews'] ) ? null : (int) $settings['pageviews'],
			),
			'dismissDays'     => (int) $settings['dismiss_days'],
			'closeOnBackdrop' => ! empty( $settings['close_on_backdrop'] ),
			'cookieBar'       => (string) $settings['cookie_bar_selector'],
		);
	}

	/**
	 * `::backdrop` colour as a concrete rule (older browsers don't inherit custom properties there).
	 *
	 * @param array $settings `popup` settings.
	 */
	public static function backdrop_css( array $settings ): string {
		$hex = Style_Vars::hex( $settings['overlay_color'] ?? '' );
		$hex = null !== $hex ? $hex : '#0a0a0a';
		if ( 4 === strlen( $hex ) ) {
			$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		$alpha = max( 0.0, min( 1.0, (float) ( $settings['overlay_opacity'] ?? 0.6 ) ) );
		return sprintf(
			'.cfbe-dialog--popup::backdrop{background:rgba(%d,%d,%d,%s)}',
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) ),
			rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' )
		);
	}

	/**
	 * Admin preview request (`?cfbe_popup_preview=1`, shop managers only).
	 */
	public static function is_preview(): bool {
		// Read-only display switch, capability-gated.
		return isset( $_GET[ self::PREVIEW ] ) && current_user_can( \CFBE\Admin\Admin_Page::capability() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Request context for Popup_Targeting.
	 */
	public static function context(): array {
		$blocked = is_admin() || is_feed() || is_customize_preview() || is_preview() || is_404()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax();

		$post_id = (int) get_queried_object_id();
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$post_id = (int) wc_get_page_id( 'shop' );
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return array(
			'blocked'       => $blocked,
			'preview'       => self::is_preview(),
			'is_cart'       => function_exists( 'is_cart' ) && is_cart(),
			'is_checkout'   => function_exists( 'is_checkout' ) && is_checkout(),
			'is_account'    => function_exists( 'is_account_page' ) && is_account_page(),
			'post_id'       => $post_id,
			'path'          => $uri,
			'thankyou_path' => \CFBE\Settings::thankyou_url(),
		);
	}
}
