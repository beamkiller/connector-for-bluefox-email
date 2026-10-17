<?php
/**
 * "Email delivery" tab view.
 *
 * @package CFBE
 *
 * @var array  $cfbe_values      `mailer` settings.
 * @var array  $cfbe_emails      Cached transactional emails (id => name).
 * @var bool   $cfbe_connected   Whether the bluefox connection is set.
 * @var bool   $cfbe_configured  Whether the connection and the transactional email are set.
 * @var bool   $cfbe_active      Whether wp_mail() is routed through bluefox.
 * @var array  $cfbe_log         Delivery log, newest first.
 * @var string $cfbe_option      Option name.
 * @var string $cfbe_admin_email Admin email (test default).
 */

defined( 'ABSPATH' ) || exit;

$cfbe_name = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
$cfbe_id   = (string) $cfbe_values['transactional_id'];
?>
<?php if ( ! $cfbe_connected ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Set up the bluefox connection on the General tab first.', 'connector-for-bluefox-email' ); ?></p></div>
<?php elseif ( $cfbe_active ) : ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'WordPress emails are sent through bluefox.', 'connector-for-bluefox-email' ); ?></p></div>
<?php elseif ( ! empty( $cfbe_values['enabled'] ) ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Sending through bluefox is enabled, but no transactional email is selected: WordPress sends emails the normal way.', 'connector-for-bluefox-email' ); ?></p></div>
<?php else : ?>
	<div class="notice notice-info inline"><p><?php esc_html_e( 'Not live yet: WordPress sends emails the normal way. Set up the template, send a test email, then enable sending.', 'connector-for-bluefox-email' ); ?></p></div>
<?php endif; ?>

<p class="description">
	<?php esc_html_e( 'bluefox can only send saved transactional emails, so every WordPress email (password resets, notifications, WooCommerce emails, …) is sent with one generic template whose subject and content are filled in at send time. One API call is made per recipient (To, Cc and Bcc).', 'connector-for-bluefox-email' ); ?>
	<?php esc_html_e( 'The sender name, address and reply-to always come from the sender identity of that template: "From" and "Reply-To" headers set by WordPress or other plugins are ignored.', 'connector-for-bluefox-email' ); ?>
</p>

<h2><?php esc_html_e( '1. Template', 'connector-for-bluefox-email' ); ?></h2>
<p class="description"><?php esc_html_e( 'Create the generic "WordPress mail" transactional email in your bluefox project (subject {{{subject}}}, content {{{html}}}), or select one you made yourself with the same placeholders.', 'connector-for-bluefox-email' ); ?></p>
<p class="cfbe-mailer-create">
	<button type="button" class="button" id="cfbe-mailer-identities" <?php disabled( ! $cfbe_connected ); ?>><?php esc_html_e( 'Create the WordPress mail template in bluefox', 'connector-for-bluefox-email' ); ?></button>
	<span id="cfbe-mailer-identity-wrap" hidden>
		<label for="cfbe-mailer-identity" class="screen-reader-text"><?php esc_html_e( 'Sender identity', 'connector-for-bluefox-email' ); ?></label>
		<select id="cfbe-mailer-identity"></select>
		<button type="button" class="button button-primary" id="cfbe-mailer-create"><?php esc_html_e( 'Create template', 'connector-for-bluefox-email' ); ?></button>
	</span>
	<span class="cfbe-mailer-status" id="cfbe-mailer-create-status" role="status" aria-live="polite"></span>
</p>

<h2><?php esc_html_e( '2. Settings', 'connector-for-bluefox-email' ); ?></h2>
<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-mailer-email"><?php esc_html_e( 'Transactional email', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-mailer-email" name="<?php echo esc_attr( $cfbe_name( 'transactional_id' ) ); ?>">
					<option value=""><?php esc_html_e( '— Select —', 'connector-for-bluefox-email' ); ?></option>
					<?php if ( '' !== $cfbe_id && ! isset( $cfbe_emails[ $cfbe_id ] ) ) : ?>
						<option value="<?php echo esc_attr( $cfbe_id ); ?>" selected><?php echo esc_html( $cfbe_id ); ?></option>
					<?php endif; ?>
					<?php foreach ( $cfbe_emails as $cfbe_email_id => $cfbe_email_name ) : ?>
						<option value="<?php echo esc_attr( (string) $cfbe_email_id ); ?>" <?php selected( $cfbe_id, (string) $cfbe_email_id ); ?>><?php echo esc_html( $cfbe_email_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="cfbe-mailer-refresh" <?php disabled( ! $cfbe_connected ); ?>><?php esc_html_e( 'Refresh list', 'connector-for-bluefox-email' ); ?></button>
				<span class="cfbe-mailer-status" id="cfbe-mailer-refresh-status" role="status" aria-live="polite"></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'If bluefox fails', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<fieldset>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'failure_mode' ) ); ?>" value="fallback" <?php checked( $cfbe_values['failure_mode'], 'fallback' ); ?>> <?php esc_html_e( 'Let WordPress send the email the normal way (recommended)', 'connector-for-bluefox-email' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'failure_mode' ) ); ?>" value="fail" <?php checked( $cfbe_values['failure_mode'], 'fail' ); ?>> <?php esc_html_e( 'Do not send it (wp_mail() returns false)', 'connector-for-bluefox-email' ); ?></label>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-mailer-max"><?php esc_html_e( 'Attachment size limit', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="number" min="1" max="<?php echo esc_attr( (string) CFBE\Mailer\Options::MAX_ATTACHMENT_MB ); ?>" class="small-text" id="cfbe-mailer-max" name="<?php echo esc_attr( $cfbe_name( 'max_attachment_mb' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['max_attachment_mb'] ); ?>"> MB
				<p class="description"><?php esc_html_e( 'Total size of the attachments of one email. Larger emails are handled as a failure (see above).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Send emails through bluefox', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'enabled' ) ); ?>" value="1" <?php checked( ! empty( $cfbe_values['enabled'] ) ); ?>> <?php esc_html_e( 'Enabled', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'Send a test email first. Emails are sent while the page loads (wp_mail() is synchronous), each recipient with its own API call.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( '3. Test', 'connector-for-bluefox-email' ); ?></h2>
