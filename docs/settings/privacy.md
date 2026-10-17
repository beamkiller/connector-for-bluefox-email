# Privacy tab

Tab id: `privacy` · Option: `cfbe_api` · Always shown.

The plugin adds suggested text to **Settings → Privacy** (policy guide) and handles **Tools → Export Personal Data** and **Tools → Erase Personal Data** for newsletter signups and the bluefox status stored with users. See [Privacy and GDPR](../help/privacy.md).

| Label | Option key | Default | What it does |
|---|---|---|---|
| IP address with consent | `cfbe_api[ip_mode]` | `anonymized` | Stored with every signup (forms, checkout, form plugins) as part of the proof of consent. `anonymized`: *Anonymised (recommended): the last part is removed, e.g. 203.0.113.0*. `full`: *Full IP address*. `off`: *Do not store IP addresses*. Switching to a stricter mode (full → anonymised → off) also anonymises or removes the addresses already stored, in the background. Switching to a less strict mode only affects new signups. |
| Erase IP addresses after | `cfbe_api[ip_retention_months]` | `24` | Months, 0–120. A daily job removes older IP addresses from signup records and checkout orders. 0 = keep them as long as the record exists. |
| Visitor IP address | `cfbe_api[ip_header]` | empty (*Direct connection*) | Where the visitor IP for rate limits and the consent record comes from. empty: *Direct connection (REMOTE_ADDR)*. `cloudflare`: *Cloudflare (CF-Connecting-IP)*. `forwarded`: *Proxy / load balancer (X-Forwarded-For)*, the last hop. `real_ip`: *Proxy (X-Real-IP)*. Behind Cloudflare or a proxy every visitor arrives from the proxy's address, so the limits would hit everyone at once. Only choose a header your server really receives from a trusted proxy: visitors can send these headers themselves. If the header is missing or invalid, `REMOTE_ADDR` is used. The [`cfbe_client_ip`](../developers/hooks.md#core) filter still applies after it. |
| Erase requests | `cfbe_api[privacy_delete_remote]` | off | **Also delete the bluefox.email contact**. When an erasure request is processed, the contact is deleted in bluefox.email too (from every list, cannot be undone). When off, only the data on this site is erased. |

The suggested privacy policy text changes with the IP mode and retention.
