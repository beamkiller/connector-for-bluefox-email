<?php
/**
 * General tab view: connection + module switches.
 *
 * @package CFBE
 *
 * @var array  $cfbe_values       Stored api settings.
 * @var bool   $cfbe_key_constant API key defined in wp-config.php.
 * @var array  $cfbe_lists        Cached lists id => name.
 * @var array  $cfbe_modules      Module definitions.
 * @var array  $cfbe_states       Module switches.
 */

use CFBE\Modules;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

$cfbe_option  = Settings::option_name( 'api' );
$cfbe_has_key = '' !== (string) $cfbe_values['api_key'];
if ( '' !== $cfbe_values['list_id'] && ! isset( $cfbe_lists[ $cfbe_values['list_id'] ] ) ) {
	$cfbe_lists[ $cfbe_values['list_id'] ] = $cfbe_values['list_id'];
}
?>
<form method="post" action="options.php" id="cfbe-api-form">
	<?php settings_fields( $cfbe_option ); ?>
	<?php // The automation webhook secret is shown on the Webhooks tab; keep it on save (an empty value would regenerate it). ?>
	<input type="hidden" name="<?php echo esc_attr( $cfbe_option ); ?>[webhook_secret]" value="<?php echo esc_attr( $cfbe_values['webhook_secret'] ); ?>">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-project-id"><?php esc_html_e( 'Project ID', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text code" id="cfbe-project-id" name="<?php echo esc_attr( $cfbe_option ); ?>[project_id]" value="<?php echo esc_attr( $cfbe_values['project_id'] ); ?>" autocomplete="off">
				<p class="description"><?php esc_html_e( 'bluefox: Project Settings → Integrations', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-api-key"><?php esc_html_e( 'API key', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<?php if ( $cfbe_key_constant ) : ?>
					<input type="password" class="regular-text" id="cfbe-api-key" value="" placeholder="<?php esc_attr_e( 'Defined in wp-config.php', 'connector-for-bluefox-email' ); ?>" disabled>
					<p class="description"><?php esc_html_e( 'The CFBE_BLUEFOX_API_KEY constant is in use.', 'connector-for-bluefox-email' ); ?></p>
				<?php else : ?>
					<input type="password" class="regular-text" id="cfbe-api-key" name="<?php echo esc_attr( $cfbe_option ); ?>[api_key]" value="" autocomplete="new-password" placeholder="<?php echo $cfbe_has_key ? esc_attr__( '•••••••• (saved – leave empty to keep it)', 'connector-for-bluefox-email' ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'bluefox: Project Settings → API Keys & Domain Whitelist. Recommended: define it in wp-config.php: define( \'CFBE_BLUEFOX_API_KEY\', \'…\' );', 'connector-for-bluefox-email' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Connection', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<button type="button" class="button" id="cfbe-test-connection"><?php esc_html_e( 'Test connection', 'connector-for-bluefox-email' ); ?></button>
				<span class="cfbe-result" id="cfbe-test-result" aria-live="polite"></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-list-id"><?php esc_html_e( 'Default subscriber list', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-list-id" name="<?php echo esc_attr( $cfbe_option ); ?>[list_id]">
					<option value=""><?php esc_html_e( '— Select a list —', 'connector-for-bluefox-email' ); ?></option>
					<?php foreach ( $cfbe_lists as $cfbe_id => $cfbe_name ) : ?>
						<option value="<?php echo esc_attr( $cfbe_id ); ?>" <?php selected( $cfbe_values['list_id'], $cfbe_id ); ?>><?php echo esc_html( $cfbe_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" id="cfbe-load-lists"><?php esc_html_e( 'Load lists', 'connector-for-bluefox-email' ); ?></button>
				<span class="cfbe-result" id="cfbe-lists-result" aria-live="polite"></span>
				<p class="description"><?php esc_html_e( 'Used by forms and the checkout unless they choose another list. Turn on double opt-in for the list in bluefox, with a confirmation email in your language.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>
	<h2><?php esc_html_e( 'Features', 'connector-for-bluefox-email' ); ?></h2>
	<p><?php esc_html_e( 'Switch on only what you use. Each feature gets its own settings tab once it is on.', 'connector-for-bluefox-email' ); ?></p>
	<table class="form-table cfbe-modules" role="presentation">
		<?php $cfbe_integrations_done = false; ?>
		<?php foreach ( $cfbe_modules as $cfbe_slug => $cfbe_module ) : ?>
			<?php
			if ( 'integrations' === ( $cfbe_module['group'] ?? '' ) ) {
				if ( ! $cfbe_integrations_done ) {
					$cfbe_integrations_done = true;
					include __DIR__ . '/modules-integrations-row.php';
				}
				continue;
			}
			$cfbe_available = Modules::available( $cfbe_slug );
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $cfbe_module['label'] ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( Modules::OPTION ); ?>[<?php echo esc_attr( $cfbe_slug ); ?>]" value="1" <?php checked( ! empty( $cfbe_states[ $cfbe_slug ] ) ); ?> <?php disabled( ! $cfbe_available ); ?>>
						<?php echo esc_html( $cfbe_module['description'] ); ?>
					</label>
					<?php if ( ! $cfbe_available ) : ?>
						<?php if ( ! empty( $cfbe_states[ $cfbe_slug ] ) ) : ?>
							<input type="hidden" name="<?php echo esc_attr( Modules::OPTION ); ?>[<?php echo esc_attr( $cfbe_slug ); ?>]" value="1">
						<?php endif; ?>
						<p class="description"><?php echo esc_html( empty( $cfbe_module['woocommerce'] ) ? __( 'Requires WordPress 6.9 or later.', 'connector-for-bluefox-email' ) : __( 'Requires WooCommerce.', 'connector-for-bluefox-email' ) ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php submit_button(); ?>
</form>
