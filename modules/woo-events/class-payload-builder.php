<?php
/**
 * Template data for order / cart emails.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the `data` object sent with the email. Keys are available at the template's top level
 * (`{{orderNumber}}`, `{{#each items}}{{name}}{{/each}}`); every value is plain text (no HTML).
 */
class Payload_Builder {

	/**
	 * Data for an order event.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $event Event.
	 * @param array     $extra Extra keys merged on top (e.g. `note` for customer notes).
	 */
	public function for_order( $order, string $event, array $extra = array() ): array {
		$currency = (string) $order->get_currency();
		$date     = $order->get_date_created();

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
			$url     = $product && is_callable( array( $product, 'get_permalink' ) ) ? (string) $product->get_permalink() : '';
			$items[] = array(
				'name'      => self::text( (string) $item->get_name() ),
				'quantity'  => (int) $item->get_quantity(),
				'total'     => self::money( (float) $item->get_total() + (float) $item->get_total_tax(), $currency ),
				'sku'       => $product ? (string) $product->get_sku() : '',
				'image'     => $product ? self::image_url( (int) $product->get_image_id() ) : '',
				'url'       => $url,
				// WooCommerce opens the Reviews tab for #reviews.
				'reviewUrl' => '' !== $url ? $url . '#reviews' : '',
			);
		}

		$data = array(
			'event'                => $event,
			'orderId'              => (int) $order->get_id(),
			'orderNumber'          => (string) $order->get_order_number(),
			'orderStatus'          => self::text( (string) wc_get_order_status_name( $order->get_status() ) ),
			'orderDate'            => $date ? (string) $date->date_i18n( (string) get_option( 'date_format', 'Y-m-d' ) ) : '',
			'orderTotal'           => self::money( (float) $order->get_total(), $currency ),
			'currency'             => $currency,
			'paymentMethod'        => self::text( (string) $order->get_payment_method_title() ),
			'shippingMethod'       => self::text( (string) $order->get_shipping_method() ),
			'customerNote'         => self::text( (string) $order->get_customer_note() ),
			'billing'              => array(
				'firstName' => (string) $order->get_billing_first_name(),
				'lastName'  => (string) $order->get_billing_last_name(),
				'company'   => (string) $order->get_billing_company(),
				'city'      => (string) $order->get_billing_city(),
				'country'   => (string) $order->get_billing_country(),
				'phone'     => (string) $order->get_billing_phone(),
			),
			'shipping'             => array(
				'firstName' => (string) $order->get_shipping_first_name(),
				'lastName'  => (string) $order->get_shipping_last_name(),
				'company'   => (string) $order->get_shipping_company(),
				'address1'  => (string) $order->get_shipping_address_1(),
				'address2'  => (string) $order->get_shipping_address_2(),
				'city'      => (string) $order->get_shipping_city(),
				'postcode'  => (string) $order->get_shipping_postcode(),
				'state'     => (string) $order->get_shipping_state(),
				'country'   => (string) $order->get_shipping_country(),
			),
			'shippingAddressLines' => self::address_lines( (string) $order->get_formatted_shipping_address( '' ) ),
			'items'                => $items,
			'itemCount'            => (int) $order->get_item_count(),
			'couponCodes'          => array_values( array_map( 'strval', (array) $order->get_coupon_codes() ) ),
			'viewOrderUrl'         => (string) $order->get_view_order_url(),
		) + self::site();

		$data = array_merge( $data, $extra );

		/**
		 * Filters the template data of a WooCommerce email.
		 *
		 * @param array           $data           Template data.
		 * @param string          $event          Event.
		 * @param \WC_Order|array $order_or_cart  Order, or the abandoned cart row.
		 */
		return (array) apply_filters( 'cfbe_woo_events_data', $data, $event, $order );
	}

	/**
	 * Data for an abandoned cart.
	 *
	 * @param array $cart Cart row (`cart` = decoded item list).
	 */
	public function for_cart( array $cart ): array {
		$currency = (string) ( $cart['currency'] ?? '' );
		$items    = array();
		$count    = 0;
		foreach ( (array) ( $cart['cart'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$quantity = (int) ( $item['quantity'] ?? 0 );
			$count   += $quantity;
			$items[]  = array(
				'name'     => self::text( (string) ( $item['name'] ?? '' ) ),
				'quantity' => $quantity,
				'total'    => self::money( (float) ( $item['line_total'] ?? 0 ), $currency ),
				'sku'      => (string) ( $item['sku'] ?? '' ),
				'image'    => (string) ( $item['image'] ?? '' ),
			);
		}

		$data = array(
			'event'     => Rules::EVENT_ABANDONED_CART,
			'cartUrl'   => Cart_Tracker::restore_url( (string) ( $cart['token'] ?? '' ) ),
			'items'     => $items,
			'itemCount' => $count,
			'cartTotal' => self::money( (float) ( $cart['cart_total'] ?? 0 ), $currency ),
			'currency'  => $currency,
		) + self::site();

		/** This filter is documented in modules/woo-events/class-payload-builder.php */
		return (array) apply_filters( 'cfbe_woo_events_data', $data, Rules::EVENT_ABANDONED_CART, $cart );
	}

	/**
	 * Store-formatted amount as plain text (`1 234 Ft`, `$12.50`), no HTML or entities.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 */
	public static function money( float $amount, string $currency ): string {
		$args = '' !== $currency ? array( 'currency' => $currency ) : array();
		return self::text( (string) wc_price( $amount, $args ) );
	}

	/**
	 * HTML → plain text (tags stripped, entities decoded, non-breaking spaces normalised).
	 *
	 * @param string $html HTML.
	 */
	public static function text( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( str_replace( "\xC2\xA0", ' ', $text ) );
	}

	/**
	 * Formatted address (`<br/>` separated) → lines.
	 *
	 * @param string $formatted Formatted address HTML.
	 * @return string[]
	 */
	public static function address_lines( string $formatted ): array {
		$lines = preg_split( '#<br\s*/?>#i', $formatted );
		$lines = array_map( array( self::class, 'text' ), is_array( $lines ) ? $lines : array() );
		return array_values( array_filter( $lines, 'strlen' ) );
	}

	/**
	 * Product image URL (shop thumbnail), '' when none.
	 *
	 * @param int $image_id Attachment ID.
	 */
	public static function image_url( int $image_id ): string {
		if ( $image_id <= 0 ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Site keys shared by every payload.
	 */
	private static function site(): array {
		return array(
			'siteName' => self::text( (string) get_bloginfo( 'name' ) ),
			'siteUrl'  => (string) home_url( '/' ),
		);
	}
}
