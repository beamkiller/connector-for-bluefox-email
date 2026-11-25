# Changelog

## 1.1.0

Released 2026-11-25.

- **New: bluefox setup** on the [General tab](../settings/general.md) – check every list and, with one click, choose its double opt-in confirmation email (only emails with `{{verifyLink}}` can be chosen) and switch on double opt-in, the thank-you page redirect and your own [preferences page](../features/preferences-page.md).
- **New: First name and Last name** on the [signup form](../settings/form.md) – in the order of the site language (family name first for Hungarian, Japanese, Chinese, Korean, Vietnamese) or as you set it. bluefox gets `firstName`, `lastName` and the full name; the checkout sends the billing first and last name too. A newly created double opt-in email greets by first name; existing confirmation emails are not changed – add `{{contact.firstName}}` to them yourself.
- **New: [form plugin integrations match fields by name](../features/form-integrations.md#match-fields-by-name)** – fields named `first_name`, `last_name`, `phone`, `company`, `address`, `city`, `zip`, `state` or `country` (and the Fluent Forms Name field `names`) go to the matching Contact sync field that is switched on, without a mapping. On by default, per form (*Match fields by name*); a field mapped under *Custom fields* wins.
- **New: [Contact sync for big lists](../features/contact-sync.md#backfill)** – short background batches (at most 50 contacts or about 20 seconds) that save every contact, continue by themselves after a stop and wait on bluefox rate limits (429, 5xx); a rejected API key pauses the run with the reason. The tab shows a live progress bar with speed, minutes left, the health of the run and the last errors, plus a **Resume** button.
- **New: billing fields** in Contact sync – `billingPhone`, `billingCompany`, `billingAddress`, `billingPostcode`, `billingState` (off by default, personal data).
- **New: [purchased product tags](../features/contact-sync.md#purchased-product-tags)** – pick products with a searchable picker (fine for big stores) and their buyers get a `bought-<product-slug>` tag in bluefox, also for earlier orders through the sync of existing customers. Off by default.
- **Improved:** the double opt-in email's *In use* status on the [Email templates](../features/email-templates.md) tab shows which lists really use it in bluefox (with a link to **bluefox setup** when none does).
- **Fix:** the first save of a settings tab could lose changes (for example a Contact sync field ticked before those settings were ever saved).
- **Fix:** list selects showed the list ID instead of its name after the one-day list cache expired.
- **Fix:** creating the double opt-in email on the Email templates tab cleared the list's confirmation title and message in bluefox.
- **[AI agent access](../features/ai-agent-access.md):** new abilities `cfbe/get-bluefox-setup`, `cfbe/apply-bluefox-setup`, `cfbe/get-sync-status`, `cfbe/start-sync`, `cfbe/resume-sync`, `cfbe/cancel-sync` and `cfbe/search-products`; `cfbe/list-bluefox-lists` also returns each list's confirmation email and redirect link; the integrations rules include `auto_fields`; abilities with only optional input run without input.

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
