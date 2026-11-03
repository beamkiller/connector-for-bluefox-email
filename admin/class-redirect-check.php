<?php
/**
 * Warns when a bluefox list sends confirmed subscribers to another site's thank-you page.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Bluefox_Client;
use CFBE\Email_Templates;
use CFBE\Form_Config;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The double opt-in redirect link is a setting of the bluefox list, shared by every site using that list. A staging
 * copy, an old test site or a manual edit in bluefox can leave it pointing elsewhere; this shows a notice on the
 * plugin's screens with a one-click fix that sets it to this site's thank-you page.
 */
final class Redirect_Check {

	const ACTION = 'cfbe_fix_redirect';
	const CACHE  = 'cfbe_redirect_check';
	const RESULT = 'cfbe_redirect_fix_';

	/**
	 * Returns the bluefox client or null.
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
		add_action( 'cfbe_admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_fix' ) );
		foreach ( array( Settings::option_name( 'emails' ), Settings::option_name( 'api' ), Email_Templates::OPTION ) as $option ) {
			add_action( 'update_option_' . $option, array( self::class, 'flush' ) );
		}
	}

	/**
	 * Forgets the cached check (settings changed, or the list was fixed).
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * Lists the plugin subscribes people to: General, popup, checkout, coupon and form-integration settings.
	 *
	 * @return string[]
	 */
	public static function used_list_ids(): array {
		$ids = array();
		foreach ( array( array( 'api', 'list_id' ), array( 'popup', 'list_id' ), array( 'checkout', 'list_id' ), array( 'coupon', 'list_id' ) ) as $setting ) {
			$ids[] = (string) Settings::get( $setting[0], $setting[1] );
		}
		$integrations = get_option( Settings::option_name( 'integrations' ), array() );
		foreach ( is_array( $integrations ) ? $integrations : array() as $item ) {
			foreach ( (array) ( is_array( $item ) ? ( $item['rules'] ?? array() ) : array() ) as $rule ) {
				$ids[] = is_array( $rule ) ? (string) ( $rule['list_id'] ?? '' ) : '';
			}
		}
		$ids = array_map( array( Form_Config::class, 'clean_list_id' ), $ids );
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Same URL, ignoring the case of scheme/host and a trailing slash.
	 *
	 * @param string $a URL.
	 * @param string $b URL.
	 */
	public static function same_url( string $a, string $b ): bool {
		$norm = static function ( string $url ): string {
			$url = untrailingslashit( trim( $url ) );
			return (string) preg_replace_callback(
				'#^[a-z][a-z0-9+.-]*://[^/?\#]+#i',
				static function ( $m ) {
					return strtolower( $m[0] );
				},
				$url
			);
		};
		return $norm( $a ) === $norm( $b );
	}

	/**
	 * Used lists with double opt-in on whose redirect link is not the expected URL.
	 *
	 * @param array    $lists    bluefox lists (`_id`, `name`, `doubleOptIn`).
	 * @param string[] $used     List IDs the plugin uses.
	 * @param string   $expected This site's thank-you URL.
	 * @return array<string, array{name: string, current: string}>
	 */
	public static function mismatches( array $lists, array $used, string $expected ): array {
		$out = array();
		foreach ( $lists as $list ) {
			$id  = is_array( $list ) ? (string) ( $list['_id'] ?? '' ) : '';
			$doi = is_array( $list ) && is_array( $list['doubleOptIn'] ?? null ) ? $list['doubleOptIn'] : array();
			if ( '' === $id || ! in_array( $id, $used, true ) || empty( $doi['active'] ) ) {
				continue;
			}
			$current = (string) ( $doi['redirectLink'] ?? '' );
			if ( ! self::same_url( $current, $expected ) ) {
				$out[ $id ] = array(
					'name'    => (string) ( $list['name'] ?? $id ),
					'current' => $current,
				);
			}
		}
		return $out;
	}

	/**
	 * Cached mismatches (an hour; ten minutes after an API error).
	 *
	 * @return array<string, array{name: string, current: string}>
	 */
	public function check(): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$used   = self::used_list_ids();
		$client = call_user_func( $this->client );
		if ( array() === $used || ! $client instanceof Bluefox_Client ) {
			return array();
		}
		$lists = $client->get_lists();
		if ( is_wp_error( $lists ) ) {
			set_transient( self::CACHE, array(), 10 * MINUTE_IN_SECONDS );
			return array();
		}
		$found = self::mismatches( $lists, $used, Settings::thankyou_url() );
		set_transient( self::CACHE, $found, HOUR_IN_SECONDS );
		return $found;
	}

