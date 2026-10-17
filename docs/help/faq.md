# FAQ

## Do I need WooCommerce?

No. Signup forms, the popup, contact sync, email delivery, form integrations, the preferences page and webhooks work on any WordPress site. The checkout opt-in, welcome coupon, order emails and abandoned cart emails need WooCommerce 9.0 or newer.

## Who sends the confirmation (double opt-in) email?

bluefox.email, using the confirmation email set on your list. The plugin adds the contact without a status; bluefox sends the email and marks the contact `active` when the link is clicked.

## Does the plugin work without double opt-in?

Technically yes – if double opt-in is off on the list, bluefox makes the contact active at once. The welcome coupon then follows as soon as the plugin sees the active status. Double opt-in is strongly recommended: it proves consent and keeps fake addresses off your list.

## When is the welcome coupon created?

Only after the subscriber confirms, once per email address (by default once per mailbox, see below), and only for people who were new to the list and signed up through this plugin. See [Welcome coupon](../features/welcome-coupon.md).

## Can people get a second coupon with another signup?

Not with the same email address: the lookup is by the normalised address. Aliases of the same mailbox (`name+tag@example.com`, Gmail addresses with dots or `googlemail.com`) get no second coupon while **Coupon → One coupon per mailbox** is on (the default). Any other address is a different person to the plugin; the coupon is locked to the address it was issued for.

## Does it work with page caching?

Yes. Public forms contain no nonces and nothing visitor-specific. Spam is blocked with a honeypot, a fill-time token the form fetches when it is shown (never cached) and rate limits. See [Troubleshooting → Caching](troubleshooting.md#caching).

## Does it work with the block checkout?

Yes. The opt-in checkbox works on both the classic and the block checkout, and the plugin declares compatibility with High-Performance Order Storage (HPOS).

## Can I use my own email designs?

Yes. Every email is designed in bluefox.email; the plugin only passes data such as `{{couponCode}}` or `{{orderNumber}}`. Example templates are in the plugin's `templates/emails` folder. See [Email template data](../developers/template-data.md).

## Can I use more than one list?

Yes. The default list is set on the General tab. A block, shortcode, the popup, the checkout opt-in and each form integration can use another list. The preferences page manages the list the newsletter was sent to (when the link carries it), otherwise the list the person signed up to on this site, otherwise the default list from the General tab.

## Does it replace my SMTP plugin?

It can: switch on **Send WordPress emails via bluefox** to route `wp_mail()` through the bluefox transactional API. Use only one plugin that replaces `wp_mail()`.

## Is my API key safe?

The API key is never printed back in wp-admin, never sent to the browser and never exposed to AI agents. For the best protection define it in `wp-config.php` as `CFBE_BLUEFOX_API_KEY`, so it is not stored in the database.

## Can an AI agent change the settings?

Yes, on WordPress 6.9 or later, after you switch on **AI agent access (experimental)** on the General tab. Only administrators can use it, and the API key and webhook secret cannot be read or changed. See [AI agent access](../features/ai-agent-access.md).

## Which languages are included?

**English and Hungarian (magyar).** The Hungarian translation is complete and ships with the plugin (`languages/connector-for-bluefox-email-hu_HU.mo` and the JavaScript translations), so there is nothing to download.

To use it, set **Settings → General → Site Language** to **Magyar** (or a user's **Language** in their profile, for wp-admin only). Then:

| Part | In Hungarian |
|---|---|
| wp-admin: all tabs, help texts, notices | ✅ |
| Signup form, popup, promo bar – default texts and messages | ✅ |
| Checkout opt-in label, preferences page, thank-you page texts | ✅ |
| Ready-made emails on the Email templates tab (subject, texts, buttons) | ✅ prefilled in Hungarian |
| Abilities for AI agents (names and descriptions) | ✅ |

Texts you have already saved on the settings tabs stay as you saved them – change them there or reset them to the default. Every text is translatable with the text domain `connector-for-bluefox-email`, so other languages can be added with Loco Translate or a `.po` file.

## What happens when I delete the plugin?

Its options, tables, user meta and pending jobs are removed. Issued WooCommerce coupons and the consent records in order meta are kept. Contacts in bluefox.email are not touched.

## Is this an official bluefox.email plugin?

No. It is developed by [Peter Kulcsár - KREMONT s.r.o.](https://www.linkedin.com/in/peterkulcsar/) and is not affiliated with bluefox.email.
