<?php
/**
 * One form rule row (also used as the JS template with index `__RULE__`).
 *
 * @package CFBE
 *
 * @var string                     $cfbe_base  Input name base, e.g. cfbe_integrations[cf7].
 * @var int|string                 $cfbe_index Row index.
 * @var array                      $cfbe_rule  Rule values.
 * @var array<string, string>|null $cfbe_forms Forms (id => title) or null (ID input).
 * @var array<string, string>      $cfbe_lists Cached lists (id => name).
 * @var string                     $cfbe_slug  Integration slug.
 */

defined( 'ABSPATH' ) || exit;

$cfbe_rule_name = $cfbe_base . '[rules][' . $cfbe_index . ']';
$cfbe_rule_id   = 'cfbe-int-' . $cfbe_slug . '-' . $cfbe_index;
$cfbe_no_rule   = '' === (string) $cfbe_rule['consent_field'] && empty( $cfbe_rule['implied_consent'] );
?>
<fieldset class="cfbe-int-rule">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-form' ); ?>"><?php esc_html_e( 'Form', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<?php if ( is_array( $cfbe_forms ) ) : ?>
					<select id="<?php echo esc_attr( $cfbe_rule_id . '-form' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[form]' ); ?>">
						<option value=""><?php esc_html_e( '— Select a form —', 'connector-for-bluefox-email' ); ?></option>
						<?php if ( '' !== (string) $cfbe_rule['form'] && ! isset( $cfbe_forms[ (string) $cfbe_rule['form'] ] ) ) : ?>
							<option value="<?php echo esc_attr( (string) $cfbe_rule['form'] ); ?>" selected>
								<?php
								/* translators: %s: form ID */
								echo esc_html( sprintf( __( '#%s (not found)', 'connector-for-bluefox-email' ), (string) $cfbe_rule['form'] ) );
								?>
							</option>
						<?php endif; ?>
						<?php foreach ( $cfbe_forms as $cfbe_form_id => $cfbe_form_title ) : ?>
							<option value="<?php echo esc_attr( (string) $cfbe_form_id ); ?>" <?php selected( (string) $cfbe_rule['form'], (string) $cfbe_form_id ); ?>><?php echo esc_html( '#' . $cfbe_form_id . ' ' . $cfbe_form_title ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" class="small-text" id="<?php echo esc_attr( $cfbe_rule_id . '-form' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[form]' ); ?>" value="<?php echo esc_attr( (string) $cfbe_rule['form'] ); ?>" placeholder="<?php esc_attr_e( 'Form ID', 'connector-for-bluefox-email' ); ?>">
				<?php endif; ?>
				<button type="button" class="button-link button-link-delete cfbe-int-remove-rule"><?php esc_html_e( 'Remove', 'connector-for-bluefox-email' ); ?></button>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-list' ); ?>"><?php esc_html_e( 'List', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $cfbe_rule_id . '-list' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[list_id]' ); ?>">
					<option value=""><?php esc_html_e( 'Default list', 'connector-for-bluefox-email' ); ?></option>
					<?php if ( '' !== (string) $cfbe_rule['list_id'] && ! isset( $cfbe_lists[ (string) $cfbe_rule['list_id'] ] ) ) : ?>
						<option value="<?php echo esc_attr( (string) $cfbe_rule['list_id'] ); ?>" selected><?php echo esc_html( (string) $cfbe_rule['list_id'] ); ?></option>
					<?php endif; ?>
					<?php foreach ( $cfbe_lists as $cfbe_list_id => $cfbe_list_name ) : ?>
						<option value="<?php echo esc_attr( (string) $cfbe_list_id ); ?>" <?php selected( (string) $cfbe_rule['list_id'], (string) $cfbe_list_id ); ?>><?php echo esc_html( (string) $cfbe_list_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( ! $cfbe_lists ) : ?>
					<p class="description"><?php esc_html_e( 'Load the lists on the General tab to choose another list.', 'connector-for-bluefox-email' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-email' ); ?>"><?php esc_html_e( 'Email field', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="<?php echo esc_attr( $cfbe_rule_id . '-email' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[email_field]' ); ?>" value="<?php echo esc_attr( (string) $cfbe_rule['email_field'] ); ?>">
				<p class="description"><?php esc_html_e( 'Empty = the first email field of the form.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-name' ); ?>"><?php esc_html_e( 'Name field (optional)', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="text" class="regular-text" id="<?php echo esc_attr( $cfbe_rule_id . '-name' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[name_field]' ); ?>" value="<?php echo esc_attr( (string) $cfbe_rule['name_field'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-consent' ); ?>"><?php esc_html_e( 'Consent field', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text cfbe-int-consent" id="<?php echo esc_attr( $cfbe_rule_id . '-consent' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[consent_field]' ); ?>" value="<?php echo esc_attr( (string) $cfbe_rule['consent_field'] ); ?>">
				<p class="description"><?php esc_html_e( 'A checkbox / acceptance field. The visitor is only subscribed when it is ticked.', 'connector-for-bluefox-email' ); ?></p>
				<p>
					<label>
						<input type="checkbox" class="cfbe-int-implied" name="<?php echo esc_attr( $cfbe_rule_name . '[implied_consent]' ); ?>" value="1" <?php checked( ! empty( $cfbe_rule['implied_consent'] ) ); ?>>
						<?php esc_html_e( 'This form itself is the newsletter signup (consent is implied by submitting)', 'connector-for-bluefox-email' ); ?>
					</label>
				</p>
				<p class="cfbe-int-warning cfbe-int-warning--implied" <?php echo empty( $cfbe_rule['implied_consent'] ) ? 'hidden' : ''; ?>><?php esc_html_e( 'Everyone who submits this form will be subscribed. Only use this when the form clearly says that it is a newsletter signup (GDPR: consent must be informed and unambiguous).', 'connector-for-bluefox-email' ); ?></p>
				<p class="cfbe-int-warning cfbe-int-warning--missing" <?php echo $cfbe_no_rule ? '' : 'hidden'; ?>><?php esc_html_e( 'Set a consent field or tick the box above - until then nobody is subscribed from this form.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $cfbe_rule_id . '-tags' ); ?>"><?php esc_html_e( 'Extra tags', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="<?php echo esc_attr( $cfbe_rule_id . '-tags' ); ?>" name="<?php echo esc_attr( $cfbe_rule_name . '[tags]' ); ?>" value="<?php echo esc_attr( (string) $cfbe_rule['tags'] ); ?>">
				<p class="description">
					<?php
					/* translators: %s: source tag, e.g. source-cf7 */
					echo esc_html( sprintf( __( 'Comma separated. The tag %s is always added.', 'connector-for-bluefox-email' ), 'source-' . $cfbe_slug ) );
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Custom fields', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<div class="cfbe-int-maps" data-next="<?php echo esc_attr( (string) count( (array) $cfbe_rule['fields'] ) ); ?>">
					<?php
					foreach ( array_values( (array) $cfbe_rule['fields'] ) as $cfbe_map_index => $cfbe_map ) {
						include __DIR__ . '/map-row.php';
					}
					?>
				</div>
				<button type="button" class="button cfbe-int-add-map"><?php esc_html_e( 'Add field', 'connector-for-bluefox-email' ); ?></button>
				<p class="description"><?php esc_html_e( 'Form field → bluefox contact field name (the field must exist in bluefox; unknown fields are ignored there).', 'connector-for-bluefox-email' ); ?></p>
				<template class="cfbe-int-map-template">
					<?php
					$cfbe_map_index = '__MAP__';
					$cfbe_map       = array(
						'form_field'    => '',
						'bluefox_field' => '',
					);
					require __DIR__ . '/map-row.php';
					?>
				</template>
			</td>
		</tr>
	</table>
</fieldset>
