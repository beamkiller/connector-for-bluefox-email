<?php
/**
 * Order emails / Abandoned carts tab view.
 *
 * @package CFBE
 *
 * @var string $cfbe_section   Section: `orders` or `cart`.
 * @var array  $cfbe_values    `woo_events` settings.
 * @var array  $cfbe_rules     Rules of this section.
 * @var array  $cfbe_cache     Cached email lists (transactional / triggered => [id => name], fetched_at).
 * @var array  $cfbe_events    Selectable events of this section (event => label).
 * @var array  $cfbe_statuses  Per-rule status (last_sent, last_error, last_error_at, count).
 * @var array  $cfbe_carts     Recent carts.
 * @var string $cfbe_option    Option name.
 * @var bool   $cfbe_connected Whether bluefox is configured.
 */

use CFBE\Woo_Events\Rules;
use CFBE\Woo_Events\Settings_Tab;

defined( 'ABSPATH' ) || exit;

$cfbe_is_cart = Settings_Tab::SECTION_CART === $cfbe_section;

$cfbe_name = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};

$cfbe_time = static function ( int $timestamp ): string {
	return $timestamp > 0 ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '';
};

$cfbe_types = array(
	Rules::TYPE_TRANSACTIONAL => __( 'Transactional (service email, anyone)', 'connector-for-bluefox-email' ),
	Rules::TYPE_TRIGGERED     => __( 'Triggered (marketing, active subscribers only)', 'connector-for-bluefox-email' ),
);
$cfbe_units = array(
	'minutes' => __( 'minutes', 'connector-for-bluefox-email' ),
	'hours'   => __( 'hours', 'connector-for-bluefox-email' ),
	'days'    => __( 'days', 'connector-for-bluefox-email' ),
);

/**
 * Prints one rule row. `$cfbe_index` is a number or the `__INDEX__` placeholder of the template row.
 */
