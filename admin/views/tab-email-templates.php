<?php
/**
 * Email templates tab view.
 *
 * @package CFBE
 *
 * @var bool                  $cfbe_ready      Whether bluefox is connected.
 * @var string                $cfbe_open       Card to open.
 * @var array|false           $cfbe_notice     Result of the last action.
 * @var array                 $cfbe_templates  Available template definitions.
 * @var array<string, string> $cfbe_identities Sender identities.
 * @var array<string, string> $cfbe_lists      Known lists.
 * @var string                $cfbe_default    Default list ID.
 * @var string                $cfbe_action     admin-post action.
 */

use CFBE\Email_Templates;

defined( 'ABSPATH' ) || exit;

$cfbe_labels = array(
	'subject' => __( 'Subject', 'connector-for-bluefox-email' ),
	'preview' => __( 'Preview text', 'connector-for-bluefox-email' ),
	'heading' => __( 'Heading', 'connector-for-bluefox-email' ),
	'intro'   => __( 'Text', 'connector-for-bluefox-email' ),
	'button'  => __( 'Button label', 'connector-for-bluefox-email' ),
	'footer'  => __( 'Small print', 'connector-for-bluefox-email' ),
);
?>
<p class="cfbe-lead"><?php esc_html_e( 'Ready-made emails for everything this plugin sends. Adjust the texts, then create the email in bluefox with one click: it is selected where the plugin uses it, so there is nothing to copy. Later changes can be sent to bluefox from here too; for the design (images, layout) open the email in bluefox.', 'connector-for-bluefox-email' ); ?></p>

<?php if ( ! $cfbe_ready ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ); ?></p></div>
<?php endif; ?>

<?php if ( ! $cfbe_templates ) : ?>
	<p><?php esc_html_e( 'Switch on a feature that sends emails (signup forms, welcome coupon, order or abandoned cart emails) to see its templates.', 'connector-for-bluefox-email' ); ?></p>
<?php endif; ?>

