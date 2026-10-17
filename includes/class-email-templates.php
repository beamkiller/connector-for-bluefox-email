<?php
/**
 * Ready-made bluefox emails: one per email the plugin sends, created and updated in bluefox from wp-admin.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Template definitions, the shared responsive layout and the record of what was created in bluefox.
 * The texts are plain admin input (Handlebars variables such as {{orderNumber}} work in them); the layout,
 * colours (Form tab), logo and data blocks (items, coupon box …) are added here.
 */
final class Email_Templates {

	const OPTION = 'cfbe_email_templates';

	const TEXT_FIELDS = array( 'subject', 'preview', 'heading', 'intro', 'button', 'footer' );

	/**
	 * Template definitions: key => label, description, type, module, event (order email rule), delay (rule delay,
	 * in delay_unit), delay_unit, button_url, blocks, variables, defaults (texts).
	 */
	public static function definitions(): array {
		$order_vars = '{{orderNumber}}, {{orderDate}}, {{orderTotal}}, {{billing.firstName}}, {{paymentMethod}}, {{shippingMethod}}, {{siteName}}';
		return array(
			'confirm'          => array(
				'label'       => __( 'Double opt-in confirmation', 'connector-for-bluefox-email' ),
				'description' => __( 'Sent by bluefox when someone subscribes. Creating it also sets it as the confirmation email of the list chosen below, with your thank-you page as the redirect link.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'forms',
				'button_url'  => '{{{verifyLink}}}',
				'blocks'      => array(),
				'variables'   => '{{contact.email}}',
				'defaults'    => array(
					'subject' => __( 'Please confirm your subscription', 'connector-for-bluefox-email' ),
					'preview' => __( 'One click and you are on the list.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Confirm your subscription', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Thank you for signing up to our newsletter. Please click the button below to confirm your email address.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Yes, subscribe me', 'connector-for-bluefox-email' ),
					'footer'  => __( 'If you did not sign up, simply ignore this email: you will not be subscribed.', 'connector-for-bluefox-email' ),
				),
			),
			'welcome_coupon'   => array(
				'label'       => __( 'Welcome coupon', 'connector-for-bluefox-email' ),
				'description' => __( 'Delivers the welcome coupon after the subscription is confirmed. Creating it also selects it on the Coupon tab.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'coupon',
				'button_url'  => '{{{shopUrl}}}',
				'blocks'      => array( 'coupon' ),
				'variables'   => '{{couponCode}}, {{discount}}, {{expiry}}, {{minSpend}}, {{name}}, {{shopName}}',
				'defaults'    => array(
					'subject' => __( 'Your welcome gift: {{discount}} off', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your coupon code is inside.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Welcome, {{DEFAULT name "friend"}}!', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Thank you for confirming your subscription. As promised, here is your welcome gift. Enter the code in the cart, using the same email address you subscribed with.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Start shopping', 'connector-for-bluefox-email' ),
					'footer'  => __( 'One welcome coupon per email address. {{terms}}', 'connector-for-bluefox-email' ),
				),
			),
			'order_processing' => array(
				'label'       => __( 'Order received', 'connector-for-bluefox-email' ),
				'description' => __( 'Order status “Processing”. Creating it also adds an order email rule for this status.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'order_emails',
				'event'       => 'order_status_processing',
				'button_url'  => '{{{viewOrderUrl}}}',
				'blocks'      => array( 'items' ),
				'variables'   => $order_vars,
				'defaults'    => array(
					'subject' => __( 'We received your order #{{orderNumber}}', 'connector-for-bluefox-email' ),
					'preview' => __( 'Thank you! We are preparing your order.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Thank you for your order!', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, we have received your order #{{orderNumber}} and are preparing it now.', 'connector-for-bluefox-email' ),
					'button'  => __( 'View your order', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Questions about your order? Simply reply to this email.', 'connector-for-bluefox-email' ),
				),
			),
			'order_completed'  => array(
				'label'       => __( 'Order completed', 'connector-for-bluefox-email' ),
				'description' => __( 'Order status “Completed”. Creating it also adds an order email rule for this status.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'order_emails',
				'event'       => 'order_status_completed',
				'button_url'  => '{{{viewOrderUrl}}}',
				'blocks'      => array( 'items' ),
				'variables'   => $order_vars,
				'defaults'    => array(
					'subject' => __( 'Your order #{{orderNumber}} is complete', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your order is on its way.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Your order is complete', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, good news: your order #{{orderNumber}} has been completed.', 'connector-for-bluefox-email' ),
					'button'  => __( 'View your order', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Thank you for shopping with us!', 'connector-for-bluefox-email' ),
				),
			),
			'first_order'      => array(
				'label'       => __( 'First order thank-you', 'connector-for-bluefox-email' ),
				'description' => __( 'The first paid order of a customer. Creating it also adds an order email rule for this event.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'order_emails',
				'event'       => 'first_order',
				'button_url'  => '{{{siteUrl}}}',
				'blocks'      => array(),
				'variables'   => $order_vars,
				'defaults'    => array(
					'subject' => __( 'Thank you for your first order!', 'connector-for-bluefox-email' ),
					'preview' => __( 'A warm welcome from our team.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Welcome to {{siteName}}!', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, thank you for choosing us for your first order. We hope you love it.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Visit the shop', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Questions? Simply reply to this email.', 'connector-for-bluefox-email' ),
				),
			),
			'customer_note'    => array(
				'label'       => __( 'Note to customer', 'connector-for-bluefox-email' ),
				'description' => __( 'Sent when you add a note to the customer on an order. Creating it also adds an order email rule for this event.', 'connector-for-bluefox-email' ),
				'type'        => 'transactional',
				'module'      => 'order_emails',
				'event'       => 'order_note_customer',
				'button_url'  => '{{{viewOrderUrl}}}',
				'blocks'      => array( 'note' ),
				'variables'   => $order_vars . ', {{note}}',
				'defaults'    => array(
					'subject' => __( 'A note about your order #{{orderNumber}}', 'connector-for-bluefox-email' ),
					'preview' => __( 'We added a note to your order.', 'connector-for-bluefox-email' ),
					'heading' => __( 'A note about your order', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, we added the following note to your order #{{orderNumber}}:', 'connector-for-bluefox-email' ),
					'button'  => __( 'View your order', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Questions? Simply reply to this email.', 'connector-for-bluefox-email' ),
				),
			),
			'review_request'   => array(
				'label'       => __( 'Review request', 'connector-for-bluefox-email' ),
				'description' => __( 'Asks for a review 7 days after the order is completed (triggered email: only active subscribers of the chosen list receive it). Creating it also adds an order email rule.', 'connector-for-bluefox-email' ),
				'type'        => 'triggered',
				'module'      => 'order_emails',
				'event'       => 'review_request',
				'delay'       => 7,
				'delay_unit'  => 'days',
				'button_url'  => '{{{viewOrderUrl}}}',
				'blocks'      => array( 'items' ),
				'variables'   => $order_vars,
				'defaults'    => array(
					'subject' => __( 'How did we do?', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your opinion helps other shoppers.', 'connector-for-bluefox-email' ),
					'heading' => __( 'How was your order?', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, we hope you are happy with your order #{{orderNumber}}. Would you take a minute to review the products?', 'connector-for-bluefox-email' ),
					'button'  => __( 'Write a review', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Thank you for helping us get better.', 'connector-for-bluefox-email' ),
				),
			),
			'cart_reminder'    => array(
				'label'       => __( 'Abandoned cart reminder', 'connector-for-bluefox-email' ),
				'description' => __( 'Reminds shoppers of the cart they left, with a restore link and the recovery coupon when it is on (triggered email: only active subscribers of the chosen list receive it). Creating it also adds an abandoned cart rule.', 'connector-for-bluefox-email' ),
				'type'        => 'triggered',
				'module'      => 'abandoned_cart',
				'event'       => 'abandoned_cart',
				'delay'       => 0,
				'delay_unit'  => 'minutes',
				'button_url'  => '{{{cartUrl}}}',
				'blocks'      => array( 'cart_items', 'cart_coupon' ),
				'variables'   => '{{cartTotal}}, {{itemCount}}, {{couponCode}}, {{couponDiscount}}, {{couponExpiry}}, {{siteName}}',
				'defaults'    => array(
					'subject' => __( 'You left something in your cart', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your cart is saved and waiting for you.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Still thinking it over?', 'connector-for-bluefox-email' ),
					'intro'   => __( 'You left these items in your cart. We saved it for you, so you can finish your order with one click.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Back to my cart', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Questions? Simply reply to this email.', 'connector-for-bluefox-email' ),
				),
			),
		);
	}

	/**
	 * Templates whose feature is switched on.
	 */
	public static function available(): array {
		return array_filter(
			self::definitions(),
			static function ( array $template ): bool {
				return Modules::enabled( (string) $template['module'] );
			}
		);
	}

	/**
	 * What was created in bluefox for a template: email_id, texts, list_id, remote_updated (bluefox updatedAt
	 * after our last write); empty when nothing was created yet.
	 *
	 * @param string $key Template key.
	 */
	public static function record( string $key ): array {
		$all = get_option( self::OPTION, array() );
		return is_array( $all ) && is_array( $all[ $key ] ?? null ) ? $all[ $key ] : array();
	}

	/**
	 * Stores a template record.
	 *
	 * @param string $key    Template key.
	 * @param array  $record Record (empty array deletes it).
	 */
	public static function save_record( string $key, array $record ): void {
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		if ( $record ) {
			$all[ $key ] = $record;
		} else {
			unset( $all[ $key ] );
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Texts of a template: the stored ones over the defaults.
	 *
	 * @param string $key Template key.
	 */
	public static function texts( string $key ): array {
		$definition = self::definitions()[ $key ] ?? null;
		if ( null === $definition ) {
			return array();
		}
		$stored = (array) ( self::record( $key )['texts'] ?? array() );
		return array_merge( $definition['defaults'], array_intersect_key( $stored, $definition['defaults'] ) );
	}

	/**
	 * Cleans posted texts: one line each, except the intro and footer (paragraphs).
	 *
	 * @param string $key   Template key.
	 * @param array  $input Raw texts.
	 */
	public static function clean_texts( string $key, array $input ): array {
		$texts = self::texts( $key );
		foreach ( self::TEXT_FIELDS as $field ) {
			if ( ! isset( $input[ $field ] ) ) {
				continue;
			}
			$value = in_array( $field, array( 'intro', 'footer' ), true ) ? sanitize_textarea_field( (string) $input[ $field ] ) : sanitize_text_field( (string) $input[ $field ] );
			if ( '' !== trim( $value ) || in_array( $field, array( 'preview', 'footer' ), true ) ) {
				$texts[ $field ] = $value;
			}
		}
		return $texts;
	}

	/**
	 * The bluefox email for a template: name, subject, previewText, type html and the Handlebars document.
	 *
	 * @param string $key   Template key.
	 * @param array  $texts Texts (see texts()).
	 */
	public static function render( string $key, array $texts ): array {
		$definition = self::definitions()[ $key ];
		$texts      = array_merge( $definition['defaults'], $texts );
		$colors     = self::colors();
		$site_raw   = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$site       = self::text( $site_raw );

		$blocks = '';
		foreach ( $definition['blocks'] as $block ) {
			$blocks .= self::block( $block, $colors );
		}
		$button = sprintf(
			'<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0 0;"><tr><td align="center" style="border-radius:10px;background:%1$s;"><a href="%2$s" target="_blank" style="display:inline-block;padding:15px 30px;font-size:16px;font-weight:600;line-height:1.2;color:%3$s;text-decoration:none;border-radius:10px;">%4$s</a></td></tr></table>',
			$colors['primary'],
			$definition['button_url'],
			$colors['button_text'],
			self::text( $texts['button'] )
		);
		$footer = '' !== trim( $texts['footer'] ) ? '<p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:' . $colors['muted'] . ';">' . nl2br( self::text( $texts['footer'] ) ) . '</p>' : '';
		$legal  = 'triggered' === $definition['type']
			/* translators: unsubscribe link label in marketing emails */
			? '<br><a href="{{{unsubscribeLink}}}" style="color:' . $colors['muted'] . ';">' . esc_html__( 'Unsubscribe', 'connector-for-bluefox-email' ) . '</a>'
			: '';

		$document = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><title>' . self::text( $texts['subject'] ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . $colors['page'] . ';">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . self::text( $texts['preview'] ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $colors['page'] . ';"><tr><td align="center" style="padding:32px 12px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:' . $colors['text'] . ';">'
			. '<tr><td align="center" style="padding:0 0 20px 0;">' . self::logo( $site_raw, $colors ) . '</td></tr>'
			. '<tr><td style="background:#ffffff;border-radius:16px;border-top:4px solid ' . $colors['accent'] . ';padding:36px 36px 32px 36px;">'
			. '<h1 style="margin:0 0 14px 0;font-size:24px;line-height:1.3;font-weight:700;color:' . $colors['text'] . ';">' . self::text( $texts['heading'] ) . '</h1>'
			. '<p style="margin:0 0 20px 0;font-size:16px;line-height:1.65;">' . nl2br( self::text( $texts['intro'] ) ) . '</p>'
			. $blocks . $button . $footer
			. '</td></tr>'
			. '<tr><td align="center" style="padding:20px 12px 0 12px;font-size:12px;line-height:1.6;color:' . $colors['muted'] . ';"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . $colors['muted'] . ';text-decoration:none;">' . $site . '</a>' . $legal . '</td></tr>'
			. '</table></td></tr></table></body></html>';

		return array(
			/* translators: %s: template label, e.g. Welcome coupon */
			'name'        => sprintf( __( '%s (Connector for bluefox.email)', 'connector-for-bluefox-email' ), $definition['label'] ),
			'subject'     => $texts['subject'],
			'previewText' => $texts['preview'],
			'type'        => 'html',
			'document'    => $document,
		);
	}

	/**
	 * Sample data for test sends.
	 *
	 * @param string $key Template key.
	 */
	public static function sample_data( string $key ): array {
		$items = array(
			array(
				'name'     => __( 'Sample product', 'connector-for-bluefox-email' ),
				'quantity' => 2,
				'total'    => '24.00',
				'image'    => '',
			),
		);
		$site  = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$base  = array(
			'siteName'     => $site,
			'siteUrl'      => home_url( '/' ),
			'shopName'     => $site,
			'shopUrl'      => home_url( '/' ),
			'name'         => 'Jane',
			'verifyLink'   => home_url( '/' ),
			'orderNumber'  => '1001',
			'orderDate'    => Dates::local_date( time() ),
			'orderTotal'   => '24.00',
			'viewOrderUrl' => home_url( '/' ),
			'billing'      => array( 'firstName' => 'Jane' ),
			'items'        => $items,
			'itemCount'    => 2,
			'note'         => __( 'Your parcel ships tomorrow.', 'connector-for-bluefox-email' ),
			'cartUrl'      => home_url( '/' ),
			'cartTotal'    => '24.00',
			'couponCode'   => 'WELCOME-TEST1234',
			'discount'     => '10%',
			'expiry'       => Dates::local_date( time() + 30 * DAY_IN_SECONDS ),
			'terms'        => '',
		);
		if ( 'cart_reminder' === $key ) {
			$base['couponDiscount'] = '10%';
			$base['couponExpiry']   = $base['expiry'];
		}
		return $base;
	}

	/**
	 * Data block HTML.
	 *
	 * @param string $block  Block name.
	 * @param array  $colors Colours.
	 */
	private static function block( string $block, array $colors ): string {
		$box = 'border:2px dashed ' . $colors['accent'] . ';border-radius:12px;background:' . $colors['card'] . ';';
		switch ( $block ) {
			case 'coupon':
				return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;' . $box . '"><tr><td align="center" style="padding:22px 16px;">'
					. '<div style="font-size:13px;color:' . $colors['muted'] . ';">' . esc_html__( 'Your coupon code', 'connector-for-bluefox-email' ) . '</div>'
					. '<div style="margin:6px 0;font-family:\'Courier New\',Courier,monospace;font-size:28px;font-weight:700;letter-spacing:3px;">{{couponCode}}</div>'
					/* translators: %s: discount placeholder, e.g. 10% */
					. '<div style="font-size:18px;font-weight:700;color:' . $colors['accent'] . ';">' . sprintf( esc_html__( '%s off', 'connector-for-bluefox-email' ), '{{discount}}' ) . '</div>'
					/* translators: %s: expiry date placeholder */
					. '<div style="margin-top:8px;font-size:13px;color:' . $colors['muted'] . ';">' . sprintf( esc_html__( 'Valid until %s', 'connector-for-bluefox-email' ), '{{expiry}}' ) . '{{#if minSpend}} · ' . sprintf( /* translators: %s: amount placeholder */ esc_html__( 'minimum order %s', 'connector-for-bluefox-email' ), '{{minSpend}}' ) . '{{/if}}</div>'
					. '</td></tr></table>';
			case 'cart_coupon':
				return '{{#if couponCode}}<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;' . $box . '"><tr><td align="center" style="padding:18px 16px;">'
					/* translators: %s: discount placeholder */
					. '<div style="font-size:15px;">' . sprintf( esc_html__( 'Finish your order now and get %s off with this code:', 'connector-for-bluefox-email' ), '<strong>{{couponDiscount}}</strong>' ) . '</div>'
					. '<div style="margin:8px 0 4px 0;font-family:\'Courier New\',Courier,monospace;font-size:24px;font-weight:700;letter-spacing:2px;">{{couponCode}}</div>'
					/* translators: %s: expiry date placeholder */
					. '<div style="font-size:13px;color:' . $colors['muted'] . ';">' . sprintf( esc_html__( 'Valid until %s. The link below applies it automatically.', 'connector-for-bluefox-email' ), '{{couponExpiry}}' ) . '</div>'
					. '</td></tr></table>{{/if}}';
			case 'note':
				return '<div style="margin:0 0 24px 0;padding:16px 18px;border-left:4px solid ' . $colors['accent'] . ';background:' . $colors['card'] . ';border-radius:8px;font-size:15px;line-height:1.6;">{{note}}</div>';
			case 'items':
			case 'cart_items':
				$total = 'items' === $block ? '{{orderTotal}}' : '{{cartTotal}}';
				$line  = 'border-bottom:1px solid ' . $colors['border'] . ';';
				return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;border-top:1px solid ' . $colors['border'] . ';">'
					. '{{#each items}}<tr>'
					. '<td style="padding:12px 12px 12px 0;' . $line . 'font-size:15px;">{{#if image}}<img src="{{{image}}}" width="48" height="48" alt="" style="display:inline-block;vertical-align:middle;margin-right:12px;border-radius:8px;object-fit:cover;">{{/if}}{{name}} <span style="color:' . $colors['muted'] . ';">× {{quantity}}</span></td>'
					. '<td align="right" style="padding:12px 0;' . $line . 'font-size:15px;white-space:nowrap;">{{total}}</td>'
					. '</tr>{{/each}}'
					. '<tr><td style="padding:12px 0;font-weight:700;">' . esc_html__( 'Total', 'connector-for-bluefox-email' ) . '</td><td align="right" style="padding:12px 0;font-weight:700;white-space:nowrap;">' . $total . '</td></tr>'
					. '</table>';
		}
		return '';
	}

	/**
	 * Site logo (custom logo, else the site icon), else the site name.
	 *
	 * @param string $site   Site name.
	 * @param array  $colors Colours.
	 */
	private static function logo( string $site, array $colors ): string {
		$url = '';
		if ( function_exists( 'get_theme_mod' ) && get_theme_mod( 'custom_logo' ) ) {
			$url = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
		}
		if ( '' === $url && function_exists( 'get_site_icon_url' ) ) {
			$url = (string) get_site_icon_url( 96 );
		}
		if ( '' !== $url ) {
			return '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $site ) . '" style="display:block;max-height:56px;max-width:220px;height:auto;border:0;"></a>';
		}
		return '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" style="font-size:20px;font-weight:700;color:' . $colors['text'] . ';text-decoration:none;">' . self::text( $site ) . '</a>';
	}

	/**
	 * Email colours from the Form tab.
	 */
	private static function colors(): array {
		$form = Settings::get( 'form' );
		$pick = static function ( string $key ) use ( $form ): string {
			$value = Style_Vars::hex( (string) ( $form[ 'color_' . $key ] ?? '' ) );
			return null !== $value ? $value : Style_Vars::DEFAULT_COLORS[ 'color_' . $key ];
		};
		return array(
			'primary'     => $pick( 'primary' ),
			'button_text' => $pick( 'button_text' ),
			'accent'      => $pick( 'accent' ),
			'text'        => $pick( 'text' ),
			'muted'       => $pick( 'muted' ),
			'border'      => $pick( 'border' ),
			'card'        => $pick( 'card' ),
			'page'        => '#f2f3f5',
		);
	}

	/**
	 * Escapes admin text for an HTML text node. Quotes and braces stay intact, so Handlebars expressions such as
	 * {{DEFAULT name "friend"}} keep working.
	 *
	 * @param string $text Text.
	 */
	private static function text( string $text ): string {
		return htmlspecialchars( $text, ENT_NOQUOTES, 'UTF-8', false );
	}
}
