<?php
/**
 * Email templates tab view.
 *
 * @package CFBE
 *
 * @var bool                  $cfbe_ready      Whether bluefox is connected.
 * @var string                $cfbe_open       Card to open.
 * @var bool                  $cfbe_saved      Whether the design was just saved.
 * @var array|false           $cfbe_notice     Result of the last action.
 * @var array                 $cfbe_templates  Available template definitions.
 * @var array<string, string> $cfbe_identities Sender identities.
 * @var array<string, string> $cfbe_lists      Known lists.
 * @var string                $cfbe_default    Default list ID.
 * @var string                $cfbe_action     admin-post action of the cards.
 * @var string                $cfbe_design     admin-post action of the design form.
 */

use CFBE\Email_Design;
use CFBE\Email_Templates;
use CFBE\Woo_Events\Rules;
use CFBE\Woo_Events\WC_Email_Switch;

defined( 'ABSPATH' ) || exit;

$cfbe_labels = array(
	'subject' => __( 'Subject', 'connector-for-bluefox-email' ),
	'preview' => __( 'Preview text', 'connector-for-bluefox-email' ),
	'heading' => __( 'Heading', 'connector-for-bluefox-email' ),
	'intro'   => __( 'Text', 'connector-for-bluefox-email' ),
	'button'  => __( 'Button label', 'connector-for-bluefox-email' ),
	'footer'  => __( 'Small print', 'connector-for-bluefox-email' ),
);
$cfbe_groups = array(
	'newsletter' => array(
		__( 'Newsletter emails', 'connector-for-bluefox-email' ),
		__( 'Sent when someone subscribes through the plugin’s forms or at checkout.', 'connector-for-bluefox-email' ),
	),
	'replaces'   => array(
		__( 'Order emails WooCommerce also sends', 'connector-for-bluefox-email' ),
		__( 'WooCommerce already sends these emails. Create one here to send the bluefox version instead: WooCommerce’s email for the same event is switched off, so customers get one email, in your design, with open and click statistics in bluefox.', 'connector-for-bluefox-email' ),
	),
	'extra'      => array(
		__( 'Extra emails WooCommerce does not send', 'connector-for-bluefox-email' ),
		__( 'Additional emails that bring customers back. They do not replace anything.', 'connector-for-bluefox-email' ),
	),
);
$cfbe_source = Email_Design::source();
$cfbe_hash   = Email_Design::hash();
$cfbe_titles = Rules::wc_email_titles();
?>
<p class="cfbe-lead"><?php esc_html_e( 'Ready-made, mobile-friendly emails for everything this plugin sends. Adjust the texts and click “Create in bluefox”: the email is created in your bluefox account and selected where the plugin uses it, so there is nothing to copy. You can change the texts here later and send them to bluefox with “Update in bluefox”.', 'connector-for-bluefox-email' ); ?></p>

<?php if ( ! $cfbe_ready ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ); ?></p></div>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cfbe-design-source">
	<input type="hidden" name="action" value="<?php echo esc_attr( $cfbe_design ); ?>">
	<?php wp_nonce_field( $cfbe_design ); ?>
	<h2><?php esc_html_e( 'Design', 'connector-for-bluefox-email' ); ?></h2>
	<?php if ( $cfbe_saved ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Design saved.', 'connector-for-bluefox-email' ); ?></p></div>
	<?php endif; ?>
	<fieldset>
		<legend class="screen-reader-text"><?php esc_html_e( 'Design', 'connector-for-bluefox-email' ); ?></legend>
		<?php if ( Email_Design::woocommerce_available() ) : ?>
			<label>
				<input type="radio" name="source" value="<?php echo esc_attr( Email_Design::SOURCE_WOOCOMMERCE ); ?>" <?php checked( Email_Design::SOURCE_WOOCOMMERCE, $cfbe_source ); ?>>
				<?php esc_html_e( 'Match the WooCommerce emails', 'connector-for-bluefox-email' ); ?>
				<span class="description">
					<?php
					printf(
						/* translators: %s: link to WooCommerce → Settings → Emails */
						esc_html__( 'Logo, colours, font and footer text from %s.', 'connector-for-bluefox-email' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) ) . '">' . esc_html__( 'WooCommerce → Settings → Emails', 'connector-for-bluefox-email' ) . '</a>'
					);
					?>
				</span>
			</label><br>
		<?php endif; ?>
		<label>
			<input type="radio" name="source" value="<?php echo esc_attr( Email_Design::SOURCE_FORM ); ?>" <?php checked( Email_Design::SOURCE_FORM, $cfbe_source ); ?>>
			<?php esc_html_e( 'Use the signup form colours', 'connector-for-bluefox-email' ); ?>
			<span class="description"><?php esc_html_e( 'Colours from the Form tab, logo from the Customizer.', 'connector-for-bluefox-email' ); ?></span>
		</label>
	</fieldset>
	<p class="description"><?php esc_html_e( 'The design is copied into each email when it is created or updated. After changing it, click “Update in bluefox” on the emails you already created.', 'connector-for-bluefox-email' ); ?></p>
	<?php submit_button( __( 'Save design', 'connector-for-bluefox-email' ), 'secondary', 'submit', false ); ?>
