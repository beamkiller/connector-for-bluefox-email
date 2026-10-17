# Privacy and GDPR

The plugin sends personal data to **bluefox.email** (`https://api.bluefox.email`), which processes it on your behalf. Mention bluefox.email as a processor in your privacy policy and check their [privacy policy](https://bluefox.email/privacy-policy) and [terms of use](https://bluefox.email/terms-of-use).

Settings: [Privacy tab](../settings/privacy.md).

## What is sent to bluefox.email

| When | What |
|---|---|
| Signup form, popup, checkout opt-in, form integration | email, name (if given), tags, list |
| Welcome coupon | subscription status lookup; coupon code, value and expiry in the coupon email; `couponCode`, `couponExpires`, `couponUsed` on the contact (if **Coupon data in bluefox** is on) |
| Contact sync | the user and customer fields you enabled |
| Email delivery | every `wp_mail()` email: recipients, subject, body, attachments |
| Order and abandoned cart emails | order or cart details used in the email |
| Preferences page | the visitor's own subscription token |

Nothing is sent before you enter a project ID and API key.

## What is stored on your site

| Where | Data |
|---|---|
| Table `{prefix}cfbe_subscribers` | one row per signup: email, name, source, list, tags, status, consent text, consent IP (see below), consent and confirmation time, coupon ID, order ID, subscription status |
| Order meta | `_cfbe_optin`, `_cfbe_optin_at`, `_cfbe_optin_text`, `_cfbe_optin_ip`, `_cfbe_optin_result` (checkout opt-in) |
| Table `{prefix}cfbe_carts` | abandoned cart tracking: email, cart contents, totals, status |
| User meta | `cfbe_list_status`, `cfbe_bounced`, `cfbe_complained` (from inbound webhooks) |
| Options | plugin log (last 200 entries), mailer delivery log (last 50), webhook event log (last 20). Email addresses are masked in all three. |

The consent record proves that a person signed up: what they agreed to, when and from which form. bluefox.email's double opt-in confirmation is the main proof.

## IP address modes

Every consent IP passes through one setting, **IP address with consent** on the Privacy tab:

| Mode | Stored value |
|---|---|
| Anonymised (default) | last IPv4 octet set to 0, or the last 80 bits of an IPv6 address cleared – e.g. `203.0.113.0` |
| Full IP address | the address as received |
| Do not store IP addresses | nothing |

Switching to a stricter mode (full → anonymised → off) starts a background job that rewrites the IPs already stored in signup rows and checkout orders.

## Retention

- **Erase IP addresses after** (default 24 months, 0 = keep): a daily job clears older IPs from signup rows and the `_cfbe_optin_ip` order meta.
- Unconfirmed signups are marked expired after the **Confirmation deadline** (Emails tab, default 14 days). The row is kept as a record; no coupon is ever created for it.
- Abandoned carts are marked expired after **Keep carts for** (default 30 days) and deleted after twice that time.
- The log options are ring buffers and keep only the latest entries.

## Privacy policy text

The plugin adds a suggested section to **Settings → Privacy → Policy guide**. It follows your IP mode and retention, so revisit it after changing them.

## Export and erase personal data

The plugin registers exporters and erasers with **Tools → Export Personal Data** and **Tools → Erase Personal Data**:

- **Newsletter (bluefox.email)** – signup rows and the user's `cfbe_*` meta.
- **bluefox.email abandoned carts** – stored carts of that email (with the abandoned cart feature on).

By default an erasure only deletes the data on your site. Turn on **Also delete the bluefox.email contact** on the Privacy tab to delete the contact in bluefox.email too (removed from every list, cannot be undone). Otherwise delete it in bluefox.email under **Contacts**.

WooCommerce coupons and orders are not erased by the plugin; WooCommerce's own eraser handles orders.

## Consent rules built into the plugin

- The checkout checkbox is unticked by default and separate from your terms acceptance.
- Form integrations only subscribe someone when the consent field is ticked, unless you mark a form as a dedicated newsletter signup.
- **Contact sync → All users and customers** creates contacts for people who never signed up. Only use it with a legal basis; it does not subscribe them to any list.
- The welcome coupon email is transactional and has no unsubscribe link, so keep it about the coupon only. Marketing goes out as bluefox campaigns or triggered emails with `{{unsubscribeLink}}`.

## Uninstall

Deleting the plugin removes its options, tables, user meta and pending jobs. Issued coupons and order meta (consent records) are kept. See [Install](../getting-started/install.md#deactivate-and-uninstall).
