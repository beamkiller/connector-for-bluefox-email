<?php
/**
 * Checkout tab view.
 *
 * @package CFBE
 *
 * @var array $cfbe_values `checkout` settings.
 * @var array $cfbe_lists  Cached bluefox lists (id => name).
 */

use CFBE\Checkout_Optin;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

$cfbe_option = Settings::option_name( 'checkout' );
$cfbe_name   = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
?>
<p class="description"><?php esc_html_e( 'An unticked newsletter checkbox at checkout: directly below the billing email on the classic checkout, and in the contact information step of the Checkout block. If the customer ticks it, the consent is saved to the order and the subscription starts in the background (the order never waits for bluefox). After that it works like the form: confirmation email, then the welcome coupon if that module is on. Customers who are already subscribed do not see it.', 'connector-for-bluefox-email' ); ?></p>

<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-checkout-label"><?php esc_html_e( 'Text', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="large-text" id="cfbe-checkout-label" name="<?php echo esc_attr( $cfbe_name( 'label' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['label'] ); ?>">
				<p class="description"><?php esc_html_e( '{discount} = the coupon value. On the classic checkout a “Privacy policy” link is added after it automatically (using the URL set on the Form tab). The Checkout block only supports plain text, so mention your privacy policy in the store’s checkout terms there.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-checkout-desc"><?php esc_html_e( 'Small print below', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="large-text" id="cfbe-checkout-desc" name="<?php echo esc_attr( $cfbe_name( 'description' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['description'] ); ?>">
				<p class="description"><?php esc_html_e( 'Classic checkout and My Account only.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-checkout-list"><?php esc_html_e( 'Subscriber list', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-checkout-list" name="<?php echo esc_attr( $cfbe_name( 'list_id' ) ); ?>">
					<option value=""><?php esc_html_e( 'Default list (General tab)', 'connector-for-bluefox-email' ); ?></option>
					<?php foreach ( $cfbe_lists as $cfbe_id => $cfbe_list_name ) : ?>
						<option value="<?php echo esc_attr( (string) $cfbe_id ); ?>" <?php selected( (string) $cfbe_values['list_id'], (string) $cfbe_id ); ?>><?php echo esc_html( (string) $cfbe_list_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The lists come from the General tab (“Load lists”).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-checkout-tags"><?php esc_html_e( 'Extra tags', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="cfbe-checkout-tags" name="<?php echo esc_attr( $cfbe_name( 'tags' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['tags'] ); ?>" placeholder="customer, webshop">
				<p class="description"><?php esc_html_e( 'Comma separated bluefox tags added to these subscribers. The source tag (source-checkout or source-account) is always added.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'My Account', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'account_optin' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['account_optin'] ) ); ?>> <?php esc_html_e( 'Also offer the checkbox on My Account → Account details', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Preview', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<p class="cfbe-checkout-preview">
					<label><input type="checkbox" disabled> <?php echo wp_kses( Checkout_Optin::label_html( $cfbe_values ), Checkout_Optin::LABEL_TAGS ); ?></label>
				</p>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>
