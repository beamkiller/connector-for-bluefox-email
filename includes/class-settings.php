<?php
/**
 * Plugin settings: one array option per area, registered with the Settings API.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Option storage, defaults and sanitizers. Modules extend an area's defaults and sanitizer
 * through the `cfbe_settings_defaults_{area}` and `cfbe_sanitize_settings_{area}` filters.
 */
final class Settings {

	const AREAS = array( 'api', 'form', 'popup', 'checkout', 'coupon', 'emails' );

	const LISTS_CACHE = 'cfbe_lists_cache';

	// Lists seen in the bluefox project (id => name), kept after the cache expires: the allowlist for signed forms.
	const KNOWN_LISTS = 'cfbe_known_lists';

	const SECRET_LENGTH = 32;

	/**
	 * All settings areas: the built-in ones plus those added by feature modules (`cfbe_settings_areas`).
	 *
	 * @return string[]
	 */
	public static function areas(): array {
		/**
		 * Filters the settings areas. A module adds its area here and its defaults / sanitizer through
		 * `cfbe_settings_defaults_{area}` and `cfbe_sanitize_settings_{area}`; it is stored in option `cfbe_{area}`.
		 *
		 * @param string[] $areas Areas.
		 */
		return array_values( array_unique( (array) apply_filters( 'cfbe_settings_areas', self::AREAS ) ) );
	}

	/**
	 * Option name of an area.
	 *
	 * @param string $area Area.
	 */
	public static function option_name( string $area ): string {
		return 'cfbe_' . $area;
	}

	/**
	 * Default values of an area.
	 *
	 * @param string $area Area.
	 */
	public static function defaults( string $area ): array {
		switch ( $area ) {
			case 'api':
				$defaults = array(
					'project_id'     => '',
					'api_key'        => '',
					'list_id'        => '',
					'webhook_secret' => '',
				);
				break;
			case 'emails':
				$defaults = array(
					// Days a signup may stay unconfirmed before it expires (all signups, with or without coupon).
					'pending_expiry_days'      => 14,
					'thankyou_url'             => '',
					'preferences_url'          => '',
					'prefs_title'              => 'Newsletter preferences',
					'prefs_intro'              => 'Here you can pause or cancel our newsletter. You will still receive emails about your orders.',
					'thankyou_confirmed_title' => 'Thank you for confirming your subscription!',
					'thankyou_confirmed_text'  => 'Welcome to our newsletter! If you signed up for a gift, it will arrive by email within a few minutes. Please check your Promotions or Spam folder too.',
					'thankyou_expired_title'   => 'The confirmation link has expired',
					'thankyou_expired_text'    => 'For security reasons the link is valid for 7 days. Please subscribe again and we will send you a new confirmation email.',
					'thankyou_error_title'     => 'Oops, something went wrong',
					'thankyou_error_text'      => 'We could not record your confirmation right now. Please try again in a few minutes or contact us.',
				);
				break;
			case 'coupon':
				$defaults = array(
					// Empty = confirmed signups on any list get the coupon.
					'list_id'               => '',
					// bluefox transactional email ID of the coupon email.
					'email_id'              => '',
					'code_prefix'           => 'WELCOME-',
					'discount_type'         => 'percent',
					'amount'                => 10,
					'expiry_days'           => 30,
					'minimum_amount'        => 0,
					'individual_use'        => true,
					// One welcome coupon per mailbox: name+tag@ aliases and Gmail dot variants count as one address.
					'one_per_mailbox'       => true,
					'exclude_sale_items'    => true,
					'free_shipping'         => false,
					'store_code_in_bluefox' => true,
					'reminder_days'         => 5,
				);
				break;
			case 'checkout':
				$defaults = array(
					// {discount} is replaced with the Coupon tab value; the privacy link is appended automatically.
					'label'         => 'Subscribe me to the newsletter.',
					'description'   => 'We will send you a confirmation email. You can unsubscribe at any time.',
					// Empty = the default list (General tab).
					'list_id'       => '',
					// Extra bluefox tags, comma separated (source-checkout is always added).
					'tags'          => '',
					'account_optin' => false,
				);
				break;
			case 'popup':
				$defaults = array(
					'enabled'             => false,
					'form_variant'        => 'card',
					// Empty heading/intro → the Form tab texts.
					'heading'             => 'Stay in the loop',
					'intro'               => 'Subscribe to our newsletter for offers, news and tips.',
					'show_interests'      => true,
					// Empty list → the default list (General tab); tags = comma-separated extra bluefox tags.
					'list_id'             => '',
					'tags'                => '',
					'trigger_scroll'      => true,
					'scroll_percent'      => 70,
					'trigger_delay'       => false,
					'delay_seconds'       => 20,
					'trigger_exit'        => false,
					'trigger_pageviews'   => false,
					'pageviews'           => 2,
					'combine'             => 'any',
					'pages'               => 'all',
					'page_ids'            => '',
					'url_contains'        => '',
					'exclude_woo'         => true,
					'device'              => 'all',
					'dismiss_days'        => 7,
					'version'             => 1,
					'size'                => 'md',
					'overlay_color'       => '#0a0a0a',
					'overlay_opacity'     => 0.6,
					'close_on_backdrop'   => true,
					'animate'             => true,
					'cookie_bar_selector' => '',
				);
				break;
			case 'form':
				$defaults = array(
					'name_enabled'      => true,
					'name_required'     => false,
					'consent_label'     => 'I agree to receive the newsletter and offers by email. I have read the privacy policy.',
					'privacy_url'       => '',
					'msg_pending'       => 'Thank you! We have sent you a confirmation email. Please click the link in it to complete your subscription.',
					'msg_already'       => 'This email address is already subscribed to our newsletter.',
					// Off: every successful signup shows msg_pending, so the form never reveals who is subscribed.
					'reveal_subscribed' => false,
					'msg_error'         => 'Sorry, we could not subscribe you right now. Please try again later.',
					// {discount} is replaced with the Coupon tab value (e.g. 10% / 20 EUR).
					'heading'           => 'Subscribe to our newsletter',
					'intro'             => 'Get offers, news and useful tips straight to your inbox.',
					'benefits'          => "Be the first to hear about offers and new products\nUseful tips and news\nUnsubscribe with one click at any time",
					'button'            => 'Subscribe',
					'offer'             => '',
					'variant'           => 'card',
					'promo_heading'     => 'Join our newsletter',
					'promo_text'        => 'Offers, news and tips – straight to your inbox.',
					'promo_button'      => 'Subscribe',
					// dialog = the button opens the form in a dialog; scroll = it scrolls to a form on the page.
					'promo_action'      => 'dialog',
					'badge_enabled'     => false,
					'badge_text'        => '{discount}',
					'badge_label'       => 'coupon',
					'layout'            => 'stacked',
					'assets_everywhere' => false,
					'radius_button'     => 10,
					'radius_input'      => 8,
				) + Style_Vars::DEFAULT_COLORS + array(
					'interests_enabled'  => true,
					'interests_required' => false,
					'interests_mode'     => 'select',
					'interests_label'    => 'What are you most interested in?',
					'interests'          => array(),
				);
				break;
			default:
				$defaults = array();
		}

		/**
		 * Filters an area's defaults. Modules add their fields here.
		 *
		 * @param array $defaults Defaults.
		 */
		return (array) apply_filters( 'cfbe_settings_defaults_' . $area, $defaults );
	}

