<?php
/**
 * Emails tab view.
 *
 * @package CFBE
 *
 * @var array  $cfbe_values       `emails` settings.
 * @var string $cfbe_thankyou_url Effective thank-you URL.
 * @var string $cfbe_prefs_url    Effective preferences page URL.
 */

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

$cfbe_option = Settings::option_name( 'emails' );
?>
<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-thankyou-url"><?php esc_html_e( 'Thank-you page URL', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="url" class="large-text" id="cfbe-thankyou-url" name="<?php echo esc_attr( $cfbe_option ); ?>[thankyou_url]" value="<?php echo esc_attr( $cfbe_values['thankyou_url'] ); ?>" placeholder="<?php echo esc_attr( $cfbe_thankyou_url ); ?>">
				<p class="description"><?php esc_html_e( 'Visitors land here after confirming. Set the same URL in the “Redirect link” field of the bluefox list’s double opt-in settings.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Thank-you page texts – [cfbe_thankyou]', 'connector-for-bluefox-email' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Add the [cfbe_thankyou] shortcode to the thank-you page (Shortcode block or your page builder’s shortcode element). Options: show_title="0" (if the design has its own heading), tag="h2".', 'connector-for-bluefox-email' ); ?></p>
	<table class="form-table" role="presentation">
		<?php
		foreach (
			array(
				'confirmed' => __( 'Confirmation successful', 'connector-for-bluefox-email' ),
				'expired'   => __( 'Expired link (?cfbe=expired)', 'connector-for-bluefox-email' ),
				'error'     => __( 'Error (?cfbe=error)', 'connector-for-bluefox-email' ),
			) as $cfbe_state => $cfbe_label
			) :
			$cfbe_title_key = 'thankyou_' . $cfbe_state . '_title';
			$cfbe_text_key  = 'thankyou_' . $cfbe_state . '_text';
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $cfbe_label ); ?></th>
				<td>
					<input type="text" class="large-text" name="<?php echo esc_attr( $cfbe_option . '[' . $cfbe_title_key . ']' ); ?>" value="<?php echo esc_attr( $cfbe_values[ $cfbe_title_key ] ); ?>" aria-label="<?php echo esc_attr( $cfbe_label . ' – ' . __( 'title', 'connector-for-bluefox-email' ) ); ?>">
					<textarea class="large-text" rows="3" name="<?php echo esc_attr( $cfbe_option . '[' . $cfbe_text_key . ']' ); ?>" aria-label="<?php echo esc_attr( $cfbe_label . ' – ' . __( 'text', 'connector-for-bluefox-email' ) ); ?>"><?php echo esc_textarea( $cfbe_values[ $cfbe_text_key ] ); ?></textarea>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>

	<h2><?php esc_html_e( 'Newsletter preferences page – [cfbe_preferences]', 'connector-for-bluefox-email' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Your own unsubscribe / pause page. Add the [cfbe_preferences] shortcode to a page and set its URL in the bluefox project (Project Settings → Custom subscription preferences URL). The “Unsubscribe” and “Pause” links in your newsletters lead here.', 'connector-for-bluefox-email' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-preferences-url"><?php esc_html_e( 'Preferences page URL', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="url" class="large-text" id="cfbe-preferences-url" name="<?php echo esc_attr( $cfbe_option ); ?>[preferences_url]" value="<?php echo esc_attr( $cfbe_values['preferences_url'] ); ?>" placeholder="<?php echo esc_attr( $cfbe_prefs_url ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-prefs-title"><?php esc_html_e( 'Title', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="text" class="large-text" id="cfbe-prefs-title" name="<?php echo esc_attr( $cfbe_option ); ?>[prefs_title]" value="<?php echo esc_attr( $cfbe_values['prefs_title'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-prefs-intro"><?php esc_html_e( 'Intro', 'connector-for-bluefox-email' ); ?></label></th>
			<td><textarea class="large-text" rows="3" id="cfbe-prefs-intro" name="<?php echo esc_attr( $cfbe_option ); ?>[prefs_intro]"><?php echo esc_textarea( $cfbe_values['prefs_intro'] ); ?></textarea></td>
		</tr>
	</table>
	<?php submit_button(); ?>
</form>
