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
 * design (Email_Design: WooCommerce's email settings or the Form tab), logo and data blocks (items, coupon box …) are added here.
 */
final class Email_Templates {

	const OPTION = 'cfbe_email_templates';

	const TEXT_FIELDS = array( 'subject', 'preview', 'heading', 'intro', 'button', 'footer' );

	/**
	 * Template definitions: key => label, group (newsletter | extra = emails WooCommerce does not send),
	 * what / why / when / on_create (card texts), type (default), types (the admin may choose; default: type only), module, event (order email rule), delay (rule delay, in
	 * delay_unit), delay_unit, button_url, blocks, variables, defaults (texts).
	 */
	public static function definitions(): array {
		$order_vars = '{{orderNumber}}, {{orderDate}}, {{orderTotal}}, {{billing.firstName}}, {{paymentMethod}}, {{shippingMethod}}, {{siteName}}';
		return array(
			'confirm'        => array(
				'label'      => __( 'Double opt-in confirmation', 'connector-for-bluefox-email' ),
				'group'      => 'newsletter',
				'what'       => __( 'Asks a new subscriber to confirm their email address (double opt-in).', 'connector-for-bluefox-email' ),
				'why'        => __( 'Only confirmed, real addresses join your list – this keeps it clean and GDPR-proof. The welcome coupon is sent only after confirmation.', 'connector-for-bluefox-email' ),
				'when'       => __( 'Right after someone subscribes. bluefox sends it.', 'connector-for-bluefox-email' ),
				'on_create'  => __( 'Switches on double opt-in for the chosen list with this email, and sets your thank-you page as the page shown after confirming.', 'connector-for-bluefox-email' ),
				'type'       => 'transactional',
				'module'     => 'forms',
				'button_url' => '{{{verifyLink}}}',
				'blocks'     => array(),
				'variables'  => '{{contact.email}}',
				'defaults'   => array(
					'subject' => __( 'Please confirm your subscription', 'connector-for-bluefox-email' ),
					'preview' => __( 'One click and you are on the list.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Confirm your subscription', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Thank you for signing up to our newsletter. Please click the button below to confirm your email address.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Yes, subscribe me', 'connector-for-bluefox-email' ),
					'footer'  => __( 'If you did not sign up, simply ignore this email: you will not be subscribed.', 'connector-for-bluefox-email' ),
				),
			),
			'welcome_coupon' => array(
				'label'      => __( 'Welcome coupon', 'connector-for-bluefox-email' ),
				'group'      => 'newsletter',
				'what'       => __( 'Delivers the welcome coupon code to a new subscriber.', 'connector-for-bluefox-email' ),
				'why'        => __( 'Rewards the signup and brings the new subscriber straight to the shop.', 'connector-for-bluefox-email' ),
				'when'       => __( 'When the subscriber has clicked the confirmation link.', 'connector-for-bluefox-email' ),
				'on_create'  => __( 'Selects it as the coupon email on the Coupon tab.', 'connector-for-bluefox-email' ),
				'type'       => 'transactional',
				'module'     => 'coupon',
				'button_url' => '{{{shopUrl}}}',
				'blocks'     => array( 'coupon' ),
				'variables'  => '{{couponCode}}, {{discount}}, {{expiry}}, {{minSpend}}, {{name}}, {{shopName}}',
				'defaults'   => array(
					'subject' => __( 'Your welcome gift: {{discount}} off', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your coupon code is inside.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Welcome, {{DEFAULT name "friend"}}!', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Thank you for confirming your subscription. As promised, here is your welcome gift. Enter the code in the cart, using the same email address you subscribed with.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Start shopping', 'connector-for-bluefox-email' ),
					'footer'  => __( 'One welcome coupon per email address. {{terms}}', 'connector-for-bluefox-email' ),
				),
			),
			'first_order'    => array(
				'label'      => __( 'First order thank-you', 'connector-for-bluefox-email' ),
				'group'      => 'extra',
				'what'       => __( 'A personal thank-you for a customer’s very first order.', 'connector-for-bluefox-email' ),
				'why'        => __( 'Makes first-time buyers feel welcome and more likely to return. WooCommerce has no such email.', 'connector-for-bluefox-email' ),
				'when'       => __( 'With the first paid order of an email address (guests included).', 'connector-for-bluefox-email' ),
				'on_create'  => __( 'Adds an Order emails rule for this event.', 'connector-for-bluefox-email' ),
				'type'       => 'triggered',
				'types'      => array( 'triggered', 'transactional' ),
				'module'     => 'order_emails',
				'event'      => 'first_order',
				'button_url' => '{{{siteUrl}}}',
				'blocks'     => array(),
				'variables'  => $order_vars,
				'defaults'   => array(
					'subject' => __( 'Thank you for your first order!', 'connector-for-bluefox-email' ),
					'preview' => __( 'A warm welcome from our team.', 'connector-for-bluefox-email' ),
					'heading' => __( 'Welcome to {{siteName}}!', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, thank you for choosing us for your first order. We hope you love it.', 'connector-for-bluefox-email' ),
					'button'  => __( 'Visit the shop', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Questions? Simply reply to this email.', 'connector-for-bluefox-email' ),
				),
			),
			'review_request' => array(
				'label'      => __( 'Review request', 'connector-for-bluefox-email' ),
				'group'      => 'extra',
				'what'       => __( 'Asks the customer to review the products they bought.', 'connector-for-bluefox-email' ),
				'why'        => __( 'More reviews build trust and bring more sales. WooCommerce does not ask for reviews by email.', 'connector-for-bluefox-email' ),
				'when'       => __( '7 days after the order is completed.', 'connector-for-bluefox-email' ),
				'on_create'  => __( 'Adds an Order emails rule with a 7-day delay.', 'connector-for-bluefox-email' ),
				'type'       => 'triggered',
				'types'      => array( 'triggered', 'transactional' ),
				'module'     => 'order_emails',
				'event'      => 'review_request',
				'delay'      => 7,
				'delay_unit' => 'days',
				'button_url' => '{{{siteUrl}}}',
				'blocks'     => array( 'review_items' ),
				'variables'  => $order_vars,
				'defaults'   => array(
					'subject' => __( 'How did we do?', 'connector-for-bluefox-email' ),
					'preview' => __( 'Your opinion helps other shoppers.', 'connector-for-bluefox-email' ),
					'heading' => __( 'How was your order?', 'connector-for-bluefox-email' ),
					'intro'   => __( 'Hi {{DEFAULT billing.firstName "there"}}, we hope you are happy with your order #{{orderNumber}}. Would you take a minute to review the products?', 'connector-for-bluefox-email' ),
					'button'  => __( 'Visit the shop', 'connector-for-bluefox-email' ),
					'footer'  => __( 'Thank you for helping us get better.', 'connector-for-bluefox-email' ),
				),
			),
			'cart_reminder'  => array(
				'label'      => __( 'Abandoned cart reminder', 'connector-for-bluefox-email' ),
				'group'      => 'extra',
				'what'       => __( 'Reminds shoppers of the cart they left, with a button that restores it.', 'connector-for-bluefox-email' ),
				'why'        => __( 'Wins back sales that would otherwise be lost; can include a single-use recovery coupon. WooCommerce has no such email.', 'connector-for-bluefox-email' ),
				'when'       => __( 'When a cart has been left untouched for the idle time set on the Abandoned carts tab – only to active subscribers of the chosen list (it is a marketing email).', 'connector-for-bluefox-email' ),
				'on_create'  => __( 'Adds an abandoned cart reminder on the Abandoned carts tab.', 'connector-for-bluefox-email' ),
				'type'       => 'triggered',
				'module'     => 'abandoned_cart',
				'event'      => 'abandoned_cart',
				'delay'      => 0,
				'delay_unit' => 'minutes',
				'button_url' => '{{{cartUrl}}}',
				'blocks'     => array( 'cart_items', 'cart_coupon' ),
				'variables'  => '{{cartTotal}}, {{itemCount}}, {{couponCode}}, {{couponDiscount}}, {{couponExpiry}}, {{siteName}}',
				'defaults'   => array(
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
	 * Email types the admin may choose for a template (the first is the default).
	 *
	 * @param string $key Template key.
	 * @return string[]
	 */
	public static function types( string $key ): array {
		$definition = self::definitions()[ $key ] ?? array();
		return (array) ( $definition['types'] ?? array( (string) ( $definition['type'] ?? 'transactional' ) ) );
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
	 * @param string $type  Email type ('' = the template's default); triggered emails get an unsubscribe link.
	 */
	public static function render( string $key, array $texts, string $type = '' ): array {
		$definition = self::definitions()[ $key ];
		$type       = '' !== $type ? $type : (string) $definition['type'];
		$texts      = array_merge( $definition['defaults'], $texts );
		$design     = Email_Design::get();
		$colors     = (array) $design['colors'];
		$site_raw   = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$site       = self::text( $site_raw );
		$font       = str_replace( '"', '\'', (string) $design['font'] );
		$logo       = (array) $design['logo'];
		// Same page and card colour (WooCommerce's newer default): a border keeps the card visible.
		$card_edge = strtolower( $colors['page'] ) === strtolower( $colors['body'] ) ? 'border:1px solid ' . $colors['border'] . ';' : '';

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
		$legal  = 'triggered' === $type
			/* translators: unsubscribe link label in marketing emails */
			? '<br><a href="{{{unsubscribeLink}}}" style="color:' . $colors['footer'] . ';">' . esc_html__( 'Unsubscribe', 'connector-for-bluefox-email' ) . '</a>'
			: '';
		$bottom = '' !== (string) $design['footer']
			? (string) preg_replace( '/<a /', '<a style="color:' . $colors['footer'] . ';" ', (string) $design['footer'] )
			: '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . $colors['footer'] . ';text-decoration:none;">' . $site . '</a>';

		$document = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting"><title>' . self::text( $texts['subject'] ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . $colors['page'] . ';">'
			. '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . self::text( $texts['preview'] ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $colors['page'] . ';"><tr><td align="center" style="padding:32px 12px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;font-family:' . $font . ';color:' . $colors['text'] . ';">'
			. '<tr><td align="' . esc_attr( (string) $logo['align'] ) . '" style="padding:0 0 20px 0;">' . self::logo( $site_raw, $colors, $logo ) . '</td></tr>'
			. '<tr><td style="background:' . $colors['body'] . ';border-radius:16px;' . $card_edge . 'border-top:4px solid ' . $colors['accent'] . ';padding:36px 36px 32px 36px;">'
			. '<h1 style="margin:0 0 14px 0;font-size:24px;line-height:1.3;font-weight:700;color:' . $colors['text'] . ';">' . self::text( $texts['heading'] ) . '</h1>'
			. '<p style="margin:0 0 20px 0;font-size:16px;line-height:1.65;">' . nl2br( self::text( $texts['intro'] ) ) . '</p>'
			. $blocks . $button . $footer
			. '</td></tr>'
			. '<tr><td align="center" style="padding:20px 12px 0 12px;font-size:12px;line-height:1.6;color:' . $colors['footer'] . ';">' . $bottom . $legal . '</td></tr>'
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
	 * Sample data for the preview and test sends.
	 *
	 * @param string $key Template key.
	 */
	public static function sample_data( string $key ): array {
		$items = array(
			array(
				'name'      => __( 'Sample product', 'connector-for-bluefox-email' ),
				'quantity'  => 2,
				'total'     => '24.00',
				'image'     => '',
				'url'       => home_url( '/' ),
				'reviewUrl' => home_url( '/' ) . '#reviews',
			),
			array(
				'name'      => __( 'Another sample product', 'connector-for-bluefox-email' ),
				'quantity'  => 1,
				'total'     => '9.90',
				'image'     => '',
				'url'       => home_url( '/' ),
				'reviewUrl' => home_url( '/' ) . '#reviews',
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
			'orderTotal'   => '33.90',
			'viewOrderUrl' => home_url( '/' ),
			'billing'      => array( 'firstName' => 'Jane' ),
			'items'        => $items,
			'itemCount'    => 3,
			'note'         => __( 'Your parcel ships tomorrow.', 'connector-for-bluefox-email' ),
			'cartUrl'      => home_url( '/' ),
			'cartTotal'    => '33.90',
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
			case 'review_items':
				// One row per product: image, name and its own review button (to the product's Reviews tab).
				return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px 0;border-top:1px solid ' . $colors['border'] . ';">'
					. '{{#each items}}<tr>'
					. '<td style="padding:14px 12px 14px 0;border-bottom:1px solid ' . $colors['border'] . ';font-size:15px;">{{#if image}}<img src="{{{image}}}" width="56" height="56" alt="" style="display:inline-block;vertical-align:middle;margin-right:12px;border-radius:8px;object-fit:cover;">{{/if}}{{name}}</td>'
					. '<td align="right" style="padding:14px 0;border-bottom:1px solid ' . $colors['border'] . ';white-space:nowrap;">{{#if reviewUrl}}<a href="{{{reviewUrl}}}" target="_blank" style="display:inline-block;padding:9px 16px;border-radius:8px;background:' . $colors['primary'] . ';color:' . $colors['button_text'] . ';font-size:14px;font-weight:600;text-decoration:none;">' . esc_html__( 'Write a review', 'connector-for-bluefox-email' ) . '</a>{{/if}}</td>'
					. '</tr>{{/each}}'
					. '</table>';
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
	 * Logo (see Email_Design), else the site name.
	 *
	 * @param string $site   Site name.
	 * @param array  $colors Colours.
	 * @param array  $logo   url, width (px, 0 = auto), align.
	 */
	private static function logo( string $site, array $colors, array $logo ): string {
		$url = (string) ( $logo['url'] ?? '' );
		if ( '' !== $url ) {
			$width = (int) ( $logo['width'] ?? 0 );
			$size  = $width > 0 ? 'width:' . $width . 'px;max-width:100%;' : 'max-height:56px;max-width:220px;';
			return '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" style="display:inline-block;"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $site ) . '"' . ( $width > 0 ? ' width="' . $width . '"' : '' ) . ' style="display:block;' . $size . 'height:auto;border:0;"></a>';
		}
		return '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" style="font-size:20px;font-weight:700;color:' . $colors['text'] . ';text-decoration:none;">' . self::text( $site ) . '</a>';
	}

	/**
	 * Where the created email is used: whether it is in use, a text and the admin URL of that place.
	 *
	 * @param string $key Template key.
	 * @return array{used: bool, text: string, url: string}
	 */
	public static function usage( string $key ): array {
		$definition = self::definitions()[ $key ] ?? array();
		$record     = self::record( $key );
		$email_id   = (string) ( $record['email_id'] ?? '' );
		$tab_url    = static function ( string $tab ): string {
			return add_query_arg( 'tab', $tab, admin_url( 'admin.php?page=' . Admin\Admin_Page::SLUG ) );
		};
		$unused     = array(
			'used' => false,
			'text' => '',
			'url'  => '',
		);
		if ( '' === $email_id ) {
			return $unused;
		}

		if ( 'confirm' === $key ) {
			$list = (string) ( $record['used_on_list'] ?? '' );
			if ( '' === $list ) {
				$unused['text'] = __( 'Not used yet: choose it as the double opt-in email of your list in bluefox.', 'connector-for-bluefox-email' );
				return $unused;
			}
			$lists = Form_Config::known_lists();
			return array(
				'used' => true,
				/* translators: %s: subscriber list name */
				'text' => sprintf( __( 'In use: double opt-in email of the list “%s”.', 'connector-for-bluefox-email' ), $lists[ $list ] ?? $list ),
				'url'  => '',
			);
		}

		if ( 'welcome_coupon' === $key ) {
			if ( (string) Settings::get( 'coupon', 'email_id' ) === $email_id ) {
				return array(
					'used' => true,
					'text' => __( 'In use: coupon email on the Coupon tab.', 'connector-for-bluefox-email' ),
					'url'  => $tab_url( 'coupon' ),
				);
			}
			$unused['text'] = __( 'Not used yet: select it as the coupon email on the Coupon tab.', 'connector-for-bluefox-email' );
			$unused['url']  = $tab_url( 'coupon' );
			return $unused;
		}

		$tab   = 'abandoned_cart' === ( $definition['event'] ?? '' ) ? 'abandoned_cart' : 'woo_events';
		$rules = (array) ( ( (array) get_option( Settings::option_name( 'woo_events' ), array() ) )['rules'] ?? array() );
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && (string) ( $rule['email_id'] ?? '' ) === $email_id ) {
				$on = ! empty( $rule['enabled'] );
				return array(
					'used' => $on,
					'text' => $on
						? ( 'abandoned_cart' === $tab ? __( 'In use: abandoned cart reminder (on).', 'connector-for-bluefox-email' ) : __( 'In use: Order emails rule (on).', 'connector-for-bluefox-email' ) )
						: __( 'Selected in a rule that is switched off.', 'connector-for-bluefox-email' ),
					'url'  => $tab_url( $tab ),
				);
			}
		}
		$unused['text'] = 'abandoned_cart' === $tab
			? __( 'Not used yet: select it as a reminder on the Abandoned carts tab.', 'connector-for-bluefox-email' )
			: __( 'Not used yet: select it in a rule on the Order emails tab.', 'connector-for-bluefox-email' );
		$unused['url']  = $tab_url( $tab );
		return $unused;
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