	/**
	 * Stores freshly loaded lists: the 1-day cache for the pickers and the lasting allowlist.
	 *
	 * @param array<string, string> $lists List ID => name.
	 */
	public static function remember_lists( array $lists ): void {
		set_transient( self::LISTS_CACHE, $lists, DAY_IN_SECONDS );
		update_option( self::KNOWN_LISTS, $lists, false );
	}

	/**
	 * Thank-you page after the double opt-in confirmation (also the bluefox list's redirect link).
	 */
	public static function thankyou_url(): string {
		$url = (string) self::get( 'emails', 'thankyou_url' );
		return '' !== $url ? $url : home_url( '/newsletter-confirmed/' );
	}

	/**
	 * Days a signup may stay unconfirmed (Emails tab). Before 1.0 the value lived on the Coupon tab.
	 */
	public static function pending_expiry_days(): int {
		$emails = get_option( self::option_name( 'emails' ), array() );
		$coupon = get_option( self::option_name( 'coupon' ), array() );
		if ( is_array( $emails ) && isset( $emails['pending_expiry_days'] ) ) {
			$days = (int) $emails['pending_expiry_days'];
		} elseif ( is_array( $coupon ) && isset( $coupon['pending_expiry_days'] ) ) {
			$days = (int) $coupon['pending_expiry_days'];
		} else {
			$days = (int) self::defaults( 'emails' )['pending_expiry_days'];
		}
		return min( 60, max( 1, $days ) );
	}

	/**
	 * Own preferences page ([cfbe_preferences]); set as the bluefox project's customSubscriptionPreferencesUrl.
	 */
	public static function preferences_url(): string {
		$url = (string) self::get( 'emails', 'preferences_url' );
		return '' !== $url ? $url : home_url( '/newsletter-preferences/' );
	}

