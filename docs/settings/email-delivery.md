# Email delivery tab

Tab id: `mailer` · Option: `cfbe_mailer` · Needs: **Send WordPress emails via bluefox**.

See [Email delivery](../features/email-delivery.md) for how `wp_mail()` is routed through bluefox.

A notice at the top shows the state: not connected, live (*WordPress emails are sent through bluefox.*), enabled without a template, or not live yet.

## 1. Template

**Create the WordPress mail template in bluefox** asks for a sender identity and creates a generic transactional email with the subject `{{{subject}}}` and the content `{{{html}}}`. You can also select an email you made yourself with the same placeholders.

## 2. Settings

| Label | Option key | Default | What it does |
|---|---|---|---|
| Transactional email | `cfbe_mailer[transactional_id]` | empty | The generic template every `wp_mail()` email is sent with. **Refresh list** reloads the emails from bluefox. An ID not found in the loaded list keeps the previous value. |
| If bluefox fails | `cfbe_mailer[failure_mode]` | `fallback` | `fallback`: *Let WordPress send the email the normal way (recommended)*. `fail`: *Do not send it (wp_mail() returns false)*. |
| Attachment size limit | `cfbe_mailer[max_attachment_mb]` | `7` | Total attachment size per email in MB, 1–40. Larger emails are handled as a failure. |
| Send emails through bluefox → Enabled | `cfbe_mailer[enabled]` | off | Goes live. Without a selected transactional email WordPress keeps sending the normal way. |

## 3. Test

**Send a test email to** (prefilled with the site admin email) → **Send test email**. Needs a saved transactional email.

## Recent deliveries

The last 50 emails sent through bluefox: time, recipient, subject (first 60 characters) and result (*Sent*, *Test sent*, *Failed*, *Failed, sent by WordPress*).