	/**
	 * Sets the redirect link of the mismatched lists to this site's thank-you page; other double opt-in
	 * settings (email, confirmation texts) are sent back unchanged.
	 *
	 * @param Bluefox_Client $client Client.
	 * @return array{fixed: string[], failed: string[]} List names.
	 */
	public function fix( Bluefox_Client $client ): array {
		$result = array(
			'fixed'  => array(),
			'failed' => array(),
		);
		$lists  = $client->get_lists();
		if ( is_wp_error( $lists ) ) {
			$result['failed'][] = $lists->get_error_message();
			return $result;
		}
		$expected = Settings::thankyou_url();
		$found    = self::mismatches( $lists, self::used_list_ids(), $expected );
		foreach ( $lists as $list ) {
			$id = (string) ( $list['_id'] ?? '' );
			if ( ! isset( $found[ $id ] ) ) {
				continue;
			}
			$doi = (array) $list['doubleOptIn'];
			unset( $doi['_id'] );
			$doi['redirectLink'] = $expected;
			$updated             = $client->update_list( $id, array( 'doubleOptIn' => $doi ) );
			$result[ is_wp_error( $updated ) ? 'failed' : 'fixed' ][] = $found[ $id ]['name'];
		}
		self::flush();
		return $result;
	}

	/**
	 * Notice above the plugin's tabs.
	 */
	public function render_notice(): void {
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			return;
		}
		$result = get_transient( self::RESULT . get_current_user_id() );
		if ( is_array( $result ) ) {
			delete_transient( self::RESULT . get_current_user_id() );
			if ( ! empty( $result['fixed'] ) ) {
				printf(
					'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
					/* translators: %s: list names */
					esc_html( sprintf( __( 'Confirmed subscribers of %s now land on this site’s thank-you page.', 'connector-for-bluefox-email' ), implode( ', ', $result['fixed'] ) ) )
				);
			}
			if ( ! empty( $result['failed'] ) ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					/* translators: %s: list names or error message */
					esc_html( sprintf( __( 'The redirect link could not be updated in bluefox (%s). Set it in the list’s double opt-in settings in bluefox.', 'connector-for-bluefox-email' ), implode( ', ', $result['failed'] ) ) )
				);
			}
		}

		$found = $this->check();
		if ( array() === $found ) {
			return;
		}
		$expected = Settings::thankyou_url();
		echo '<div class="notice notice-warning cfbe-redirect-notice"><p><strong>' . esc_html__( 'Confirmed subscribers are not sent to this site’s thank-you page', 'connector-for-bluefox-email' ) . '</strong></p><ul class="ul-disc">';
		foreach ( $found as $list ) {
			echo '<li>' . esc_html(
				'' === $list['current']
					/* translators: %s: list name */
					? sprintf( __( '“%s” shows bluefox’s own confirmation message.', 'connector-for-bluefox-email' ), $list['name'] )
					/* translators: 1: list name, 2: URL */
					: sprintf( __( '“%1$s” redirects to %2$s', 'connector-for-bluefox-email' ), $list['name'], $list['current'] )
			) . '</li>';
		}
		echo '</ul><p>' . esc_html(
			/* translators: %s: thank-you page URL */
			sprintf( __( 'This site’s thank-you page is %s. If another site (for example a staging copy) uses the same list, fixing it here moves the redirect to this site.', 'connector-for-bluefox-email' ), $expected )
		) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><p>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		submit_button( __( 'Use this site’s thank-you page', 'connector-for-bluefox-email' ), 'secondary', 'submit', false );
		echo '</p></form></div>';
	}

	/**
	 * Fix button (admin-post, nonce + capability).
	 */
	public function handle_fix(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), '', array( 'response' => 403 ) );
		}
		$client = call_user_func( $this->client );
		$result = $client instanceof Bluefox_Client
			? $this->fix( $client )
			: array(
				'fixed'  => array(),
				'failed' => array( __( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ) ),
			);
		set_transient( self::RESULT . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=' . Admin_Page::SLUG ) );
		exit;
	}
}