	/**
	 * Stored values merged over the defaults; with $key, a single value.
	 *
	 * @param string      $area Area.
	 * @param string|null $key  Key.
	 * @return mixed
	 */
	public static function get( string $area, ?string $key = null ) {
		$values = array_merge( self::defaults( $area ), (array) get_option( self::option_name( $area ), array() ) );
		if ( null === $key ) {
			return $values;
		}
		return $values[ $key ] ?? null;
	}

	/**
	 * Saves some keys of an area from code. The complete area (defaults + stored + changes) is written: the
	 * settings sanitizers read a missing checkbox as "off", so saving only the changed keys of a never-saved area
	 * would switch its defaults off.
	 *
	 * @param string $area    Area.
	 * @param array  $changes key => value.
	 */
	public static function update( string $area, array $changes ): bool {
		return (bool) update_option( self::option_name( $area ), array_merge( self::get( $area ), $changes ) );
	}

	/**
	 * Whether the API key comes from wp-config.php.
	 */
	public static function api_key_from_constant(): bool {
		return defined( 'CFBE_BLUEFOX_API_KEY' ) && '' !== (string) CFBE_BLUEFOX_API_KEY;
	}

	/**
	 * Registers the options with the Settings API (admin_init).
	 */
	public function register(): void {
		// Module switches are saved with the General tab form (same option group as the connection).
		register_setting(
			self::option_name( 'api' ),
			Modules::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Modules::class, 'sanitize' ),
				'default'           => array(),
			)
		);
		foreach ( self::areas() as $area ) {
			register_setting(
				self::option_name( $area ),
				self::option_name( $area ),
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $input ) use ( $area ) {
						return $this->sanitize( $area, $input );
					},
					'default'           => self::defaults( $area ),
				)
			);
		}
	}

	/**
	 * Sanitizes an area; keys not present in the defaults are dropped.
	 *
	 * @param string $area  Area.
	 * @param mixed  $input Raw input.
	 */
	public function sanitize( string $area, $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$previous = (array) get_option( self::option_name( $area ), array() );

		if ( 'api' === $area ) {
			$clean = $this->sanitize_api( $input, $previous );
		} else {
			$clean = $this->sanitize_generic( self::defaults( $area ), $input );
		}

		/**
		 * Filters a sanitized area. Modules sanitize their own fields here.
		 *
		 * @param array $clean    Sanitized values.
		 * @param array $input    Raw input.
		 * @param array $previous Previously stored values.
		 */
		$clean = (array) apply_filters( 'cfbe_sanitize_settings_' . $area, $clean, $input, $previous );

		return array_intersect_key( $clean, self::defaults( $area ) );
	}

	/**
	 * Sanitizes the Connection (API) area.
	 *
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	private function sanitize_api( array $input, array $previous ): array {
		$clean = array(
			'project_id' => preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['project_id'] ?? '' ) ),
		);

		$key = trim( sanitize_text_field( (string) ( $input['api_key'] ?? '' ) ) );
		// An empty password field means "keep the stored key" - it is never printed back.
		$clean['api_key'] = '' === $key ? (string) ( $previous['api_key'] ?? '' ) : $key;

		$list_id = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['list_id'] ?? '' ) );
		$known   = get_transient( self::LISTS_CACHE );
		if ( is_array( $known ) && $known && '' !== $list_id && ! isset( $known[ $list_id ] ) ) {
			$list_id = (string) ( $previous['list_id'] ?? '' );
		}
		$clean['list_id'] = $list_id;

		$secret                  = (string) ( $input['webhook_secret'] ?? '' );
		$clean['webhook_secret'] = self::is_valid_secret( $secret ) ? $secret : self::generate_secret();

		return $clean;
	}

	/**
	 * Type-driven sanitizer for areas whose module adds its own sanitizer via filter.
	 *
	 * @param array $defaults Defaults (define the allowed keys and types).
	 * @param array $input    Raw input.
	 */
	private function sanitize_generic( array $defaults, array $input ): array {
		$clean = array();
		foreach ( $defaults as $key => $default ) {
			if ( is_bool( $default ) ) {
				$clean[ $key ] = ! empty( $input[ $key ] );
			} elseif ( ! array_key_exists( $key, $input ) ) {
				$clean[ $key ] = $default;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = absint( $input[ $key ] );
			} elseif ( is_string( $default ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			} else {
				$clean[ $key ] = $default;
			}
		}
		return $clean;
	}

	/**
	 * New random webhook secret ([A-Za-z0-9], 32 chars).
	 */
	public static function generate_secret(): string {
		return wp_generate_password( self::SECRET_LENGTH, false, false );
	}

	/**
	 * Whether a secret has the expected format.
	 *
	 * @param string $secret Secret.
	 */
	public static function is_valid_secret( string $secret ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9]{' . self::SECRET_LENGTH . '}$/', $secret );
	}
}
