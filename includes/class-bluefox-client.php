<?php
/**
 * REST API client for bluefox.email.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Thin client for https://api.bluefox.email (project-scoped v1 endpoints).
 *
 * Every method returns the decoded `result` (array) or a \WP_Error whose code is
 * `cfbe_bluefox_<error name>` (e.g. `cfbe_bluefox_validation_error`), `cfbe_bluefox_not_found`,
 * `cfbe_bluefox_invalid_json` or a transport error from wp_remote_request().
 *
 * Not final: it is the external boundary that other modules mock in unit tests.
 */
class Bluefox_Client {

	const API_BASE = 'https://api.bluefox.email/v1/projectId/';
	const TIMEOUT  = 10;

	/**
	 * Project ID.
	 *
	 * @var string
	 */
	private $project_id;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @param string $project_id Project ID.
	 * @param string $api_key    API key.
	 */
	public function __construct( string $project_id, string $api_key ) {
		$this->project_id = $project_id;
		$this->api_key    = $api_key;
	}

	/**
	 * The API key in use: the CFBE_BLUEFOX_API_KEY constant wins over the stored option.
	 */
	public static function configured_api_key(): string {
		if ( defined( 'CFBE_BLUEFOX_API_KEY' ) ) {
			return (string) CFBE_BLUEFOX_API_KEY;
		}
		return (string) ( ( (array) get_option( 'cfbe_api', array() ) )['api_key'] ?? '' );
	}

	/**
	 * Builds a client from the `cfbe_api` option; the CFBE_BLUEFOX_API_KEY constant wins over the stored key.
	 *
	 * @return Bluefox_Client|\WP_Error
	 */
	public static function from_settings() {
		$options    = (array) get_option( 'cfbe_api', array() );
		$project_id = trim( (string) ( $options['project_id'] ?? '' ) );
		$api_key    = self::configured_api_key();

		if ( '' === $project_id || '' === $api_key ) {
			// Also called while the plugin boots (before `init`), when translations must not load yet.
			$message = did_action( 'init' )
				? __( 'The bluefox connection is not configured (Project ID / API key).', 'connector-for-bluefox-email' )
				: 'The bluefox connection is not configured (Project ID / API key).';
			return new \WP_Error( 'cfbe_not_configured', $message );
		}
		return new self( $project_id, $api_key );
	}

	/**
	 * Project details.
	 *
	 * @return array|\WP_Error
	 */
	public function get_project() {
		return $this->request( 'GET', '' );
	}

