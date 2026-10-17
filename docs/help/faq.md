# FAQ

## Do I need WooCommerce?

No. Signup forms, the popup, contact sync, email delivery, form integrations, the preferences page and webhooks work on any WordPress site. The checkout opt-in, welcome coupon, order emails and abandoned cart emails need WooCommerce 9.0 or newer.

## Who sends the confirmation (double opt-in) email?

bluefox.email, using the confirmation email set on your list. The plugin adds the contact without a status; bluefox sends the email and marks the contact `active` when the link is clicked.

## Does the plugin work without double opt-in?

Technically yes – if double opt-in is off on the list, bluefox makes the contact active at once. The welcome coupon then follows as soon as the plugin sees the active status. Double opt-in is strongly recommended: it proves consent and keeps fake addresses off your list.

## When is the welcome coupon created?

Only after the subscriber confirms, once per email address, and only for people who were new to the list and signed up through this plugin. See [Welcome coupon](../features/welcome-coupon.md).

## Can people get a second coupon with another signup?

Not with the same email address: the lookup is by the normalised address. A different address is a different person to the plugin; the coupon is locked to the address it was issued for.

## Does it work with page caching?

Yes. Public forms contain no nonces and nothing visitor-specific. Spam is blocked with a honeypot, a time trap and rate limits. See [Troubleshooting → Caching](troubleshooting.md#caching).

## Does it work with the block checkout?

Yes. The opt-in checkbox works on both the classic and the block checkout, and the plugin declares compatibility with High-Performance Order Storage (HPOS).

## Can I use my own email designs?

Yes. Every email is designed in bluefox.email; the plugin only passes data such as `{{couponCode}}` or `{{orderNumber}}`. Example templates are in the plugin's `templates/emails` folder. See [Email template data](../developers/template-data.md).

## Can I use more than one list?

Yes. The default list is set on the General tab. A block, shortcode, the popup, the checkout opt-in and each form integration can use another list. The preferences page only manages the default list.

## Does it replace my SMTP plugin?

It can: switch on **Send WordPress emails via bluefox** to route `wp_mail()` through the bluefox transactional API. Use only one plugin that replaces `wp_mail()`.

## Is my API key safe?

The API key is never printed back in wp-admin, never sent to the browser and never exposed to AI agents. For the best protection define it in `wp-config.php` as `CFBE_BLUEFOX_API_KEY`, so it is not stored in the database.

## Can an AI agent change the settings?

Yes, on WordPress 6.9 or later, after you switch on **AI agent access (experimental)** on the General tab. Only administrators can use it, and the API key and webhook secret cannot be read or changed. See [AI agent access](../features/ai-agent-access.md).

## Which languages are included?

English and Hungarian. All texts are translatable with the text domain `connector-for-bluefox-email` (for example with Loco Translate). The texts you enter on the settings tabs are stored as you type them.

## What happens when I delete the plugin?

Its options, tables, user meta and pending jobs are removed. Issued WooCommerce coupons and the consent records in order meta are kept. Contacts in bluefox.email are not touched.

## Is this an official bluefox.email plugin?

No. It is developed by KREMONT s.r.o. and is not affiliated with bluefox.email.
