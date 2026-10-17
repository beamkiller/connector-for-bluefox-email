# Troubleshooting

Start with **bluefox.email → Logs**. Most problems show up there as a bluefox API error, a retry or a rejected webhook.

## Test connection fails

- Check the **Project ID** (bluefox: **Project Settings → Integrations**) and the API key.
- If you use `CFBE_BLUEFOX_API_KEY` in `wp-config.php`, the constant wins over the stored key. Make sure it is not empty and has no extra spaces.
- A 403 from bluefox means the key is wrong or belongs to another project. If the project has an API domain whitelist, add your site's domain.

## No confirmation email arrives

- Double opt-in must be **on** for the list in bluefox, with a confirmation email selected. The plugin never sends it itself. See [Set up your bluefox list](../getting-started/bluefox-list.md).
- New bluefox projects are in a **sandbox** with a small daily limit and a sandbox sender address. Check the spam folder and the bluefox sending limits.
- An address that was suppressed or bounced in bluefox is rejected (bluefox 405). The visitor sees the error message and the log says so.

## Confirmed, but no coupon

1. Is the **Welcome coupon** feature on and a **Coupon email ID** set on the Coupon tab?
2. Did the signup come through this plugin, and was the person new to the list? Existing and reactivated subscribers get no coupon.
3. Is **Reward signups on list** set to another list than the one the form used?
4. Has the confirmation been detected? Without a webhook it takes up to 15 minutes. Run `wp cfbe poll` to check pending signups now, and `wp cfbe status <email>` to see the local and bluefox status.

## Webhook returns 401 {#webhook-returns-401}

bluefox authenticates the project webhook with `Authorization: Bearer <API key>`. The plugin compares it with the configured API key. A 401 means the header did not arrive or the key differs.

- **The header is stripped.** Apache with PHP-FPM / FastCGI often drops `Authorization`. The plugin also reads `REDIRECT_HTTP_AUTHORIZATION` and `apache_request_headers()`, which covers most hosts. If it still fails, pass the header on in `.htaccess` above the WordPress block:

    ```apache
    RewriteEngine On
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    ```

    On nginx with PHP-FPM, add `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`.

- **The key changed.** If you created a new API key, click **Register webhook in bluefox** again on the Webhooks tab.
- **Security plugins / firewalls** may block `POST /wp-json/cfbe/v1/bluefox-hook` or REST requests without a logged-in user. Allow that route.
- **The legacy automation step** sends `X-CFBE-Secret` instead. After **New secret** you must update the header in the bluefox automation.

Use **Send test event** and **Check status** on the Webhooks tab to test. Polling still confirms signups while the webhook is broken, only slower.

## Caching

Forms are cache-safe: they contain no nonces and nothing visitor-specific. Still:

- **JavaScript delay / combine.** Exclude `cfbe-form.js` and `cfbe-popup.js` from "Delay JavaScript execution" (WP Rocket) or similar options, or the popup and the form submit won't work until the visitor interacts with the page.
- **Changed texts or the coupon value still show the old version.** Saving the Form, Popup or Coupon tab purges WP Rocket's cache automatically. Other cache plugins: purge manually, or hook `cfbe_purge_page_cache` (see [Hooks](../developers/hooks.md)).
- **The REST API must not be cached.** Exclude `/wp-json/` from page caches and CDNs, or at least `/wp-json/cfbe/v1/`.
- **Unstyled form for a moment.** Turn on **Form → Load CSS/JS on every page** when a page builder adds the form late.
- The preferences page sends no-cache headers and defines `DONOTCACHEPAGE` when a token is present. If your CDN ignores those, exclude the page.

## Popup does not appear

- **Popup → Enabled** must be ticked (it is off after install).
- It shows at most once per session and not for some days after it was closed. Test in a private window, or use **Preview on the site**.
- Check page targeting, the device setting and **Never on the cart, checkout and my account pages**.
- It waits while a cookie bar matching **Cookie bar selector** is visible, while another dialog is open and while an inline form is on screen.
- A successful signup hides it for good in that browser (`localStorage` key `cfbe_popup`).

## Background jobs do not run (cron / Action Scheduler)

Subscriptions after errors, checkout opt-ins, coupons, contact sync, order and cart emails and the 15-minute poll all run in the background:

- With **WooCommerce** (or any plugin that bundles Action Scheduler) jobs run through Action Scheduler, group `cfbe`. Look under **Tools → Scheduled Actions** for pending or failed `cfbe_*` actions.
- Without it, the plugin uses **WP-Cron**. WP-Cron only runs when someone visits the site. On low-traffic sites, or with `DISABLE_WP_CRON`, set up a real cron job:

    ```bash
    */5 * * * * wp cron event run --due-now --path=/path/to/wordpress
    ```

- Password-protected or maintenance-mode sites can block the loopback request WP-Cron and Action Scheduler use. **Tools → Site Health** reports this.
- Deactivating the plugin cancels its pending jobs; reactivating schedules the recurring ones again.

## WordPress emails not sent through bluefox

- Both switches are needed: **Send WordPress emails via bluefox** on the General tab and **Send emails through bluefox** on the Email delivery tab.
- Another SMTP plugin that replaces `wp_mail()` may take over. Use only one mail plugin.
- bluefox always uses the sender identity of the selected template. *From* and *Reply-To* set by WordPress or other plugins are ignored.
- Check **Recent deliveries** on the Email delivery tab. With **If bluefox fails → Let WordPress send the email the normal way**, failed emails fall back to the default mailer.

## Form plugin submissions are not subscribed

- The integration switch for that form plugin must be on, and a row for the form must exist on the Form integrations tab.
- Check the field names: the email field, the name field and the consent field must match the form plugin's field names exactly.
- The visitor is only subscribed when the consent field is ticked, unless **This form itself is the newsletter signup** is set for the form.
