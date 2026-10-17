<?php
/**
 * Coupon tab view.
 *
 * @package CFBE
 *
 * @var array $cfbe_values `coupon` settings.
 */

use CFBE\Admin\Coupon_Tab;
use CFBE\Admin\List_Options;
use CFBE\Coupon_Data;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

$cfbe_option = Settings::option_name( 'coupon' );
$cfbe_name   = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
$cfbe_lists  = List_Options::lists( (string) $cfbe_values['list_id'] );
?>
<p class="cfbe-preview"><strong><?php esc_html_e( 'Example:', 'connector-for-bluefox-email' ); ?></strong> <?php echo esc_html( Coupon_Tab::preview( $cfbe_values ) ); ?></p>
<p class="description"><?php esc_html_e( 'Each email address can receive one welcome coupon, only as a new subscriber and only after confirming the subscription. The coupon can be redeemed in the cart.', 'connector-for-bluefox-email' ); ?></p>

<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-coupon-email-id-c"><?php esc_html_e( 'Coupon email ID', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text code" id="cfbe-coupon-email-id-c" name="<?php echo esc_attr( $cfbe_name( 'email_id' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['email_id'] ); ?>" placeholder="<?php echo esc_attr( (string) Settings::get( 'emails', 'coupon_email_id' ) ); ?>">
				<p class="description"><?php esc_html_e( 'ID of the bluefox transactional email that delivers the coupon (see “Coupon email in bluefox” below). No coupon is sent without it.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-coupon-list"><?php esc_html_e( 'Reward signups on list', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-coupon-list" name="<?php echo esc_attr( $cfbe_name( 'list_id' ) ); ?>">
					<option value=""><?php esc_html_e( 'Any list', 'connector-for-bluefox-email' ); ?></option>
					<?php foreach ( $cfbe_lists as $cfbe_id => $cfbe_list_name ) : ?>
						<option value="<?php echo esc_attr( (string) $cfbe_id ); ?>" <?php selected( (string) $cfbe_values['list_id'], (string) $cfbe_id ); ?>><?php echo esc_html( (string) $cfbe_list_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Only signups confirmed on this list receive the coupon. The lists come from the General tab (“Load lists”).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-discount-type"><?php esc_html_e( 'Discount', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" inputmode="decimal" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'amount' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['amount'] ); ?>" aria-label="<?php esc_attr_e( 'Discount amount', 'connector-for-bluefox-email' ); ?>">
				<select id="cfbe-discount-type" name="<?php echo esc_attr( $cfbe_name( 'discount_type' ) ); ?>">
					<option value="percent" <?php selected( $cfbe_values['discount_type'], 'percent' ); ?>><?php esc_html_e( '% off the cart', 'connector-for-bluefox-email' ); ?></option>
					<option value="fixed_cart" <?php selected( $cfbe_values['discount_type'], 'fixed_cart' ); ?>><?php echo esc_html( get_woocommerce_currency_symbol() ); ?> <?php esc_html_e( 'off the cart', 'connector-for-bluefox-email' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-expiry"><?php esc_html_e( 'Valid for (days)', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="1" max="365" class="small-text" id="cfbe-expiry" name="<?php echo esc_attr( $cfbe_name( 'expiry_days' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['expiry_days'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-min"><?php esc_html_e( 'Minimum order amount', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="text" inputmode="decimal" class="small-text" id="cfbe-min" name="<?php echo esc_attr( $cfbe_name( 'minimum_amount' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['minimum_amount'] ); ?>"> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
			<p class="description"><?php esc_html_e( '0 = no minimum.', 'connector-for-bluefox-email' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Restrictions', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'individual_use' ) ); ?>" value="1" <?php checked( $cfbe_values['individual_use'] ); ?>> <?php esc_html_e( 'Cannot be combined with other coupons', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'exclude_sale_items' ) ); ?>" value="1" <?php checked( $cfbe_values['exclude_sale_items'] ); ?>> <?php esc_html_e( 'Not valid on sale items', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'free_shipping' ) ); ?>" value="1" <?php checked( $cfbe_values['free_shipping'] ); ?>> <?php esc_html_e( 'Also grants free shipping', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-prefix"><?php esc_html_e( 'Code prefix', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="text" class="small-text code" id="cfbe-prefix" maxlength="10" name="<?php echo esc_attr( $cfbe_name( 'code_prefix' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['code_prefix'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-pending"><?php esc_html_e( 'Confirmation deadline (days)', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="1" max="60" class="small-text" id="cfbe-pending" name="<?php echo esc_attr( $cfbe_name( 'pending_expiry_days' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['pending_expiry_days'] ); ?>">
			<p class="description"><?php esc_html_e( 'Only subscriptions confirmed within this many days receive a coupon; after that the pending signup expires.', 'connector-for-bluefox-email' ); ?></p></td>
		</tr>
	</table>

	<h2><?php esc_html_e( '“Your coupon expires soon” reminder', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Coupon data in bluefox', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'store_code_in_bluefox' ) ); ?>" value="1" <?php checked( $cfbe_values['store_code_in_bluefox'] ); ?>> <?php esc_html_e( 'Update the couponCode, couponExpires and couponUsed fields', 'connector-for-bluefox-email' ); ?></label>
				<p><button type="button" class="button" id="cfbe-setup-fields"><?php esc_html_e( 'Create bluefox fields', 'connector-for-bluefox-email' ); ?></button> <span class="cfbe-result" id="cfbe-fields-result" aria-live="polite"></span></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-reminder"><?php esc_html_e( 'Reminder before expiry (days)', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="1" max="30" class="small-text" id="cfbe-reminder" name="<?php echo esc_attr( $cfbe_name( 'reminder_days' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['reminder_days'] ); ?>"></td>
		</tr>
	</table>
	<div class="cfbe-guide">
		<p><strong><?php esc_html_e( 'Setup in bluefox (one-time):', 'connector-for-bluefox-email' ); ?></strong></p>
		<ol>
			<li><?php esc_html_e( 'Segments → new segment “Coupon expires soon”: couponUsed = false AND couponExpires within the next days:', 'connector-for-bluefox-email' ); ?> <strong><?php echo esc_html( (string) $cfbe_values['reminder_days'] ); ?></strong>.</li>
			<li><?php esc_html_e( 'Automations → new automation: Enter segment “Coupon expires soon” (once per contact) → Send email.', 'connector-for-bluefox-email' ); ?></li>
			<li><?php esc_html_e( 'Available in the email: {{contact.couponCode}}, {{contact.name}}.', 'connector-for-bluefox-email' ); ?></li>
		</ol>
	</div>
	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Coupon email in bluefox', 'connector-for-bluefox-email' ); ?></h2>
<div class="cfbe-guide">
	<ol>
		<li><?php esc_html_e( 'In bluefox, open Transactional emails and create a new email of type HTML (for example “Welcome coupon”). Set its subject, for example: Your {{discount}} welcome coupon.', 'connector-for-bluefox-email' ); ?></li>
		<li><?php esc_html_e( 'Paste the template below as the HTML content and adjust the texts and colours to your brand.', 'connector-for-bluefox-email' ); ?></li>
		<li><?php esc_html_e( 'Save it, copy the email ID and paste it into “Coupon email ID” above.', 'connector-for-bluefox-email' ); ?></li>
	</ol>
	<p><strong><?php esc_html_e( 'Merge tags sent with the coupon email:', 'connector-for-bluefox-email' ); ?></strong></p>
	<ul class="cfbe-merge-tags">
		<?php foreach ( Coupon_Data::merge_tags() as $cfbe_tag => $cfbe_tag_help ) : ?>
			<li><code>{{<?php echo esc_html( $cfbe_tag ); ?>}}</code> – <?php echo esc_html( $cfbe_tag_help ); ?></li>
		<?php endforeach; ?>
	</ul>
	<p class="description"><?php esc_html_e( 'Contact fields work too, e.g. {{contact.name}}. The coupon email is transactional (no unsubscribe link), so keep it about the coupon only.', 'connector-for-bluefox-email' ); ?></p>
</div>
<?php
foreach (
	array(
		'welcome-coupon'       => __( 'Welcome coupon template', 'connector-for-bluefox-email' ),
		'confirm-subscription' => __( 'Double opt-in confirmation template (set it as the confirmation email of the bluefox list; it must contain {{verifyLink}})', 'connector-for-bluefox-email' ),
	) as $cfbe_template => $cfbe_template_label
) :
	$cfbe_html = Coupon_Tab::template( $cfbe_template );
	if ( '' === $cfbe_html ) {
		continue;
	}
	?>
	<div class="cfbe-template">
		<p><label for="cfbe-template-<?php echo esc_attr( $cfbe_template ); ?>"><strong><?php echo esc_html( $cfbe_template_label ); ?></strong></label></p>
		<textarea id="cfbe-template-<?php echo esc_attr( $cfbe_template ); ?>" class="large-text code" rows="8" readonly><?php echo esc_textarea( $cfbe_html ); ?></textarea>
		<p><button type="button" class="button cfbe-copy" data-target="cfbe-template-<?php echo esc_attr( $cfbe_template ); ?>"><?php esc_html_e( 'Copy', 'connector-for-bluefox-email' ); ?></button></p>
	</div>
<?php endforeach; ?>