	/**
	 * Subscriber lists (max 30).
	 *
	 * @return array|\WP_Error List items.
	 */
	public function get_lists() {
		$result = $this->request( 'GET', 'subscriber-lists?limit=30' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
	}

	/**
	 * One subscriber's status on a list. 404 → `cfbe_bluefox_not_found`.
	 *
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @return array|\WP_Error
	 */
	public function get_subscriber( string $list_id, string $email ) {
		return $this->request( 'GET', $this->subscriber_path( $list_id, $email ) );
	}

	/**
	 * Subscribes a contact (creates it if needed). Triggers DOI when enabled and no status is sent.
	 *
	 * @param string $list_id List ID.
	 * @param array  $data    email, name, status, tags, custom fields.
	 * @return array|\WP_Error
	 */
	public function add_subscriber( string $list_id, array $data ) {
		return $this->request( 'POST', 'subscriber-lists/' . rawurlencode( $list_id ) . '/subscribers', $data );
	}

	/**
	 * Updates a subscriber on a list (status, name ...).
	 *
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @param array  $data    Fields to change.
	 * @return array|\WP_Error
	 */
	public function update_subscriber( string $list_id, string $email, array $data ) {
		return $this->request( 'PATCH', $this->subscriber_path( $list_id, $email ), $data );
	}

	/**
	 * Resends the DOI email to a still-unverified subscriber.
	 *
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @return array|\WP_Error
	 */
	public function resend_verification( string $list_id, string $email ) {
		return $this->request( 'POST', 'subscriber-lists/' . rawurlencode( $list_id ) . '/contacts/' . rawurlencode( $email ) . '/resend-verification-email' );
	}

	/**
	 * One contact (tags, custom fields as flat keys).
	 *
	 * @param string $email Email address.
	 * @return array|\WP_Error
	 */
	public function get_contact( string $email ) {
		return $this->request( 'GET', 'contacts/' . rawurlencode( $email ) );
	}

	/**
	 * Custom contact field definitions (e.g. the required `name` field).
	 *
	 * @return array|\WP_Error Field items.
	 */
	public function get_contact_fields() {
		$result = $this->request( 'GET', 'contacts/fields' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
	}

	/**
	 * Creates a custom contact field definition.
	 *
	 * @param string $name Field name.
	 * @param string $type string | number | boolean | date.
	 * @return array|\WP_Error
	 */
	public function create_contact_field( string $name, string $type ) {
		return $this->request(
			'POST',
			'contacts/fields',
			array(
				'name' => $name,
				'type' => $type,
			)
		);
	}

	/**
	 * Updates contact fields (custom fields as flat keys, tags).
	 *
	 * @param string $email  Email address.
	 * @param array  $fields Fields.
	 * @return array|\WP_Error
	 */
	public function update_contact( string $email, array $fields ) {
		return $this->request( 'PATCH', 'contacts/' . rawurlencode( $email ), $fields );
	}

	/**
	 * Sends a transactional email; `data` keys are available at the template's top level.
	 *
	 * @param string $transactional_id Transactional email ID.
	 * @param string $email            Recipient.
	 * @param array  $data             Template data.
	 * @return array|\WP_Error
	 */
	public function send_transactional( string $transactional_id, string $email, array $data = array() ) {
		return $this->request(
			'POST',
			'send-transactional',
			array(
				'email'           => $email,
				'transactionalId' => $transactional_id,
				'data'            => (object) $data,
			)
		);
	}

	/**
	 * Sends a transactional email with attachments (each `filename` + base64 `content`).
	 *
	 * @param string $transactional_id Transactional email ID.
	 * @param string $email            Recipient.
	 * @param array  $data             Template data.
	 * @param array  $attachments      Attachments.
	 * @return array|\WP_Error
	 */
	public function send_transactional_with_attachments( string $transactional_id, string $email, array $data, array $attachments ) {
		$body = array(
			'email'           => $email,
			'transactionalId' => $transactional_id,
			'data'            => (object) $data,
		);
		if ( $attachments ) {
			$body['attachments'] = array_values( $attachments );
		}
		return $this->request( 'POST', 'send-transactional', $body );
	}

	/**
	 * Sends a triggered email (reaches only active subscribers of the email's list).
	 *
	 * @param string   $triggered_id Triggered email ID.
	 * @param string[] $emails       Recipients.
	 * @param array    $data         Template data (top level in the template).
	 * @return array|\WP_Error
	 */
	public function send_triggered( string $triggered_id, array $emails, array $data = array() ) {
		return $this->request(
			'POST',
			'send-triggered',
			array(
				'triggeredId' => $triggered_id,
				'emails'      => array_values( $emails ),
				'data'        => (object) $data,
			)
		);
	}

	/**
	 * Transactional emails of the project (id, name, subject ...).
	 *
	 * @return array|\WP_Error Items.
	 */
	public function get_transactional_emails() {
		return $this->items( 'transactional-emails?limit=100' );
	}

	/**
	 * Triggered emails of the project.
	 *
	 * @return array|\WP_Error Items.
	 */
	public function get_triggered_emails() {
		return $this->items( 'triggered-emails?limit=100' );
	}

	/**
	 * Creates a transactional email (type html/text: `document` is a Handlebars template string).
	 *
	 * @param array $data name, subject, previewText, senderIdentity, replyTo, type, document.
	 * @return array|\WP_Error
	 */
	public function create_transactional_email( array $data ) {
		return $this->request( 'POST', 'transactional-emails', $data );
	}

	/**
	 * Creates a triggered email (bound to a subscriber list).
	 *
	 * @param array $data name, subject, previewText, subscriberListId, senderIdentity, type, document.
	 * @return array|\WP_Error
	 */
	public function create_triggered_email( array $data ) {
		return $this->request( 'POST', 'triggered-emails', $data );
	}

	/**
	 * One email (content, type and updatedAt included).
	 *
	 * @param string $type     transactional | triggered.
	 * @param string $email_id Email ID.
	 * @return array|\WP_Error
	 */
	public function get_email( string $type, string $email_id ) {
		return $this->request( 'GET', self::email_path( $type, $email_id ) );
	}

	/**
	 * Partially updates an email (only the given fields change).
	 *
	 * @param string $type     transactional | triggered.
	 * @param string $email_id Email ID.
	 * @param array  $data     Fields.
	 * @return array|\WP_Error
	 */
	public function update_email( string $type, string $email_id, array $data ) {
		return $this->request( 'PATCH', self::email_path( $type, $email_id ), $data );
	}

	/**
	 * Sends a test of an email with sample template data.
	 *
	 * @param string $type     transactional | triggered.
	 * @param string $email_id Email ID.
	 * @param string $to       Recipient.
	 * @param array  $data     Template data.
	 * @return array|\WP_Error
	 */
	public function send_test_email( string $type, string $email_id, string $to, array $data = array() ) {
		return $this->request(
			'POST',
			'test-email/' . rawurlencode( $email_id ),
			array(
				'type'  => $type,
				'email' => $to,
				'data'  => (object) $data,
			)
		);
	}

	/**
	 * Partially updates a subscriber list (e.g. its double opt-in settings).
	 *
	 * @param string $list_id List ID.
	 * @param array  $data    Fields.
	 * @return array|\WP_Error
	 */
	public function update_list( string $list_id, array $data ) {
		return $this->request( 'PATCH', 'subscriber-lists/' . rawurlencode( $list_id ), $data );
	}

	/**
	 * Path of an email resource.
	 *
	 * @param string $type     transactional | triggered.
	 * @param string $email_id Email ID.
	 */
	private static function email_path( string $type, string $email_id ): string {
		return ( 'triggered' === $type ? 'triggered-emails/' : 'transactional-emails/' ) . rawurlencode( $email_id );
	}

	/**
	 * Sender identities of the project.
	 *
	 * @return array|\WP_Error Items.
	 */
	public function get_sender_identities() {
		return $this->items( 'sender-identities' );
	}

	/**
	 * Creates a contact (custom fields as flat keys, tags).
	 *
	 * @param array $data email, name, tags, custom fields.
	 * @return array|\WP_Error
	 */
	public function create_contact( array $data ) {
		return $this->request( 'POST', 'contacts', $data );
	}

	/**
	 * Deletes a contact (bluefox also removes it from every subscriber list).
	 *
	 * @param string $email Email address.
	 * @return array|\WP_Error
	 */
	public function delete_contact( string $email ) {
		return $this->request( 'DELETE', 'contacts/' . rawurlencode( $email ) );
	}

	/**
	 * Bulk-adds up to 1000 contacts to a list as active, without double opt-in (only for consented contacts).
	 *
	 * @param string $list_id  List ID.
	 * @param array  $contacts Rows: email, name, tags, custom fields.
	 * @return array|\WP_Error
	 */
	public function import_contacts( string $list_id, array $contacts ) {
		return $this->request( 'POST', 'subscriber-lists/' . rawurlencode( $list_id ) . '/import', array( 'contacts' => array_values( $contacts ) ) );
	}

	/**
	 * The project webhook configuration.
	 *
	 * @return array|\WP_Error
	 */
	public function get_webhook() {
		return $this->request( 'GET', 'webhook' );
	}

	/**
	 * Creates or replaces the project webhook (`secretKey` must be one of the project's API keys).
	 *
	 * @param array $data url, secretKey, bounce, complaint, click, open, sent, failed, subscription.
	 * @return array|\WP_Error
	 */
	public function update_webhook( array $data ) {
		return $this->request( 'PATCH', 'webhook', $data );
	}

	/**
	 * Removes the project webhook.
	 *
	 * @return array|\WP_Error
	 */
	public function delete_webhook() {
		return $this->request( 'DELETE', 'webhook' );
	}

	/**
	 * Asks bluefox to send a synthetic test event to the configured webhook.
	 *
	 * @param string $type Event type (required by the API): bounce, complaint, open, click, pause-subscription,
	 *                     unsubscribe, subscribe, resubscribe, sent, failed.
	 * @return array|\WP_Error
	 */
	public function test_webhook( string $type = 'subscribe' ) {
		return $this->request( 'GET', 'test-webhook?type=' . rawurlencode( $type ) );
	}

	/**
	 * The configured API key (used as the project webhook's secret).
	 */
	public function api_key(): string {
		return $this->api_key;
	}

	/**
	 * GET a collection and return its `items`.
	 *
	 * @param string $path Path with query string.
	 * @return array|\WP_Error
	 */
	private function items( string $path ) {
		$result = $this->request( 'GET', $path );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
	}

	/**
	 * Public lists with this email's subscription state, authorised by the preferences-link JWT.
	 *
	 * @param string $email Email address (from the token payload).
	 * @param string $token Raw JWT from the `?token=` parameter.
	 * @return array|\WP_Error
	 */
	public function get_public_lists_with_token( string $email, string $token ) {
		return $this->request( 'GET', 'subscriber-lists/public/' . rawurlencode( $email ), null, $token );
	}

	/**
	 * Changes a subscription status, authorised by the preferences-link JWT.
	 *
	 * @param string      $list_id      List ID.
	 * @param string      $email        Email address.
	 * @param string      $token        Raw JWT.
	 * @param string      $status       active | unsubscribed | paused.
	 * @param string|null $paused_until ISO date, required for paused.
	 * @return array|\WP_Error
	 */
	public function update_subscriber_with_token( string $list_id, string $email, string $token, string $status, ?string $paused_until = null ) {
		$body = array( 'status' => $status );
		if ( null !== $paused_until ) {
			$body['pausedUntil'] = $paused_until;
		}
		return $this->request( 'PATCH', $this->subscriber_path( $list_id, $email ), $body, $token );
	}

	/**
	 * Performs a request and maps the response envelope.
	 *
	 * @param string      $method HTTP method.
	 * @param string      $path   Path relative to the project (may include a query string).
	 * @param array|null  $body   JSON body.
	 * @param string|null $bearer Bearer credential overriding the API key.
	 * @return array|\WP_Error
	 */
	private function request( string $method, string $path, ?array $body = null, ?string $bearer = null ) {
		$url  = self::API_BASE . rawurlencode( $this->project_id ) . ( '' === $path ? '' : '/' . $path );
		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Bearer ' . ( null !== $bearer ? $bearer : $this->api_key ),
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $json ) ) {
			if ( 404 === $code ) {
				return $this->not_found( $code );
			}
			return new \WP_Error( 'cfbe_bluefox_invalid_json', sprintf( 'bluefox: invalid JSON response (HTTP %d)', $code ), array( 'status' => $code ) );
		}

		$status = isset( $json['status'] ) ? (int) $json['status'] : $code;
		if ( $code >= 200 && $code < 300 && $status >= 200 && $status < 300 ) {
			return isset( $json['result'] ) && is_array( $json['result'] ) ? $json['result'] : array();
		}
		if ( 404 === $code || 404 === $status ) {
			return $this->not_found( 404 );
		}

		$name    = isset( $json['error']['name'] ) ? (string) $json['error']['name'] : 'http';
		$message = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : sprintf( 'bluefox: HTTP %d', $code );
		$slug    = trim( strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '_', $name ) ), '_' );

		return new \WP_Error( 'cfbe_bluefox_' . $slug, $message, array( 'status' => $status >= 400 ? $status : $code ) );
	}

	/**
	 * Path of one subscriber on a list.
	 *
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 */
	private function subscriber_path( string $list_id, string $email ): string {
		return 'subscriber-lists/' . rawurlencode( $list_id ) . '/subscribers/' . rawurlencode( $email );
	}

	/**
	 * Not-found error.
	 *
	 * @param int $status HTTP status.
	 */
	private function not_found( int $status ): \WP_Error {
		return new \WP_Error( 'cfbe_bluefox_not_found', 'bluefox: not found', array( 'status' => $status ) );
	}
}
