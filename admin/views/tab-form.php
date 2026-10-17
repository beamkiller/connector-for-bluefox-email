<?php
/**
 * Form tab view (fields, consent, messages, interests).
 *
 * @package CFBE
 *
 * @var array  $cfbe_values  `form` settings.
 * @var string $cfbe_preview Rendered form HTML for the live preview.
 */

use CFBE\Settings;
use CFBE\Style_Vars;

defined( 'ABSPATH' ) || exit;

$cfbe_color_labels = array(
	'color_primary'       => __( 'Button / highlight', 'connector-for-bluefox-email' ),
	'color_primary_hover' => __( 'Button – hover', 'connector-for-bluefox-email' ),
	'color_button_text'   => __( 'Button text', 'connector-for-bluefox-email' ),
	'color_accent'        => __( 'Focus / asterisk', 'connector-for-bluefox-email' ),
	'color_text'          => __( 'Text', 'connector-for-bluefox-email' ),
	'color_muted'         => __( 'Muted text', 'connector-for-bluefox-email' ),
	'color_background'    => __( 'Field background', 'connector-for-bluefox-email' ),
	'color_border'        => __( 'Field border', 'connector-for-bluefox-email' ),
	'color_error'         => __( 'Error', 'connector-for-bluefox-email' ),
	'color_success'       => __( 'Success', 'connector-for-bluefox-email' ),
	'color_card'          => __( 'Card background', 'connector-for-bluefox-email' ),
);

$cfbe_option = Settings::option_name( 'form' );
$cfbe_name   = static function ( string $key ) use ( $cfbe_option ): string {
	return $cfbe_option . '[' . $key . ']';
};
$cfbe_rows   = $cfbe_values['interests'] ? $cfbe_values['interests'] : array(
	array(
		'label' => '',
		'tag'   => '',
	),
);
?>
<div class="cfbe-preview-box">
	<p class="cfbe-preview-box__label"><?php esc_html_e( 'Preview (saved fields, live colors and texts) – the button does not submit anything here.', 'connector-for-bluefox-email' ); ?></p>
	<div id="cfbe-preview" class="cfbe-preview" style="<?php echo esc_attr( Style_Vars::declarations( $cfbe_values ) ); ?>" data-cfbe-discount="<?php echo esc_attr( \CFBE\Coupon_Data::discount_label( (string) \CFBE\Settings::get( 'coupon', 'discount_type' ), (float) \CFBE\Settings::get( 'coupon', 'amount' ) ) ); ?>">
		<?php echo $cfbe_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Form_Renderer escapes every value. ?>
	</div>
	<p class="description"><?php esc_html_e( 'Add it with the "bluefox.email signup form" block, or anywhere with [cfbe_form] (Shortcode block or your page builder’s shortcode element). Options: list="LIST_ID", tags="a,b", variant="card|banner|plain", heading="", heading_tag="h3", intro="", benefits="First|Second", badge="0", button="", offer="", layout="inline", show_name="0", show_interests="0", place="", class="". Subscribers are tagged source-<source>, placement-<name> and interest-<slug>; see the Shortcodes tab.', 'connector-for-bluefox-email' ); ?></p>
</div>