</form>

<?php if ( ! $cfbe_templates ) : ?>
	<p><?php esc_html_e( 'Switch on a feature that sends emails (signup forms, welcome coupon, order or abandoned cart emails) to see its templates.', 'connector-for-bluefox-email' ); ?></p>
<?php endif; ?>

<?php
foreach ( $cfbe_groups as $cfbe_group => $cfbe_group_text ) :
	$cfbe_in_group = array_filter(
		$cfbe_templates,
		static function ( array $template ) use ( $cfbe_group ): bool {
			return ( $template['group'] ?? '' ) === $cfbe_group;
		}
	);
	if ( ! $cfbe_in_group ) {
		continue;
	}
	?>
	<h2 class="cfbe-templates__group"><?php echo esc_html( $cfbe_group_text[0] ); ?></h2>
	<p class="description cfbe-templates__intro"><?php echo esc_html( $cfbe_group_text[1] ); ?></p>
	<div class="cfbe-templates">
	<?php
	foreach ( $cfbe_in_group as $cfbe_key => $cfbe_template ) :
		$cfbe_record  = Email_Templates::record( $cfbe_key );
		$cfbe_created = '' !== (string) ( $cfbe_record['email_id'] ?? '' );
		$cfbe_usage   = Email_Templates::usage( $cfbe_key );
		$cfbe_texts   = Email_Templates::texts( $cfbe_key );
		$cfbe_here    = is_array( $cfbe_notice ) && ( $cfbe_notice['template'] ?? '' ) === $cfbe_key;
		if ( $cfbe_here && ! empty( $cfbe_notice['texts'] ) ) {
			$cfbe_texts = array_merge( $cfbe_texts, (array) $cfbe_notice['texts'] );
		}
		$cfbe_list   = (string) ( $cfbe_record['list_id'] ?? '' );
		$cfbe_list   = '' !== $cfbe_list ? $cfbe_list : $cfbe_default;
		$cfbe_id     = 'cfbe-template-' . $cfbe_key;
		$cfbe_event  = (string) ( $cfbe_template['event'] ?? '' );
		$cfbe_wc     = 'replaces' === $cfbe_group && isset( $cfbe_titles[ $cfbe_event ] ) ? $cfbe_titles[ $cfbe_event ] : '';
		$cfbe_stale  = $cfbe_created && '' !== (string) ( $cfbe_record['design'] ?? '' ) && $cfbe_record['design'] !== $cfbe_hash;
		$cfbe_status = __( 'Not created yet', 'connector-for-bluefox-email' );
		$cfbe_class  = '';
		if ( $cfbe_created ) {
			$cfbe_status = $cfbe_usage['used'] ? __( 'In use', 'connector-for-bluefox-email' ) : __( 'Created, not in use', 'connector-for-bluefox-email' );
			$cfbe_class  = $cfbe_usage['used'] ? 'is-used' : 'is-created';
		}
		?>
		<details class="cfbe-template" id="<?php echo esc_attr( $cfbe_id ); ?>" <?php echo $cfbe_open === $cfbe_key ? 'open' : ''; ?>>
			<summary>
				<span class="cfbe-template__head">
					<span class="cfbe-template__title"><?php echo esc_html( $cfbe_template['label'] ); ?></span>
					<span class="cfbe-template__what"><?php echo esc_html( $cfbe_template['what'] ); ?></span>
				</span>
				<span class="cfbe-template__type is-<?php echo esc_attr( $cfbe_template['type'] ); ?>"><?php echo esc_html( 'triggered' === $cfbe_template['type'] ? __( 'Marketing', 'connector-for-bluefox-email' ) : __( 'Transactional', 'connector-for-bluefox-email' ) ); ?></span>
				<span class="cfbe-template__status <?php echo esc_attr( $cfbe_class ); ?>"><?php echo esc_html( $cfbe_status ); ?></span>
			</summary>

			<div class="cfbe-template__body">
				<?php if ( $cfbe_here ) : ?>
					<div class="notice inline notice-<?php echo esc_attr( 'success' === $cfbe_notice['type'] ? 'success' : ( 'conflict' === $cfbe_notice['type'] ? 'warning' : 'error' ) ); ?>"><p><?php echo esc_html( (string) $cfbe_notice['message'] ); ?></p></div>
				<?php endif; ?>
				<?php if ( $cfbe_stale ) : ?>
					<div class="notice inline notice-info"><p><?php esc_html_e( 'The design changed since this email was last sent to bluefox. Click “Update in bluefox” to apply it.', 'connector-for-bluefox-email' ); ?></p></div>
				<?php endif; ?>

				<dl class="cfbe-template__facts">
					<dt><?php esc_html_e( 'Why use it', 'connector-for-bluefox-email' ); ?></dt>
					<dd><?php echo esc_html( $cfbe_template['why'] ); ?></dd>
					<dt><?php esc_html_e( 'When it is sent', 'connector-for-bluefox-email' ); ?></dt>
					<dd><?php echo esc_html( $cfbe_template['when'] ); ?></dd>
					<dt><?php esc_html_e( 'Email type', 'connector-for-bluefox-email' ); ?></dt>
					<dd>
						<?php
						echo esc_html(
							'triggered' === $cfbe_template['type']
								? __( 'Marketing (triggered): bluefox delivers it only to active subscribers of the chosen list, with an unsubscribe link.', 'connector-for-bluefox-email' )
								: __( 'Transactional: a service email every recipient gets, no unsubscribe link needed.', 'connector-for-bluefox-email' )
						);
						?>
					</dd>
					<?php if ( '' !== $cfbe_wc ) : ?>
						<dt><?php esc_html_e( 'WooCommerce', 'connector-for-bluefox-email' ); ?></dt>
						<dd>
							<?php
							if ( $cfbe_created && WC_Email_Switch::replaced( $cfbe_event ) ) {
								/* translators: %s: WooCommerce email name */
								echo esc_html( sprintf( __( 'WooCommerce’s “%s” email is switched off: customers get this email instead.', 'connector-for-bluefox-email' ), $cfbe_wc ) );
							} else {
								/* translators: %s: WooCommerce email name */
								echo esc_html( sprintf( __( 'WooCommerce sends its own “%s” email for this event.', 'connector-for-bluefox-email' ), $cfbe_wc ) );
							}
							?>
						</dd>
					<?php endif; ?>
					<?php if ( $cfbe_created ) : ?>
						<dt><?php esc_html_e( 'Status', 'connector-for-bluefox-email' ); ?></dt>
						<dd>
							<?php echo esc_html( $cfbe_usage['text'] ); ?>
							<?php if ( '' !== $cfbe_usage['url'] ) : ?>
								<a href="<?php echo esc_url( $cfbe_usage['url'] ); ?>"><?php esc_html_e( 'Open', 'connector-for-bluefox-email' ); ?></a>
							<?php endif; ?>
							<br><span class="description">
								<?php
								/* translators: %s: bluefox email ID */
								printf( esc_html__( 'bluefox email ID: %s', 'connector-for-bluefox-email' ), '<code>' . esc_html( (string) $cfbe_record['email_id'] ) . '</code>' );
								?>
							</span>
						</dd>
					<?php else : ?>
						<dt><?php esc_html_e( 'On “Create”', 'connector-for-bluefox-email' ); ?></dt>
						<dd><?php echo esc_html( $cfbe_template['on_create'] ); ?></dd>
					<?php endif; ?>
				</dl>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $cfbe_action ); ?>">
					<input type="hidden" name="template" value="<?php echo esc_attr( $cfbe_key ); ?>">
					<?php wp_nonce_field( $cfbe_action . '_' . $cfbe_key ); ?>
					<h3 class="cfbe-template__subhead"><?php esc_html_e( 'Texts', 'connector-for-bluefox-email' ); ?></h3>
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
												: __( 'Only active subscribers of this list receive the email.', 'connector-for-bluefox-email' )
										);
										?>
									</p>
								</td>
							</tr>
						<?php endif; ?>
						<?php if ( ! $cfbe_created ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Use it', 'connector-for-bluefox-email' ); ?></th>
								<td>
									<label><input type="checkbox" name="assign" value="1" checked> <?php esc_html_e( 'Select the new email where the plugin needs it (recommended)', 'connector-for-bluefox-email' ); ?></label>
									<?php if ( '' !== $cfbe_wc ) : ?>
										<br><label><input type="checkbox" name="replace_wc" value="1" checked>
										<?php
										/* translators: %s: WooCommerce email name */
										echo esc_html( sprintf( __( 'Switch off WooCommerce’s “%s” email, so customers get only this one (recommended)', 'connector-for-bluefox-email' ), $cfbe_wc ) );
										?>
										</label>
									<?php endif; ?>
								</td>
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
<?php endforeach; ?>
