# Changelog

## 1.1.0 (beta)

Beta for testing: install the zip from the [GitHub pre-release](https://github.com/beamkiller/connector-for-bluefox-email/releases). WordPress.org still offers 1.0.0.

- **Form integrations: match fields by name** *(beta 7)*: form fields named like a Contact sync field that is switched on – `first_name`, `last_name`, `phone`, `company`, `address`, `city`, `zip`, `state`, `country`, Fluent Forms `names.first_name` … – go to that bluefox field without a mapping; a Fluent Forms name group as the Name field also fills First name / Last name. Setting **Match fields by name** per form (on), `cfbe_auto_fields` in Elementor; the log lists the matched fields.
- **Contact sync backfill for big lists** *(beta 6)*: short time-limited batches that save every contact, a watchdog that continues a stopped batch, retries with growing pauses on bluefox rate limits and outages, a pause with the reason on a rejected API key, a live progress bar with speed, minutes left, health and the last errors, a **Resume** button, and the open tab keeps a stalled sync going. Agents: `cfbe/get-sync-status`, `cfbe/start-sync`, `cfbe/resume-sync`, `cfbe/cancel-sync`.
- **First name + Last name on the signup form** *(beta 5)*: Form tab → Name field → *First name and Last name*, with the order automatic from the site language (family name first for Hungarian) or set by hand. bluefox gets `firstName`, `lastName` (the names of the Contact sync table) and the full `name`; the checkout sends the billing first and last name too. **bluefox setup** checks and creates the name fields. The double opt-in template now greets by first name; existing confirmation emails (created before 1.1.0 or outside the plugin) are not changed – add `{{contact.firstName}}` yourself, see [Email templates](../features/email-templates.md). The subscriber table gets first / last name columns and a `created_at` index.
- **Billing details in Contact sync** *(beta 4)*: new fields `billingPhone`, `billingCompany`, `billingAddress`, `billingPostcode`, `billingState` – off by default (personal data); **Create missing fields in bluefox** creates them.
- **Purchased product tags** in [Contact sync](../features/contact-sync.md#purchased-product-tags) *(beta 3)*: choose products with a searchable picker, and everyone with a paid order of one gets a `bought-<product-slug>` tag in bluefox – for segments and campaigns aimed at the product's buyers. Off by default; earlier orders are tagged by the sync of existing customers. Agents: `cfbe/search-products`.
- **bluefox setup** on the General tab: choose the double opt-in confirmation email per list from your bluefox transactional emails (only emails with `{{verifyLink}}` are selectable), and switch on double opt-in, the thank-you page redirect and your own preferences page in bluefox with one click. See [General tab](../settings/general.md#bluefox-setup).
- Fix: the very first save of a settings tab could lose changes – e.g. a Contact sync field ticked before those settings were ever saved was unticked again after the reload *(beta 8)*.
- Fix: list selects showed the list ID instead of its name once the one-day list cache expired.
- Fix: creating the double opt-in email on the [Email templates](../features/email-templates.md) tab cleared the list's confirmation title and message in bluefox.
- Email templates: the double opt-in email's *In use* status now reflects which lists really use it in bluefox (with a link to **bluefox setup** when none does), and the email is marked *(from Email templates)* in the bluefox setup.
- [AI agent access](../features/ai-agent-access.md) *(beta 2)*: new abilities `cfbe/get-bluefox-setup` and `cfbe/apply-bluefox-setup`; `cfbe/list-bluefox-lists` also returns each list's confirmation email and redirect link; `cfbe/get-settings` and the setup abilities run without input (they failed with *input is not of type object* before).

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
