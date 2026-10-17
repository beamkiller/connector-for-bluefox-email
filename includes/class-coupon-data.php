<?php
/**
 * Coupon email / bluefox field data.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Formats coupon details for the transactional email (`data`) and bluefox contact fields.
 */
final class Coupon_Data {

	/**
	 * Merge tags sent in `data` with the coupon email (key => description), shown on the Coupon tab.
	 */
	public static function merge_tags(): array {
		return array(
			'couponCode' => __( 'the coupon code, e.g. WELCOME-K7M2QX9P', 'connector-for-bluefox-email' ),
			'discount'   => __( 'the discount in the store format, e.g. 10% or €20', 'connector-for-bluefox-email' ),
			'expiry'     => __( 'last day of validity, in the site date format', 'connector-for-bluefox-email' ),
			'minSpend'   => __( 'minimum order amount, empty when there is none (use {{#if minSpend}}…{{/if}})', 'connector-for-bluefox-email' ),
			'terms'      => __( 'one sentence with the coupon conditions', 'connector-for-bluefox-email' ),
			'name'       => __( 'the subscriber name, may be empty (use {{DEFAULT name "there"}})', 'connector-for-bluefox-email' ),
			'shopName'   => __( 'the site title', 'connector-for-bluefox-email' ),
			'shopUrl'    => __( 'the shop home page URL', 'connector-for-bluefox-email' ),
		);
	}

	/**
	 * Transactional email data.
	 *
	 * @param array  $details Coupon_Factory::details().
	 * @param string $name    Subscriber name.
	 */
	public static function email_data( array $details, string $name ): array {
		return array(
			'couponCode' => $details['code'],
			'discount'   => self::discount_label( $details['discount_type'], $details['amount'] ),
			'expiry'     => $details['expires'] ? Dates::local_date( $details['expires'] ) : '',
			'minSpend'   => $details['minimum_amount'] > 0 ? self::money( $details['minimum_amount'] ) : '',
			'name'       => $name,
			'shopName'   => function_exists( 'get_bloginfo' ) ? html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) : '',
			'shopUrl'    => home_url( '/' ),
			'terms'      => self::terms( $details ),
		);
	}

	/**
	 * Conditions sentence built from the issued coupon, for {{terms}} in the email template.
	 *
	 * @param array $details Coupon_Factory::details().
	 */
	public static function terms( array $details ): string {
		$parts = array( __( 'The coupon can be used once', 'connector-for-bluefox-email' ) );
		if ( ! empty( $details['minimum_amount'] ) ) {
			/* translators: %s: minimum order amount */
			$parts[] = sprintf( __( 'on orders over %s', 'connector-for-bluefox-email' ), self::money( (float) $details['minimum_amount'] ) );
		}
		if ( ! empty( $details['individual_use'] ) ) {
			$parts[] = __( 'cannot be combined with other coupons', 'connector-for-bluefox-email' );
		}
		if ( ! empty( $details['exclude_sale'] ) ) {
			$parts[] = __( 'is not valid on sale items', 'connector-for-bluefox-email' );
		}
		if ( ! empty( $details['free_shipping'] ) ) {
			$parts[] = __( 'includes free shipping', 'connector-for-bluefox-email' );
		}
		$parts[] = __( 'and is valid until the date shown', 'connector-for-bluefox-email' );
		return implode( ', ', $parts ) . '.';
	}

	/**
	 * Bluefox contact fields for the expiry reminder.
	 *
	 * @param array $details Coupon_Factory::details().
	 */
	public static function contact_fields( array $details ): array {
		$fields = array(
			'couponCode' => $details['code'],
			'couponUsed' => false,
		);
		if ( $details['expires'] ) {
			$fields['couponExpires'] = Dates::iso_utc( $details['expires'] );
		}
		return $fields;
	}

	/**
	 * "10%" or the amount in the shop currency ("€20", "20 Ft", per WooCommerce settings).
	 *
	 * @param string $type   percent | fixed_cart.
	 * @param float  $amount Amount.
	 */
	public static function discount_label( string $type, float $amount ): string {
		if ( 'percent' === $type ) {
			if ( floor( $amount ) === $amount ) {
				return number_format( $amount, 0, '.', '' ) . '%';
			}
			$decimals = floor( $amount * 10 ) === $amount * 10 ? 1 : 2;
			return number_format( $amount, $decimals, self::decimal_separator(), '' ) . '%';
		}
		return self::money( $amount );
	}

	/**
	 * Amount in the shop's currency format as plain text (no HTML): WooCommerce decimals, separators, symbol and
	 * currency position (left / right, with or without space).
	 *
	 * @param float $amount Amount.
	 */
	public static function money( float $amount ): string {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		$thousand = function_exists( 'wc_get_price_thousand_separator' ) ? (string) wc_get_price_thousand_separator() : ',';
		$number   = number_format( abs( $amount ), max( 0, $decimals ), self::decimal_separator(), $thousand );
		$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? self::plain( (string) get_woocommerce_currency_symbol() ) : '';
		if ( '' === $symbol ) {
			return ( $amount < 0 ? '-' : '' ) . $number;
		}
		$format = function_exists( 'get_woocommerce_price_format' ) ? self::plain( (string) get_woocommerce_price_format() ) : '%1$s%2$s';
		if ( false === strpos( $format, '%1$s' ) || false === strpos( $format, '%2$s' ) ) {
			$format = '%1$s%2$s';
		}
		return ( $amount < 0 ? '-' : '' ) . str_replace( array( '%1$s', '%2$s' ), array( $symbol, $number ), $format );
	}

	/**
	 * Shop decimal separator ('.' without WooCommerce).
	 */
	private static function decimal_separator(): string {
		$separator = function_exists( 'wc_get_price_decimal_separator' ) ? (string) wc_get_price_decimal_separator() : '.';
		return '' !== $separator ? $separator : '.';
	}

	/**
	 * Entity-decoded plain text; non-breaking spaces become regular spaces.
	 *
	 * @param string $text Text (may contain HTML entities such as &euro; or &nbsp;).
	 */
	private static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		return trim( str_replace( "\u{00A0}", ' ', $text ), "\t\n\r\0\x0B" );
	}
}
