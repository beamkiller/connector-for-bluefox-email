# Webhook

The plugin learns about confirmations in two ways:

- **Webhook (fast)** – bluefox calls `https://your-site.example/wp-json/cfbe/v1/bluefox-hook` within seconds of the click.
- **Polling (safety net)** – every 15 minutes a background job asks bluefox for the status of pending signups (25 per run). It works without a webhook, only slower.

The webhook endpoint is always registered. To register it in bluefox:

1. Make sure the **Inbound webhooks** switch is on (default) and open the **Webhooks** tab.
2. Choose the events. **Subscriptions** is needed for confirmations and status tracking; **Bounces** and **Spam complaints** are on by default; **Opens**, **Clicks**, **Sent emails** and **Failed sends** can mean many requests.
3. Click **Save Changes**, then **Register webhook in bluefox**. This replaces the project webhook in bluefox (a project has one) with this URL, the selected events and your API key as its secret.
4. Use **Send test event** and **Check status** to verify. Received events appear under **Last received events**.

The site must be reachable from the internet. A local or password-protected site cannot receive webhooks – polling still works there.

## Authentication

bluefox sends one of the project's API keys as `Authorization: Bearer …`. The plugin accepts it when it equals the configured API key (constant-time comparison). If your server strips the `Authorization` header, see [Troubleshooting](../help/troubleshooting.md#webhook-returns-401).

### Legacy: automation webhook step

Instead of the project webhook you can add a **Webhook** step to a bluefox "Contact added" automation: `POST` to the webhook URL with **Pass Contact Data** on and a custom header `X-CFBE-Secret` set to the **Webhook secret** shown on the Webhooks tab. This only confirms signups; it carries no other events. **New secret** generates a new secret – update the automation header afterwards.

!!! info "Webhook data is never trusted"
    A webhook only queues a check. Before marking a signup confirmed or creating a coupon, the plugin reads the subscriber's status from the bluefox API again.
