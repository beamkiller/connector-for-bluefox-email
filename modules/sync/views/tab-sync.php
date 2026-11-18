<?php
/**
 * Contact sync tab view.
 *
 * @package CFBE
 *
 * @var array $cfbe_values     `sync` settings (Fields::settings()).
 * @var bool  $cfbe_woo        Whether WooCommerce is active.
 * @var array $cfbe_progress   Backfill progress.
 * @var bool  $cfbe_configured Whether the bluefox connection is configured.
 */

use CFBE\Settings;
use CFBE\Sync\Fields;
use CFBE\Sync\Sync_Tab;

defined( 'ABSPATH' ) || exit;

$cfbe_option = Settings::option_name( Fields::AREA );
$cfbe_name   = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
$cfbe_labels = Fields::labels();
?>
<p class="description"><?php esc_html_e( 'Keeps bluefox contacts up to date with WordPress user and WooCommerce customer data, so you can build segments in bluefox (has tag, field conditions). Changes are synced in the background a few seconds after they happen.', 'connector-for-bluefox-email' ); ?></p>
<?php if ( ! $cfbe_configured ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Connect to bluefox first (General tab).', 'connector-for-bluefox-email' ); ?></p></div>
<?php endif; ?>

<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Which contacts', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<fieldset>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'scope' ) ); ?>" value="subscribers" <?php checked( $cfbe_values['scope'], 'subscribers' ); ?>> <?php esc_html_e( 'Only people who are already bluefox contacts (recommended)', 'connector-for-bluefox-email' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'scope' ) ); ?>" value="all" <?php checked( $cfbe_values['scope'], 'all' ); ?>> <?php esc_html_e( 'All users and customers (create missing contacts)', 'connector-for-bluefox-email' ); ?></label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Creating a contact does not subscribe it to any list: it will not receive campaigns unless it joins a list.', 'connector-for-bluefox-email' ); ?></p>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'GDPR: with “All users and customers” you transfer personal data of people who did not sign up for your newsletter to bluefox. Only use it if you have a legal basis (e.g. legitimate interest or consent), have documented it and mention bluefox.email as a processor in your privacy policy. You are responsible for this decision.', 'connector-for-bluefox-email' ); ?></p></div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Automatic sync', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'auto_sync' ) ); ?>" value="1" <?php checked( $cfbe_values['auto_sync'] ); ?>> <?php esc_html_e( 'Sync when a user registers or updates the profile, an order is paid, completed or refunded, and when a signup is confirmed', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Tags', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'tag_wp_user' ) ); ?>" value="1" <?php checked( $cfbe_values['tag_wp_user'] ); ?>>
					<?php
					/* translators: %s: tag name */
					echo esc_html( sprintf( __( 'Add the %s tag to registered users', 'connector-for-bluefox-email' ), Fields::TAG_WP_USER ) );
					?>
				</label><br>
				<?php if ( $cfbe_woo ) : ?>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'tag_customer' ) ); ?>" value="1" <?php checked( $cfbe_values['tag_customer'] ); ?>>
					<?php
					/* translators: %s: tag name */
					echo esc_html( sprintf( __( 'Add the %s tag to people with at least one paid order', 'connector-for-bluefox-email' ), Fields::TAG_CUSTOMER ) );
					?>
				</label>
				<?php else : ?>
				<input type="hidden" name="<?php echo esc_attr( $cfbe_name( 'tag_customer' ) ); ?>" value="<?php echo $cfbe_values['tag_customer'] ? '1' : ''; ?>">
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Tags are added to the existing ones; nothing is removed.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<?php if ( $cfbe_woo ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Purchased product tags', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'product_tags' ) ); ?>" value="1" <?php checked( $cfbe_values['product_tags'] ); ?>>
					<?php esc_html_e( 'Tag contacts who bought one of the products below', 'connector-for-bluefox-email' ); ?>
				</label>
				<p>
					<label for="cfbe-sync-products" class="screen-reader-text"><?php esc_html_e( 'Products', 'connector-for-bluefox-email' ); ?></label>
					<select id="cfbe-sync-products" class="wc-product-search" multiple="multiple" style="width: 100%; max-width: 40em;" name="<?php echo esc_attr( $cfbe_name( 'product_tag_ids' ) ); ?>[]" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'connector-for-bluefox-email' ); ?>" data-action="woocommerce_json_search_products" data-minimum_input_length="2">
						<?php foreach ( $cfbe_products as $cfbe_product_id => $cfbe_product_name ) : ?>
							<option value="<?php echo esc_attr( (string) $cfbe_product_id ); ?>" selected="selected"><?php echo esc_html( $cfbe_product_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="cfbe-sync-tag-prefix"><?php esc_html_e( 'Tag prefix', 'connector-for-bluefox-email' ); ?></label>
					<input type="text" id="cfbe-sync-tag-prefix" class="regular-text code" maxlength="40" pattern="[a-z0-9_\-]+" name="<?php echo esc_attr( $cfbe_name( 'product_tag_prefix' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['product_tag_prefix'] ); ?>">
				</p>
				<p class="description">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: example tag, 2: maximum number of products */
							__( 'Each chosen product becomes a tag such as %1$s on everyone with a paid order of it (variations count as their product), so you can build segments and campaigns for its buyers. Type at least two letters to search; up to %2$d products. Tags are only added, never removed. To tag earlier orders, run “Sync all existing users/customers now” below after saving.', 'connector-for-bluefox-email' ),
							$cfbe_values['product_tag_prefix'] . 'example-product',
							\CFBE\Sync\Fields::PRODUCT_TAG_MAX
						)
					);
					?>
				</p>
			</td>
		</tr>
		<?php else : ?>
		<tr class="hidden">
			<td colspan="2">
				<input type="hidden" name="<?php echo esc_attr( $cfbe_name( 'product_tags' ) ); ?>" value="<?php echo $cfbe_values['product_tags'] ? '1' : ''; ?>">
				<input type="hidden" name="<?php echo esc_attr( $cfbe_name( 'product_tag_prefix' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['product_tag_prefix'] ); ?>">
				<?php foreach ( $cfbe_values['product_tag_ids'] as $cfbe_product_id ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $cfbe_name( 'product_tag_ids' ) ); ?>[]" value="<?php echo esc_attr( (string) (int) $cfbe_product_id ); ?>">
				<?php endforeach; ?>
			</td>
		</tr>
		<?php endif; ?>
	</table>

	<h2><?php esc_html_e( 'Fields', 'connector-for-bluefox-email' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Custom contact fields written to bluefox. The field must exist in bluefox with the given name, otherwise bluefox silently ignores the value. Allowed characters: A–Z, a–z, 0–9 and _.', 'connector-for-bluefox-email' ); ?></p>
	<table class="widefat striped cfbe-sync-fields">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Sync', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Data', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'bluefox field name', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Type', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( Fields::DEFINITIONS as $cfbe_key => $cfbe_def ) :
			if ( $cfbe_def['woo'] && ! $cfbe_woo ) {
				continue;
			}
			$cfbe_row    = $cfbe_values['fields'][ $cfbe_key ];
			$cfbe_prefix = $cfbe_name( 'fields' ) . '[' . $cfbe_key . ']';
			$cfbe_id     = 'cfbe-sync-' . strtolower( $cfbe_key );
			?>
			<tr>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $cfbe_prefix . '[present]' ); ?>" value="1">
					<input type="checkbox" id="<?php echo esc_attr( $cfbe_id ); ?>" name="<?php echo esc_attr( $cfbe_prefix . '[enabled]' ); ?>" value="1" <?php checked( $cfbe_row['enabled'] ); ?>>
				</td>
				<td><label for="<?php echo esc_attr( $cfbe_id ); ?>"><?php echo esc_html( $cfbe_labels[ $cfbe_key ] ?? $cfbe_key ); ?></label></td>
				<td>
					<?php if ( Fields::is_fixed( $cfbe_key ) ) : ?>
						<code><?php echo esc_html( $cfbe_key ); ?></code>
						<p class="description"><?php esc_html_e( 'Fixed name, used in templates as {{contact.name}}. An existing name in bluefox is never cleared.', 'connector-for-bluefox-email' ); ?></p>
					<?php else : ?>
						<input type="text" class="regular-text code" maxlength="64" pattern="[A-Za-z0-9_]+" name="<?php echo esc_attr( $cfbe_prefix . '[name]' ); ?>" value="<?php echo esc_attr( $cfbe_row['name'] ); ?>" aria-label="<?php echo esc_attr( $cfbe_labels[ $cfbe_key ] ?? $cfbe_key ); ?>">
					<?php endif; ?>
				</td>
				<td><code><?php echo esc_html( $cfbe_def['type'] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( ! $cfbe_woo ) : ?>
		<p class="description"><?php esc_html_e( 'Order and customer fields become available when WooCommerce is active.', 'connector-for-bluefox-email' ); ?></p>
	<?php endif; ?>
	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Fields in bluefox', 'connector-for-bluefox-email' ); ?></h2>
<p><?php esc_html_e( 'Creates the enabled fields above that do not exist in your bluefox project yet. Save the settings first.', 'connector-for-bluefox-email' ); ?></p>
<p><button type="button" class="button" id="cfbe-sync-fields" <?php disabled( ! $cfbe_configured ); ?>><?php esc_html_e( 'Create missing fields in bluefox', 'connector-for-bluefox-email' ); ?></button> <span class="cfbe-result" id="cfbe-sync-fields-result" aria-live="polite"></span></p>

<h2><?php esc_html_e( 'Sync existing users and customers', 'connector-for-bluefox-email' ); ?></h2>
<p><?php esc_html_e( 'Runs in the background in short batches (about 20 seconds each), with pauses that keep it within the bluefox API limits. Every contact is saved as soon as it is done, so a stopped batch goes on where it was. You can leave this page; while it is open, it also keeps a stalled sync going.', 'connector-for-bluefox-email' ); ?></p>
<?php $cfbe_status = Sync_Tab::status_data( $cfbe_progress ); ?>
<div id="cfbe-sync-live" data-running="<?php echo $cfbe_status['running'] ? '1' : '0'; ?>">
	<p class="cfbe-sync-bar">
		<progress id="cfbe-sync-bar" max="100" value="<?php echo esc_attr( (string) $cfbe_status['percent'] ); ?>" style="width: 100%; max-width: 40em; height: 18px; vertical-align: middle;"><?php echo esc_html( $cfbe_status['percent'] . '%' ); ?></progress>
		<strong id="cfbe-sync-percent"><?php echo esc_html( $cfbe_status['percent'] . '%' ); ?></strong>
	</p>
	<p id="cfbe-sync-progress" aria-live="polite"><?php echo esc_html( $cfbe_status['message'] ); ?></p>
	<p id="cfbe-sync-stats" class="description"></p>
	<div id="cfbe-sync-health" class="notice notice-warning inline" <?php echo '' === $cfbe_status['health']['text'] ? 'hidden' : ''; ?>><p><?php echo esc_html( $cfbe_status['health']['text'] ); ?></p></div>
</div>
<p>
	<button type="button" class="button button-primary" id="cfbe-sync-start" <?php disabled( ! $cfbe_configured || $cfbe_status['running'] ); ?>><?php esc_html_e( 'Sync all existing users/customers now', 'connector-for-bluefox-email' ); ?></button>
	<button type="button" class="button" id="cfbe-sync-resume" <?php echo $cfbe_status['can_resume'] ? '' : 'hidden'; ?>><?php esc_html_e( 'Resume', 'connector-for-bluefox-email' ); ?></button>
	<button type="button" class="button" id="cfbe-sync-cancel" <?php disabled( ! $cfbe_status['running'] && 'paused' !== $cfbe_status['status'] ); ?>><?php esc_html_e( 'Cancel', 'connector-for-bluefox-email' ); ?></button>
</p>
<details id="cfbe-sync-errors" <?php echo $cfbe_status['errors'] ? '' : 'hidden'; ?>>
	<summary><?php esc_html_e( 'Last errors', 'connector-for-bluefox-email' ); ?> (<span id="cfbe-sync-errors-count"><?php echo esc_html( (string) count( $cfbe_status['errors'] ) ); ?></span>)</summary>
	<table class="widefat striped" style="max-width: 60em;">
		<thead><tr><th><?php esc_html_e( 'Time', 'connector-for-bluefox-email' ); ?></th><th><?php esc_html_e( 'Contact', 'connector-for-bluefox-email' ); ?></th><th><?php esc_html_e( 'bluefox response', 'connector-for-bluefox-email' ); ?></th></tr></thead>
		<tbody id="cfbe-sync-errors-body">
			<?php foreach ( $cfbe_status['errors'] as $cfbe_error ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $cfbe_error['time'] ) ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_error['email'] ); ?></td>
					<td><?php echo esc_html( ( $cfbe_error['status'] ? 'HTTP ' . $cfbe_error['status'] . ': ' : '' ) . $cfbe_error['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</details>
<p class="description">
	<?php
	/* translators: %s: WP-CLI command */
	echo esc_html( sprintf( __( 'Also available on the command line: %s', 'connector-for-bluefox-email' ), 'wp cfbe sync [--dry-run] [--limit=<n>]' ) );
	?>
</p>
<p class="description"><?php esc_html_e( 'Deleting a WordPress user does not delete the bluefox contact, and changing an account email does not rename it. Remove such contacts in bluefox if needed.', 'connector-for-bluefox-email' ); ?></p>
