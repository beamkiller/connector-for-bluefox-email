# Set up your bluefox list

The plugin relies on bluefox.email for double opt-in (DOI). It adds the visitor to the list without a status, bluefox sends its confirmation email, and the contact becomes `active` when they click the link. Only then does the plugin count the signup as confirmed and, if the [welcome coupon](../features/welcome-coupon.md) is on, create the coupon.

Configure each list you use once in bluefox:

1. **Turn on double opt-in** for the list.
2. **Confirmation email** – create a transactional email (type HTML) in your language and choose it as the list's confirmation email. It must contain the `{{verifyLink}}` merge tag. A ready-made template is on the plugin's **Coupon** tab (*Double opt-in confirmation template*) and in the plugin folder at `templates/emails/confirm-subscription.html`.
3. **Redirect link** – set it to your thank-you page, by default `https://your-site.example/newsletter-confirmed/`. Use the same URL as **Thank-you page URL** on the plugin's [Emails tab](../settings/emails.md). Creating the confirmation email on the [Email templates](../features/email-templates.md) tab sets it for you. bluefox appends `?token=…` (a signed, read-only token for that subscriber, valid for 7 days); the plugin keeps that page out of caches and search engines and does not pass the URL on to other sites.

    The redirect link belongs to the list, so every site that uses the list shares it. If it points somewhere else – an old test site, a staging copy – the plugin shows a warning on its settings screens with a **Use this site's thank-you page** button. See [Emails tab](../settings/emails.md#redirect-link-check).
4. **Custom subscription preferences URL** (project settings) – if you use the [preferences page](../features/preferences-page.md), set it to that page, by default `https://your-site.example/newsletter-preferences/`.

## Contact fields

bluefox contacts have no built-in name field. Create these custom contact fields in bluefox (the plugin can create them for you):

| Field | Type | Used by |
|---|---|---|
| `name` | string | every signup (`{{contact.name}}` in templates) |
| `couponCode` | string | welcome coupon |
| `couponExpires` | date | welcome coupon reminder |
| `couponUsed` | boolean | welcome coupon reminder |

- **Coupon → Create bluefox fields** creates all four.
- **Contact sync → Create missing fields in bluefox** creates the sync fields you enabled.

bluefox silently drops values of fields that do not exist.

## Leaving the sandbox

New bluefox projects send from a sandbox address with a small daily limit. Verify your sending domain in bluefox and apply for production access before going live.
