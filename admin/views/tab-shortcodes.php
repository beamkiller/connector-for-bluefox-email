<?php
/**
 * Shortcodes tab view.
 *
 * @package CFBE
 *
 * @var array $cfbe_examples  Tested placements.
 * @var array $cfbe_reference Attribute reference per shortcode.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="description cfbe-sc-lead">
	<?php esc_html_e( 'In the block editor, add the "bluefox.email signup form" block (Widgets category) and pick the list, tags and texts in its sidebar. Anywhere else, paste a shortcode into a Shortcode block or your page builder’s shortcode element. Texts and colors are set on the Form tab; attributes only override a single placement. The automatic popup needs no shortcode – enable it on the Popup tab.', 'connector-for-bluefox-email' ); ?>
</p>

<h2><?php esc_html_e( 'Recommended placements (tested)', 'connector-for-bluefox-email' ); ?></h2>
<div class="cfbe-sc-examples">
	<?php foreach ( $cfbe_examples as $cfbe_i => $cfbe_example ) : ?>
		<div class="cfbe-sc-card">
			<h3><?php echo esc_html( $cfbe_example['title'] ); ?></h3>
			<p class="cfbe-sc-code">
				<textarea class="large-text code" rows="3" id="cfbe-sc-<?php echo esc_attr( (string) $cfbe_i ); ?>" readonly aria-label="<?php echo esc_attr( $cfbe_example['title'] ); ?>"><?php echo esc_textarea( $cfbe_example['code'] ); ?></textarea>
				<button type="button" class="button cfbe-copy" data-target="cfbe-sc-<?php echo esc_attr( (string) $cfbe_i ); ?>"><?php esc_html_e( 'Copy', 'connector-for-bluefox-email' ); ?></button>
			</p>
			<p><strong><?php esc_html_e( 'Where:', 'connector-for-bluefox-email' ); ?></strong> <?php echo esc_html( $cfbe_example['where'] ); ?></p>
			<p><strong><?php esc_html_e( 'What it does:', 'connector-for-bluefox-email' ); ?></strong> <?php echo esc_html( $cfbe_example['what'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>

<h2><?php esc_html_e( 'All options', 'connector-for-bluefox-email' ); ?></h2>
<?php foreach ( $cfbe_reference as $cfbe_tag => $cfbe_doc ) : ?>
	<h3 class="cfbe-sc-tag"><code>[<?php echo esc_html( $cfbe_tag ); ?>]</code></h3>
	<p><?php echo esc_html( $cfbe_doc['purpose'] ); ?></p>
	<table class="widefat striped cfbe-sc-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Attribute', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Values', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Default', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Description', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cfbe_doc['attrs'] as $cfbe_attr => $cfbe_info ) : ?>
				<tr>
					<td><code><?php echo esc_html( $cfbe_attr ); ?></code></td>
					<td><code><?php echo esc_html( $cfbe_info['values'] ); ?></code></td>
					<td><?php echo esc_html( $cfbe_info['default'] ); ?></td>
					<td><?php echo esc_html( $cfbe_info['desc'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endforeach; ?>

<h2><?php esc_html_e( 'bluefox tags', 'connector-for-bluefox-email' ); ?></h2>
<p><?php esc_html_e( 'Every subscriber gets a source tag: source-shortcode ([cfbe_form]), source-block (block), source-promo ([cfbe_promo]) or source-popup (popup). The place attribute becomes a placement-<name> tag, the selected interests become interest-<slug> tags, and the tags attribute (or the block and popup "Extra tags" setting) adds your own tags. Use them to target segments and campaigns in bluefox.', 'connector-for-bluefox-email' ); ?></p>
