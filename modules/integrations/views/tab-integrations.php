<?php
/**
 * Form integrations tab.
 *
 * @package CFBE
 *
 * @var array                                       $cfbe_values       `integrations` settings.
 * @var \CFBE\Integrations\Integration[]            $cfbe_integrations Integrations (slug => instance).
 * @var array<string, string>                       $cfbe_lists        Cached bluefox lists (id => name).
 * @var string                                      $cfbe_option       Option name.
 */

use CFBE\Integrations\Rules;

defined( 'ABSPATH' ) || exit;
?>
<p class="description"><?php esc_html_e( 'Subscribe people who submit forms built with other form plugins. Add a row per form: which list, which field holds the email address, and which checkbox is the newsletter consent. The subscription runs in the background (the form never waits for bluefox) and works like the plugin’s own form: double opt-in email, existing contacts, retries.', 'connector-for-bluefox-email' ); ?></p>

<p class="description"><?php esc_html_e( 'Switch form plugins on or off on the General tab.', 'connector-for-bluefox-email' ); ?></p>

<form method="post" action="options.php" class="cfbe-integrations">
	<?php settings_fields( $cfbe_option ); ?>

	<?php foreach ( $cfbe_integrations as $cfbe_slug => $cfbe_integration ) : ?>
		<?php
		if ( ! $cfbe_integration->is_active() || ! $cfbe_integration->is_enabled() ) {
			continue;
		}
		$cfbe_settings = isset( $cfbe_values[ $cfbe_slug ] ) && is_array( $cfbe_values[ $cfbe_slug ] ) ? $cfbe_values[ $cfbe_slug ] : array();
		$cfbe_base     = $cfbe_option . '[' . $cfbe_slug . ']';
		?>
		<div class="cfbe-int" id="cfbe-int-<?php echo esc_attr( $cfbe_slug ); ?>">
			<h2><?php echo esc_html( $cfbe_integration->label() ); ?></h2>

			<input type="hidden" name="<?php echo esc_attr( $cfbe_base . '[present]' ); ?>" value="1">
			<p class="description"><?php echo esc_html( $cfbe_integration->field_help() ); ?></p>

			<?php if ( $cfbe_integration->has_rules() ) : ?>
				<?php
				$cfbe_forms = $cfbe_integration->forms();
				$cfbe_rules = isset( $cfbe_settings['rules'] ) && is_array( $cfbe_settings['rules'] ) ? $cfbe_settings['rules'] : array();
				?>
				<div class="cfbe-int__rules" data-next="<?php echo esc_attr( (string) count( $cfbe_rules ) ); ?>">
					<?php
					foreach ( array_values( $cfbe_rules ) as $cfbe_index => $cfbe_rule ) {
						$cfbe_rule = array_merge( Rules::blank_rule(), (array) $cfbe_rule );
						include __DIR__ . '/rule-row.php';
					}
					?>
				</div>
				<p>
					<button type="button" class="button cfbe-int-add-rule"><?php esc_html_e( 'Add form', 'connector-for-bluefox-email' ); ?></button>
				</p>
				<template class="cfbe-int-rule-template">
					<?php
					$cfbe_index = '__RULE__';
					$cfbe_rule  = Rules::blank_rule();
					include __DIR__ . '/rule-row.php';
					?>
				</template>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<?php submit_button(); ?>
</form>
