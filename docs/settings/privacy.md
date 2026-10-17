# Privacy tab

Tab id: `privacy` · Option: `cfbe_api` · Always shown.

The plugin adds suggested text to **Settings → Privacy** (policy guide) and handles **Tools → Export Personal Data** and **Tools → Erase Personal Data** for newsletter signups and the bluefox status stored with users. See [Privacy and GDPR](../help/privacy.md).

| Label | Option key | Default | What it does |
|---|---|---|---|
| IP address with consent | `cfbe_api[ip_mode]` | `anonymized` | Stored with every signup (forms, checkout, form plugins) as part of the proof of consent. `anonymized`: *Anonymised (recommended): the last part is removed, e.g. 203.0.113.0*. `full`: *Full IP address*. `off`: *Do not store IP addresses*. Switching to a stricter mode (full → anonymised → off) also anonymises or removes the addresses already stored, in the background. Switching to a less strict mode only affects new signups. |
| Erase IP addresses after | `cfbe_api[ip_retention_months]` | `24` | Months, 0–120. A daily job removes older IP addresses from signup records and checkout orders. 0 = keep them as long as the record exists. |
| Erase requests | `cfbe_api[privacy_delete_remote]` | off | **Also delete the bluefox.email contact**. When an erasure request is processed, the contact is deleted in bluefox.email too (from every list, cannot be undone). When off, only the data on this site is erased. |

The suggested privacy policy text changes with the IP mode and retention.
