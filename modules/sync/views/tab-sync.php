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
<p><?php esc_html_e( 'Runs in the background in batches of 50, one batch per minute, to stay within the bluefox API limits. You can leave this page.', 'connector-for-bluefox-email' ); ?></p>
<p id="cfbe-sync-progress" aria-live="polite" data-running="<?php echo 'running' === $cfbe_progress['status'] ? '1' : '0'; ?>"><?php echo esc_html( Sync_Tab::progress_text( $cfbe_progress ) ); ?></p>
<p>
	<button type="button" class="button button-primary" id="cfbe-sync-start" <?php disabled( ! $cfbe_configured || 'running' === $cfbe_progress['status'] ); ?>><?php esc_html_e( 'Sync all existing users/customers now', 'connector-for-bluefox-email' ); ?></button>
	<button type="button" class="button" id="cfbe-sync-cancel" <?php disabled( 'running' !== $cfbe_progress['status'] ); ?>><?php esc_html_e( 'Cancel', 'connector-for-bluefox-email' ); ?></button>
</p>
<p class="description">
	<?php
	/* translators: %s: WP-CLI command */
	echo esc_html( sprintf( __( 'Also available on the command line: %s', 'connector-for-bluefox-email' ), 'wp cfbe sync [--dry-run] [--limit=<n>]' ) );
	?>
</p>
<p class="description"><?php esc_html_e( 'Deleting a WordPress user does not delete the bluefox contact, and changing an account email does not rename it. Remove such contacts in bluefox if needed.', 'connector-for-bluefox-email' ); ?></p>
