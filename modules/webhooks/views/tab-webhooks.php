<?php
/**
 * Webhooks tab view.
 *
 * @package CFBE
 *
 * @var array    $cfbe_values       Stored `webhooks` settings.
 * @var string   $cfbe_option       Option name of the area.
 * @var string   $cfbe_webhook_url  Endpoint URL.
 * @var string   $cfbe_secret       Legacy automation secret.
 * @var array    $cfbe_events       Event => label.
 * @var string[] $cfbe_test_types   Test event types.
 * @var array[]  $cfbe_log          Last received events.
 * @var bool     $cfbe_configured   bluefox connection configured.
 * @var bool     $cfbe_key_constant API key comes from wp-config.php.
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'Endpoint', 'connector-for-bluefox-email' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="cfbe-webhook-url"><?php esc_html_e( 'Webhook URL', 'connector-for-bluefox-email' ); ?></label></th>
		<td>
			<input type="text" class="large-text code" id="cfbe-webhook-url" value="<?php echo esc_attr( $cfbe_webhook_url ); ?>" readonly>
			<button type="button" class="button cfbe-copy" data-target="cfbe-webhook-url"><?php esc_html_e( 'Copy', 'connector-for-bluefox-email' ); ?></button>
			<p class="description"><?php esc_html_e( 'bluefox posts events here. The site must be reachable from the internet (not a local or password-protected site).', 'connector-for-bluefox-email' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Authentication', 'connector-for-bluefox-email' ); ?></th>
		<td>
			<p><strong><?php esc_html_e( 'Project webhook (recommended):', 'connector-for-bluefox-email' ); ?></strong>
				<?php esc_html_e( 'bluefox sends one of your project API keys as “Authorization: Bearer …”. It is accepted when it equals the API key configured on the General tab. Use the button below to register the webhook with that key.', 'connector-for-bluefox-email' ); ?></p>
			<p><strong><?php esc_html_e( 'Automation webhook step (legacy):', 'connector-for-bluefox-email' ); ?></strong>
				<?php esc_html_e( 'a Webhook step in a bluefox “Contact added” automation, POST to the URL above with “Pass Contact Data” on and the header X-CFBE-Secret set to the secret below. It only confirms signups; it carries no other events.', 'connector-for-bluefox-email' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Project webhook in bluefox', 'connector-for-bluefox-email' ); ?></h2>
<form method="post" action="options.php" id="cfbe-webhooks-form">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Events', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<fieldset id="cfbe-webhook-events">
					<legend class="screen-reader-text"><?php esc_html_e( 'Events', 'connector-for-bluefox-email' ); ?></legend>
					<?php foreach ( $cfbe_events as $cfbe_event => $cfbe_label ) : ?>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $cfbe_option ); ?>[event_<?php echo esc_attr( $cfbe_event ); ?>]" value="1" data-event="<?php echo esc_attr( $cfbe_event ); ?>" <?php checked( ! empty( $cfbe_values[ 'event_' . $cfbe_event ] ) ); ?>>
							<?php echo esc_html( $cfbe_label ); ?>
						</label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Subscriptions are needed for confirmations and status tracking. Opens and clicks can mean a lot of requests on a busy list.', 'connector-for-bluefox-email' ); ?></p>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'bluefox', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<?php if ( ! $cfbe_configured ) : ?>
					<p class="description"><?php esc_html_e( 'Connect to bluefox first (General tab: Project ID and API key).', 'connector-for-bluefox-email' ); ?></p>
				<?php else : ?>
					<button type="button" class="button button-primary" id="cfbe-webhook-register"><?php esc_html_e( 'Register webhook in bluefox', 'connector-for-bluefox-email' ); ?></button>
					<label class="screen-reader-text" for="cfbe-webhook-test-type"><?php esc_html_e( 'Test event type', 'connector-for-bluefox-email' ); ?></label>
					<select id="cfbe-webhook-test-type">
						<?php foreach ( $cfbe_test_types as $cfbe_type ) : ?>
							<option value="<?php echo esc_attr( $cfbe_type ); ?>"><?php echo esc_html( $cfbe_type ); ?></option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="cfbe-webhook-test"><?php esc_html_e( 'Send test event', 'connector-for-bluefox-email' ); ?></button>
					<button type="button" class="button-link" id="cfbe-webhook-refresh"><?php esc_html_e( 'Check status', 'connector-for-bluefox-email' ); ?></button>
					<p class="cfbe-result" id="cfbe-webhook-result" aria-live="polite"></p>
					<p class="description"><?php esc_html_e( 'Registering replaces the project webhook in bluefox (a project has one) with this URL, the selected events and your API key as the secret.', 'connector-for-bluefox-email' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Users list', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $cfbe_option ); ?>[users_column]" value="1" <?php checked( ! empty( $cfbe_values['users_column'] ) ); ?>>
					<?php esc_html_e( 'Show the bluefox status (subscription, bounce, complaint) in a column of the Users list', 'connector-for-bluefox-email' ); ?>
				</label>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Automation webhook secret (legacy)', 'connector-for-bluefox-email' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="cfbe-webhook-secret"><?php esc_html_e( 'Webhook secret', 'connector-for-bluefox-email' ); ?></label></th>
		<td>
			<input type="text" class="regular-text code" id="cfbe-webhook-secret" value="<?php echo esc_attr( $cfbe_secret ); ?>" readonly>
			<button type="button" class="button cfbe-copy" data-target="cfbe-webhook-secret"><?php esc_html_e( 'Copy', 'connector-for-bluefox-email' ); ?></button>
			<button type="button" class="button" id="cfbe-regen-secret"><?php esc_html_e( 'New secret', 'connector-for-bluefox-email' ); ?></button>
			<p class="description"><?php esc_html_e( 'Add it as the X-CFBE-Secret header in the Webhook step of the bluefox “Contact added” automation.', 'connector-for-bluefox-email' ); ?></p>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Last received events', 'connector-for-bluefox-email' ); ?></h2>
<?php if ( ! $cfbe_log ) : ?>
	<p><?php esc_html_e( 'No events received yet.', 'connector-for-bluefox-email' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Time', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Event', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Email', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cfbe_log as $cfbe_entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $cfbe_entry['time'] ?? 0 ) ) ); ?></td>
					<td><code><?php echo esc_html( (string) ( $cfbe_entry['type'] ?? '' ) ); ?></code></td>
					<td><?php echo esc_html( (string) ( $cfbe_entry['email'] ?? '' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
