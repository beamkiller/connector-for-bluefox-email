# WP-CLI

The plugin registers the `wp cfbe` command group. `wp cfbe sync` is only available while the **Contact sync** module is on.

| Command | Description |
|---|---|
| `wp cfbe poll` | Run the confirmation poller now |
| `wp cfbe status <email>` | Show the local signup, the bluefox status and the coupon of an email |
| `wp cfbe resend-coupon <email>` | Re-send the welcome coupon email (same code) |
| `wp cfbe sync [--dry-run] [--limit=<n>]` | Sync users and customers to bluefox contacts |

## wp cfbe poll

Runs the job that normally runs every 15 minutes: expires signups older than the confirmation deadline and queues a confirmation check for pending signups.

=== "WP-CLI"

    ```bash
    wp cfbe poll
    # Success: 3 reward job(s) queued.
    ```

=== "PHP"

    ```php
    $queued = CFBE\Plugin::instance()->rewards()->poll();
    ```

The queued checks run in the background (Action Scheduler or WP-Cron). The **Subscribers** tab has a **Check confirmations now** button for the same thing.

## wp cfbe status

```bash
wp cfbe status jane@example.com
# Local: {"status":"confirmed","source":"popup","tags":"…","attempts":"0","last_error":null,"created_at":"…","confirmed_at":"…","coupon_id":"123"}
# bluefox: active
# Coupon: WELCOME-K7M2QX9P
```

- **Local** – the row in `{prefix}cfbe_subscribers`, or `no row`.
- **bluefox** – the subscriber status on the list the person signed up to on this site (the default list from the General tab when there is no local row), or the error code.
- **Coupon** – the welcome coupon code, if one was issued.

## wp cfbe resend-coupon

Re-sends the welcome coupon email with the existing code. It does not create a new coupon.

=== "WP-CLI"

    ```bash
    wp cfbe resend-coupon jane@example.com
    # Success: Coupon email re-sent.
    ```

=== "PHP"

    ```php
    $result = CFBE\Plugin::instance()->rewards()->resend( 'jane@example.com' );
    if ( is_wp_error( $result ) ) {
        error_log( $result->get_error_message() );
    }
    ```

Admins can do the same from the **Subscribers** tab.

## wp cfbe sync

Syncs WordPress users, then guest WooCommerce customers, to bluefox contacts in the foreground, one at a time. It writes the fields enabled on the [Contact sync tab](../settings/contact-sync.md). See [Contact sync](../features/contact-sync.md).

| Option | Description |
|---|---|
| `--dry-run` | Show what would be sent without writing to bluefox. Contacts are still read. |
| `--limit=<n>` | Stop after this many email addresses. |

```bash
wp cfbe sync --dry-run --limit=10
# jane@example.com: updated {"name":"Jane Doe","orderCount":3,…}
# Success: 10 processed (dry run). updated: 7, unchanged: 2, no_data: 1
```

Outcomes per email: `created`, `updated`, `unchanged`, `no_data` (no user and no paid order), `not_contact` (not a bluefox contact yet and the scope is *existing contacts only*), `invalid`, `error`. The command stops with an error when bluefox is not configured.

There is a pause of 250 ms between contacts to stay within the bluefox rate limit; change it with the [`cfbe_sync_contact_pause_ms`](hooks.md#contact-sync) filter. For large stores prefer the background **backfill** on the Contact sync tab. `--limit` always starts from the first user, so use it for testing.
