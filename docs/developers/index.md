# Developers

The plugin is plain PHP 8.1 with vanilla JavaScript; there is no build step. Classes live in the `CFBE\` namespace and are autoloaded from `includes/`, `admin/`, `public/` and `modules/`.

| Page | Contents |
|---|---|
| [Hooks](hooks.md) | Every filter and action, with parameters |
| [Shortcodes and block](shortcodes-block.md) | `[cfbe_form]`, `[cfbe_promo]`, `[cfbe_thankyou]`, `[cfbe_preferences]` and the `cfbe/signup-form` block |
| [Email template data](template-data.md) | Handlebars variables passed to your bluefox emails |
| [REST API](rest-api.md) | The `cfbe/v1` endpoints |
| [WP-CLI](wp-cli.md) | `wp cfbe` commands |

## Bootstrap

`CFBE\Plugin::boot()` runs on `plugins_loaded`. It creates the core services, then the modules that are switched on, then fires two actions:

- `cfbe_init` – after the core services exist. `CFBE\Plugin::instance()` then gives you `settings()`, `repository()`, `logger()`, `jobs()`, `subscription()`, `rewards()` and `client()` (the bluefox API client, `null` while the connection is not configured).
- `cfbe_register_modules` – after the built-in modules are registered. Add-ons hook in here.

## The module system

Every optional feature is a module with a switch on the **General** tab. The switches are stored together in the option `cfbe_modules` (`slug => bool`).

`CFBE\Modules::FLAGS` defines the built-in modules as `slug => [ needs WooCommerce, default, group ]`:

| Slug | Needs | Default | Group |
|---|---|---|---|
| `forms` | – | on | – |
| `checkout` | WooCommerce | off | – |
| `coupon` | WooCommerce | off | – |
| `sync` | – | off | – |
| `mailer` | – | off | – |
| `order_emails` | WooCommerce | off | `woo_events` |
| `abandoned_cart` | WooCommerce | off | `woo_events` |
| `integration_cf7` | Contact Form 7 | off | `integrations` |
| `integration_wpforms` | WPForms | off | `integrations` |
| `integration_fluentforms` | Fluent Forms | off | `integrations` |
| `integration_gravityforms` | Gravity Forms | off | `integrations` |
| `integration_elementor` | Elementor Pro | off | `integrations` |
| `preferences` | – | on | – |
| `webhooks` | – | on | – |
| `abilities` | WordPress 6.9 (`wp_register_ability`) | off | – |

Third-party requirements are listed in `Modules::REQUIRES` (any one of a constant, class or function is enough).

Switches in one **group** load the same code module. `Plugin::FEATURE_MODULES` maps a slug or group to the entry class in `modules/<slug>/`:

| Slug / group | Entry class |
|---|---|
| `sync` | `CFBE\Sync\Module` |
| `mailer` | `CFBE\Mailer\Module` |
| `woo_events` | `CFBE\Woo_Events\Module` |
| `integrations` | `CFBE\Integrations\Module` |
| `webhooks` | `CFBE\Webhooks\Module` |

An entry class takes the `Plugin` instance in its constructor and hooks itself (frontend, jobs, admin tab) in `register()`. It is only instantiated when its switch, or any switch of its group, is on. The other modules (`forms`, `checkout`, `coupon`, `preferences`, `abilities`) are wired directly in `Plugin`.

### Checking a module

```php
use CFBE\Modules;

if ( Modules::enabled( 'coupon' ) ) {
    // Switched on and WooCommerce is active.
}

Modules::group_enabled( 'woo_events' ); // Any switch of the group is on.
Modules::available( 'integration_cf7' ); // Its dependencies are active, whatever the switch.
Modules::states();                       // All switches, stored values merged over the defaults.
```

`Modules::enabled()` is true only when the switch is on **and** the module is available. A switch whose dependency is missing keeps its stored value and comes back when the dependency returns.

Filter `cfbe_modules` to change labels, descriptions or defaults of the module definitions.

### Settings areas

Settings are stored as one array option per area, `cfbe_{area}`. The built-in areas are `api`, `form`, `popup`, `checkout`, `coupon` and `emails`. Modules add their own:

| Area | Option | Added by |
|---|---|---|
| `sync` | `cfbe_sync` | Contact sync |
| `mailer` | `cfbe_mailer` | Email delivery |
| `woo_events` | `cfbe_woo_events` | Order and abandoned cart emails |
| `integrations` | `cfbe_integrations` | Form plugin integrations |
| `webhooks` | `cfbe_webhooks` | Inbound webhooks |

A module registers an area with three filters:

```php
add_filter( 'cfbe_settings_areas', function ( array $areas ) {
    $areas[] = 'my_area';
    return $areas;
} );

add_filter( 'cfbe_settings_defaults_my_area', function ( array $defaults ) {
    return $defaults + array( 'enabled' => false, 'label' => 'Hello' );
} );

add_filter( 'cfbe_sanitize_settings_my_area', function ( array $clean, array $input, array $previous ) {
    $clean['label'] = sanitize_text_field( $input['label'] ?? '' );
    return $clean;
}, 10, 3 );
```

The defaults define the allowed keys: anything not in the defaults is dropped after sanitizing. Without a sanitizer, values are cleaned by the type of their default (bool, int, string).

Read settings with `CFBE\Settings::get( 'popup' )` (all values merged over the defaults) or `Settings::get( 'popup', 'scroll_percent' )` (one value). See the [Settings reference](../settings/index.md) for every key.

### Adding a settings tab

Tabs are registered with the `cfbe_settings_tabs` filter:

```php
add_filter( 'cfbe_settings_tabs', function ( array $tabs ) {
    $tabs['my_tab'] = array(
        'label'    => __( 'My tab', 'my-plugin' ),
        'render'   => 'my_render_callback',
        'position' => 80, // General is 10, Subscribers 100, Logs 110.
    );
    return $tabs;
} );
```

## Logging and background jobs

- The plugin logs to the **Logs** tab (and to the WooCommerce logger with source `cfbe` when WooCommerce is active). Email addresses are masked in log context.
- Background work runs through Action Scheduler when it is available (WooCommerce bundles it) and WP-Cron otherwise. See [Troubleshooting](../help/troubleshooting.md).
