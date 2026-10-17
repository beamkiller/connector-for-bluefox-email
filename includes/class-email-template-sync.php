<?php
/**
 * Creates and updates the ready-made emails in bluefox.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Create → the email exists in bluefox and is wired up (coupon email, order / cart rule, the list's double
 * opt-in email). Update → only when nobody changed the email in bluefox since our last write (else the admin
 * confirms the overwrite), and never for emails converted to bluefox's visual editor.
 */
final class Email_Template_Sync {

	/**
	 * The bluefox client.
	 *
	 * @var Bluefox_Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client $client Client.
	 */
	public function __construct( Bluefox_Client $client ) {
		$this->client = $client;
	}

	/**
	 * Creates the email in bluefox.
	 *
	 * @param string $key      Template key.
	 * @param array  $texts    Texts.
	 * @param string $identity Sender identity ID ('' = project default).
	 * @param string $list_id  List (triggered emails: required; confirmation: the list to use it on).
	 * @param bool   $assign   Wire it up (coupon email, rule, double opt-in email).
	 * @param bool   $replace  The new rule switches off WooCommerce's own email for the event (where it has one).
	 * @return array|\WP_Error email_id, notes.
	 */
	public function create( string $key, array $texts, string $identity, string $list_id, bool $assign, bool $replace = true ) {
		$definition = Email_Templates::definitions()[ $key ] ?? null;
		if ( null === $definition ) {
			return new \WP_Error( 'cfbe_template_unknown', __( 'Unknown email template.', 'connector-for-bluefox-email' ) );
		}
		$type    = (string) $definition['type'];
		$payload = Email_Templates::render( $key, $texts );
		if ( '' !== $identity ) {
			$payload['senderIdentity'] = $identity;
		}
		if ( 'triggered' === $type ) {
			if ( '' === $list_id ) {
				return new \WP_Error( 'cfbe_template_list', __( 'Choose the subscriber list of this triggered email.', 'connector-for-bluefox-email' ) );
			}
			$payload['subscriberListId'] = $list_id;
		}

		$created = 'triggered' === $type ? $this->client->create_triggered_email( $payload ) : $this->client->create_transactional_email( $payload );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$email_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $created['_id'] ?? '' ) );
		if ( '' === $email_id ) {
			return new \WP_Error( 'cfbe_template_no_id', __( 'bluefox did not return the ID of the new email.', 'connector-for-bluefox-email' ) );
		}

		Email_Templates::save_record(
			$key,
			array(
				'email_id'       => $email_id,
				'type'           => $type,
				'texts'          => $texts,
				'list_id'        => $list_id,
				'remote_updated' => $this->remote_updated( $type, $email_id, $created ),
				'design'         => Email_Design::hash(),
			)
		);

		return array(
			'email_id' => $email_id,
			'notes'    => $assign ? $this->assign( $key, $definition, $email_id, $payload['name'], $list_id, $replace ) : array(),
		);
	}

	/**
	 * Updates the email in bluefox with new texts.
	 *
	 * @param string $key   Template key.
	 * @param array  $texts Texts.
	 * @param bool   $force Overwrite changes made in bluefox.
	 * @return true|\WP_Error Codes: cfbe_template_missing, cfbe_template_visual, cfbe_template_changed.
	 */
	public function update( string $key, array $texts, bool $force = false ) {
		$record = Email_Templates::record( $key );
		$id     = (string) ( $record['email_id'] ?? '' );
		$type   = (string) ( $record['type'] ?? '' );
		if ( '' === $id ) {
			return new \WP_Error( 'cfbe_template_missing', __( 'This email has not been created in bluefox yet.', 'connector-for-bluefox-email' ) );
		}

		$remote = $this->client->get_email( $type, $id );
		if ( is_wp_error( $remote ) ) {
			if ( 'cfbe_bluefox_not_found' === $remote->get_error_code() ) {
				Email_Templates::save_record( $key, array() );
				return new \WP_Error( 'cfbe_template_missing', __( 'The email no longer exists in bluefox. Create it again.', 'connector-for-bluefox-email' ) );
			}
			return $remote;
		}
		if ( 'html' !== ( $remote['type'] ?? '' ) ) {
			return new \WP_Error( 'cfbe_template_visual', __( 'This email was switched to bluefox’s visual editor, so it can only be edited in bluefox now.', 'connector-for-bluefox-email' ) );
		}
		$known = (string) ( $record['remote_updated'] ?? '' );
		if ( ! $force && '' !== $known && (string) ( $remote['updatedAt'] ?? '' ) !== $known ) {
			return new \WP_Error( 'cfbe_template_changed', __( 'This email was changed in bluefox after it was created here. Updating it replaces those changes.', 'connector-for-bluefox-email' ) );
		}

		$email   = Email_Templates::render( $key, $texts );
		$updated = $this->client->update_email(
			$type,
			$id,
			array(
				'subject'     => $email['subject'],
				'previewText' => $email['previewText'],
				'type'        => 'html',
				'document'    => $email['document'],
			)
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$record['texts']          = $texts;
		$record['remote_updated'] = $this->remote_updated( $type, $id, $updated );
		$record['design']         = Email_Design::hash();
		Email_Templates::save_record( $key, $record );
		return true;
	}

	/**
	 * Sends a test of the created email with sample data.
	 *
	 * @param string $key Template key.
	 * @param string $to  Recipient.
	 * @return true|\WP_Error
	 */
	public function test( string $key, string $to ) {
		$record = Email_Templates::record( $key );
		if ( '' === (string) ( $record['email_id'] ?? '' ) ) {
			return new \WP_Error( 'cfbe_template_missing', __( 'This email has not been created in bluefox yet.', 'connector-for-bluefox-email' ) );
		}
		$sent = $this->client->send_test_email( (string) $record['type'], (string) $record['email_id'], $to, Email_Templates::sample_data( $key ) );
		return is_wp_error( $sent ) ? $sent : true;
	}

	/**
	 * Wires a new email up where the plugin uses it.
	 *
	 * @param string $key        Template key.
	 * @param array  $definition Definition.
	 * @param string $email_id   New email ID.
	 * @param string $name       Email name.
	 * @param string $list_id    List.
	 * @param bool   $replace    Switch off WooCommerce's own email for the event.
	 * @return string[] Notes for the admin.
	 */
	private function assign( string $key, array $definition, string $email_id, string $name, string $list_id, bool $replace ): array {
		if ( 'confirm' === $key ) {
			if ( '' === $list_id ) {
				return array( __( 'Choose it as the double opt-in email of your list in bluefox.', 'connector-for-bluefox-email' ) );
			}
			$result = $this->client->update_list(
				$list_id,
				array(
					'doubleOptIn' => array(
						'active'       => true,
						'emailId'      => $email_id,
						'redirectLink' => Settings::thankyou_url(),
					),
				)
			);
			if ( is_wp_error( $result ) ) {
				/* translators: %s: error message */
				return array( sprintf( __( 'The list could not be updated (%s): choose the email as its double opt-in email in bluefox.', 'connector-for-bluefox-email' ), $result->get_error_message() ) );
			}
			$record                 = Email_Templates::record( $key );
			$record['used_on_list'] = $list_id;
			Email_Templates::save_record( $key, $record );
			return array( __( 'Double opt-in is on for the list, with this email and your thank-you page as the redirect link.', 'connector-for-bluefox-email' ) );
		}

		if ( 'welcome_coupon' === $key ) {
			$coupon             = (array) get_option( Settings::option_name( 'coupon' ), array() );
			$coupon['email_id'] = $email_id;
			update_option( Settings::option_name( 'coupon' ), $coupon );
			return array( __( 'Selected as the coupon email on the Coupon tab.', 'connector-for-bluefox-email' ) );
		}

		if ( empty( $definition['event'] ) ) {
			return array();
		}
		$type    = (string) $definition['type'];
		$event   = (string) $definition['event'];
		$replace = $replace && Woo_Events\Rules::TYPE_TRANSACTIONAL === $type && isset( Woo_Events\Rules::WC_EMAILS[ $event ] );
		$this->remember_email( $type, $email_id, $name );

		$replaced = '';
		if ( $replace ) {
			/* translators: %s: WooCommerce email name, e.g. Processing order */
			$replaced = ' ' . sprintf( __( 'WooCommerce’s “%s” email is switched off for customers, so they get only this email.', 'connector-for-bluefox-email' ), Woo_Events\Rules::wc_email_titles()[ $event ] ?? $event );
		}

		$option   = Settings::option_name( Woo_Events\Module::AREA );
		$settings = (array) get_option( $option, array() );
		$rules    = (array) ( $settings['rules'] ?? array() );
		foreach ( $rules as $i => $rule ) {
			if ( ! is_array( $rule ) || ( $rule['event'] ?? '' ) !== $event ) {
				continue;
			}
			if ( ! empty( $rule['email_id'] ) ) {
				return array( __( 'A rule for this event already exists: select the new email in it if you want to use it.', 'connector-for-bluefox-email' ) );
			}
			// A rule without an email (e.g. added before the email existed): fill it in.
			$rules[ $i ]       = array_merge(
				$rule,
				array(
					'type'       => $type,
					'email_id'   => $email_id,
					'enabled'    => true,
					'replace_wc' => $replace,
				)
			);
			$settings['rules'] = $rules;
			update_option( $option, $settings );
			return array( __( 'The new email was selected in the existing rule for this event, and the rule was switched on.', 'connector-for-bluefox-email' ) . $replaced );
		}
		$rules[]           = array(
			'id'         => Woo_Events\Rules::new_id(),
			'event'      => $event,
			'type'       => $type,
			'email_id'   => $email_id,
			'delay'      => (int) ( $definition['delay'] ?? 0 ),
			'delay_unit' => (string) ( $definition['delay_unit'] ?? 'minutes' ),
			'enabled'    => true,
			'replace_wc' => $replace,
		);
		$settings['rules'] = $rules;
		update_option( $option, $settings );
		return array( __( 'A rule that sends this email was added and switched on.', 'connector-for-bluefox-email' ) . $replaced );
	}

	/**
	 * Adds the new email to the cached email lists, so the rule tables show its name.
	 *
	 * @param string $type     Email type.
	 * @param string $email_id Email ID.
	 * @param string $name     Email name.
	 */
	private function remember_email( string $type, string $email_id, string $name ): void {
		$cache = get_option( Woo_Events\Settings_Tab::EMAILS_OPTION, array() );
		$cache = is_array( $cache ) ? $cache : array();
		if ( ! isset( $cache[ $type ] ) || ! is_array( $cache[ $type ] ) ) {
			$cache[ $type ] = array();
		}
		$cache[ $type ][ $email_id ] = $name;
		update_option( Woo_Events\Settings_Tab::EMAILS_OPTION, $cache, false );
	}

	/**
	 * The updatedAt of bluefox after our write (from the response, else read back).
	 *
	 * @param string $type     Email type.
	 * @param string $email_id Email ID.
	 * @param mixed  $response Create / update response.
	 */
	private function remote_updated( string $type, string $email_id, $response ): string {
		if ( is_array( $response ) && ! empty( $response['updatedAt'] ) ) {
			return (string) $response['updatedAt'];
		}
		$remote = $this->client->get_email( $type, $email_id );
		return is_array( $remote ) ? (string) ( $remote['updatedAt'] ?? '' ) : '';
	}
}
