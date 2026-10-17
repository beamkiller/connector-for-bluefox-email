# Hooks

All hooks are prefixed `cfbe_`. Hooks that belong to a module only fire while that module is switched on.

## Core

### Filters

| Filter | Parameters | Description |
|---|---|---|
| `cfbe_capability` | `string $capability` (default `manage_options`) | Capability needed for the settings page and admin actions, e.g. `manage_woocommerce` for shop managers. |
| `cfbe_modules` | `array $modules` – `slug => [label, description, woocommerce, default, group]` | Module definitions shown on the General tab. |
| `cfbe_settings_tabs` | `array $tabs` – `slug => [label, render, position]` | Settings tabs. |
| `cfbe_settings_areas` | `string[] $areas` | Settings areas; each is stored in option `cfbe_{area}`. |
| `cfbe_settings_defaults_{area}` | `array $defaults` | Defaults of an area. They also define the allowed keys. |
| `cfbe_sanitize_settings_{area}` | `array $clean`, `array $input`, `array $previous` | Sanitized values of an area before saving. |
| `cfbe_client_ip` | `string $ip` (`REMOTE_ADDR`) | Visitor IP for rate limiting and consent records. Use it behind a proxy or Cloudflare. |

### Actions

| Action | Parameters | Description |
|---|---|---|
| `cfbe_activate` | – | On plugin activation, after the tables are created. |
| `cfbe_init` | `CFBE\Plugin $plugin` | The core services exist (`plugins_loaded`). |
| `cfbe_register_modules` | `CFBE\Plugin $plugin` | The built-in modules are registered; add-ons hook in here. |
| `cfbe_load_settings_sanitizers` | – | Before settings are saved outside the settings screens (AI agent abilities), so the screens' sanitizers can hook in. |
| `cfbe_purge_page_cache` | – | After a saved change to the Form, Popup or Coupon settings asked the page cache to purge (WP Rocket is purged automatically). Hook other cache plugins here. |

## Signups and confirmation

| Hook | Type | Parameters | Description |
|---|---|---|---|
| `cfbe_subscribed` | action | `string $email`, `CFBE\Subscribe_Result $result`, `string $source` | After a signup was handed to bluefox, or failed. `$result->code` is `pending`, `resent`, `already_active`, `queued`, `invalid`, `rejected` or `rate_limited`; `$result->is_success()` tells whether the visitor saw a success message. |
| `cfbe_signup_confirmed` | action | `string $email`, `string $list_id`, `array $row` | Once, when a signup made through the plugin is confirmed (double opt-in done, contact `active`). `$row` is the local row from `{prefix}cfbe_subscribers`. |
| `cfbe_checkout_subscriber_name` | filter | `string $name` ("First Last"), `string $first`, `string $last`, `WC_Order $order` | Subscriber name built from the billing name at checkout. |

`$source` is one of `shortcode`, `block`, `promo`, `popup`, `checkout`, `account`, `cf7`, `wpforms`, `fluentforms`, `gravityforms`, `elementor`.

## Popup

| Filter | Parameters | Description |
|---|---|---|
| `cfbe_popup_should_render` | `bool $render`, `array $settings` (`popup` area), `array $context` | Whether the popup is printed on this request. `$context` has `blocked`, `preview`, `is_cart`, `is_checkout`, `is_account`, `post_id`, `path`, `thankyou_path`. |

## Form plugin integrations

| Filter | Parameters | Description |
|---|---|---|
| `cfbe_integration_signup` | `array $input`, `string $source`, `string $form_id` | A signup from a third-party form. `$input` has `email`, `name`, `consent`, `consent_text`, `ip`, `list_id`, `tags`, `fields`, `skip_form_rules`. Return `false` to skip it. `$source` is `cf7`, `wpforms`, `fluentforms`, `gravityforms` or `elementor`. |

## Order and abandoned cart emails

| Filter | Parameters | Description |
|---|---|---|
| `cfbe_woo_events_data` | `array $data`, `string $event`, `WC_Order` or `array` `$order_or_cart` | Template data of an order or abandoned cart email. For `abandoned_cart` the third parameter is the cart row (array). See [Email template data](template-data.md#order-emails). |

## Contact sync

| Filter | Parameters | Description |
|---|---|---|
| `cfbe_sync_contact_data` | `array $fields` (bluefox field name => value), `string $email`, `WP_User` or `null` `$user` | Custom field values sent to the bluefox contact. `email` and `tags` are removed afterwards. |
| `cfbe_sync_contact_pause_ms` | `int $ms` (default 250) | Pause between contacts during the backfill and `wp cfbe sync`. |

## Email delivery

| Hook | Type | Parameters | Description |
|---|---|---|---|
| `cfbe_mailer_data` | filter | `array $data` (`subject`, `html`, `previewText`), `CFBE\Mailer\Message $message` | Template data sent with every `wp_mail()` message. |
| `cfbe_mailer_failed` | action | `WP_Error $error`, `array $mail_data` | The bluefox path could not deliver a message, before falling back to the default mailer or failing. |

The mailer also fires the core hooks `wp_mail_succeeded` and `wp_mail_failed`, and applies `wp_mail_content_type` and `wp_mail_charset`, like core `wp_mail()`.

## Inbound webhooks

| Action | Parameters | Description |
|---|---|---|
| `cfbe_webhook_received` | `string $type`, `array $payload` | Every authenticated project webhook event, during the HTTP request and before the core handling. Only queue work here; bluefox expects a fast response. Only fires while the **Inbound webhooks** feature is on. |
| `cfbe_webhook_event` | `string $type`, `array $payload` | Every project webhook event, in a background job after the plugin has stored it. Needs the Inbound webhooks module. |
| `cfbe_webhook_{$type}` | `array $payload` | One event type, e.g. `cfbe_webhook_bounce`, `cfbe_webhook_unsubscribe`, `cfbe_webhook_pause-subscription`. Needs the Inbound webhooks module. |

Event types: `subscribe`, `resubscribe`, `unsubscribe`, `pause-subscription`, `bounce`, `complaint`, `click`, `open`, `sent`, `failed`. The payload is untrusted input.

## Examples

Let shop managers manage the plugin:

```php
add_filter( 'cfbe_capability', fn() => 'manage_woocommerce' );
```

Use the visitor IP from Cloudflare:

```php
add_filter( 'cfbe_client_ip', function ( $ip ) {
    $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
    return filter_var( $cf, FILTER_VALIDATE_IP ) ? $cf : $ip;
} );
```

Hide the popup inside a page builder's editor:

```php
add_filter( 'cfbe_popup_should_render', function ( $render ) {
    return isset( $_GET['ct_builder'] ) ? false : $render;
} );
```

Add a field to every order email:

```php
add_filter( 'cfbe_woo_events_data', function ( array $data, string $event, $order ) {
    if ( $order instanceof WC_Order ) {
        $data['trackingNumber'] = (string) $order->get_meta( '_tracking_number' );
    }
    return $data;
}, 10, 3 );
```

React to a confirmed signup:

```php
add_action( 'cfbe_signup_confirmed', function ( string $email, string $list_id, array $row ) {
    // E.g. tag the WordPress user.
    $user = get_user_by( 'email', $email );
    if ( $user ) {
        update_user_meta( $user->ID, 'newsletter_confirmed', time() );
    }
}, 10, 3 );
```

Act on bounces:

```php
add_action( 'cfbe_webhook_bounce', function ( array $payload ) {
    // $payload is untrusted: validate before use.
} );
```