$cfbe_row = static function ( $cfbe_index, array $cfbe_rule ) use ( $cfbe_name, $cfbe_events, $cfbe_types, $cfbe_units, $cfbe_cache, $cfbe_statuses, $cfbe_time ): void {
	$cfbe_base   = $cfbe_name( 'rules' ) . '[' . $cfbe_index . ']';
	$cfbe_type   = (string) ( $cfbe_rule['type'] ?? Rules::TYPE_TRANSACTIONAL );
	$cfbe_emails = (array) ( $cfbe_cache[ $cfbe_type ] ?? array() );
	$cfbe_email  = (string) ( $cfbe_rule['email_id'] ?? '' );
	$cfbe_status = (array) ( $cfbe_statuses[ (string) ( $cfbe_rule['id'] ?? '' ) ] ?? array() );
	?>
	<tr class="cfbe-rule">
		<td>
			<input type="hidden" name="<?php echo esc_attr( $cfbe_base . '[id]' ); ?>" value="<?php echo esc_attr( (string) ( $cfbe_rule['id'] ?? '' ) ); ?>">
			<label class="screen-reader-text"><?php esc_html_e( 'Event', 'connector-for-bluefox-email' ); ?></label>
			<select class="cfbe-rule-event" name="<?php echo esc_attr( $cfbe_base . '[event]' ); ?>">
				<?php foreach ( $cfbe_events as $cfbe_event => $cfbe_label ) : ?>
					<option value="<?php echo esc_attr( $cfbe_event ); ?>" <?php selected( (string) ( $cfbe_rule['event'] ?? '' ), $cfbe_event ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select class="cfbe-rule-type" name="<?php echo esc_attr( $cfbe_base . '[type]' ); ?>">
				<?php foreach ( $cfbe_types as $cfbe_value => $cfbe_label ) : ?>
					<option value="<?php echo esc_attr( $cfbe_value ); ?>" <?php selected( $cfbe_type, $cfbe_value ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td>
			<select class="cfbe-rule-email" name="<?php echo esc_attr( $cfbe_base . '[email_id]' ); ?>" data-value="<?php echo esc_attr( $cfbe_email ); ?>">
				<option value=""><?php esc_html_e( '— Select an email —', 'connector-for-bluefox-email' ); ?></option>
				<?php foreach ( $cfbe_emails as $cfbe_id => $cfbe_label ) : ?>
					<option value="<?php echo esc_attr( (string) $cfbe_id ); ?>" <?php selected( $cfbe_email, (string) $cfbe_id ); ?>><?php echo esc_html( (string) $cfbe_label ); ?></option>
				<?php endforeach; ?>
				<?php if ( '' !== $cfbe_email && ! isset( $cfbe_emails[ $cfbe_email ] ) ) : ?>
					<option value="<?php echo esc_attr( $cfbe_email ); ?>" selected><?php echo esc_html( $cfbe_email . ' ' . __( '(not found in bluefox)', 'connector-for-bluefox-email' ) ); ?></option>
				<?php endif; ?>
			</select>
		</td>
		<td class="cfbe-rule-delay">
			<input type="number" min="0" step="1" class="small-text" name="<?php echo esc_attr( $cfbe_base . '[delay]' ); ?>" value="<?php echo esc_attr( (string) (int) ( $cfbe_rule['delay'] ?? 0 ) ); ?>" aria-label="<?php esc_attr_e( 'Delay', 'connector-for-bluefox-email' ); ?>">
			<select name="<?php echo esc_attr( $cfbe_base . '[delay_unit]' ); ?>" aria-label="<?php esc_attr_e( 'Delay unit', 'connector-for-bluefox-email' ); ?>">
				<?php foreach ( $cfbe_units as $cfbe_value => $cfbe_label ) : ?>
					<option value="<?php echo esc_attr( $cfbe_value ); ?>" <?php selected( (string) ( $cfbe_rule['delay_unit'] ?? 'minutes' ), $cfbe_value ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td><input type="checkbox" value="1" name="<?php echo esc_attr( $cfbe_base . '[enabled]' ); ?>" <?php checked( ! empty( $cfbe_rule['enabled'] ) ); ?> aria-label="<?php esc_attr_e( 'Enabled', 'connector-for-bluefox-email' ); ?>"></td>
		<td class="cfbe-rule-status">
			<?php if ( ! empty( $cfbe_status['last_sent'] ) ) : ?>
				<span class="cfbe-ok">
					<?php
					/* translators: 1: date and time, 2: number of emails sent */
					echo esc_html( sprintf( __( 'Last sent: %1$s (%2$d total)', 'connector-for-bluefox-email' ), $cfbe_time( (int) $cfbe_status['last_sent'] ), (int) ( $cfbe_status['count'] ?? 0 ) ) );
					?>
				</span><br>
			<?php endif; ?>
			<?php if ( ! empty( $cfbe_status['last_error'] ) ) : ?>
				<span class="cfbe-error">
					<?php
					/* translators: 1: date and time, 2: error message */
					echo esc_html( sprintf( __( 'Last error (%1$s): %2$s', 'connector-for-bluefox-email' ), $cfbe_time( (int) ( $cfbe_status['last_error_at'] ?? 0 ) ), (string) $cfbe_status['last_error'] ) );
					?>
				</span>
			<?php endif; ?>
			<?php if ( empty( $cfbe_status ) ) : ?>
				<span class="description">—</span>
			<?php endif; ?>
		</td>
		<td><button type="button" class="button-link button-link-delete cfbe-rule-remove"><?php esc_html_e( 'Remove', 'connector-for-bluefox-email' ); ?></button></td>
	</tr>
	<?php
};
?>
<?php if ( $cfbe_is_cart ) : ?>
	<p class="description"><?php esc_html_e( 'Carts are stored for logged-in customers and for guests once they type their email address at checkout. If the cart is not ordered within the idle time, the reminder is sent once, with a link ({{{cartUrl}}}) that restores the cart. Reminders are triggered emails (marketing, with an unsubscribe link), so bluefox only delivers them to active subscribers of the email’s list.', 'connector-for-bluefox-email' ); ?></p>
	<ul class="cfbe-woo-events-help">
		<li><?php esc_html_e( 'Template data: cartUrl, items (name, quantity, total, sku, image), itemCount, cartTotal, currency, siteName, siteUrl; with the recovery coupon also couponCode, couponDiscount, couponExpiry, couponMinSpend.', 'connector-for-bluefox-email' ); ?></li>
		<li><?php esc_html_e( 'Put links in triple braces, e.g. href="{{{cartUrl}}}": with two braces Handlebars HTML-escapes characters such as = and &, and link click tracking can break such URLs.', 'connector-for-bluefox-email' ); ?></li>
	</ul>
<?php else : ?>
	<p class="description"><?php esc_html_e( 'Send your own bluefox emails when something happens to an order. Every email is sent in the background, never during checkout. Each rule is sent at most once per order. WooCommerce’s own emails are not affected.', 'connector-for-bluefox-email' ); ?></p>
	<ul class="cfbe-woo-events-help">
		<li><?php esc_html_e( 'Transactional emails are service emails (order updates) and reach any customer. Triggered emails are marketing emails with an unsubscribe link: bluefox only delivers them to active subscribers of the email’s list. Use triggered emails for review requests.', 'connector-for-bluefox-email' ); ?></li>
		<li><?php esc_html_e( 'Template data (use them as {{orderNumber}}, {{#each items}}{{name}}{{/each}} …): orderId, orderNumber, orderStatus, orderDate, orderTotal, currency, paymentMethod, shippingMethod, customerNote, billing.firstName/lastName/company/city/country/phone, shipping.*, shippingAddressLines, items (name, quantity, total, sku, image), itemCount, couponCodes, viewOrderUrl, siteName, siteUrl, note (customer note emails).', 'connector-for-bluefox-email' ); ?></li>
		<li><?php esc_html_e( 'Put links in triple braces, e.g. href="{{{viewOrderUrl}}}": with two braces Handlebars HTML-escapes characters such as = and &, and link click tracking can break such URLs.', 'connector-for-bluefox-email' ); ?></li>
	</ul>
<?php endif; ?>

<?php if ( ! $cfbe_connected ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Connect bluefox on the General tab to load your emails.', 'connector-for-bluefox-email' ); ?></p></div>
<?php endif; ?>

<form method="post" action="options.php" id="cfbe-woo-events-form">
	<?php settings_fields( $cfbe_option ); ?>
	<input type="hidden" name="<?php echo esc_attr( $cfbe_name( '_section' ) ); ?>" value="<?php echo esc_attr( $cfbe_section ); ?>">

	<h2><?php echo esc_html( $cfbe_is_cart ? __( 'Reminder email', 'connector-for-bluefox-email' ) : __( 'Rules', 'connector-for-bluefox-email' ) ); ?></h2>
	<p>
		<button type="button" class="button" id="cfbe-woo-events-refresh" <?php disabled( ! $cfbe_connected ); ?>><?php esc_html_e( 'Refresh email lists', 'connector-for-bluefox-email' ); ?></button>
		<span id="cfbe-woo-events-refresh-result" class="cfbe-result" role="status" aria-live="polite">
			<?php
			if ( $cfbe_cache['fetched_at'] > 0 ) {
				/* translators: %s: date and time */
				echo esc_html( sprintf( __( 'Lists loaded: %s', 'connector-for-bluefox-email' ), $cfbe_time( (int) $cfbe_cache['fetched_at'] ) ) );
			}
			?>
		</span>
	</p>
	<table class="widefat striped cfbe-rules">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Event', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Email type', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'bluefox email', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Delay', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'On', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'connector-for-bluefox-email' ); ?></span></th>
			</tr>
		</thead>
		<tbody id="cfbe-rules-body">
			<?php
			foreach ( $cfbe_rules as $cfbe_i => $cfbe_rule ) {
				$cfbe_row( $cfbe_i, (array) $cfbe_rule );
			}
			?>
		</tbody>
	</table>
	<template id="cfbe-rule-template">
		<?php
		$cfbe_row(
			'__INDEX__',
			array(
				'id'      => '',
				'event'   => $cfbe_is_cart ? Rules::EVENT_ABANDONED_CART : Rules::STATUS_PREFIX . 'completed',
				'type'    => $cfbe_is_cart ? Rules::TYPE_TRIGGERED : Rules::TYPE_TRANSACTIONAL,
				'enabled' => true,
			)
		);
		?>
	</template>
	<p><button type="button" class="button" id="cfbe-rule-add"><?php echo esc_html( $cfbe_is_cart ? __( 'Add reminder', 'connector-for-bluefox-email' ) : __( 'Add rule', 'connector-for-bluefox-email' ) ); ?></button></p>
	<?php if ( $cfbe_is_cart ) : ?>
		<p class="description"><?php esc_html_e( 'The delay counts from the moment the cart is found idle. Add a second reminder with a longer delay for a follow-up.', 'connector-for-bluefox-email' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Review request: sent the set delay after the order is completed (e.g. 7 days). First paid order: the customer’s first processing/completed order by billing email (guests included).', 'connector-for-bluefox-email' ); ?></p>
		<?php submit_button(); ?>
</form>
		<?php return; ?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Cart settings', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Cart tracking', 'connector-for-bluefox-email' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'cart_tracking' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['cart_tracking'] ) ); ?>> <?php esc_html_e( 'Store carts (only while a reminder is enabled)', 'connector-for-bluefox-email' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Opted-in shoppers only', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'cart_optin_only' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['cart_optin_only'] ) ); ?>> <?php esc_html_e( 'Only track carts of people who opted in', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'Opted in = confirmed a newsletter signup made with this plugin (form, checkout or form plugin). When off, every cart with a known email is stored (bluefox still only delivers triggered reminders to active subscribers).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-idle"><?php esc_html_e( 'Idle time', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="15" max="10080" class="small-text" id="cfbe-cart-idle" name="<?php echo esc_attr( $cfbe_name( 'cart_idle_minutes' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cfbe_values['cart_idle_minutes'] ); ?>"> <?php esc_html_e( 'minutes without changes before a cart counts as abandoned (checked every 15 minutes)', 'connector-for-bluefox-email' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-expiry"><?php esc_html_e( 'Keep carts for', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="1" max="365" class="small-text" id="cfbe-cart-expiry" name="<?php echo esc_attr( $cfbe_name( 'cart_expiry_days' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cfbe_values['cart_expiry_days'] ); ?>"> <?php esc_html_e( 'days (then expired; deleted after twice as long)', 'connector-for-bluefox-email' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Recovery coupon', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['cart_coupon'] ) ); ?>> <?php esc_html_e( 'Add a single-use coupon to the reminder', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'Created when the reminder is sent, one per cart, usable once and only with the shopper’s email address. The restore link applies it automatically. Template data: {{couponCode}}, {{couponDiscount}}, {{couponExpiry}}, {{couponMinSpend}} – wrap them in {{#if couponCode}}…{{/if}}.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-coupon-amount"><?php esc_html_e( 'Coupon discount', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="number" min="0" step="any" class="small-text" id="cfbe-cart-coupon-amount" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_amount' ) ); ?>" value="<?php echo esc_attr( (string) (float) $cfbe_values['cart_coupon_amount'] ); ?>">
				<select name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_type' ) ); ?>" aria-label="<?php esc_attr_e( 'Discount type', 'connector-for-bluefox-email' ); ?>">
					<option value="percent" <?php selected( 'percent', $cfbe_values['cart_coupon_type'] ); ?>><?php esc_html_e( '% off the cart', 'connector-for-bluefox-email' ); ?></option>
					<option value="fixed_cart" <?php selected( 'fixed_cart', $cfbe_values['cart_coupon_type'] ); ?>><?php echo esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '' ); ?> <?php esc_html_e( 'off the cart', 'connector-for-bluefox-email' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-coupon-days"><?php esc_html_e( 'Coupon valid for', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="1" max="365" class="small-text" id="cfbe-cart-coupon-days" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_days' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cfbe_values['cart_coupon_days'] ); ?>"> <?php esc_html_e( 'days after the reminder', 'connector-for-bluefox-email' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-coupon-min"><?php esc_html_e( 'Coupon minimum order', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="number" min="0" step="any" class="small-text" id="cfbe-cart-coupon-min" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_min' ) ); ?>" value="<?php echo esc_attr( (string) (float) $cfbe_values['cart_coupon_min'] ); ?>"> <?php esc_html_e( '0 = no minimum', 'connector-for-bluefox-email' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-coupon-cooldown"><?php esc_html_e( 'Coupon cooldown', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="number" min="0" max="365" class="small-text" id="cfbe-cart-coupon-cooldown" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_cooldown' ) ); ?>" value="<?php echo esc_attr( (string) (int) $cfbe_values['cart_coupon_cooldown'] ); ?>"> <?php esc_html_e( 'days', 'connector-for-bluefox-email' ); ?>
				<p class="description"><?php esc_html_e( 'Stops shoppers from abandoning carts on purpose to collect discounts: within this period an email address gets at most one recovery coupon. A still unused one is shown again instead of creating a new one; after a redeemed one the reminder goes out without a coupon. 0 = a coupon for every reminder.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-cart-coupon-prefix"><?php esc_html_e( 'Coupon code prefix', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text code" id="cfbe-cart-coupon-prefix" maxlength="12" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_prefix' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['cart_coupon_prefix'] ); ?>">
				<p>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_individual' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['cart_coupon_individual'] ) ); ?>> <?php esc_html_e( 'Cannot be combined with other coupons', 'connector-for-bluefox-email' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'cart_coupon_exclude_sale' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['cart_coupon_exclude_sale'] ) ); ?>> <?php esc_html_e( 'Not valid on sale items', 'connector-for-bluefox-email' ); ?></label>
				</p>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Recent carts', 'connector-for-bluefox-email' ); ?></h2>
<?php if ( ! $cfbe_carts ) : ?>
	<p class="description"><?php esc_html_e( 'No carts stored yet.', 'connector-for-bluefox-email' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Email', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Items', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Total', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Last updated (UTC)', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cfbe_carts as $cfbe_cart ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $cfbe_cart['email'] ); ?></td>
					<td><?php echo esc_html( (string) array_sum( array_map( 'intval', array_column( (array) $cfbe_cart['cart'], 'quantity' ) ) ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (float) $cfbe_cart['cart_total'], 2 ) . ' ' . (string) $cfbe_cart['currency'] ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_cart['status'] ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_cart['updated_at'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