<p>
	<label for="cfbe-mailer-test-to"><?php esc_html_e( 'Send a test email to', 'connector-for-bluefox-email' ); ?></label>
	<input type="email" class="regular-text" id="cfbe-mailer-test-to" value="<?php echo esc_attr( $cfbe_admin_email ); ?>">
	<button type="button" class="button" id="cfbe-mailer-test" <?php disabled( ! $cfbe_configured ); ?>><?php esc_html_e( 'Send test email', 'connector-for-bluefox-email' ); ?></button>
	<span class="cfbe-mailer-status" id="cfbe-mailer-test-status" role="status" aria-live="polite"></span>
</p>
<?php if ( ! $cfbe_configured ) : ?>
	<p class="description"><?php esc_html_e( 'Save a transactional email above to send a test.', 'connector-for-bluefox-email' ); ?></p>
<?php endif; ?>

<h2><?php esc_html_e( 'Recent deliveries', 'connector-for-bluefox-email' ); ?></h2>
<?php if ( ! $cfbe_log ) : ?>
	<p><em><?php esc_html_e( 'No emails sent through bluefox yet.', 'connector-for-bluefox-email' ); ?></em></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Time', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Recipient', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Subject', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Result', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$cfbe_results = array(
				'sent'     => __( 'Sent', 'connector-for-bluefox-email' ),
				'test'     => __( 'Test sent', 'connector-for-bluefox-email' ),
				'failed'   => __( 'Failed', 'connector-for-bluefox-email' ),
				'fallback' => __( 'Failed, sent by WordPress', 'connector-for-bluefox-email' ),
			);
			foreach ( $cfbe_log as $cfbe_entry ) :
				$cfbe_result = (string) ( $cfbe_entry['result'] ?? '' );
				?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $cfbe_entry['time'] ?? 0 ) ) ); ?></td>
					<td><?php echo esc_html( (string) ( $cfbe_entry['recipient'] ?? '' ) ); ?></td>
					<td><?php echo esc_html( (string) ( $cfbe_entry['subject'] ?? '' ) ); ?></td>
					<td>
						<?php echo esc_html( $cfbe_results[ $cfbe_result ] ?? $cfbe_result ); ?>
						<?php if ( ! empty( $cfbe_entry['note'] ) ) : ?>
							<br><small><?php echo esc_html( (string) $cfbe_entry['note'] ); ?></small>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
