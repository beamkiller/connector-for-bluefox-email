# Contact sync tab

Tab id: `sync` · Option: `cfbe_sync` · Needs: **Contact sync**.

See [Contact sync](../features/contact-sync.md) for how and when contacts are synced.

## Settings

| Label | Option key | Default | What it does |
|---|---|---|---|
| Which contacts | `cfbe_sync[scope]` | `subscribers` | `subscribers` (*Only people who are already bluefox contacts (recommended)*) only updates existing contacts. `all` (*All users and customers (create missing contacts)*) also creates contacts. A created contact is not added to any list, so it receives no campaigns. |
| Automatic sync | `cfbe_sync[auto_sync]` | on | Syncs when a user registers or updates the profile, an order is paid, completed or refunded, and when a signup is confirmed. |
| Tags → Add the wp-user tag to registered users | `cfbe_sync[tag_wp_user]` | off | Adds the `wp-user` tag. |
| Tags → Add the customer tag to people with at least one paid order | `cfbe_sync[tag_customer]` | off | Adds the `customer` tag. Shown only with WooCommerce. |

Tags are only added, never removed.

!!! warning "GDPR"
    With *All users and customers* you send personal data of people who did not sign up for your newsletter to bluefox. Only use it with a documented legal basis, and name bluefox.email as a processor in your privacy policy.

## Fields

One row per field: **Sync** (on/off), **Data**, **bluefox field name** and **Type**. Option keys: `cfbe_sync[fields][<key>][enabled]` and `cfbe_sync[fields][<key>][name]`. The default bluefox field name equals the key.

Field names may contain `A–Z`, `a–z`, `0–9` and `_` (max. 64 characters). An empty, duplicate or reserved name (`email`, `tags`, `_id`, `_lists`, `createdAt`, `updatedAt`, `accountId`, `projectId`) falls back to the key. The field must exist in bluefox – bluefox silently drops unknown fields.

| Data | Key | Type | Default | WooCommerce |
|---|---|---|---|---|
| Full name (first + last name) | `name` | string | on | – |
| First name | `firstName` | string | on | – |
| Last name | `lastName` | string | on | – |
| WordPress user ID | `wpUserId` | number | on | – |
| WordPress role | `wpRole` | string | on | – |
| Registration date | `registeredAt` | date | on | – |
| Number of orders | `orderCount` | number | on | yes |
| Total spent | `totalSpent` | number | on | yes |
| Last order date | `lastOrderDate` | date | on | yes |
| Last order total | `lastOrderTotal` | number | on | yes |
| First order date | `firstOrderDate` | date | on | yes |
| Billing country | `billingCountry` | string | on | yes |
| Billing city | `billingCity` | string | on | yes |
| Purchased product categories (comma-separated slugs) | `purchasedCategories` | string | off | yes |
| Customer type (customer / guest) | `customerType` | string | on | yes |

`name` has a fixed bluefox name (it is `{{contact.name}}` in templates), and an existing name in bluefox is never cleared. WooCommerce fields are hidden while WooCommerce is off; their stored settings are kept.

## Actions

| Button | What it does |
|---|---|
| Create missing fields in bluefox | Creates the enabled fields that do not exist in your bluefox project yet. Save the settings first. |
| Sync all existing users/customers now | Starts the backfill: in the background, batches of 50, one batch per minute. Progress is shown on the tab (updated, created, unchanged, skipped, errors). Also available as `wp cfbe sync [--dry-run] [--limit=<n>]`. |
| Cancel | Stops a running backfill. |

Deleting a WordPress user does not delete the bluefox contact, and changing an account email does not rename it.
