# Install

=== "From a zip file"

    1. Download the latest zip from [GitHub](https://github.com/beamkiller/connector-for-bluefox-email) (**Releases**, or **Code → Download ZIP**).
    2. In wp-admin go to **Plugins → Add New → Upload Plugin**, choose the zip and click **Install Now**.
    3. Click **Activate**.

=== "With git"

    ```bash
    cd wp-content/plugins
    git clone https://github.com/beamkiller/connector-for-bluefox-email.git
    wp plugin activate connector-for-bluefox-email
    ```

    The repository root is the plugin itself; there is no build step.

=== "From WordPress.org"

    The plugin is planned for the WordPress.org plugin directory. Once it is listed, search for **Connector for bluefox.email** under **Plugins → Add New**.

On activation the plugin creates its table `{prefix}cfbe_subscribers` (signups and consent records). The abandoned cart feature adds `{prefix}cfbe_carts` the first time it loads.

## Language

The plugin speaks **English and Hungarian** out of the box. On a site whose language is **Magyar** (Settings → General → Site Language) everything – admin screens, forms, popup, emails created from the templates – is in Hungarian. See [Which languages are included?](../help/faq.md#which-languages-are-included)

## Requirements

- WordPress 6.7 or newer, PHP 8.1 or newer.
- WooCommerce 9.0 or newer for the checkout opt-in, welcome coupon, order emails and abandoned cart emails. Everything else works without WooCommerce.
- WordPress 6.9 or newer for [AI agent access](../features/ai-agent-access.md).

## Deactivate and uninstall

- **Deactivate** cancels the plugin's pending background jobs. Recurring jobs are scheduled again when you reactivate.
- **Delete** (uninstall) removes the plugin's options (`cfbe_*`, including transients), its tables, its user meta (`cfbe_*`), its WP-Cron events and its pending Action Scheduler jobs. WooCommerce coupons that were already issued are kept, and so is order meta (the checkout consent records).
