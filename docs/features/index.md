# Features

Each feature is a module with its own switch on the **General** tab (see [Switch on features](../getting-started/features.md)).

| Feature | What it does |
|---|---|
| [Signup forms](signup-forms.md) | Block, `[cfbe_form]` shortcode and `[cfbe_promo]` bar with double opt-in |
| [Popup](popup.md) | The signup form in a popup with triggers, targeting and frequency rules |
| [Interests and tags](interests-tags.md) | How signups are tagged in bluefox for segments |
| [Checkout opt-in](checkout-opt-in.md) | Newsletter checkbox on the classic and block checkout and on My Account |
| [Welcome coupon](welcome-coupon.md) | Single-use coupon for new subscribers, after they confirm |
| [Order emails](order-emails.md) | Your bluefox emails on order events |
| [Abandoned cart emails](abandoned-carts.md) | Reminders with a restore link and an optional recovery coupon |
| [Contact sync](contact-sync.md) | User and customer data on bluefox contacts, with backfill |
| [Email delivery](email-delivery.md) | `wp_mail()` through the bluefox transactional API |
| [Form plugin integrations](form-integrations.md) | Contact Form 7, WPForms, Fluent Forms, Gravity Forms, Elementor Pro |
| [Preferences page](preferences-page.md) | Your own unsubscribe / pause page |
| [Inbound webhooks](inbound-webhooks.md) | bluefox events stored in WordPress, developer hooks |
| [AI agent access](ai-agent-access.md) | Settings as WordPress abilities for AI agents (experimental) |

## How a signup works

Every signup – form, popup, checkout or a third-party form – goes through the same steps:

1. **Consent is recorded first.** The plugin stores a row in `{prefix}cfbe_subscribers` with the email, name, source, list, tags, the consent text, the (optionally anonymised) IP address and the time.
2. **The contact is added to bluefox.** The plugin first looks the email up on the list:
    - not on the list → added without a status, so bluefox sends its double opt-in email;
    - `unverified` → bluefox re-sends its confirmation email;
    - `unsubscribed` → added again, bluefox runs double opt-in again;
    - `active` → shown *Already subscribed*, tags and name are merged into the contact, no coupon;
    - `paused` → made active again without an email, no coupon.
3. **bluefox errors never lose a signup.** Network errors, rate limits and 5xx answers are retried in the background after 5 minutes, 30 minutes and 2 hours. A suppressed or bounced address (bluefox 405) is marked failed and the visitor sees the generic error message.
4. **Confirmation is detected** by the [webhook](../getting-started/webhook.md) or by the 15-minute poll. The plugin re-reads the status from bluefox, marks the signup confirmed and fires `cfbe_signup_confirmed`. Unconfirmed signups expire after the **Confirmation deadline** (Emails tab, default 14 days).
