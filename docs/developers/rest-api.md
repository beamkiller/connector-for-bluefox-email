# REST API

The plugin registers three routes under the `cfbe/v1` namespace. All responses send `Cache-Control: no-store`.

| Route | Method | Used by | Auth |
|---|---|---|---|
| `/wp-json/cfbe/v1/subscribe` | POST | the signup forms and the popup | none (public); honeypot, time trap, rate limits |
| `/wp-json/cfbe/v1/bluefox-hook` | POST | bluefox webhooks | Bearer API key or `X-CFBE-Secret` |
| `/wp-json/cfbe/v1/cart-email` | POST | the classic checkout (abandoned carts) | `wp_rest` nonce |

## Subscribe

`POST /wp-json/cfbe/v1/subscribe` with a JSON body. It is public and nonce-less on purpose, so forms work on cached pages.

| Field | Description |
|---|---|
| `source` | Required: `shortcode`, `block`, `promo` or `popup`. Other values return 400. |
| `email` | Email address. |
| `name` | Name (optional, depending on the Form tab). |
| `interests` | Array of interest tags (only configured tags are accepted). |
| `consent` | `true` – the consent checkbox. |
| `place` | Placement name (adds `placement-<name>`). |
| `cfbe_cfg` | The signed list / tags configuration printed in the form. Unsigned or tampered values are ignored. |
| `cfbe_hp` | Honeypot; must be empty. |
| `cfbe_elapsed` | Milliseconds since the form was shown. |

The consent text is never taken from the request: the plugin stores the text configured on the Form tab.

Protection:

- body larger than 4 KB → 413;
- honeypot filled or `cfbe_elapsed` under 2500 ms → a fake success, nothing is stored;
- more than 5 requests per IP in 10 minutes, or 3 per email address in an hour → 429.

Response:

```json
{ "success": true, "code": "pending", "message": "Thank you! We have sent you a confirmation email…" }
```

| `code` | `success` | Meaning |
|---|---|---|
| `pending` | true | Added; bluefox sends the confirmation email. |
| `resent` | true | Was unconfirmed; bluefox re-sent the confirmation email. |
| `already_active` | true | Already an active subscriber. |
| `queued` | true | bluefox was unreachable; retried in the background. |
| `invalid` | false | Validation failed; `fields` maps field names to messages (HTTP 400). |
| `rejected` | false | bluefox refused the address, or the plugin is not configured. |
| `rate_limited` | false | Too many attempts (HTTP 429). |

`message` is meant for the visitor: the success, already-subscribed and error texts come from the Form tab. Errors never disclose the reason bluefox gave.

## bluefox webhook

`POST /wp-json/cfbe/v1/bluefox-hook` – the endpoint is always registered, whether or not the Inbound webhooks module is on. See [Webhook](../getting-started/webhook.md) for the setup.

Authentication, compared in constant time:

- **Project webhook** – `Authorization: Bearer <API key>`, equal to the configured API key. If the server hides the header, the plugin also reads `REDIRECT_HTTP_AUTHORIZATION` and `apache_request_headers()`.
- **Automation webhook step** – header `X-CFBE-Secret: <webhook secret>`.

Payloads:

- Project webhook: `{ "type": "subscribe", "subscription": { "email", "status", "subscriberList": { "_id" } }, … }`. While Inbound webhooks is on, every authenticated event fires [`cfbe_webhook_received`](hooks.md#inbound-webhooks). Subscription events update the local list status. Only `subscribe` / `resubscribe` with status `active`, on the list of the email's own signup, queue a confirmation check.
- Automation step: `{ "automationId", "contactId", "contact": { "email", … } }`. Queues a confirmation check if the email has a local signup.

The webhook only queues work. The confirmation itself re-reads the subscriber's status from the bluefox API.

| Status | Body | Meaning |
|---|---|---|
| 200 | `{"ok":true,"action":"queued"}` | A confirmation check was queued. |
| 200 | `{"ok":true,"action":"ignored"}` | Not relevant (other list, other status, unknown contact). |
| 200 | `{"ok":true,"action":"received"}` | A delivery or engagement event (bounce, open, …). |
| 400 | `{"ok":false}` | Automation payload without a valid email. |
| 401 | `{"ok":false}` | Authentication failed. |
| 413 | `{"ok":false}` | Body larger than 64 KB. |
| 429 | `{"ok":false}` | More than 60 requests per minute from one IP. |

## Cart email

`POST /wp-json/cfbe/v1/cart-email` – registered by the Abandoned cart emails module. On the classic checkout, the browser sends the email address the shopper typed (`email`, and `optin` for the newsletter checkbox) so the cart can be stored for a guest. It needs the `wp_rest` nonce in `X-WP-Nonce` and is limited to 20 requests per IP in 10 minutes. The block checkout does not use it.

Response: `{ "stored": true|false }`.

## Not REST

The admin screens use `admin-ajax.php` actions (`cfbe_*`) protected by nonces and the [`cfbe_capability`](hooks.md#core) capability. The [AI agent abilities](../features/ai-agent-access.md) are exposed by the WordPress Abilities API, not by routes of this plugin.
