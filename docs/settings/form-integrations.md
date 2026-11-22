# Form integrations tab

Tab id: `integrations` · Option: `cfbe_integrations` · Needs: at least one **Form plugin integrations** switch on the General tab, and that form plugin active.

See [Form plugin integrations](../features/form-integrations.md) for what happens after a submission.

The tab has one section per switched-on form plugin. Contact Form 7, WPForms, Fluent Forms and Gravity Forms are configured here with one block per form (**Add form**). Elementor Pro forms are configured in the form widget instead (see below).

## Form rule

Option keys: `cfbe_integrations[<plugin>][rules][n][…]`, where `<plugin>` is `cf7`, `wpforms`, `fluentforms` or `gravityforms`. Up to 50 forms per plugin.

| Label | Key | Default | What it does |
|---|---|---|---|
| Form | `form` | – | The form (a dropdown of the plugin's forms, or a form ID). A rule without a form is dropped on save. **Remove** deletes the rule. |
| List | `list_id` | empty (*Default list*) | bluefox list for this form. |
| Email field | `email_field` | empty | Field holding the email address. Empty = the first email field of the form. |
| Name field (optional) | `name_field` | empty | Field holding the name, or a name group (Fluent Forms `names`: also sent as First name / Last name). Empty = found by *Match fields by name*. |
| Consent field | `consent_field` | empty | A checkbox / acceptance field. The visitor is only subscribed when it is ticked. |
| This form itself is the newsletter signup (consent is implied by submitting) | `implied_consent` | off | Subscribes everyone who submits the form. Only use it when the form clearly says it is a newsletter signup. |
| Extra tags | `tags` | empty | Comma-separated. `source-<plugin>` is always added. |
| Match fields by name | `auto_fields` | on | Sends form fields whose name matches a Contact sync field that is switched on (`first_name`, `last_name`, `phone`, `city`, …) under that field's bluefox name. Rules saved before 1.1.0 count as on. See [Match fields by name](../features/form-integrations.md#match-fields-by-name). *Since 1.1.0.* |
| Custom fields | `fields[m][form_field]`, `fields[m][bluefox_field]` | none | Form field → bluefox contact field name, up to 30 per form. `email`, `tags` and `status` cannot be mapped. Wins over a match by name. |

!!! warning "No consent, no signup"
    Without a consent field and without the implied-consent box, nobody is subscribed from that form. The tab shows a warning until one is set.

## Field names per plugin

=== "Contact Form 7"

    Use the CF7 field names, e.g. `your-email`, `your-name`, `acceptance-newsletter`.

=== "WPForms"

    Use the WPForms field IDs (shown in the form builder), e.g. `1`, `2`; name parts as `1.first` / `1.last`.

=== "Fluent Forms"

    Use the input names (*Advanced Options → Name Attribute*), e.g. `email`, `names.first_name`, `terms-n-condition`.

=== "Gravity Forms"

    Use the field IDs, e.g. `3`; a single input as `1.3` (first name) or `1.6` (last name). A Name field ID (`1`) gives the full name, a Consent field ID gives its checkbox.

=== "Elementor Pro"

    Configured in each Elementor Form widget: **Actions After Submit → bluefox.email**, then the **bluefox.email** section. Use the fields' custom IDs (*Advanced → ID*), e.g. `email`, `name`, `newsletter`. Stored with the widget, not in `cfbe_integrations`.

    | Control | Setting | What it does |
    |---|---|---|
    | List | `cfbe_list` | bluefox list; empty = *Default list*. |
    | Email field ID | `cfbe_email_field` | Empty = the first email field. |
    | Name field ID (optional) | `cfbe_name_field` | |
    | Consent field ID | `cfbe_consent_field` | An Acceptance or Checkbox field. |
    | This form itself is the newsletter signup | `cfbe_implied_consent` | Consent is implied by submitting. |
    | Extra tags | `cfbe_tags` | Comma-separated. |
    | Match fields by name | `cfbe_auto_fields` | On by default. *Since 1.1.0.* |
    | Custom fields | `cfbe_field_map` | One per line: `form field ID=bluefox contact field name`, e.g. `phone=phone`. |
