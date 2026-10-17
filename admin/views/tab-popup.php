<?php
/**
 * Popup tab view.
 *
 * @package CFBE
 *
 * @var array  $cfbe_values      `popup` settings.
 * @var string $cfbe_preview_url Frontend preview URL.
 * @var array  $cfbe_lists       Cached bluefox lists (ID => name).
 */

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

$cfbe_option = Settings::option_name( 'popup' );
$cfbe_name   = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
$cfbe_form   = Settings::get( 'form' );
?>
<p>
	<a class="button" href="<?php echo esc_url( $cfbe_preview_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview on the site', 'connector-for-bluefox-email' ); ?></a>
	<span class="description"><?php esc_html_e( 'For admins it opens immediately, even when disabled. Save your changes first.', 'connector-for-bluefox-email' ); ?></span>
</p>

<form method="post" action="options.php">
	<?php settings_fields( $cfbe_option ); ?>

	<h2><?php esc_html_e( 'General', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Popup', 'connector-for-bluefox-email' ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'enabled' ) ); ?>" value="1" <?php checked( $cfbe_values['enabled'] ); ?>> <?php esc_html_e( 'Enabled', 'connector-for-bluefox-email' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-heading"><?php esc_html_e( 'Heading', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="large-text" id="cfbe-popup-heading" name="<?php echo esc_attr( $cfbe_name( 'heading' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['heading'] ); ?>" placeholder="<?php echo esc_attr( $cfbe_form['heading'] ); ?>">
				<p class="description"><?php esc_html_e( '{discount} = the coupon value. If empty, the heading from the Form tab is used.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-intro"><?php esc_html_e( 'Intro text', 'connector-for-bluefox-email' ); ?></label></th>
			<td><textarea class="large-text" rows="2" id="cfbe-popup-intro" name="<?php echo esc_attr( $cfbe_name( 'intro' ) ); ?>" placeholder="<?php echo esc_attr( $cfbe_form['intro'] ); ?>"><?php echo esc_textarea( $cfbe_values['intro'] ); ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-variant"><?php esc_html_e( 'Form style', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-popup-variant" name="<?php echo esc_attr( $cfbe_name( 'form_variant' ) ); ?>">
					<option value="card" <?php selected( $cfbe_values['form_variant'], 'card' ); ?>><?php esc_html_e( 'Card', 'connector-for-bluefox-email' ); ?></option>
					<option value="banner" <?php selected( $cfbe_values['form_variant'], 'banner' ); ?>><?php esc_html_e( 'Banner', 'connector-for-bluefox-email' ); ?></option>
					<option value="plain" <?php selected( $cfbe_values['form_variant'], 'plain' ); ?>><?php esc_html_e( 'Plain', 'connector-for-bluefox-email' ); ?></option>
				</select>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'show_interests' ) ); ?>" value="1" <?php checked( $cfbe_values['show_interests'] ); ?>> <?php esc_html_e( 'Interests field (if enabled on the Form tab)', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-list"><?php esc_html_e( 'bluefox list', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<?php if ( $cfbe_lists ) : ?>
					<select id="cfbe-popup-list" name="<?php echo esc_attr( $cfbe_name( 'list_id' ) ); ?>">
						<option value=""><?php esc_html_e( 'Default list (General tab)', 'connector-for-bluefox-email' ); ?></option>
						<?php foreach ( $cfbe_lists as $cfbe_list_id => $cfbe_list_name ) : ?>
							<option value="<?php echo esc_attr( $cfbe_list_id ); ?>" <?php selected( $cfbe_values['list_id'], $cfbe_list_id ); ?>><?php echo esc_html( $cfbe_list_name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" class="regular-text code" id="cfbe-popup-list" name="<?php echo esc_attr( $cfbe_name( 'list_id' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['list_id'] ); ?>" placeholder="<?php esc_attr_e( 'Default list', 'connector-for-bluefox-email' ); ?>">
					<p class="description"><?php esc_html_e( 'Load your lists on the General tab to pick one here, or paste a list ID.', 'connector-for-bluefox-email' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-tags"><?php esc_html_e( 'Extra tags', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="cfbe-popup-tags" name="<?php echo esc_attr( $cfbe_name( 'tags' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['tags'] ); ?>" placeholder="spring-campaign">
				<p class="description"><?php esc_html_e( 'Comma-separated bluefox tags added to everyone who subscribes through the popup (besides source-popup and interest-… tags).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'When to show?', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Triggers', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Triggers', 'connector-for-bluefox-email' ); ?></legend>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'trigger_scroll' ) ); ?>" value="1" <?php checked( $cfbe_values['trigger_scroll'] ); ?>> <?php esc_html_e( 'Scroll:', 'connector-for-bluefox-email' ); ?></label>
					<input type="number" min="10" max="100" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'scroll_percent' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['scroll_percent'] ); ?>" aria-label="<?php esc_attr_e( 'Scroll percentage', 'connector-for-bluefox-email' ); ?>"> %<br>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'trigger_delay' ) ); ?>" value="1" <?php checked( $cfbe_values['trigger_delay'] ); ?>> <?php esc_html_e( 'Delay:', 'connector-for-bluefox-email' ); ?></label>
					<input type="number" min="1" max="300" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'delay_seconds' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['delay_seconds'] ); ?>" aria-label="<?php esc_attr_e( 'Delay in seconds', 'connector-for-bluefox-email' ); ?>"> <?php esc_html_e( 'seconds', 'connector-for-bluefox-email' ); ?><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'trigger_exit' ) ); ?>" value="1" <?php checked( $cfbe_values['trigger_exit'] ); ?>> <?php esc_html_e( 'Exit intent (desktop, when the mouse leaves through the top of the page)', 'connector-for-bluefox-email' ); ?></label><br>
					<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'trigger_pageviews' ) ); ?>" value="1" <?php checked( $cfbe_values['trigger_pageviews'] ); ?>> <?php esc_html_e( 'Page views: at least', 'connector-for-bluefox-email' ); ?></label>
					<input type="number" min="1" max="20" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'pageviews' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['pageviews'] ); ?>" aria-label="<?php esc_attr_e( 'Number of page views', 'connector-for-bluefox-email' ); ?>"> <?php esc_html_e( 'pages viewed', 'connector-for-bluefox-email' ); ?>
				</fieldset>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-combine"><?php esc_html_e( 'With multiple triggers', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-popup-combine" name="<?php echo esc_attr( $cfbe_name( 'combine' ) ); ?>">
					<option value="any" <?php selected( $cfbe_values['combine'], 'any' ); ?>><?php esc_html_e( 'Any is met', 'connector-for-bluefox-email' ); ?></option>
					<option value="all" <?php selected( $cfbe_values['combine'], 'all' ); ?>><?php esc_html_e( 'All are met', 'connector-for-bluefox-email' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Where and for whom?', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-popup-pages"><?php esc_html_e( 'Pages', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-popup-pages" name="<?php echo esc_attr( $cfbe_name( 'pages' ) ); ?>">
					<option value="all" <?php selected( $cfbe_values['pages'], 'all' ); ?>><?php esc_html_e( 'All pages', 'connector-for-bluefox-email' ); ?></option>
					<option value="include" <?php selected( $cfbe_values['pages'], 'include' ); ?>><?php esc_html_e( 'Only these', 'connector-for-bluefox-email' ); ?></option>
					<option value="exclude" <?php selected( $cfbe_values['pages'], 'exclude' ); ?>><?php esc_html_e( 'All except these', 'connector-for-bluefox-email' ); ?></option>
				</select>
				<p><label for="cfbe-popup-ids"><?php esc_html_e( 'Page / product IDs (comma-separated)', 'connector-for-bluefox-email' ); ?></label><br>
				<input type="text" class="regular-text code" id="cfbe-popup-ids" name="<?php echo esc_attr( $cfbe_name( 'page_ids' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['page_ids'] ); ?>"></p>
				<p><label for="cfbe-popup-urls"><?php esc_html_e( 'URL contains (one per line, e.g. /product-category/shoes/)', 'connector-for-bluefox-email' ); ?></label><br>
				<textarea class="regular-text code" rows="3" id="cfbe-popup-urls" name="<?php echo esc_attr( $cfbe_name( 'url_contains' ) ); ?>"><?php echo esc_textarea( $cfbe_values['url_contains'] ); ?></textarea></p>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'exclude_woo' ) ); ?>" value="1" <?php checked( $cfbe_values['exclude_woo'] ); ?>> <?php esc_html_e( 'Never on the cart, checkout and my account pages', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'Never shown on the thank-you page.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-device"><?php esc_html_e( 'Device', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-popup-device" name="<?php echo esc_attr( $cfbe_name( 'device' ) ); ?>">
					<option value="all" <?php selected( $cfbe_values['device'], 'all' ); ?>><?php esc_html_e( 'All', 'connector-for-bluefox-email' ); ?></option>
					<option value="desktop" <?php selected( $cfbe_values['device'], 'desktop' ); ?>><?php esc_html_e( 'Desktop / tablet only', 'connector-for-bluefox-email' ); ?></option>
					<option value="mobile" <?php selected( $cfbe_values['device'], 'mobile' ); ?>><?php esc_html_e( 'Mobile only', 'connector-for-bluefox-email' ); ?></option>
				</select>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Frequency', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-popup-dismiss"><?php esc_html_e( 'After closing', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="number" min="0" max="365" class="small-text" id="cfbe-popup-dismiss" name="<?php echo esc_attr( $cfbe_name( 'dismiss_days' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['dismiss_days'] ); ?>"> <?php esc_html_e( 'days before showing again', 'connector-for-bluefox-email' ); ?>
				<p class="description"><?php esc_html_e( 'Shown at most once per session, and never again after a successful signup (on any form).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Show again to everyone', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'bump_version' ) ); ?>" value="1"> <?php esc_html_e( 'Reset previous dismissals on save (e.g. for a new promotion)', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'It still won’t be shown to people who have already subscribed.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Look', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-popup-size"><?php esc_html_e( 'Width', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<select id="cfbe-popup-size" name="<?php echo esc_attr( $cfbe_name( 'size' ) ); ?>">
					<option value="sm" <?php selected( $cfbe_values['size'], 'sm' ); ?>><?php esc_html_e( 'Narrow (420 px)', 'connector-for-bluefox-email' ); ?></option>
					<option value="md" <?php selected( $cfbe_values['size'], 'md' ); ?>><?php esc_html_e( 'Medium (520 px)', 'connector-for-bluefox-email' ); ?></option>
					<option value="lg" <?php selected( $cfbe_values['size'], 'lg' ); ?>><?php esc_html_e( 'Wide (640 px)', 'connector-for-bluefox-email' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-overlay"><?php esc_html_e( 'Overlay', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="color" id="cfbe-popup-overlay" name="<?php echo esc_attr( $cfbe_name( 'overlay_color' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['overlay_color'] ); ?>">
				<input type="number" min="0" max="1" step="0.05" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'overlay_opacity' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['overlay_opacity'] ); ?>" aria-label="<?php esc_attr_e( 'Opacity (0–1)', 'connector-for-bluefox-email' ); ?>">
				<span class="description"><?php esc_html_e( 'opacity (0–1)', 'connector-for-bluefox-email' ); ?></span>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Behavior', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'close_on_backdrop' ) ); ?>" value="1" <?php checked( $cfbe_values['close_on_backdrop'] ); ?>> <?php esc_html_e( 'Close when clicking the overlay', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'animate' ) ); ?>" value="1" <?php checked( $cfbe_values['animate'] ); ?>> <?php esc_html_e( 'Animate on open', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'The Esc key always closes it (accessibility).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-popup-cookie"><?php esc_html_e( 'Cookie bar selector', 'connector-for-bluefox-email' ); ?></label></th>
			<td>
				<input type="text" class="large-text code" id="cfbe-popup-cookie" name="<?php echo esc_attr( $cfbe_name( 'cookie_bar_selector' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['cookie_bar_selector'] ); ?>">
				<p class="description"><?php esc_html_e( 'While this element (cookie consent bar) is visible, the popup waits. Leave empty to show the popup regardless of the cookie bar (visitors then accept cookies after closing the popup).', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="description"><?php esc_html_e( 'If a caching plugin delays JavaScript (e.g. WP Rocket “Delay JavaScript execution”), exclude: cfbe-form.js, cfbe-popup.js.', 'connector-for-bluefox-email' ); ?></p>
	<?php submit_button(); ?>
</form>
