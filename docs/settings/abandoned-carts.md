# Abandoned carts tab

Tab id: `abandoned_cart` · Option: `cfbe_woo_events` · Needs: **Abandoned cart emails** and WooCommerce.

See [Abandoned cart emails](../features/abandoned-carts.md) for how carts are tracked and restored, and [Email template data](../developers/template-data.md) for the template variables.

## Reminder email

The same rule table as on the [Order emails tab](order-emails.md), limited to the *Abandoned cart* event. **Add reminder** adds a row.

| Column | Option key | Default for a new row | What it does |
|---|---|---|---|
| Event | `cfbe_woo_events[rules][n][event]` | `abandoned_cart` | Always *Abandoned cart*. |
| Email type | `cfbe_woo_events[rules][n][type]` | `triggered` | Always saved as triggered: reminders are marketing emails, so bluefox only delivers them to active subscribers of the email's list. |
| bluefox email | `cfbe_woo_events[rules][n][email_id]` | empty | The triggered email to send. |
| Delay | `cfbe_woo_events[rules][n][delay]`, `[delay_unit]` | `0` `minutes` | Counted from the moment the cart is found idle. Add a second reminder with a longer delay for a follow-up. |
| On | `cfbe_woo_events[rules][n][enabled]` | on | A rule without an email is saved as off. |

## Cart settings

| Label | Option key | Default | What it does |
|---|---|---|---|
| Cart tracking | `cfbe_woo_events[cart_tracking]` | on | **Store carts (only while a reminder is enabled)**. |
| Opted-in shoppers only | `cfbe_woo_events[cart_optin_only]` | on | Only tracks carts of people who confirmed a signup made with this plugin or ticked the checkout opt-in. When off, every cart with a known email is stored. |
| Idle time | `cfbe_woo_events[cart_idle_minutes]` | `60` | Minutes without changes before a cart counts as abandoned. 15–10080. Checked every 15 minutes. |
| Keep carts for | `cfbe_woo_events[cart_expiry_days]` | `30` | Days, 1–365. Then the cart expires; it is deleted after twice as long. |
| Recovery coupon | `cfbe_woo_events[cart_coupon]` | off | **Add a single-use coupon to the reminder**. Created when the reminder is sent, one per cart, only for the shopper's email. The restore link applies it automatically. |
| Coupon discount → amount | `cfbe_woo_events[cart_coupon_amount]` | `10` | Percent values are capped at 100. |
| Coupon discount → type | `cfbe_woo_events[cart_coupon_type]` | `percent` | `percent` or `fixed_cart`. |
| Coupon valid for | `cfbe_woo_events[cart_coupon_days]` | `7` | Days after the reminder, 1–365. |
| Coupon minimum order | `cfbe_woo_events[cart_coupon_min]` | `0` | 0 = no minimum. |
| Coupon cooldown | `cfbe_woo_events[cart_coupon_cooldown]` | `30` | Days, 0–365. Within this period an email address gets at most one recovery coupon: an unused one is shown again, after a redeemed one the reminder goes out without a coupon. 0 = a coupon for every reminder. |
| Coupon code prefix | `cfbe_woo_events[cart_coupon_prefix]` | `CART-` | Uppercased; letters, digits and hyphens; max. 12 characters. Empty = `CART-`. |
| Cannot be combined with other coupons | `cfbe_woo_events[cart_coupon_individual]` | on | WooCommerce *Individual use only*. |
| Not valid on sale items | `cfbe_woo_events[cart_coupon_exclude_sale]` | on | WooCommerce *Exclude sale items*. |

!!! tip "Links in templates"
    Put links in triple braces, e.g. `href="{{{cartUrl}}}"`. With two braces Handlebars HTML-escapes characters such as `=` and `&`.

## Recent carts

The 20 most recent stored carts: email, items, total, status and last update (UTC).
