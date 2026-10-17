# Thank-you and preferences pages

## Pages to create

| Page | Suggested slug | Content | Required | Link it in |
|---|---|---|---|---|
| Newsletter confirmed | `newsletter-confirmed` | `[cfbe_thankyou]` | yes | bluefox list → double opt-in **Redirect link** |
| Newsletter preferences | `newsletter-preferences` | `[cfbe_preferences]` | when the preferences page switch is on | bluefox project → **Custom subscription preferences URL** |
| Newsletter (signup) | any, e.g. `newsletter` | **bluefox.email signup form** block or `[cfbe_form]` | optional | your menu, footer or promo banners (`[cfbe_promo]`) |

Other URLs than the suggested slugs work too: enter them on the [Emails tab](../settings/emails.md). The signup form can also live in any existing page, widget area or popup – see [Signup forms](../features/signup-forms.md).

## Thank-you page

bluefox sends the visitor to the list's **Redirect link** after they confirm. Create that page in WordPress:

1. Create a page, e.g. **Newsletter confirmed** with the slug `newsletter-confirmed`.
2. Add the `[cfbe_thankyou]` shortcode (Shortcode block, or your page builder's shortcode element).
3. If you use another URL than `/newsletter-confirmed/`, enter it as **Thank-you page URL** on the [Emails tab](../settings/emails.md).
4. Set the same URL as the **Redirect link** of the bluefox list.

The shortcode shows the *Confirmation successful* title and text from the Emails tab. `?cfbe=expired` and `?cfbe=error` in the URL switch to the other two messages. The popup is never shown on the thank-you page.

## Preferences page

Needs the **Subscription preferences page** switch (on by default).

1. Create a page, e.g. **Newsletter preferences** with the slug `newsletter-preferences`.
2. Add the `[cfbe_preferences]` shortcode.
3. If you use another URL, enter it as **Preferences page URL** on the Emails tab.
4. In bluefox set the project's **Custom subscription preferences URL** to this page.

The "Unsubscribe" and "Pause" links of your newsletters now lead to this page. See [Preferences page](../features/preferences-page.md).

!!! tip "Page caching"
    Both pages work with page caching. The preferences page sends no-cache headers and defines `DONOTCACHEPAGE` whenever a token is present.
