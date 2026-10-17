# Checkout tab

Tab id: `checkout` · Option: `cfbe_checkout` · Needs: **Checkout opt-in** and WooCommerce.

See [Checkout opt-in](../features/checkout-opt-in.md) for how the checkbox works on the classic and block checkout.

| Label | Option key | Default | What it does |
|---|---|---|---|
| Text | `cfbe_checkout[label]` | *Subscribe me to the newsletter.* | Checkbox label. `{discount}` is replaced with the coupon value. On the classic checkout a *Privacy policy* link (Form tab URL) is added after it. Empty = the default text. |
| Small print below | `cfbe_checkout[description]` | *We will send you a confirmation email. You can unsubscribe at any time.* | Text under the checkbox. Classic checkout and My Account only. |
| Subscriber list | `cfbe_checkout[list_id]` | empty (*Default list (General tab)*) | List for checkout and My Account signups. |
| Extra tags | `cfbe_checkout[tags]` | empty | Comma-separated tags. `source-checkout` or `source-account` is always added. |
| My Account | `cfbe_checkout[account_optin]` | off | **Also offer the checkbox on My Account → Account details**. |
| Preview | – | – | Shows the label as customers will see it. |

The **Checkout opt-in** switch on the General tab turns the feature on and off.
