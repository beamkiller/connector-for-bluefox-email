=== Connector for bluefox.email – Newsletter and WooCommerce Marketing ===
Contributors: beamkiller
Tags: newsletter, email marketing, woocommerce, double opt-in, transactional email
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Signup forms with double opt-in, WooCommerce opt-in and coupons, contact sync and email delivery for bluefox.email.

== Description ==

Connector for bluefox.email links your WordPress site and WooCommerce store to your [bluefox.email](https://bluefox.email) account. Every feature is a module you can switch on or off, so the plugin only loads what you use.

**Ready-made emails**

* One click creates mobile-friendly emails in bluefox.email for everything the plugin sends – double opt-in confirmation, welcome coupon, coupon expiry reminder, first order thank-you, review request, abandoned cart reminder – and wires them up. They match your WooCommerce email design (logo, colours, font, footer) or your signup form colours. Edit the texts in WordPress, update them later, or keep designing them in bluefox.

**Signup forms**

* Gutenberg block, `[cfbe_form]` shortcode and a configurable popup (scroll, delay, exit intent, page views, page targeting, device, frequency).
* Double opt-in handled by bluefox.email: the confirmation email is sent by your bluefox list.
* Choose the list per form, add tags, optional name and "interests" field (each interest becomes a tag).
* Accessible markup (labels, focus trap in the popup, ESC to close, reduced-motion support) and page-cache friendly (no nonces in public forms; honeypot, time trap and rate limiting instead).

**WooCommerce** (optional)

* Newsletter opt-in checkbox on the checkout – classic and block checkout – and optionally on My Account.
* Welcome coupon: a single-use coupon locked to the subscriber's email address, created only after they confirm their subscription, one per email address.
* Coupon expiry reminder: subscribers who have not used their welcome coupon get one reminder email a few days before it expires.
* Excluded user roles: wholesale customers, resellers or any other role can subscribe without getting the welcome coupon, and the cart rejects it for them.
* Order emails: send your own bluefox transactional or triggered emails on order status changes, first purchase, customer notes and review requests. WooCommerce keeps sending its own order emails – these come on top.
* Abandoned cart emails: remind shoppers of carts they left, with a restore link and an optional single-use recovery coupon.
* Compatible with High-Performance Order Storage (HPOS).

**Contact sync**

* Keep bluefox contacts enriched with user and customer data (name, order count, total spent, last order date, purchased categories, …) for segmentation.
* Bulk backfill and WP-CLI command.

**Send WordPress emails through bluefox.email**

* Route `wp_mail()` – WordPress and WooCommerce system emails – through the bluefox transactional API. Off by default, with a test-send button and fallback to the default mailer.

**Form plugin integrations**

* Subscribe people from Contact Form 7, WPForms, Fluent Forms, Gravity Forms and Elementor Pro forms, with consent checks and field mapping.

**Subscription preferences page**

* Your own, translatable unsubscribe / pause page on your site instead of the bluefox hosted page.

**Inbound webhooks**

* Receive bluefox events (subscription changes, bounces, complaints, opens, clicks …), store the status on the WordPress user and react with developer hooks.

**AI agent access (experimental, off by default)**

* On WordPress 6.9 and later the settings can be registered as abilities: an AI agent connected through the MCP Adapter plugin can read and change them in plain language ("show the popup after 15 seconds", "switch on the checkout opt-in"). Only administrators can use them, every value goes through the same validation as the settings screens, and the API key and webhook secret are never shown or changed.

**Translations**

* English and a complete Hungarian (magyar) translation are included: set the site language to Magyar and the admin screens, forms, popup and ready-made emails are in Hungarian.

This plugin is not developed by or affiliated with bluefox.email.

== External services ==

This plugin connects to the **bluefox.email API** (`https://api.bluefox.email`), an email marketing and delivery service. It is required for every feature of the plugin and is only contacted after you enter your bluefox.email project ID and API key.

What is sent and when:

* When someone submits a signup form, ticks the checkout opt-in or submits a connected third-party form: their email address, name (if given), tags and the list they subscribe to.
* When the Welcome coupon module confirms a signup: the subscription status is read and the coupon email is sent with the coupon code, value and expiry date.
* When a welcome coupon is about to expire unused (and a reminder email is set): the reminder email is sent to that subscriber with the same coupon details.
* When the Contact sync module is on: the user and customer fields you enable (for example name, order count, total spent, last order date).
* When the Email delivery module is on: every email sent with `wp_mail()` (recipients, subject, body, attachments) is sent to bluefox.email for delivery.
* When order or abandoned cart emails are on: order or cart details used in the email (order number, items, totals, customer name and address).
* On the preferences page: the visitor's own subscription token, to read and change their subscription.
* In the admin: your lists, emails, sender identities and webhook settings when you load or change them.

bluefox.email terms of service: https://bluefox.email/terms-of-use
bluefox.email privacy policy: https://bluefox.email/privacy-policy

== Installation ==

1. Install and activate the plugin.
2. Go to **bluefox.email → General**, enter your project ID and API key (or define `CFBE_BLUEFOX_API_KEY` in `wp-config.php`), click **Test connection** and **Load lists**, then choose your default list.
3. In bluefox.email, turn on double opt-in for the list and set its confirmation email.
4. Switch on the features you need under **Features** and configure each one in its tab.

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. Signup forms, contact sync, email delivery, form integrations, the preferences page and webhooks work on any WordPress site. The checkout opt-in, welcome coupon, order emails and abandoned cart emails need WooCommerce 9.0 or newer.

= Who sends the confirmation (double opt-in) email? =

bluefox.email, using the confirmation email set on your list. The plugin never subscribes anyone without their consent.

= When is the welcome coupon created? =

Only after the subscriber confirms the double opt-in email, once per email address, and only for people who signed up through this plugin.

= Does it work with page caching? =

Yes. Public forms contain no nonces and do not depend on the visitor; spam is blocked with a honeypot, a time trap and rate limiting.

= Can an AI agent change the settings? =

Yes, on WordPress 6.9 or later, once you switch on the experimental **AI agent access** feature on the General tab (it is off by default). The plugin then registers the abilities `cfbe/get-settings`, `cfbe/update-settings`, `cfbe/set-modules`, `cfbe/list-bluefox-lists`, `cfbe/list-bluefox-emails`, `cfbe/list-email-templates`, `cfbe/create-email-template`, `cfbe/update-email-template`, `cfbe/send-test-email` and `cfbe/set-email-design`. With the MCP Adapter plugin they are available to MCP clients such as Claude, signed in as an administrator (for example with an application password). The API key and webhook secret can only be set in wp-admin.

= Is it available in Hungarian? =

Yes. The complete Hungarian translation ships with the plugin. Set Settings → General → Site Language to Magyar; texts you have already saved on the settings tabs stay as you saved them.

= Can I use my own email templates? =

Yes. All emails are designed in bluefox.email; the plugin passes data (for example `{{couponCode}}` or `{{orderNumber}}`) that you can use in your templates. Example templates are included in the plugin's `templates/emails` folder.

== Screenshots ==

1. General tab: bluefox connection, default list and feature switches.
2. The signup form (card style) with benefits and the consent checkbox.
3. Form tab: fields, texts and colours with a live preview.
4. Popup settings: triggers, page targeting, devices and frequency.
5. Order emails: extra bluefox emails on WooCommerce order events (WooCommerce keeps its own emails).

== Changelog ==

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
