# Email templates

Tab: **General → Email templates** (`tab=email_templates`). Needs a bluefox connection.

The plugin never writes emails itself: it tells bluefox *which* email to send and fills in the data (coupon code, order items, cart link …). Normally you would build each email in bluefox and copy its ID into the plugin. The **Email templates** tab does that for you: ready-made, mobile-friendly emails for everything the plugin sends, created in bluefox with one click and selected where the plugin uses them.

![Email templates](../assets/images/email-templates.png)

## The templates

| Template | Type | Shown when | Wired up on **Create** |
|---|---|---|---|
| Double opt-in confirmation | transactional | Signup forms on | Double opt-in is switched on for the chosen list with this email, and your thank-you page becomes the list's redirect link. |
| Welcome coupon | transactional | Welcome coupon on | Selected as **Coupon email ID** on the Coupon tab. |
| Order received | transactional | Order emails on | Rule *Order status: Processing*. |
| Order completed | transactional | Order emails on | Rule *Order status: Completed*. |
| First order thank-you | transactional | Order emails on | Rule *First paid order of a customer*. |
| Note to customer | transactional | Order emails on | Rule *Note to customer added*. |
| Review request | triggered | Order emails on | Rule *Review request*, 7 days after completion. |
| Abandoned cart reminder | triggered | Abandoned cart emails on | Rule *Abandoned cart*, with the item list, restore button and – when switched on – the recovery coupon. |

Rules: when a rule for the event already exists **without** an email, the new email is filled into it and the rule is switched on. When the existing rule already has an email, nothing is changed – select the new email there if you want it.

**Triggered** emails are marketing emails: they belong to a subscriber list, only its active subscribers receive them, and they carry an unsubscribe link. Choose the list when you create them.

## Texts

Each card has six texts, prefilled in the site language: **Subject**, **Preview text**, **Heading**, **Text**, **Button label** and **Small print**. The variables listed on the card work in every text, e.g. `Your order #{{orderNumber}}` or `Hi {{DEFAULT billing.firstName "there"}}`. The layout, the data blocks (items, coupon box, note), the button link and the unsubscribe link are added by the plugin.

The design follows the **Form** tab colours (button = primary colour, accent line and coupon frame = accent colour) and shows your site logo (Customizer logo, else the site icon, else the site name).

## Buttons

| Button | What it does |
|---|---|
| **Create in bluefox** | Creates the email (HTML type) with the chosen **Sender** (empty = the project's default sender) and, for triggered emails, the **Subscriber list**. With **Use it** ticked (default) it is wired up as in the table above. |
| **Update in bluefox** | Sends the edited texts to the existing email. |
| **Send me a test** | bluefox sends the email to your own admin address, filled with sample data. |

### Editing in two places

You can keep editing the texts here, or open the email in bluefox and change it there – nothing is lost silently:

- **Update in bluefox** first checks when the email was last changed in bluefox. If someone changed it there after the plugin's last write, you are warned and can choose **Replace the version in bluefox** (or leave it).
- An email switched to bluefox's visual (drag-and-drop) editor is never overwritten: edit it in bluefox.
- An email deleted in bluefox is forgotten; the card offers **Create in bluefox** again.

What was created is stored in the option `cfbe_email_templates` (email ID, type, texts, list and bluefox's `updatedAt` after the last write).

!!! tip "Already have your own emails?"
    You don't need the templates. Select your own bluefox emails on the Coupon, Order emails and Abandoned carts tabs as before.
