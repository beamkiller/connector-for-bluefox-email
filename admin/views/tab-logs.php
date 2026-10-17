<?php
/**
 * Logs tab view.
 *
 * @package CFBE
 *
 * @var array[] $cfbe_entries Log entries, newest first (time, level, message).
 */

defined( 'ABSPATH' ) || exit;
?>
<p>
	<?php esc_html_e( 'Recent warnings and errors of this plugin (all messages when WooCommerce is not active). Email addresses are masked and secrets are never logged.', 'connector-for-bluefox-email' ); ?>
	<?php if ( function_exists( 'wc_get_logger' ) ) : ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=cfbe' ) ); ?>"><?php esc_html_e( 'Full log in WooCommerce → Status → Logs', 'connector-for-bluefox-email' ); ?></a>
	<?php endif; ?>
</p>
<?php if ( ! $cfbe_entries ) : ?>
	<p><em><?php esc_html_e( 'No log entries yet.', 'connector-for-bluefox-email' ); ?></em></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Time', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Level', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Message', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cfbe_entries as $cfbe_entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $cfbe_entry['time'] ) ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_entry['level'] ); ?></td>
					<td><code><?php echo esc_html( (string) $cfbe_entry['message'] ); ?></code></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="cfbe_clear_log">
		<?php wp_nonce_field( 'cfbe_clear_log' ); ?>
		<?php submit_button( __( 'Clear log', 'connector-for-bluefox-email' ), 'secondary' ); ?>
	</form>
<?php endif; ?>
