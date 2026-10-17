# Signup forms

Switch: **Signup forms** (on by default). Settings: [Form tab](../settings/form.md).

The form has an optional **Name** field, the required **Email address**, an optional [interests](interests-tags.md) field, a required **consent checkbox** and a submit button. Texts, colours and corner radius are set on the Form tab; the tab shows a live preview.

| Card | Banner |
|---|---|
| ![Card style](../assets/images/form-card.png) | ![Banner style, inline layout](../assets/images/form-banner.png) |

Three styles are available: **Card** (light background, subtle border), **Banner** (primary-colour background, light button) and **Plain** (no decoration). The **Inline** layout puts the fields in one row on wide screens.

## Add a form to a page

=== "Block"

    In the block editor add the **bluefox.email signup form** block. The sidebar has three panels:

    - **Subscription** – the bluefox list (empty = default list), extra tags and a placement name.
    - **Content** – heading, intro text and button label (empty = the Form tab text).
    - **Layout and fields** – style, and toggles to hide the Name and Interests fields.

    The block supports wide and full alignment and renders on the server, so the editor preview matches the site.

=== "Shortcode"

    Put `[cfbe_form]` in a Shortcode block, a classic editor, a widget or your page builder's shortcode element. `[cfbe_newsletter]` is an alias.

    ```text
    [cfbe_form variant="banner" layout="inline" heading_tag="h3" place="footer"]
    [cfbe_form list="LIST_ID" tags="webinar,spring-campaign" place="webinar"]
    ```

    All attributes are listed in [Shortcodes and block](../developers/shortcodes-block.md).

=== "Promo bar"

    `[cfbe_promo]` shows a compact box or bar with a button. The button opens the full form in a dialog, or scrolls to a form on the page.

    ```text
    [cfbe_promo style="bar" place="top"]
    [cfbe_promo style="bar" place="top" action="scroll"]
    [cfbe_promo style="dark" place="product"]
    ```

    ![Promo box and rounded bar](../assets/images/promo.png)

    With `action="scroll"` the button scrolls to the last `[cfbe_form]` on the page (or the `target="#id"` element) and focuses the email field; if there is no form, it opens the dialog.

=== "Popup"

    See [Popup](popup.md).

The **Shortcodes** tab in wp-admin has ready-to-copy examples (top bar, footer band, card in the content, list-specific form, thank-you and preferences pages) and the full attribute reference.

## List and tags per form

A form can subscribe to another list than the default and add extra tags. The list and tags are **signed** into the form (HMAC with the site's salt), so visitors cannot change them in the browser. Unsigned values are ignored.

## `{discount}` placeholder

In the heading, intro, benefits, button, offer line, badge and promo texts, `{discount}` is replaced with the welcome coupon value from the Coupon tab, e.g. `10%` or `€20`.

## After submit

The visitor sees one of the messages from the Form tab:

| Situation | Message |
|---|---|
| New signup, confirmation email sent, or queued for retry | Successful signup |
| Already an active subscriber | Already subscribed |
| bluefox rejected the address or is not configured | Error |

## Spam protection without nonces

Public forms contain no nonces and nothing visitor-specific, so they work on cached pages. Instead:

- a hidden **honeypot** field;
- a **time trap** – submissions faster than 2.5 seconds are dropped (the bot still sees a success message);
- **rate limits** – 5 submissions per IP per 10 minutes and 3 per email address per hour;
- a 4 KB request size limit.

## Accessibility

Labels on every field, error messages linked with `aria-describedby`, a polite live region for the result, focus moved to the result, and in the popup a focus trap, `role="dialog"`, `aria-modal` and Esc to close. Animations respect `prefers-reduced-motion`.

## Loading of assets

By default the CSS and JavaScript (`cfbe-form.css`, `cfbe-form.js`) load only on pages with a form. Turn on **Form → Loading → Load CSS/JS on every page** to avoid a flash of unstyled content when a form is added by a page builder late in the page. If a caching plugin delays JavaScript (for example WP Rocket's *Delay JavaScript execution*), exclude `cfbe-form.js` and `cfbe-popup.js`.
