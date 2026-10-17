<?php
/**
 * General tab: one row with a switch per form plugin integration.
 *
 * @package CFBE
 *
 * @var array $cfbe_modules Module definitions.
 * @var array $cfbe_states  Module switches.
 */

use CFBE\Modules;

defined( 'ABSPATH' ) || exit;
?>
<tr>
	<th scope="row"><?php esc_html_e( 'Form plugin integrations', 'connector-for-bluefox-email' ); ?></th>
	<td>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Form plugin integrations', 'connector-for-bluefox-email' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Subscribe people who submit forms built with these plugins. Only active plugins can be switched on.', 'connector-for-bluefox-email' ); ?></p>
			<?php foreach ( $cfbe_modules as $cfbe_int_slug => $cfbe_int ) : ?>
				<?php
				if ( 'integrations' !== ( $cfbe_int['group'] ?? '' ) ) {
					continue;
				}
				$cfbe_int_available = Modules::available( $cfbe_int_slug );
				$cfbe_int_on        = ! empty( $cfbe_states[ $cfbe_int_slug ] );
				?>
				<label class="cfbe-module-member">
					<input type="checkbox" name="<?php echo esc_attr( Modules::OPTION ); ?>[<?php echo esc_attr( $cfbe_int_slug ); ?>]" value="1" <?php checked( $cfbe_int_on ); ?> <?php disabled( ! $cfbe_int_available ); ?>>
					<?php echo esc_html( $cfbe_int['label'] ); ?>
					<?php if ( ! $cfbe_int_available ) : ?>
						<span class="description"><?php esc_html_e( '(not active)', 'connector-for-bluefox-email' ); ?></span>
					<?php endif; ?>
				</label><br>
				<?php if ( ! $cfbe_int_available && $cfbe_int_on ) : ?>
					<input type="hidden" name="<?php echo esc_attr( Modules::OPTION ); ?>[<?php echo esc_attr( $cfbe_int_slug ); ?>]" value="1">
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>
	</td>
</tr>
