# Email template data

Every email is designed in bluefox.email. The plugin sends the email through the bluefox API with a `data` object; its keys are available at the top level of the Handlebars template (`{{couponCode}}`, `{{#each items}}…{{/each}}`). Contact fields are available as `{{contact.<field>}}`.

All values are plain text, not HTML. Use triple braces (`{{{shopUrl}}}`) for URLs inside `href` so they are not HTML-escaped.

## Contact fields

These are fields on the bluefox contact, not `data` keys. bluefox drops values of fields that do not exist, so create them first (see [Set up your bluefox list](../getting-started/bluefox-list.md#contact-fields)).

| Field | Written by | Example |
|---|---|---|
| `{{contact.name}}` | every signup, contact sync | `Jane Doe` |
| `{{contact.couponCode}}`, `{{contact.couponExpires}}`, `{{contact.couponUsed}}` | welcome coupon (with **Coupon data in bluefox** on) | `WELCOME-K7M2QX9P` |
| `{{contact.firstName}}`, `{{contact.orderCount}}`, `{{contact.totalSpent}}` … | [contact sync](../features/contact-sync.md) | |

The contact sync fields are `name`, `firstName`, `lastName`, `wpUserId`, `wpRole`, `registeredAt`, `orderCount`, `totalSpent`, `lastOrderDate`, `lastOrderTotal`, `firstOrderDate`, `billingCountry`, `billingCity`, `purchasedCategories` and `customerType`. Their bluefox names can be changed on the [Contact sync tab](../settings/contact-sync.md), except `name`.

## Confirmation email

Sent by bluefox, not by the plugin, when double opt-in is on for the list. bluefox provides:

| Variable | Description |
|---|---|
| `{{verifyLink}}` | The confirmation link. Required. |
| `{{contact.name}}` | The name given at signup. May be empty: use `{{DEFAULT contact.name "there"}}`. |

Example template: `templates/emails/confirm-subscription.html` in the plugin, also shown on the Coupon tab.

## Welcome coupon email

A transactional email sent by the plugin after the signup is confirmed (**Coupon email ID** on the [Coupon tab](../settings/coupon.md)).

| Variable | Description | Example |
|---|---|---|
| `{{couponCode}}` | The coupon code | `WELCOME-K7M2QX9P` |
| `{{discount}}` | The discount in the store format | `10%`, `€20` |
| `{{expiry}}` | Last day of validity, in the site date format | `November 16, 2026` |
| `{{minSpend}}` | Minimum order amount; empty when there is none | `€50` |
| `{{terms}}` | One sentence with the coupon conditions | `The coupon can be used once, cannot be combined with other coupons and is valid until the date shown.` |
| `{{name}}` | Subscriber name; may be empty | `Jane` |
| `{{shopName}}` | Site title | |
| `{{shopUrl}}` | Home page URL | |

```handlebars
<p>Hi {{DEFAULT name "there"}},</p>
<p>Your {{discount}} coupon: <strong>{{couponCode}}</strong>, valid until {{expiry}}.</p>
{{#if minSpend}}<p>Minimum order: {{minSpend}}</p>{{/if}}
<a href="{{{shopUrl}}}">Start shopping</a>
```

Example template: `templates/emails/welcome-coupon.html`.

## Coupon expiry reminder

A triggered (marketing) email sent by the plugin once per unused welcome coupon, *Reminder before expiry* days before it expires (**Reminder email ID** on the [Coupon tab](../settings/coupon.md)). bluefox delivers it only to active subscribers and fills in `{{{unsubscribeLink}}}`.

It gets every variable of the welcome coupon email above, plus:

| Variable | Description | Example |
|---|---|---|
| `{{daysLeft}}` | Whole days until the coupon expires, at least 1 | `5` |

```handlebars
<p>Hi {{DEFAULT name "there"}}, only {{daysLeft}} days left to use your {{discount}} coupon <strong>{{couponCode}}</strong> (valid until {{expiry}}).</p>
<a href="{{{shopUrl}}}">Use my coupon</a>
<p><a href="{{{unsubscribeLink}}}">Unsubscribe</a></p>
```

## Order emails

Sent on the events of the [Order emails](../features/order-emails.md) rules, as a transactional or triggered bluefox email. Every order event gets:

| Variable | Description |
|---|---|
| `{{event}}` | Event key: `order_status_<status>` (e.g. `order_status_completed`), `first_order`, `order_note_customer`, `review_request` |
| `{{orderId}}` | Order ID |
| `{{orderNumber}}` | Order number |
| `{{orderStatus}}` | Status name, e.g. `Completed` |
| `{{orderDate}}` | Order date in the site date format |
| `{{orderTotal}}` | Order total with currency, e.g. `$12.50` |
| `{{currency}}` | Currency code, e.g. `EUR` |
| `{{paymentMethod}}` | Payment method title |
| `{{shippingMethod}}` | Shipping method |
| `{{customerNote}}` | Note the customer left at checkout |
| `{{billing.firstName}}`, `{{billing.lastName}}`, `{{billing.company}}`, `{{billing.city}}`, `{{billing.country}}`, `{{billing.phone}}` | Billing details |
| `{{shipping.firstName}}`, `{{shipping.lastName}}`, `{{shipping.company}}`, `{{shipping.address1}}`, `{{shipping.address2}}`, `{{shipping.city}}`, `{{shipping.postcode}}`, `{{shipping.state}}`, `{{shipping.country}}` | Shipping address |
| `{{shippingAddressLines}}` | Formatted shipping address as a list of lines |
| `{{items}}` | Line items, each with `name`, `quantity`, `total` (incl. tax), `sku`, `image` (thumbnail URL), `url` (product page) and `reviewUrl` (product page, Reviews tab) |
| `{{itemCount}}` | Number of items |
| `{{couponCodes}}` | Coupon codes used on the order (list) |
| `{{viewOrderUrl}}` | "View order" URL in My Account |
| `{{siteName}}`, `{{siteUrl}}` | Site title and home URL |

The **Note to customer added** event (`order_note_customer`) also gets `{{note}}`, the text of the note.

```handlebars
<p>Order #{{orderNumber}} – {{orderStatus}}</p>
<ul>
{{#each items}}<li>{{quantity}} × {{name}} – {{total}}</li>{{/each}}
</ul>
<p>Total: {{orderTotal}}</p>
{{#each shippingAddressLines}}{{this}}<br>{{/each}}
<a href="{{{viewOrderUrl}}}">View order</a>
```

## Abandoned cart email

Always a triggered email, so bluefox only delivers it to active subscribers of the email's list.

| Variable | Description |
|---|---|
| `{{event}}` | `abandoned_cart` |
| `{{cartUrl}}` | Link that restores the cart |
| `{{items}}` | Cart items, each with `name`, `quantity`, `total`, `sku`, `image` |
| `{{itemCount}}` | Total quantity |
| `{{cartTotal}}` | Cart total with currency |
| `{{currency}}` | Currency code |
| `{{siteName}}`, `{{siteUrl}}` | Site title and home URL |

### Recovery coupon

When the recovery coupon is on and a coupon could be created, the reminder also gets:

| Variable | Description |
|---|---|
| `{{couponCode}}` | The recovery coupon code |
| `{{couponDiscount}}` | `10%` or the amount in the store format |
| `{{couponExpiry}}` | Last day of validity |
| `{{couponMinSpend}}` | Minimum order amount; empty when there is none |

A coupon is not always created (for example during the anti-abuse cooldown), and the reminder is sent without it. Always wrap the coupon block in `{{#if couponCode}}…{{/if}}`.

```handlebars
<a href="{{{cartUrl}}}">Complete your order</a>
{{#if couponCode}}
<p>Use <strong>{{couponCode}}</strong> for {{couponDiscount}} off, valid until {{couponExpiry}}.</p>
{{/if}}
```

## WordPress emails (email delivery)

With [Email delivery](../features/email-delivery.md) on, every `wp_mail()` message is sent through one generic transactional email. The plugin can create it for you; it uses:

| Variable | Description |
|---|---|
| `{{{subject}}}` | The message subject |
| `{{{html}}}` | The message body as HTML. Plain-text messages are converted to a minimal HTML document. |
| `{{previewText}}` | The first 100 characters of the body as plain text |

Set the template's subject to `{{{subject}}}` and its whole content to `{{{html}}}`. Add keys with the [`cfbe_mailer_data`](hooks.md#email-delivery) filter.

## Preferences page

The plugin sends no email for the preferences page. Your bluefox newsletters carry the unsubscribe and pause links; once the project's **Custom subscription preferences URL** points to the `[cfbe_preferences]` page, those links open it with a `?token=…` parameter.

## Adding your own data

| Email | Filter |
|---|---|
| Order and abandoned cart emails | [`cfbe_woo_events_data`](hooks.md#order-and-abandoned-cart-emails) |
| WordPress emails | [`cfbe_mailer_data`](hooks.md#email-delivery) |
| Contact fields | [`cfbe_sync_contact_data`](hooks.md#contact-sync) |