<form method="post" action="options.php" id="cfbe-form-settings">
	<?php settings_fields( $cfbe_option ); ?>

	<h2><?php esc_html_e( 'Appearance', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		foreach (
			array(
				'heading' => __( 'Heading', 'connector-for-bluefox-email' ),
				'button'  => __( 'Button text', 'connector-for-bluefox-email' ),
				'offer'   => __( 'Offer line (below the button)', 'connector-for-bluefox-email' ),
			) as $cfbe_key => $cfbe_label
		) :
			?>
			<tr>
				<th scope="row"><label for="cfbe-<?php echo esc_attr( $cfbe_key ); ?>"><?php echo esc_html( $cfbe_label ); ?></label></th>
				<td><input type="text" class="large-text" id="cfbe-<?php echo esc_attr( $cfbe_key ); ?>" name="<?php echo esc_attr( $cfbe_name( $cfbe_key ) ); ?>" value="<?php echo esc_attr( $cfbe_values[ $cfbe_key ] ); ?>" data-cfbe-preview-text=".cfbe-form__<?php echo esc_attr( 'button' === $cfbe_key ? 'submit-text' : $cfbe_key ); ?>"></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="cfbe-intro"><?php esc_html_e( 'Intro text', 'connector-for-bluefox-email' ); ?></label></th>
			<td><textarea class="large-text" rows="2" id="cfbe-intro" name="<?php echo esc_attr( $cfbe_name( 'intro' ) ); ?>" data-cfbe-preview-text=".cfbe-form__intro"><?php echo esc_textarea( $cfbe_values['intro'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Leave the heading / intro / offer line empty to hide it. {discount} is replaced with the discount set on the Coupon tab (e.g. 10%).', 'connector-for-bluefox-email' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Promo bar – [cfbe_promo]', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<p><label><?php esc_html_e( 'Title', 'connector-for-bluefox-email' ); ?><br><input type="text" class="large-text" name="<?php echo esc_attr( $cfbe_name( 'promo_heading' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['promo_heading'] ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Text', 'connector-for-bluefox-email' ); ?><br><input type="text" class="large-text" name="<?php echo esc_attr( $cfbe_name( 'promo_text' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['promo_text'] ); ?>"></label></p>
				<p><label><?php esc_html_e( 'Button', 'connector-for-bluefox-email' ); ?><br><input type="text" class="regular-text" name="<?php echo esc_attr( $cfbe_name( 'promo_button' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['promo_button'] ); ?>"></label></p>
				<p><label><?php esc_html_e( 'The button', 'connector-for-bluefox-email' ); ?><br>
				<select name="<?php echo esc_attr( $cfbe_name( 'promo_action' ) ); ?>">
					<option value="dialog" <?php selected( $cfbe_values['promo_action'], 'dialog' ); ?>><?php esc_html_e( 'opens the form in a popup', 'connector-for-bluefox-email' ); ?></option>
					<option value="scroll" <?php selected( $cfbe_values['promo_action'], 'scroll' ); ?>><?php esc_html_e( 'scrolls to the form on the page (e.g. footer bar)', 'connector-for-bluefox-email' ); ?></option>
				</select></label></p>
				<p class="description"><?php esc_html_e( 'When scrolling, it jumps to the last [cfbe_form] form on the page (or to the target="#element-id" element); if there is none, the form opens in a popup. Options: style="dark|bar", action="dialog|scroll", target="#newsletter", form_variant="banner", heading="", text="", button="".', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-benefits"><?php esc_html_e( 'Benefits (one per line)', 'connector-for-bluefox-email' ); ?></label></th>
			<td><textarea class="large-text" rows="3" id="cfbe-benefits" name="<?php echo esc_attr( $cfbe_name( 'benefits' ) ); ?>"><?php echo esc_textarea( $cfbe_values['benefits'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Checkmark list below the heading, max. 5 lines. Leave empty to hide it.', 'connector-for-bluefox-email' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Style', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<?php
				foreach (
					array(
						'card'   => __( 'Card – light background with a subtle border (recommended)', 'connector-for-bluefox-email' ),
						'banner' => __( 'Banner – primary color background, light button (for wide areas)', 'connector-for-bluefox-email' ),
						'plain'  => __( 'Plain – the form only, no decoration', 'connector-for-bluefox-email' ),
					) as $cfbe_key => $cfbe_label
				) :
					?>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'variant' ) ); ?>" value="<?php echo esc_attr( $cfbe_key ); ?>" <?php checked( $cfbe_values['variant'], $cfbe_key ); ?> data-cfbe-variant> <?php echo esc_html( $cfbe_label ); ?></label><br>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Can be overridden per shortcode (variant="banner") or block.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer badge', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'badge_enabled' ) ); ?>" value="1" <?php checked( $cfbe_values['badge_enabled'] ); ?>> <?php esc_html_e( 'Show a small badge above the heading (Card and Banner styles, promo bar), e.g. "10% off" or "Free guide". {discount} = the coupon value.', 'connector-for-bluefox-email' ); ?></label>
				<p>
					<label><?php esc_html_e( 'Value', 'connector-for-bluefox-email' ); ?> <input type="text" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'badge_text' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['badge_text'] ); ?>"></label>
					<label><?php esc_html_e( 'Label', 'connector-for-bluefox-email' ); ?> <input type="text" class="regular-text" name="<?php echo esc_attr( $cfbe_name( 'badge_label' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['badge_label'] ); ?>"></label>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Layout', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'layout' ) ); ?>" value="stacked" <?php checked( $cfbe_values['layout'], 'stacked' ); ?>> <?php esc_html_e( 'Stacked', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'layout' ) ); ?>" value="inline" <?php checked( $cfbe_values['layout'], 'inline' ); ?>> <?php esc_html_e( 'Inline (for wide areas)', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Colors', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<div class="cfbe-colors">
					<?php foreach ( $cfbe_color_labels as $cfbe_key => $cfbe_label ) : ?>
						<label class="cfbe-color">
							<input type="color" name="<?php echo esc_attr( $cfbe_name( $cfbe_key ) ); ?>" value="<?php echo esc_attr( $cfbe_values[ $cfbe_key ] ); ?>" data-cfbe-var="<?php echo esc_attr( Style_Vars::var_name( $cfbe_key ) ); ?>" data-cfbe-default="<?php echo esc_attr( Style_Vars::DEFAULT_COLORS[ $cfbe_key ] ); ?>">
							<span><?php echo esc_html( $cfbe_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="cfbe-colors-reset"><?php esc_html_e( 'Reset default colors', 'connector-for-bluefox-email' ); ?></button></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Corner radius (px)', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><?php esc_html_e( 'Button', 'connector-for-bluefox-email' ); ?> <input type="number" min="0" max="30" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'radius_button' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['radius_button'] ); ?>" data-cfbe-var="--cfbe-radius-button" data-cfbe-unit="px"></label>
				<label><?php esc_html_e( 'Field', 'connector-for-bluefox-email' ); ?> <input type="number" min="0" max="30" class="small-text" name="<?php echo esc_attr( $cfbe_name( 'radius_input' ) ); ?>" value="<?php echo esc_attr( (string) $cfbe_values['radius_input'] ); ?>" data-cfbe-var="--cfbe-radius-input" data-cfbe-unit="px"></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Loading', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'assets_everywhere' ) ); ?>" value="1" <?php checked( $cfbe_values['assets_everywhere'] ); ?>> <?php esc_html_e( 'Load CSS/JS on every page', 'connector-for-bluefox-email' ); ?></label>
				<p class="description"><?php esc_html_e( 'By default the assets load only where the form appears. Turn this on to avoid a flash of unstyled content. If a caching plugin delays JavaScript (e.g. WP Rocket “Delay JavaScript execution”), exclude: cfbe-form.js', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Fields', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Name field', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'name_enabled' ) ); ?>" value="1" <?php checked( $cfbe_values['name_enabled'] ); ?>> <?php esc_html_e( 'Show', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'name_required' ) ); ?>" value="1" <?php checked( $cfbe_values['name_required'] ); ?>> <?php esc_html_e( 'Required', 'connector-for-bluefox-email' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Interests', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'interests_enabled' ) ); ?>" value="1" <?php checked( $cfbe_values['interests_enabled'] ); ?>> <?php esc_html_e( 'Show', 'connector-for-bluefox-email' ); ?></label><br>
				<label><input type="checkbox" name="<?php echo esc_attr( $cfbe_name( 'interests_required' ) ); ?>" value="1" <?php checked( $cfbe_values['interests_required'] ); ?>> <?php esc_html_e( 'Required', 'connector-for-bluefox-email' ); ?></label>
				<p>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'interests_mode' ) ); ?>" value="select" <?php checked( $cfbe_values['interests_mode'], 'select' ); ?>> <?php esc_html_e( 'Dropdown (single choice)', 'connector-for-bluefox-email' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( $cfbe_name( 'interests_mode' ) ); ?>" value="checkbox" <?php checked( $cfbe_values['interests_mode'], 'checkbox' ); ?>> <?php esc_html_e( 'Checkboxes (multiple choice)', 'connector-for-bluefox-email' ); ?></label>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-interests-label"><?php esc_html_e( 'Question text', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="text" class="regular-text" id="cfbe-interests-label" name="<?php echo esc_attr( $cfbe_name( 'interests_label' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['interests_label'] ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Options', 'connector-for-bluefox-email' ); ?></th>
			<td>
				<table class="widefat cfbe-interests" id="cfbe-interests">
					<thead><tr>
						<th><?php esc_html_e( 'Label', 'connector-for-bluefox-email' ); ?></th>
						<th><?php esc_html_e( 'bluefox tag', 'connector-for-bluefox-email' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'connector-for-bluefox-email' ); ?></span></th>
					</tr></thead>
					<tbody>
					<?php foreach ( array_values( $cfbe_rows ) as $cfbe_i => $cfbe_row ) : ?>
						<tr>
							<td><input type="text" class="regular-text" name="<?php echo esc_attr( $cfbe_option . '[interests][' . $cfbe_i . '][label]' ); ?>" value="<?php echo esc_attr( $cfbe_row['label'] ); ?>" aria-label="<?php esc_attr_e( 'Label', 'connector-for-bluefox-email' ); ?>"></td>
							<td><input type="text" class="code" name="<?php echo esc_attr( $cfbe_option . '[interests][' . $cfbe_i . '][tag]' ); ?>" value="<?php echo esc_attr( $cfbe_row['tag'] ); ?>" placeholder="<?php esc_attr_e( 'automatic', 'connector-for-bluefox-email' ); ?>" aria-label="<?php esc_attr_e( 'bluefox tag', 'connector-for-bluefox-email' ); ?>"></td>
							<td class="cfbe-row-actions">
								<button type="button" class="button cfbe-row-up" aria-label="<?php esc_attr_e( 'Up', 'connector-for-bluefox-email' ); ?>">↑</button>
								<button type="button" class="button cfbe-row-down" aria-label="<?php esc_attr_e( 'Down', 'connector-for-bluefox-email' ); ?>">↓</button>
								<button type="button" class="button cfbe-row-remove" aria-label="<?php esc_attr_e( 'Remove', 'connector-for-bluefox-email' ); ?>">✕</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="cfbe-interest-add" data-option="<?php echo esc_attr( $cfbe_option ); ?>"><?php esc_html_e( '+ Add option', 'connector-for-bluefox-email' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'If left empty, the tag is generated from the label (e.g. “Special offers” → interest-special-offers). Do not rename tags after going live, because bluefox segments rely on them.', 'connector-for-bluefox-email' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Consent', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cfbe-consent-label"><?php esc_html_e( 'Checkbox text', 'connector-for-bluefox-email' ); ?></label></th>
			<td><textarea class="large-text" rows="3" id="cfbe-consent-label" name="<?php echo esc_attr( $cfbe_name( 'consent_label' ) ); ?>"><?php echo esc_textarea( $cfbe_values['consent_label'] ); ?></textarea>
			<p class="description"><?php esc_html_e( 'This text is stored as proof of consent.', 'connector-for-bluefox-email' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="cfbe-privacy-url"><?php esc_html_e( 'Privacy policy URL', 'connector-for-bluefox-email' ); ?></label></th>
			<td><input type="url" class="large-text" id="cfbe-privacy-url" name="<?php echo esc_attr( $cfbe_name( 'privacy_url' ) ); ?>" value="<?php echo esc_attr( $cfbe_values['privacy_url'] ); ?>"></td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Messages', 'connector-for-bluefox-email' ); ?></h2>
	<table class="form-table" role="presentation">
		<?php
		foreach (
			array(
				'msg_pending' => __( 'Successful signup', 'connector-for-bluefox-email' ),
				'msg_already' => __( 'Already subscribed', 'connector-for-bluefox-email' ),
				'msg_error'   => __( 'Error', 'connector-for-bluefox-email' ),
			) as $cfbe_key => $cfbe_label
		) :
			?>
			<tr>
				<th scope="row"><label for="cfbe-<?php echo esc_attr( $cfbe_key ); ?>"><?php echo esc_html( $cfbe_label ); ?></label></th>
				<td><textarea class="large-text" rows="2" id="cfbe-<?php echo esc_attr( $cfbe_key ); ?>" name="<?php echo esc_attr( $cfbe_name( $cfbe_key ) ); ?>"><?php echo esc_textarea( $cfbe_values[ $cfbe_key ] ); ?></textarea></td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php submit_button(); ?>
</form>