<div class="cfbe-templates">
<?php
foreach ( $cfbe_templates as $cfbe_key => $cfbe_template ) :
	$cfbe_record  = Email_Templates::record( $cfbe_key );
	$cfbe_created = '' !== (string) ( $cfbe_record['email_id'] ?? '' );
	$cfbe_texts   = Email_Templates::texts( $cfbe_key );
	$cfbe_here    = is_array( $cfbe_notice ) && ( $cfbe_notice['template'] ?? '' ) === $cfbe_key;
	if ( $cfbe_here && ! empty( $cfbe_notice['texts'] ) ) {
		$cfbe_texts = array_merge( $cfbe_texts, (array) $cfbe_notice['texts'] );
	}
	$cfbe_list = (string) ( $cfbe_record['list_id'] ?? '' );
	$cfbe_list = '' !== $cfbe_list ? $cfbe_list : $cfbe_default;
	$cfbe_id   = 'cfbe-template-' . $cfbe_key;
	?>
	<details class="cfbe-template" id="<?php echo esc_attr( $cfbe_id ); ?>" <?php echo $cfbe_open === $cfbe_key ? 'open' : ''; ?>>
		<summary>
			<span class="cfbe-template__title"><?php echo esc_html( $cfbe_template['label'] ); ?></span>
			<span class="cfbe-template__type"><?php echo esc_html( 'triggered' === $cfbe_template['type'] ? __( 'Triggered (marketing)', 'connector-for-bluefox-email' ) : __( 'Transactional', 'connector-for-bluefox-email' ) ); ?></span>
			<?php if ( $cfbe_created ) : ?>
				<span class="cfbe-template__status is-created"><?php esc_html_e( 'Created in bluefox', 'connector-for-bluefox-email' ); ?></span>
			<?php else : ?>
				<span class="cfbe-template__status"><?php esc_html_e( 'Not created yet', 'connector-for-bluefox-email' ); ?></span>
			<?php endif; ?>
		</summary>

		<div class="cfbe-template__body">
			<?php if ( $cfbe_here ) : ?>
				<div class="notice inline notice-<?php echo esc_attr( 'success' === $cfbe_notice['type'] ? 'success' : ( 'conflict' === $cfbe_notice['type'] ? 'warning' : 'error' ) ); ?>"><p><?php echo esc_html( (string) $cfbe_notice['message'] ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php echo esc_html( $cfbe_template['description'] ); ?></p>
			<?php if ( $cfbe_created ) : ?>
				<p class="description">
					<?php
					/* translators: %s: bluefox email ID */
					printf( esc_html__( 'bluefox email ID: %s', 'connector-for-bluefox-email' ), '<code>' . esc_html( (string) $cfbe_record['email_id'] ) . '</code>' );
					?>
				</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $cfbe_action ); ?>">
				<input type="hidden" name="template" value="<?php echo esc_attr( $cfbe_key ); ?>">
				<?php wp_nonce_field( $cfbe_action . '_' . $cfbe_key ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $cfbe_labels as $cfbe_field => $cfbe_label ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $cfbe_id . '-' . $cfbe_field ); ?>"><?php echo esc_html( $cfbe_label ); ?></label></th>
							<td>
								<?php if ( in_array( $cfbe_field, array( 'intro', 'footer' ), true ) ) : ?>
									<textarea class="large-text" rows="3" id="<?php echo esc_attr( $cfbe_id . '-' . $cfbe_field ); ?>" name="texts[<?php echo esc_attr( $cfbe_field ); ?>]"><?php echo esc_textarea( (string) $cfbe_texts[ $cfbe_field ] ); ?></textarea>
								<?php else : ?>
									<input type="text" class="large-text" id="<?php echo esc_attr( $cfbe_id . '-' . $cfbe_field ); ?>" name="texts[<?php echo esc_attr( $cfbe_field ); ?>]" value="<?php echo esc_attr( (string) $cfbe_texts[ $cfbe_field ] ); ?>">
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Variables', 'connector-for-bluefox-email' ); ?></th>
						<td><p class="description"><?php echo esc_html( (string) $cfbe_template['variables'] ); ?> – <?php esc_html_e( 'usable in every text; the plugin fills them in when it sends the email.', 'connector-for-bluefox-email' ); ?></p></td>
					</tr>
					<?php if ( ! $cfbe_created ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $cfbe_id . '-identity' ); ?>"><?php esc_html_e( 'Sender', 'connector-for-bluefox-email' ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $cfbe_id . '-identity' ); ?>" name="identity">
									<option value=""><?php esc_html_e( 'Project default sender', 'connector-for-bluefox-email' ); ?></option>
									<?php foreach ( $cfbe_identities as $cfbe_identity => $cfbe_identity_label ) : ?>
										<option value="<?php echo esc_attr( $cfbe_identity ); ?>"><?php echo esc_html( $cfbe_identity_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( ! $cfbe_created && ( 'triggered' === $cfbe_template['type'] || 'confirm' === $cfbe_key ) ) : ?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $cfbe_id . '-list' ); ?>"><?php esc_html_e( 'Subscriber list', 'connector-for-bluefox-email' ); ?></label></th>
							<td>
								<select id="<?php echo esc_attr( $cfbe_id . '-list' ); ?>" name="list_id">
									<?php if ( ! $cfbe_lists ) : ?>
										<option value="<?php echo esc_attr( $cfbe_default ); ?>"><?php echo esc_html( '' !== $cfbe_default ? $cfbe_default : __( '— Load lists on the General tab —', 'connector-for-bluefox-email' ) ); ?></option>
									<?php endif; ?>
									<?php foreach ( $cfbe_lists as $cfbe_list_id => $cfbe_list_name ) : ?>
										<option value="<?php echo esc_attr( $cfbe_list_id ); ?>" <?php selected( $cfbe_list, $cfbe_list_id ); ?>><?php echo esc_html( $cfbe_list_name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php
									echo esc_html(
										'confirm' === $cfbe_key
											? __( 'Double opt-in is switched on for this list with the new email.', 'connector-for-bluefox-email' )
											: __( 'Triggered emails belong to a list: only its active subscribers receive them, and they get an unsubscribe link.', 'connector-for-bluefox-email' )
									);
									?>
								</p>
							</td>
						</tr>
						<?php endif; ?>
					<?php if ( ! $cfbe_created ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Use it', 'connector-for-bluefox-email' ); ?></th>
							<td><label><input type="checkbox" name="assign" value="1" checked> <?php esc_html_e( 'Select the new email where the plugin needs it (recommended)', 'connector-for-bluefox-email' ); ?></label></td>
						</tr>
					<?php endif; ?>
				</table>

				<p class="cfbe-template__actions">
					<?php if ( ! $cfbe_created ) : ?>
						<button type="submit" name="do" value="create" class="button button-primary" <?php disabled( ! $cfbe_ready ); ?>><?php esc_html_e( 'Create in bluefox', 'connector-for-bluefox-email' ); ?></button>
					<?php elseif ( $cfbe_here && 'conflict' === $cfbe_notice['type'] ) : ?>
						<button type="submit" name="do" value="overwrite" class="button button-primary"><?php esc_html_e( 'Replace the version in bluefox', 'connector-for-bluefox-email' ); ?></button>
					<?php else : ?>
						<button type="submit" name="do" value="update" class="button button-primary" <?php disabled( ! $cfbe_ready ); ?>><?php esc_html_e( 'Update in bluefox', 'connector-for-bluefox-email' ); ?></button>
						<button type="submit" name="do" value="test" class="button" <?php disabled( ! $cfbe_ready ); ?>><?php esc_html_e( 'Send me a test', 'connector-for-bluefox-email' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</div>
	</details>
<?php endforeach; ?>
</div>
