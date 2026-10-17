# Help

- [Privacy and GDPR](privacy.md) – what the plugin stores, IP address modes, retention, data export and erasure.
- [FAQ](faq.md) – short answers to common questions.
- [Troubleshooting](troubleshooting.md) – caching, the webhook `Authorization` header, background jobs.
- [Changelog](changelog.md) – what changed in each version.

## Where to look first

1. **bluefox.email → Logs** in wp-admin shows the last 200 plugin log entries (API errors, retries, webhook decisions). Email addresses are masked and secrets are never written.
2. With WooCommerce active, the same entries go to **WooCommerce → Status → Logs** under the source `cfbe`.
3. **Tools → Scheduled Actions** (WooCommerce / Action Scheduler) lists the plugin's background jobs in the group `cfbe`.

## Reporting a problem

Open an issue on [GitHub](https://github.com/beamkiller/connector-for-bluefox-email/issues). Include the WordPress, PHP and WooCommerce versions, the features you have switched on and the relevant log lines. Remove email addresses, IDs and keys before you post.
