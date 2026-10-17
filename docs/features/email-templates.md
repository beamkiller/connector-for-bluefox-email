# Email templates

Tab: **General → Email templates** (`tab=email_templates`). Needs a bluefox connection.

The plugin never writes emails itself: it tells bluefox *which* email to send and fills in the data (coupon code, order items, cart link …). Normally you would build each email in bluefox and copy its ID into the plugin. The **Email templates** tab does that for you: ready-made, mobile-friendly emails for everything the plugin sends, created in bluefox with one click and selected where the plugin uses them.

Every card says **what** the email is, **why** you would use it, **when** it is sent, its **email type** and – once created – **where it is used** (*In use*, *Created, not in use*, with a link to that tab).

![Email templates](../assets/images/email-templates.png)

## The templates

The cards are grouped:

- **Newsletter emails** – double opt-in confirmation and welcome coupon.
- **Order emails WooCommerce also sends** – order received, order completed, note to customer. Creating one switches WooCommerce's email for the same event off (the **Switch off WooCommerce's “…” email** checkbox, on by default), so customers get one email. See [Replacing WooCommerce's emails](order-emails.md#replacing-woocommerces-emails).
- **Extra emails WooCommerce does not send** – first order thank-you, review request, abandoned cart reminder.

| Template | Type | Shown when | Wired up on **Create** |
|---|---|---|---|
| Double opt-in confirmation | transactional | Signup forms on | Double opt-in is switched on for the chosen list with this email, and your thank-you page becomes the list's redirect link. |
| Welcome coupon | transactional | Welcome coupon on | Selected as **Coupon email ID** on the Coupon tab. |
| Order received | transactional | Order emails on | Rule *Order status: Processing*, replacing WooCommerce's *Processing order* email. |
| Order completed | transactional | Order emails on | Rule *Order status: Completed*, replacing WooCommerce's *Completed order* email. |
| First order thank-you | transactional | Order emails on | Rule *First paid order of a customer*. |
| Note to customer | transactional | Order emails on | Rule *Note to customer added*, replacing WooCommerce's *Customer note* email. |
| Review request | triggered | Order emails on | Rule *Review request*, 7 days after completion. |
| Abandoned cart reminder | triggered | Abandoned cart emails on | Rule *Abandoned cart*, with the item list, restore button and – when switched on – the recovery coupon. |

Rules: when a rule for the event already exists **without** an email, the new email is filled into it and the rule is switched on. When the existing rule already has an email, nothing is changed – select the new email there if you want it.

**Triggered** emails are marketing emails: they belong to a subscriber list, only its active subscribers receive them, and they carry an unsubscribe link. Choose the list when you create them.

## Texts

Each card has six texts, prefilled in the site language: **Subject**, **Preview text**, **Heading**, **Text**, **Button label** and **Small print**. The variables listed on the card work in every text, e.g. `Your order #{{orderNumber}}` or `Hi {{DEFAULT billing.firstName "there"}}`. The layout, the data blocks (items, coupon box, note), the button link and the unsubscribe link are added by the plugin.

## Design

Choose the design at the top of the tab:

| Design | Uses |
|---|---|
| **Match the WooCommerce emails** (default with WooCommerce) | **WooCommerce → Settings → Emails**: header image (width and alignment), base colour (button, accent line, coupon frame), background, body background, text and footer text colours, font family and footer text (`{site_title}`, `{store_address}` … filled in). The bluefox emails look like the shop's own emails. |
| **Use the signup form colours** | The **Form** tab colours (button = primary colour, accent line and coupon frame = accent colour) and the site logo (Customizer logo, else the site icon, else the site name). |

The design is copied into the email when it is **created** or **updated**. If you change it later (or change WooCommerce's email settings), the cards of emails already in bluefox show *The design changed since this email was last sent to bluefox* – click **Update in bluefox** to apply it. Developers can change the design with the `cfbe_email_design` filter.

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

What was created is stored in the option `cfbe_email_templates` (email ID, type, texts, list, bluefox's `updatedAt` after the last write and a fingerprint of the design). The design choice is in `cfbe_email_design` (`woocommerce` or `form`).

!!! tip "Already have your own emails?"
    You don't need the templates. Select your own bluefox emails on the Coupon, Order emails and Abandoned carts tabs as before.
