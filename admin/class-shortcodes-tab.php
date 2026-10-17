<?php
/**
 * Shortcodes tab: tested placements and the full attribute reference.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only help tab. The data lives in plain arrays so a test can check that every accepted attribute
 * is documented.
 */
final class Shortcodes_Tab {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
	}

	/**
	 * Adds the tab (position 90, before Subscribers).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( array $tabs ): array {
		$tabs['shortcodes'] = array(
			'label'    => __( 'Shortcodes', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render' ),
			'position' => 90,
		);
		return $tabs;
	}

	/**
	 * Tested placements, primary use first.
	 *
	 * @return array<int, array{title: string, code: string, where: string, what: string}>
	 */
	public static function examples(): array {
		return array(
			array(
				'title' => __( 'Top bar on every page', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_promo style="bar" place="top"]',
				'where' => __( 'In your header template, directly below the header (e.g. in a Shortcode block). Give the surrounding container full width and no padding.', 'connector-for-bluefox-email' ),
				'what'  => __( 'A slim bar (optional offer badge, heading, button). The button opens the full form in a popup. On mobile only the badge, the heading and the button are shown.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Signup band above the footer', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_form variant="banner" layout="inline" heading_tag="h3" place="footer"]',
				'where' => __( 'In your footer template, directly above the footer. For an edge-to-edge band, give the surrounding container the same background color as the band.', 'connector-for-bluefox-email' ),
				'what'  => __( 'The full form in a colored band, with the fields in one row. It is at the bottom of every page, so it is the main signup point.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Top bar that scrolls to the footer form', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_promo style="bar" place="top" action="scroll"]',
				'where' => __( 'Instead of the first example, when the footer band is also in place.', 'connector-for-bluefox-email' ),
				'what'  => __( 'The button does not open a popup: it smoothly scrolls to the footer form and puts the cursor in the email field. If the page has no form, it opens a popup.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Card in the content (blog, category, landing page)', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_form place="blog"]',
				'where' => __( 'In the content of any page or template, e.g. at the end of a blog post. In the block editor you can use the "bluefox.email signup form" block instead.', 'connector-for-bluefox-email' ),
				'what'  => __( 'A light card with the heading, an optional offer badge and a benefits list (according to the Form tab settings).', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Form for a specific list, with extra tags', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_form list="LIST_ID" tags="webinar,spring-campaign" place="webinar"]',
				'where' => __( 'On a landing page whose subscribers belong to their own bluefox list (e.g. a webinar or a lead magnet). Replace LIST_ID with the ID of the list.', 'connector-for-bluefox-email' ),
				'what'  => __( 'Subscribes to that list instead of the default one and adds the given tags. The list and the tags are signed into the form, so visitors cannot change them.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Compact promo box in the content', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_promo place="product"]',
				'where' => __( 'Where space is tight: below a product description or in a sidebar. On a dark background: style="dark". As a rounded bar: style="bar" corners="round".', 'connector-for-bluefox-email' ),
				'what'  => __( 'A compact box with a button that opens the form in a popup.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Thank-you page after confirmation', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_thankyou]',
				'where' => __( 'On the /newsletter-confirmed/ page (use the same URL in the "Redirect link" field of the bluefox list and on the Emails tab).', 'connector-for-bluefox-email' ),
				'what'  => __( 'Shows the result of the confirmation (confirmed / expired link / error). The texts are editable on the Emails tab.', 'connector-for-bluefox-email' ),
			),
			array(
				'title' => __( 'Newsletter preferences (unsubscribe, pause)', 'connector-for-bluefox-email' ),
				'code'  => '[cfbe_preferences]',
				'where' => __( 'On the /newsletter-preferences/ page. Use the same URL as the "Custom subscription preferences URL" of the bluefox project and on the Emails tab.', 'connector-for-bluefox-email' ),
				'what'  => __( 'The "Unsubscribe" / "Pause" links of your newsletters lead here: it shows the subscription status and pauses (1/3/6 months), unsubscribes or resubscribes with one click. Without a link it only shows an informational text.', 'connector-for-bluefox-email' ),
			),
		);
	}

	/**
	 * Attribute reference per shortcode.
	 *
	 * @return array<string, array{purpose: string, attrs: array<string, array{values: string, default: string, desc: string}>}>
	 */
	public static function reference(): array {
		$form_tab  = __( 'Form tab', 'connector-for-bluefox-email' );
		$text      = __( 'text', 'connector-for-bluefox-email' );
		$off       = '0';
		$discount  = __( '{discount} is replaced with the coupon value (e.g. 10%).', 'connector-for-bluefox-email' );
		$place     = __( 'Placement name. The subscriber gets a "placement-<name>" tag in bluefox (e.g. placement-footer), so you can compare which placement brings more subscribers. Lowercase, no accents, max. 30 characters.', 'connector-for-bluefox-email' );
		$variants  = 'card | banner | plain';
		$empty_off = __( 'Empty ("") hides it.', 'connector-for-bluefox-email' );
		$list      = array(
			'values'  => __( 'list ID', 'connector-for-bluefox-email' ),
			'default' => __( 'General tab list', 'connector-for-bluefox-email' ),
			'desc'    => __( 'bluefox list to subscribe to (the ID from the list picker on the General tab or from bluefox). Signed into the form, so it cannot be changed in the browser.', 'connector-for-bluefox-email' ),
		);
		$tags      = array(
			'values'  => 'a,b,c',
			'default' => '–',
			'desc'    => __( 'Extra bluefox tags, comma-separated (lowercase letters, digits, hyphens; max. 20). Added next to the source-…, placement-… and interest-… tags. Signed into the form.', 'connector-for-bluefox-email' ),
		);

		return array(
			'cfbe_form'        => array(
				'purpose' => __( 'The full signup form directly on the page ([cfbe_newsletter] works as an alias). The "bluefox.email signup form" block offers the same options in the block editor.', 'connector-for-bluefox-email' ),
				'attrs'   => array(
					'list'           => $list,
					'tags'           => $tags,
					'variant'        => array(
						'values'  => $variants,
						'default' => $form_tab . ' (Style)',
						'desc'    => __( 'card: light card (with the optional offer badge) · banner: colored band · plain: no decoration, blends into the surrounding design.', 'connector-for-bluefox-email' ),
					),
					'layout'         => array(
						'values'  => 'stacked | inline',
						'default' => $form_tab,
						'desc'    => __( 'inline: fields in one row on wide screens (for bands), stacked: fields below each other (for cards, sidebars).', 'connector-for-bluefox-email' ),
					),
					'heading'        => array(
						'values'  => $text,
						'default' => $form_tab,
						'desc'    => $discount . ' ' . $empty_off,
					),
					'heading_tag'    => array(
						'values'  => 'h2 | h3 | h4',
						'default' => 'h2',
						'desc'    => __( 'Heading level. Use h3 in the footer or after h2 sections.', 'connector-for-bluefox-email' ),
					),
					'intro'          => array(
						'values'  => $text,
						'default' => $form_tab,
						'desc'    => $discount . ' ' . $empty_off,
					),
					'benefits'       => array(
						'values'  => 'a|b|c',
						'default' => $form_tab,
						'desc'    => __( 'Checkmarked benefits list, separated by "|", max. 5. Empty ("") hides it.', 'connector-for-bluefox-email' ),
					),
					'badge'          => array(
						'values'  => $off,
						'default' => $form_tab,
						'desc'    => __( 'badge="0": hides the offer badge (card and banner styles; turn it on on the Form tab).', 'connector-for-bluefox-email' ),
					),
					'button'         => array(
						'values'  => $text,
						'default' => $form_tab,
						'desc'    => __( 'Label of the submit button.', 'connector-for-bluefox-email' ),
					),
					'offer'          => array(
						'values'  => $text,
						'default' => $form_tab,
						'desc'    => __( 'Small print below the button (e.g. coupon terms).', 'connector-for-bluefox-email' ) . ' ' . $empty_off,
					),
					'show_name'      => array(
						'values'  => $off,
						'default' => $form_tab,
						'desc'    => __( 'show_name="0": hides the Name field. It can only turn it off; turn it on on the Form tab.', 'connector-for-bluefox-email' ),
					),
					'show_interests' => array(
						'values'  => $off,
						'default' => $form_tab,
						'desc'    => __( 'show_interests="0": hides the interests field. It can only turn it off.', 'connector-for-bluefox-email' ),
					),
					'place'          => array(
						'values'  => __( 'name', 'connector-for-bluefox-email' ),
						'default' => '–',
						'desc'    => $place,
					),
					'class'          => array(
						'values'  => __( 'CSS class(es)', 'connector-for-bluefox-email' ),
						'default' => '–',
						'desc'    => __( 'Custom CSS class on the outer element, for custom styling.', 'connector-for-bluefox-email' ),
					),
				),
			),
			'cfbe_promo'       => array(
				'purpose' => __( 'A compact attention bar or box with a button. The button opens the full form in a popup, or scrolls to the form on the page. Primary placement: top bar below the header.', 'connector-for-bluefox-email' ),
				'attrs'   => array(
					'style'        => array(
						'values'  => 'light | dark | bar',
						'default' => 'light',
						'desc'    => __( 'light: light box · dark: colored box · bar: slim single-row bar below the header.', 'connector-for-bluefox-email' ),
					),
					'corners'      => array(
						'values'  => 'square | round',
						'default' => 'square',
						'desc'    => __( 'Only with style="bar": round = box with rounded corners in the content (e.g. on a product page), square = square, full-width bar.', 'connector-for-bluefox-email' ),
					),
					'action'       => array(
						'values'  => 'dialog | scroll',
						'default' => $form_tab . ' (Promo bar → Button action)',
						'desc'    => __( 'dialog: opens the form in a popup · scroll: scrolls to the form on the page; if there is none, opens a popup.', 'connector-for-bluefox-email' ),
					),
					'target'       => array(
						'values'  => '#element-id',
						'default' => __( 'the last form on the page', 'connector-for-bluefox-email' ),
						'desc'    => __( 'Only with action="scroll": the element to scroll to (e.g. a section ID: #newsletter).', 'connector-for-bluefox-email' ),
					),
					'heading'      => array(
						'values'  => $text,
						'default' => $form_tab . ' (Promo bar)',
						'desc'    => $discount,
					),
					'text'         => array(
						'values'  => $text,
						'default' => $form_tab . ' (Promo bar)',
						'desc'    => __( 'One-line supporting text (hidden on mobile with the bar style).', 'connector-for-bluefox-email' ) . ' ' . $empty_off,
					),
					'button'       => array(
						'values'  => $text,
						'default' => $form_tab . ' (Promo bar)',
						'desc'    => __( 'Button label.', 'connector-for-bluefox-email' ),
					),
					'form_variant' => array(
						'values'  => $variants,
						'default' => 'card',
						'desc'    => __( 'Style of the form shown in the popup.', 'connector-for-bluefox-email' ),
					),
					'place'        => array(
						'values'  => __( 'name', 'connector-for-bluefox-email' ),
						'default' => '–',
						'desc'    => $place,
					),
					'list'         => $list,
					'tags'         => $tags,
				),
			),
			'cfbe_thankyou'    => array(
				'purpose' => __( 'The message shown on the thank-you page after the subscription is confirmed. The state comes from the ?cfbe= URL parameter: none → confirmed, expired → expired link, error → error.', 'connector-for-bluefox-email' ),
				'attrs'   => array(
					'tag'        => array(
						'values'  => 'h1 | h2 | h3 | h4',
						'default' => 'h1',
						'desc'    => __( 'Title level. h1 if this is the main title of the page.', 'connector-for-bluefox-email' ),
					),
					'show_title' => array(
						'values'  => $off,
						'default' => '1',
						'desc'    => __( 'show_title="0": hides the title when the page already has its own heading.', 'connector-for-bluefox-email' ),
					),
				),
			),
			'cfbe_preferences' => array(
				'purpose' => __( 'Newsletter preferences page for the bluefox unsubscribe / pause link (?token=…). The title and the intro are editable on the Emails tab.', 'connector-for-bluefox-email' ),
				'attrs'   => array(
					'tag'        => array(
						'values'  => 'h1 | h2 | h3 | h4',
						'default' => 'h1',
						'desc'    => __( 'Title level. h1 if this is the main title of the page.', 'connector-for-bluefox-email' ),
					),
					'show_title' => array(
						'values'  => $off,
						'default' => '1',
						'desc'    => __( 'show_title="0": hides the title when the page already has its own heading.', 'connector-for-bluefox-email' ),
					),
				),
			),
		);
	}

	/**
	 * Renders the tab.
	 */
	public function render(): void {
		$cfbe_examples  = self::examples();
		$cfbe_reference = self::reference();
		include __DIR__ . '/views/tab-shortcodes.php';
	}
}
