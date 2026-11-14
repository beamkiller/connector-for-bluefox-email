# Changelog

## 1.1.0 (beta)

Beta for testing: install the zip from the [GitHub pre-release](https://github.com/beamkiller/connector-for-bluefox-email/releases). WordPress.org still offers 1.0.0.

- **bluefox setup** on the General tab: choose the double opt-in confirmation email per list from your bluefox transactional emails (only emails with `{{verifyLink}}` are selectable), and switch on double opt-in, the thank-you page redirect and your own preferences page in bluefox with one click. See [General tab](../settings/general.md#bluefox-setup).
- Fix: list selects showed the list ID instead of its name once the one-day list cache expired.

## 1.0.0

First public release.

- Signup forms: block, `[cfbe_form]` and `[cfbe_promo]` shortcodes, popup with scroll, delay, exit-intent and page-view triggers, page targeting and frequency rules. Double opt-in handled by bluefox.email.
- Interests field and automatic `source-`, `placement-` and `interest-` tags.
- WooCommerce checkout opt-in (classic and block checkout) and My Account opt-in.
- Welcome coupon after confirmation, one per email address and by default one per mailbox (aliases and Gmail dots count as one), with an expiry reminder through bluefox contact fields.
- Spam and abuse protection without nonces: honeypot, server-signed fill-time token, per-IP (IPv6 per /64), per-mailbox and site-wide rate limits, at most one re-sent confirmation email per mailbox per day.
- Neutral form responses that never reveal who is subscribed (optional *Already subscribed* message). Existing contacts are only changed after the person confirms, unless the address owner makes the request.
- Signed form configuration (list, tags, placement) limited to the project's loaded lists and the lists chosen in the settings.
- Order emails and abandoned cart emails with an optional recovery coupon.
- Contact sync with backfill and WP-CLI.
- `wp_mail()` delivery through the bluefox transactional API.
- Contact Form 7, WPForms, Fluent Forms, Gravity Forms and Elementor Pro integrations.
- Own subscription preferences page and inbound bluefox webhooks.
- Privacy tools: visitor IP source for Cloudflare and proxies, IP address modes, retention, personal data exporters and erasers (including issued coupons and the checkout consent IP), masked email addresses in logs.
- AI agent access through the WordPress Abilities API (experimental, off by default).
- Redirect link check: a warning with a one-click fix when a used bluefox list sends confirmed subscribers to another site's thank-you page.
- Thank-you page opened with bluefox's `?token=` is never cached, indexed or leaked in the `Referer` header.
- Coupon expiry reminder runs under a lock, so overlapping runs never send a reminder twice.
- *Settings* link on the Plugins screen.
- English and Hungarian translations.
