<?php
/**
 * Privacy / GDPR integration: suggested policy text, personal data exporter and eraser.
 *
 * @package CFBE
 */

namespace CFBE;

use CFBE\Admin\Admin_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into the WordPress privacy tools (Tools → Export / Erase Personal Data, Settings → Privacy).
 *
 * Local data: the `cfbe_subscribers` row of the address and the user's `cfbe_*` / `_cfbe_*` meta.
 * Data held by bluefox.email is not touched unless the `api.privacy_delete_remote` setting is on, in which case
 * the eraser also deletes the bluefox contact (DELETE contacts/{email}, which removes it from every list).
 */
final class Privacy {

	const SLUG        = 'connector-for-bluefox-email';
	const SETTING     = 'privacy_delete_remote';
	const SAVE_ACTION = 'cfbe_save_privacy';

	/**
	 * Repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Returns a Bluefox_Client or WP_Error (tests inject one).
	 *
	 * @var callable
	 */
	private $client_factory;

	/**
	 * Constructor.
	 *
	 * @param Subscriber_Repository $repository     Repository.
	 * @param callable|null         $client_factory Returns Bluefox_Client|\WP_Error; defaults to the stored settings.
	 */
	public function __construct( Subscriber_Repository $repository, ?callable $client_factory = null ) {
		$this->repository     = $repository;
		$this->client_factory = $client_factory ? $client_factory : array( Bluefox_Client::class, 'from_settings' );
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
		add_filter( 'cfbe_settings_defaults_api', array( $this, 'add_default' ) );
		add_filter( 'cfbe_sanitize_settings_api', array( $this, 'sanitize' ), 10, 3 );
		add_filter( 'cfbe_settings_tabs', array( $this, 'add_tab' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'save' ) );
		// WooCommerce's eraser ("Remove personal data from orders"): our consent record on the order goes too.
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( $this, 'erase_order' ) );
	}

	/**
	 * Removes the checkout opt-in consent IP from an order being anonymised by WooCommerce.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function erase_order( $order ): void {
		if ( ! is_object( $order ) || ! is_callable( array( $order, 'delete_meta_data' ) ) ) {
			return;
		}
		$order->delete_meta_data( Checkout_Optin::META_IP );
		$order->save();
	}

	/**
	 * Welcome and recovery coupons issued to an address: unused ones are deleted, used ones keep no trace of it.
	 *
	 * @param string $email Email address.
	 * @return int Number of coupons changed.
	 */
	private function erase_coupons( string $email ): int {
		if ( ! class_exists( '\WC_Coupon' ) || ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$email = strtolower( trim( $email ) );
		$ids   = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 100,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Rare (personal data erasure only).
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => Coupon_Factory::META,
						'value' => $email,
					),
					array(
						'key'   => '_cfbe_cart_email',
						'value' => $email,
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			$coupon = new \WC_Coupon( (int) $id );
			if ( 0 === (int) $coupon->get_usage_count() ) {
				$coupon->delete( true );
				continue;
			}
			// Used up (single use): no longer needs the address to stay locked.
			$coupon->set_description( __( 'Newsletter coupon (personal data erased)', 'connector-for-bluefox-email' ) );
			$coupon->set_email_restrictions( array() );
			$coupon->set_usage_limit( max( 1, (int) $coupon->get_usage_count() ) );
			$coupon->delete_meta_data( Coupon_Factory::META );
			$coupon->delete_meta_data( '_cfbe_cart_email' );
			$coupon->save();
		}
		return count( $ids );
	}

	/**
	 * Suggested privacy policy text (Settings → Privacy → Policy guide).
	 */
	public function add_policy_content(): void {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( __( 'Connector for bluefox.email', 'connector-for-bluefox-email' ), wp_kses_post( wpautop( $this->policy_text(), false ) ) );
		}
	}

	/**
	 * The suggested text.
	 */
	public function policy_text(): string {
		$parts = array(
			'<strong class="privacy-policy-tutorial">' . __( 'Suggested text:', 'connector-for-bluefox-email' ) . '</strong> '
			. __( 'When you subscribe to our newsletter (signup form, popup, checkout checkbox or another form on this site), we send your email address, your name if you give it, and tags describing your interests and where you signed up to bluefox.email, the email service we use to send our newsletters and service emails. bluefox.email sends you a confirmation email; you are only subscribed after you confirm.', 'connector-for-bluefox-email' ),
			$this->stored_data_text(),
			__( 'If we have enabled it, we also keep the bluefox.email contact of our registered users and customers up to date with customer data (such as name, number of orders and total spent), and some emails sent by this site (for example order emails) are delivered through bluefox.email. bluefox.email informs us when an email bounces, is marked as spam, or when you unsubscribe or pause your subscription; we store this with your account on this site.', 'connector-for-bluefox-email' ),
			sprintf(
				/* translators: 1: bluefox.email privacy policy URL, 2: bluefox.email terms URL */
				__( 'bluefox.email processes this data on our behalf. For where and how it stores data, see the <a href="%1$s">bluefox.email privacy policy</a> and <a href="%2$s">terms of use</a>.', 'connector-for-bluefox-email' ),
				'https://bluefox.email/privacy-policy',
				'https://bluefox.email/terms-of-use'
			),
			__( 'You can unsubscribe at any time with the link in every newsletter. You can ask us to export or erase the data stored on this site, and to delete your contact at bluefox.email.', 'connector-for-bluefox-email' ),
		);
		return implode( "\n\n", $parts );
	}

	/**
	 * Policy paragraph about the data stored on this site, matching the IP setting.
	 */
	private function stored_data_text(): string {
		switch ( Consent_Ip::mode() ) {
			case Consent_Ip::MODE_OFF:
				$text = __( 'On this site we store your email address, name, the signup source and list, the text of the consent you gave and the time of your consent and confirmation, so that we can prove your consent. We do not store your IP address.', 'connector-for-bluefox-email' );
				break;
			case Consent_Ip::MODE_FULL:
				$text = __( 'On this site we store your email address, name, the signup source and list, the text of the consent you gave, your IP address and the time of your consent and confirmation, so that we can prove your consent.', 'connector-for-bluefox-email' );
				break;
			default:
				$text = __( 'On this site we store your email address, name, the signup source and list, the text of the consent you gave, a shortened (anonymised) form of your IP address and the time of your consent and confirmation, so that we can prove your consent.', 'connector-for-bluefox-email' );
		}
		$months = Consent_Ip::retention_months();
		if ( Consent_Ip::MODE_OFF !== Consent_Ip::mode() && $months > 0 ) {
			/* translators: %d: number of months */
			$text .= ' ' . sprintf( _n( 'The IP address is erased after %d month.', 'The IP address is erased after %d months.', $months, 'connector-for-bluefox-email' ), $months );
		}
		return $text . ' ' . __( 'If we give you a welcome coupon, its code, expiry date and whether it was used are also stored in bluefox.email.', 'connector-for-bluefox-email' );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 */
	public function register_exporter( $exporters ): array {
		$exporters               = (array) $exporters;
		$exporters[ self::SLUG ] = array(
			'exporter_friendly_name' => __( 'Newsletter (bluefox.email)', 'connector-for-bluefox-email' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 */
	public function register_eraser( $erasers ): array {
		$erasers               = (array) $erasers;
		$erasers[ self::SLUG ] = array(
			'eraser_friendly_name' => __( 'Newsletter (bluefox.email)', 'connector-for-bluefox-email' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Personal data exporter: signup rows + the user's plugin meta. Everything fits on one page.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (1-based).
	 * @return array{data: array, done: bool}
	 */
	public function export( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- All data fits on page 1.
		$email = (string) $email;
		$data  = array();

		foreach ( $this->repository->export_by_email( $email ) as $row ) {
			$data[] = array(
				'group_id'          => 'cfbe-newsletter',
				'group_label'       => __( 'Newsletter signup', 'connector-for-bluefox-email' ),
				'group_description' => __( 'Newsletter signup and consent records stored by Connector for bluefox.email.', 'connector-for-bluefox-email' ),
				'item_id'           => 'cfbe-signup-' . (int) ( $row['id'] ?? 0 ),
				'data'              => $this->row_fields( $row ),
			);
		}

		$meta = $this->user_meta( $email );
		if ( $meta ) {
			$fields = array();
			foreach ( $meta as $key => $value ) {
				$fields[] = array(
					'name'  => $key,
					'value' => is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value ),
				);
			}
			$data[] = array(
				'group_id'          => 'cfbe-user',
				'group_label'       => __( 'Newsletter status', 'connector-for-bluefox-email' ),
				'group_description' => __( 'Subscription, bounce and complaint status reported by bluefox.email.', 'connector-for-bluefox-email' ),
				'item_id'           => 'cfbe-user',
				'data'              => $fields,
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Personal data eraser: deletes the local rows and user meta; optionally the bluefox contact.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page (1-based).
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- All data fits on page 1.
		$email    = (string) $email;
		$removed  = $this->repository->delete_by_email( $email ) > 0;
		$retained = false;
		$messages = array();

		$removed = $this->erase_coupons( $email ) > 0 || $removed;

		$user = get_user_by( 'email', $email );
		if ( $user ) {
			foreach ( array_keys( $this->user_meta( $email ) ) as $key ) {
				$removed = delete_user_meta( $user->ID, $key ) || $removed;
			}
		}

		if ( Settings::get( 'api', self::SETTING ) ) {
			$client = call_user_func( $this->client_factory );
			$result = is_wp_error( $client ) ? $client : $client->delete_contact( $email );
			if ( ! is_wp_error( $result ) ) {
				$removed    = true;
				$messages[] = __( 'The contact was deleted from bluefox.email (all lists).', 'connector-for-bluefox-email' );
			} elseif ( 'cfbe_bluefox_not_found' === $result->get_error_code() ) {
				$messages[] = __( 'No contact with this email address exists in bluefox.email.', 'connector-for-bluefox-email' );
			} else {
				$retained   = true;
				$messages[] = sprintf(
					/* translators: %s: error message */
					__( 'The contact could not be deleted from bluefox.email (%s). Delete it there manually or run the eraser again.', 'connector-for-bluefox-email' ),
					$result->get_error_message()
				);
			}
		} else {
			$retained   = true;
			$messages[] = __( 'Only the data stored on this site was erased. The contact in bluefox.email was not deleted: delete it in bluefox.email (Contacts), or turn on “Also delete the bluefox.email contact” on the plugin’s Privacy tab.', 'connector-for-bluefox-email' );
		}

		return array(
			'items_removed'  => $removed,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Adds the `privacy_delete_remote` default (off) to the `api` area.
	 *
	 * @param array $defaults Defaults.
	 */
	public function add_default( $defaults ): array {
		$defaults                                  = (array) $defaults;
		$defaults[ self::SETTING ]                 = false;
		$defaults[ Consent_Ip::SETTING_MODE ]      = Consent_Ip::DEFAULT_MODE;
		$defaults[ Consent_Ip::SETTING_RETENTION ] = Consent_Ip::DEFAULT_RETENTION;
		$defaults[ Rate_Limiter::SETTING_HEADER ]  = '';
		return $defaults;
	}

	/**
	 * Sanitizes the setting; keeps the stored value when the posted form does not contain it (e.g. the General tab).
	 *
	 * @param array $clean    Sanitized values.
	 * @param array $input    Raw input.
	 * @param array $previous Stored values.
	 */
	public function sanitize( $clean, $input = array(), $previous = array() ): array {
		$clean    = (array) $clean;
		$input    = (array) $input;
		$previous = (array) $previous;
		if ( array_key_exists( self::SETTING, $input ) ) {
			$clean[ self::SETTING ] = ! empty( $input[ self::SETTING ] );
		} else {
			$clean[ self::SETTING ] = ! empty( $previous[ self::SETTING ] );
		}
		// Forms without the IP settings (they live on the Privacy tab) keep the stored values.
		$mode = (string) ( $input[ Consent_Ip::SETTING_MODE ] ?? $previous[ Consent_Ip::SETTING_MODE ] ?? Consent_Ip::DEFAULT_MODE );

		$clean[ Consent_Ip::SETTING_MODE ]      = in_array( $mode, Consent_Ip::MODES, true ) ? $mode : Consent_Ip::DEFAULT_MODE;
		$clean[ Consent_Ip::SETTING_RETENTION ] = min( 120, absint( $input[ Consent_Ip::SETTING_RETENTION ] ?? $previous[ Consent_Ip::SETTING_RETENTION ] ?? Consent_Ip::DEFAULT_RETENTION ) );
		$header                                 = (string) ( $input[ Rate_Limiter::SETTING_HEADER ] ?? $previous[ Rate_Limiter::SETTING_HEADER ] ?? '' );
		$clean[ Rate_Limiter::SETTING_HEADER ]  = isset( Rate_Limiter::HEADERS[ $header ] ) ? $header : '';
		return $clean;
	}

	/**
	 * Adds the Privacy tab (position 105).
	 *
	 * @param array $tabs Tabs.
	 */
	public function add_tab( $tabs ): array {
		$tabs            = (array) $tabs;
		$tabs['privacy'] = array(
			'label'    => __( 'Privacy', 'connector-for-bluefox-email' ),
			'render'   => array( $this, 'render_tab' ),
			'position' => 105,
		);
		return $tabs;
	}

	/**
	 * Renders the Privacy tab.
	 */
	public function render_tab(): void {
		$enabled   = (bool) Settings::get( 'api', self::SETTING );
		$ip_mode   = Consent_Ip::mode();
		$retention = Consent_Ip::retention_months();
		$modes     = array(
			Consent_Ip::MODE_ANONYMIZED => __( 'Anonymised (recommended): the last part is removed, e.g. 203.0.113.0', 'connector-for-bluefox-email' ),
			Consent_Ip::MODE_FULL       => __( 'Full IP address', 'connector-for-bluefox-email' ),
			Consent_Ip::MODE_OFF        => __( 'Do not store IP addresses', 'connector-for-bluefox-email' ),
		);
		?>
		<p><?php esc_html_e( 'The plugin adds suggested text to Settings → Privacy (policy guide) and handles Tools → Export / Erase Personal Data for newsletter signups and the bluefox status stored with users.', 'connector-for-bluefox-email' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
			<?php wp_nonce_field( self::SAVE_ACTION ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'IP address with consent', 'connector-for-bluefox-email' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $modes as $cfbe_value => $cfbe_label ) : ?>
								<label><input type="radio" name="<?php echo esc_attr( Consent_Ip::SETTING_MODE ); ?>" value="<?php echo esc_attr( $cfbe_value ); ?>" <?php checked( $ip_mode, $cfbe_value ); ?>> <?php echo esc_html( $cfbe_label ); ?></label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Stored with every signup (forms, checkout, form plugins) as part of the proof of consent. bluefox.email’s double opt-in confirmation is the main proof; the IP is optional extra evidence and personal data under the GDPR.', 'connector-for-bluefox-email' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cfbe-ip-retention"><?php esc_html_e( 'Erase IP addresses after', 'connector-for-bluefox-email' ); ?></label></th>
					<td>
						<input type="number" min="0" max="120" class="small-text" id="cfbe-ip-retention" name="<?php echo esc_attr( Consent_Ip::SETTING_RETENTION ); ?>" value="<?php echo esc_attr( (string) $retention ); ?>"> <?php esc_html_e( 'months', 'connector-for-bluefox-email' ); ?>
						<p class="description"><?php esc_html_e( 'A daily job removes older IP addresses from signup records and checkout orders. 0 = keep them as long as the record exists.', 'connector-for-bluefox-email' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cfbe-ip-header"><?php esc_html_e( 'Visitor IP address', 'connector-for-bluefox-email' ); ?></label></th>
					<td>
						<select id="cfbe-ip-header" name="<?php echo esc_attr( Rate_Limiter::SETTING_HEADER ); ?>">
							<?php
							foreach (
								array(
									''           => __( 'Direct connection (REMOTE_ADDR)', 'connector-for-bluefox-email' ),
									'cloudflare' => __( 'Cloudflare (CF-Connecting-IP)', 'connector-for-bluefox-email' ),
									'forwarded'  => __( 'Proxy / load balancer (X-Forwarded-For)', 'connector-for-bluefox-email' ),
									'real_ip'    => __( 'Proxy (X-Real-IP)', 'connector-for-bluefox-email' ),
								) as $cfbe_value => $cfbe_label
							) :
								?>
								<option value="<?php echo esc_attr( $cfbe_value ); ?>" <?php selected( (string) Settings::get( 'api', Rate_Limiter::SETTING_HEADER ), $cfbe_value ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Used for rate limiting and the consent record. Behind Cloudflare or a proxy every visitor arrives from the proxy’s address, so the signup limits would hit everyone at once: choose the header your proxy sets. Only choose a header your server really receives from a trusted proxy, because visitors can send these headers themselves.', 'connector-for-bluefox-email' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Erase requests', 'connector-for-bluefox-email' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::SETTING ); ?>" value="1" <?php checked( $enabled ); ?>>
							<?php esc_html_e( 'Also delete the bluefox.email contact', 'connector-for-bluefox-email' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When an erasure request is processed, the contact is deleted in bluefox.email too (removed from every list, cannot be undone). When off, only the data on this site is erased.', 'connector-for-bluefox-email' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Saves the Privacy tab (admin-post, nonce + capability).
	 */
	public function save(): void {
		check_admin_referer( self::SAVE_ACTION );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), '', array( 'response' => 403 ) );
		}
		$values                  = (array) get_option( Settings::option_name( 'api' ), array() );
		$old_mode                = Consent_Ip::mode();
		$values[ self::SETTING ] = ! empty( $_POST[ self::SETTING ] );
		$mode                    = isset( $_POST[ Consent_Ip::SETTING_MODE ] ) ? sanitize_key( wp_unslash( $_POST[ Consent_Ip::SETTING_MODE ] ) ) : Consent_Ip::DEFAULT_MODE;

		$values[ Consent_Ip::SETTING_MODE ]      = in_array( $mode, Consent_Ip::MODES, true ) ? $mode : Consent_Ip::DEFAULT_MODE;
		$values[ Consent_Ip::SETTING_RETENTION ] = isset( $_POST[ Consent_Ip::SETTING_RETENTION ] ) ? min( 120, absint( $_POST[ Consent_Ip::SETTING_RETENTION ] ) ) : Consent_Ip::DEFAULT_RETENTION;
		$header                                  = isset( $_POST[ Rate_Limiter::SETTING_HEADER ] ) ? sanitize_key( wp_unslash( $_POST[ Rate_Limiter::SETTING_HEADER ] ) ) : '';
		$values[ Rate_Limiter::SETTING_HEADER ]  = isset( Rate_Limiter::HEADERS[ $header ] ) ? $header : '';
		update_option( Settings::option_name( 'api' ), $values );
		( new Consent_Ip( $this->repository, new Jobs() ) )->mode_changed( $old_mode, Consent_Ip::mode() );

		wp_safe_redirect( add_query_arg( 'tab', 'privacy', admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ) );
		exit;
	}

	/**
	 * Export fields of a signup row.
	 *
	 * @param array $row Row.
	 */
	private function row_fields( array $row ): array {
		$labels = array(
			'email'          => __( 'Email', 'connector-for-bluefox-email' ),
			'name'           => __( 'Name', 'connector-for-bluefox-email' ),
			'source'         => __( 'Signup source', 'connector-for-bluefox-email' ),
			'list_id'        => __( 'bluefox list', 'connector-for-bluefox-email' ),
			'tags'           => __( 'Tags', 'connector-for-bluefox-email' ),
			'status'         => __( 'Signup status', 'connector-for-bluefox-email' ),
			'list_status'    => __( 'Subscription status', 'connector-for-bluefox-email' ),
			'consent_text'   => __( 'Consent text', 'connector-for-bluefox-email' ),
			'consent_ip'     => __( 'Consent IP address', 'connector-for-bluefox-email' ),
			'consent_at'     => __( 'Consent time (UTC)', 'connector-for-bluefox-email' ),
			'confirmed_at'   => __( 'Confirmation time (UTC)', 'connector-for-bluefox-email' ),
			'coupon_id'      => __( 'Welcome coupon ID', 'connector-for-bluefox-email' ),
			'order_id'       => __( 'Order ID', 'connector-for-bluefox-email' ),
			'created_at'     => __( 'Created (UTC)', 'connector-for-bluefox-email' ),
			'list_status_at' => __( 'Subscription status changed (UTC)', 'connector-for-bluefox-email' ),
		);
		$fields = array();
		foreach ( $labels as $key => $label ) {
			$value = $row[ $key ] ?? null;
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}
			if ( null === $value || '' === (string) $value ) {
				continue;
			}
			$fields[] = array(
				'name'  => $label,
				'value' => (string) $value,
			);
		}
		return $fields;
	}

	/**
	 * The plugin's meta (`cfbe_*`, `_cfbe_*`) of the user with this email.
	 *
	 * @param string $email Email address.
	 * @return array Meta key => value.
	 */
	private function user_meta( string $email ): array {
		$user = '' !== $email ? get_user_by( 'email', $email ) : false;
		if ( ! $user ) {
			return array();
		}
		$meta = array();
		foreach ( (array) get_user_meta( $user->ID ) as $key => $values ) {
			if ( 0 === strpos( (string) $key, 'cfbe_' ) || 0 === strpos( (string) $key, '_cfbe_' ) ) {
				$meta[ (string) $key ] = maybe_unserialize( is_array( $values ) ? ( $values[0] ?? '' ) : $values );
			}
		}
		return $meta;
	}
}
