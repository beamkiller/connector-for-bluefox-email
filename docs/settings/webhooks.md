# Webhooks tab

Tab id: `webhooks` · Option: `cfbe_webhooks` · Needs: **Inbound webhooks**.

See [Webhook](../getting-started/webhook.md) for setup and [Inbound webhooks](../features/inbound-webhooks.md) for what the plugin does with each event.

## Endpoint

| Label | What it shows |
|---|---|
| Webhook URL | `https://your-site.example/wp-json/cfbe/v1/bluefox-hook`, with a **Copy** button. The site must be reachable from the internet. |
| Authentication | The two ways bluefox can authenticate: the project webhook (`Authorization: Bearer <API key>`, recommended) or the legacy automation webhook step (`X-CFBE-Secret` header). |

## Project webhook in bluefox

| Label | Option key | Default | What it does |
|---|---|---|---|
| Events → Subscriptions (subscribe, resubscribe, unsubscribe, pause) | `cfbe_webhooks[event_subscription]` | on | Needed for confirmations and status tracking. |
| Events → Bounces | `cfbe_webhooks[event_bounce]` | on | |
| Events → Spam complaints | `cfbe_webhooks[event_complaint]` | on | |
| Events → Failed sends | `cfbe_webhooks[event_failed]` | off | |
| Events → Sent emails | `cfbe_webhooks[event_sent]` | off | Can mean many requests. |
| Events → Opens | `cfbe_webhooks[event_open]` | off | Can mean many requests. |
| Events → Clicks | `cfbe_webhooks[event_click]` | off | Can mean many requests. |
| Users list | `cfbe_webhooks[users_column]` | off | **Show the bluefox status (subscription, bounce, complaint) in a column of the Users list**. |

The event checkboxes choose which events are requested when you register the webhook. Buttons (shown once the connection is set up):

| Button | What it does |
|---|---|
| Register webhook in bluefox | Saves the selected events and replaces the project webhook in bluefox (a project has one) with this URL, these events and your API key as the secret. |
| Send test event | Asks bluefox to send a test event of the chosen type: `subscribe`, `resubscribe`, `unsubscribe`, `pause-subscription`, `bounce`, `complaint`, `open`, `click`, `sent` or `failed`. Reload the page to see it below. |
| Check status | Shows the webhook currently registered in bluefox. |

## Automation webhook secret (legacy)

| Label | Option key | What it does |
|---|---|---|
| Webhook secret | `cfbe_api[webhook_secret]` | Read-only, 32 letters and digits, generated automatically. Add it as the `X-CFBE-Secret` header of the Webhook step in a bluefox *Contact added* automation. **New secret** generates a new one – update the automation afterwards. |

## Last received events

The last 20 events received: time, event type and email.
