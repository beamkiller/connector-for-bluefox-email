# Switch on features

The **Features** section of the **General** tab has one switch per module. Switch on only what you use: a module that is off loads no code and shows no tab.

| Switch | Default | Needs | Tabs it adds |
|---|---|---|---|
| Signup forms | on | – | Form, Popup, Emails, Shortcodes |
| Checkout opt-in | off | WooCommerce | Checkout |
| Welcome coupon | off | WooCommerce | Coupon |
| Contact sync | off | – | Contact sync |
| Send WordPress emails via bluefox | off | – | Email delivery |
| Order emails | off | WooCommerce | Order emails |
| Abandoned cart emails | off | WooCommerce | Abandoned carts |
| Form plugin integrations (one switch per plugin) | off | the form plugin | Form integrations |
| Subscription preferences page | on | – | – (texts on the Emails tab) |
| Inbound webhooks | on | – | Webhooks |
| AI agent access (experimental) | off | WordPress 6.9+ | – |

The **Subscribers**, **Privacy** and **Logs** tabs are always there.

A switch whose requirement is missing is greyed out (*Requires WooCommerce*, *Requires WordPress 6.9 or later*, *(not active)*). If it was on before, it stays on and comes back when the requirement returns.

!!! note "Inner switches"
    Two features have a second switch on their own tab, so you can set them up before they go live: the popup (**Popup → Enabled**) and email delivery (**Email delivery → Send emails through bluefox**).

!!! note "Emails tab and the preferences page"
    The thank-you and preferences texts live on the **Emails** tab, which belongs to **Signup forms**. Keep Signup forms on if you want to edit them.
