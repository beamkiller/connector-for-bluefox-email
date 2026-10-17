# Connector for bluefox.email – Newsletter and WooCommerce Emails

Connector for bluefox.email links your WordPress site and WooCommerce store to your [bluefox.email](https://bluefox.email) account. Every feature is a module you switch on or off, so the plugin only loads what you use.

![Signup form, card style](assets/images/form-card.png){ width="420" }

## What it does

<div class="grid cards" markdown>

- **Signup forms** – a block, the `[cfbe_form]` shortcode, a compact promo bar and a popup with scroll, delay, exit-intent and page-view triggers. Double opt-in is handled by your bluefox list. [Read more](features/signup-forms.md)
- **WooCommerce opt-in and welcome coupon** – an unticked newsletter checkbox on the classic and block checkout, and a single-use coupon locked to the subscriber's email, created only after they confirm. [Read more](features/welcome-coupon.md)
- **Order and abandoned cart emails** – send your own bluefox emails on order events, and remind shoppers of carts they left, with an optional recovery coupon. [Read more](features/order-emails.md)
- **Contact sync** – keep bluefox contacts enriched with user and customer data (order count, total spent, last order date…) for segmentation. [Read more](features/contact-sync.md)
- **Email delivery** – route `wp_mail()` through the bluefox transactional API. [Read more](features/email-delivery.md)
- **Form plugin integrations** – subscribe people from Contact Form 7, WPForms, Fluent Forms, Gravity Forms and Elementor Pro forms. [Read more](features/form-integrations.md)
- **Preferences page and webhooks** – your own translatable unsubscribe / pause page, and bluefox events stored in WordPress. [Read more](features/preferences-page.md)
- **AI agent access (experimental)** – let an AI agent read and change the settings through the WordPress Abilities API. [Read more](features/ai-agent-access.md)

</div>

## Requirements

| | Version |
|---|---|
| WordPress | 6.7 or newer (6.9+ for AI agent access) |
| PHP | 8.1 or newer |
| WooCommerce | optional, 9.0 or newer – HPOS and the block checkout are supported |
| bluefox.email | an account with a project and an API key |

Translations: English and Hungarian are bundled.

## Where to start

1. [Install the plugin](getting-started/install.md).
2. [Connect it to bluefox.email](getting-started/connect.md).
3. [Set up double opt-in on your bluefox list](getting-started/bluefox-list.md).
4. [Switch on the features you need](getting-started/features.md).
5. [Create the thank-you and preferences pages](getting-started/pages.md) and [register the webhook](getting-started/webhook.md).

!!! note "Not an official bluefox.email product"
    The plugin is developed by [Peter Kulcsár - KREMONT s.r.o.](https://www.linkedin.com/in/peterkulcsar/) and released under GPL-2.0-or-later. It is not developed by or affiliated with bluefox.email.
