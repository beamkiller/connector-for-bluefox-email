<?php
/**
 * One custom field mapping row (also the JS template with index `__MAP__`).
 *
 * @package CFBE
 *
 * @var string     $cfbe_rule_name Rule input name base.
 * @var int|string $cfbe_map_index Row index.
 * @var array      $cfbe_map       { form_field, bluefox_field }.
 */

defined( 'ABSPATH' ) || exit;

$cfbe_map_name = $cfbe_rule_name . '[fields][' . $cfbe_map_index . ']';
?>
<div class="cfbe-int-map">
	<input type="text" name="<?php echo esc_attr( $cfbe_map_name . '[form_field]' ); ?>" value="<?php echo esc_attr( (string) ( $cfbe_map['form_field'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Form field', 'connector-for-bluefox-email' ); ?>" aria-label="<?php esc_attr_e( 'Form field', 'connector-for-bluefox-email' ); ?>">
	<span aria-hidden="true">→</span>
	<input type="text" name="<?php echo esc_attr( $cfbe_map_name . '[bluefox_field]' ); ?>" value="<?php echo esc_attr( (string) ( $cfbe_map['bluefox_field'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'bluefox field', 'connector-for-bluefox-email' ); ?>" aria-label="<?php esc_attr_e( 'bluefox field', 'connector-for-bluefox-email' ); ?>">
	<button type="button" class="button-link button-link-delete cfbe-int-remove-map"><?php esc_html_e( 'Remove', 'connector-for-bluefox-email' ); ?></button>
</div>
