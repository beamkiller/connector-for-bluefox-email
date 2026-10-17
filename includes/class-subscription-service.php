<?php
/**
 * Signup pipeline shared by every source.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, records consent locally, then hands the signup to bluefox according to the
 * subscriber's current state there (see SPEC-subscribe.md state table).
 */
class Subscription_Service {

	const RETRY_HOOK = 'cfbe_retry_subscribe';

	// Every source together: signups per mailbox and hour, confirmation emails re-sent per mailbox and day.
	const MAILBOX_LIMIT  = 3;
	const MAILBOX_WINDOW = 3600;
	const RESEND_WINDOW  = 86400;
	const RETRY_DELAYS   = array(
		1 => 300,
		2 => 1800,
		3 => 7200,
	);

	/**
	 * Bluefox client, null when not configured.
	 *
	 * @var Bluefox_Client|null
	 */
	private $client;

	/**
	 * Subscribers repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Background jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Validator.
	 *
	 * @var Signup_Validator
	 */
	private $validator;

	/**
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private $limiter;

	/**
	 * Constructor.
	 *
	 * @param Bluefox_Client|null   $client     Client (null when bluefox is not configured).
	 * @param Subscriber_Repository $repository Repository.
	 * @param Jobs                  $jobs       Jobs.
	 * @param Logger                $logger     Logger.
	 * @param Signup_Validator|null $validator  Validator.
	 * @param Rate_Limiter|null     $limiter    Rate limiter.
	 */
	public function __construct( ?Bluefox_Client $client, Subscriber_Repository $repository, Jobs $jobs, Logger $logger, ?Signup_Validator $validator = null, ?Rate_Limiter $limiter = null ) {
		$this->client     = $client;
		$this->repository = $repository;
		$this->jobs       = $jobs;
		$this->logger     = $logger;
		$this->validator  = $validator ? $validator : new Signup_Validator();
		$this->limiter    = $limiter ? $limiter : new Rate_Limiter();
	}

	/**
	 * Hooks the retry job.
	 */
	public function register(): void {
		add_action( self::RETRY_HOOK, array( $this, 'retry' ), 10, 3 );
		add_action( 'cfbe_signup_confirmed', array( $this, 'on_confirmed' ), 5, 3 );
	}

	/**
	 * `cfbe_signup_confirmed`: the confirmation proved who owns the address.
	 *
	 * @param string $email   Email address.
	 * @param string $list_id List ID.
	 * @param array  $row     Local signup row.
	 */
	public function on_confirmed( $email, $list_id, $row ): void {
		if ( is_array( $row ) ) {
			$this->apply_deferred( $row );
		}
	}

	/**
	 * Processes a signup.
	 *
	 * @param array  $input  email, name, interests, consent, consent_text, ip, order_id, place (placement tag),
	 *                       list_id (trusted: checked by the caller; default list when empty), tags (extra tags),
	 *                       fields (extra bluefox contact fields as flat keys), skip_form_rules (no interest/name rules),
	 *                       owner_verified (the request comes from the address owner's own account: an existing
	 *                       contact may be updated at once; otherwise changes wait for the confirmation).
	 * @param string $source popup | shortcode | block | promo | checkout | integration slug.
	 */
	public function subscribe( array $input, string $source ): Subscribe_Result {
		$form = Settings::get( 'form' );
		if ( ! empty( $input['skip_form_rules'] ) || 'checkout' === $source ) {
			// Checkout and third-party forms have no interest field; the name is optional there.
			$form['interests_required'] = false;
			$form['name_required']      = false;
			$form['name_enabled']       = true; // Keep a name the form or billing details provide.
		}
		$validation = $this->validator->validate( $input, $form );
		if ( $validation['errors'] ) {
			return new Subscribe_Result(
				Subscribe_Result::INVALID,
				__( 'Please correct the highlighted fields.', 'connector-for-bluefox-email' ),
				$validation['errors'],
				400
			);
		}

		$data = $validation['data'];
		if ( ! $this->limiter->hit( 'mailbox', Subscriber_Repository::mailbox( $data['email'] ), self::MAILBOX_LIMIT, self::MAILBOX_WINDOW ) ) {
			$this->logger->warning( 'Signup rate limited (mailbox)', array( 'email' => $data['email'] ) );
			return new Subscribe_Result( Subscribe_Result::RATE_LIMITED, __( 'Too many attempts, please try again later.', 'connector-for-bluefox-email' ), array(), 429 );
		}
		$tags    = Signup_Validator::tags_for( $data['interests'], $source, (string) ( $input['place'] ?? '' ) );
		$tags    = array_values( array_unique( array_merge( $tags, Signup_Validator::clean_tags( (array) ( $input['tags'] ?? array() ) ) ) ) );
		$list_id = self::list_id( (string) ( $input['list_id'] ?? '' ) );
		$fields  = self::clean_fields( (array) ( $input['fields'] ?? array() ) );

		// Only a row created by THIS request can be classified as an already-existing subscriber.
		$is_new_row = null === $this->repository->find_by_email( $data['email'] );

		// Consent is recorded before any remote call.
		$this->repository->insert_pending(
			array(
				'email'        => $data['email'],
				'name'         => '' === $data['name'] ? null : $data['name'],
				'source'       => $source,
				'list_id'      => $list_id,
				'tags'         => $tags,
				'consent_text' => (string) ( $input['consent_text'] ?? '' ),
				'consent_ip'   => Consent_Ip::prepare( (string) ( $input['ip'] ?? '' ) ),
				'order_id'     => isset( $input['order_id'] ) ? (int) $input['order_id'] : null,
			)
		);

		return $this->sync( $data['email'], $data['name'], $tags, $source, 0, $is_new_row, $list_id, $fields, ! empty( $input['owner_verified'] ) );
	}

	/**
	 * The list to use: the given one, else the default list (General tab).
	 *
	 * @param string $list_id Requested list ID.
	 */
	public static function list_id( string $list_id = '' ): string {
		$list_id = (string) preg_replace( '/[^A-Za-z0-9]/', '', $list_id );
		return '' !== $list_id ? $list_id : (string) Settings::get( 'api', 'list_id' );
	}

	/**
	 * Extra contact fields: flat scalar values under safe keys (bluefox drops unknown keys).
	 *
	 * @param array $fields Raw fields.
	 */
	public static function clean_fields( array $fields ): array {
		$clean = array();
		foreach ( $fields as $key => $value ) {
			$key = (string) preg_replace( '/[^A-Za-z0-9_]/', '', (string) $key );
			if ( '' === $key || in_array( $key, array( 'email', 'tags', 'status' ), true ) || ! is_scalar( $value ) ) {
				continue;
			}
			$clean[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}
		return $clean;
	}

	/**
	 * Retry job callback (Action Scheduler passes the args positionally).
	 *
	 * @param string $email   Email address.
	 * @param string $source  Signup source.
	 * @param int    $attempt Attempt number (1-based).
	 */
	public function retry( $email, $source, $attempt ): void {
		$row = $this->repository->find_by_email( (string) $email );
		if ( ! $row || Subscriber_Repository::STATUS_PENDING !== $row['status'] ) {
			return;
		}
		$this->sync( $row['email'], (string) $row['name'], (array) $row['tags'], (string) $source, (int) $attempt, false, self::list_id( (string) ( $row['list_id'] ?? '' ) ) );
	}

	/**
	 * Hands a stored signup to bluefox according to its current state there.
	 *
	 * @param string   $email      Email.
	 * @param string   $name       Name ('' when not given).
	 * @param string[] $tags       Tags.
	 * @param string   $source     Source.
	 * @param int      $attempt    Retry attempt (0 = original request).
	 * @param bool     $is_new_row Whether this request created the local row.
	 * @param string   $list_id    bluefox list ID.
	 * @param array    $fields     Extra contact fields.
	 * @param bool     $verified   The address owner made the request (see subscribe()).
	 */
	private function sync( string $email, string $name, array $tags, string $source, int $attempt, bool $is_new_row, string $list_id, array $fields = array(), bool $verified = false ): Subscribe_Result {
		if ( null === $this->client || '' === $list_id ) {
			$this->logger->error( 'Subscribe skipped: bluefox is not configured', array( 'email' => $email ) );
			return $this->finish( $email, $source, $this->rejected() );
		}

		$subscriber = $this->client->get_subscriber( $list_id, $email );

		if ( is_wp_error( $subscriber ) ) {
			if ( 'cfbe_bluefox_not_found' !== $subscriber->get_error_code() ) {
				return $this->finish( $email, $source, $this->handle_error( $subscriber, $email, $source, $attempt ) );
			}
			$body = $fields + array(
				'email' => $email,
				'tags'  => $tags,
			);
			if ( '' !== $name ) {
				$body['name'] = $name;
			}
			$added = $this->client->add_subscriber( $list_id, $body );
			if ( is_wp_error( $added ) ) {
				return $this->finish( $email, $source, $this->handle_error( $added, $email, $source, $attempt ) );
			}
			return $this->finish( $email, $source, $this->message_result( Subscribe_Result::PENDING, 'msg_pending' ) );
		}

		$status = (string) ( $subscriber['status'] ?? '' );
		switch ( $status ) {
			case 'unverified':
				if ( $attempt > 0 ) {
					// A retry found the signup already in bluefox (an earlier attempt went through): don't resend the DOI email.
					return $this->finish( $email, $source, $this->message_result( Subscribe_Result::PENDING, 'msg_pending' ) );
				}
				// One confirmation email per mailbox and day, whoever asks.
				if ( $this->limiter->hit( 'resend', Subscriber_Repository::mailbox( $email ), 1, self::RESEND_WINDOW ) ) {
					$sent = $this->client->resend_verification( $list_id, $email );
					if ( is_wp_error( $sent ) ) {
						return $this->finish( $email, $source, $this->handle_error( $sent, $email, $source, $attempt ) );
					}
				}
				$this->update_or_defer( $email, $name, $tags, $fields, $verified );
				return $this->finish( $email, $source, $this->message_result( Subscribe_Result::RESENT, 'msg_pending' ) );

			case 'active':
				// Active before this very signup → already a subscriber: no welcome coupon (new subscribers only).
				// A pre-existing local row or a retry means it's OUR subscriber who confirmed meanwhile: keep it.
				if ( 0 === $attempt && $is_new_row ) {
					$this->repository->mark_existing( $email );
				}
				if ( $verified ) {
					// Nobody else may change an active contact: there is no confirmation step left to prove ownership.
					$this->merge_contact( $email, $name, $tags, $fields );
				}
				return $this->finish( $email, $source, $this->message_result( Subscribe_Result::ALREADY, 'msg_already' ) );

			case 'unsubscribed':
				// bluefox re-runs double opt-in: its confirmation email goes out again, the status stays unsubscribed until the click.
				if ( ! $verified && ! $this->limiter->hit( 'resend', Subscriber_Repository::mailbox( $email ), 1, self::RESEND_WINDOW ) ) {
					return $this->finish( $email, $source, $this->message_result( Subscribe_Result::RESENT, 'msg_pending' ) );
				}
				return $this->finish( $email, $source, $this->resubscribe( $email, $name, $tags, $source, $attempt, Subscribe_Result::PENDING, $list_id, $fields, $verified ) );

			case 'paused':
				// Paused never withdrew consent: bluefox makes them active at once, without an email. Not a new subscriber → no coupon.
				// Only the owner may do that; anyone else could switch a paused newsletter back on.
				if ( ! $verified ) {
					$this->repository->mark_existing( $email );
					return $this->finish( $email, $source, $this->message_result( Subscribe_Result::ALREADY, 'msg_already' ) );
				}
				return $this->finish( $email, $source, $this->resubscribe( $email, $name, $tags, $source, $attempt, Subscribe_Result::ALREADY, $list_id, $fields, true ) );

			default:
				$this->logger->warning( 'Unknown bluefox subscriber status', array( 'status' => $status ) );
				return $this->finish( $email, $source, $this->rejected() );
		}
	}

	/**
	 * Returning contact (unsubscribed or paused): subscribes again through bluefox (see SPEC-subscribe.md §1a).
	 *
	 * @param string   $email      Email.
	 * @param string   $name       Name.
	 * @param string[] $tags       Tags.
	 * @param string   $source     Source.
	 * @param int      $attempt    Attempt.
	 * @param string   $code       Result code on success (PENDING: confirmation email sent; ALREADY: active again).
	 * @param string   $list_id    bluefox list ID.
	 * @param array    $fields     Extra contact fields.
	 * @param bool     $verified   The address owner made the request.
	 */
	private function resubscribe( string $email, string $name, array $tags, string $source, int $attempt, string $code, string $list_id, array $fields = array(), bool $verified = false ): Subscribe_Result {
		// No tags in the body: bluefox replaces them on an existing contact; merge_contact() adds ours.
		$body = array( 'email' => $email );
		if ( '' !== $name && $verified ) {
			$body['name'] = $name;
		}
		$added = $this->client->add_subscriber( $list_id, $body );
		if ( is_wp_error( $added ) ) {
			return $this->handle_error( $added, $email, $source, $attempt );
		}

		if ( Subscribe_Result::ALREADY === $code ) {
			// Paused means they were active before, so a pending row can only be this signup (also on a retry).
			$this->repository->mark_existing( $email );
		}
		$this->update_or_defer( $email, $name, $tags, $fields, $verified );
		return $this->message_result( $code, Subscribe_Result::ALREADY === $code ? 'msg_already' : 'msg_pending' );
	}

	/**
	 * Updates an existing contact now when the owner asked, otherwise after the confirmation (Reward_Service).
	 *
	 * @param string   $email    Email.
	 * @param string   $name     Name.
	 * @param string[] $tags     Tags to add.
	 * @param array    $fields   Extra contact fields.
	 * @param bool     $verified The address owner made the request.
	 */
	private function update_or_defer( string $email, string $name, array $tags, array $fields, bool $verified ): void {
		if ( $verified ) {
			$this->merge_contact( $email, $name, $tags, $fields );
			return;
		}
		$this->repository->set_merge_pending( $email, true );
	}

	/**
	 * After a confirmation: adds the name and tags of a signup that was waiting for it (see update_or_defer()).
	 *
	 * @param array $row Local signup row.
	 */
	public function apply_deferred( array $row ): void {
		if ( null === $this->client || empty( $row['merge_pending'] ) ) {
			return;
		}
		$this->merge_contact( (string) $row['email'], (string) ( $row['name'] ?? '' ), (array) ( $row['tags'] ?? array() ) );
		$this->repository->set_merge_pending( (string) $row['email'], false );
	}

	/**
	 * Adds tags (bluefox replaces on PATCH, so merge first) and sets the name.
	 *
	 * @param string   $email Email.
	 * @param string   $name  Name.
	 * @param string[] $tags   Tags to add.
	 * @param array    $fields Extra contact fields.
	 */
	private function merge_contact( string $email, string $name, array $tags, array $fields = array() ): void {
		$contact = $this->client->get_contact( $email );
		$current = is_wp_error( $contact ) ? array() : (array) ( $contact['tags'] ?? array() );
		$body    = $fields + array( 'tags' => array_values( array_unique( array_merge( $current, $tags ) ) ) );
		if ( '' !== $name ) {
			$body['name'] = $name;
		}
		$updated = $this->client->update_contact( $email, $body );
		if ( is_wp_error( $updated ) ) {
			$this->logger->warning(
				'Contact tag/name update failed',
				array(
					'email' => $email,
					'error' => $updated->get_error_code(),
				)
			);
		}
	}

	/**
	 * Maps a bluefox error: transient → retry (queued), 405 → suppressed, other 4xx → rejected.
	 *
	 * @param \WP_Error $error   Error.
	 * @param string    $email   Email.
	 * @param string    $source  Source.
	 * @param int       $attempt Attempt.
	 */
	private function handle_error( \WP_Error $error, string $email, string $source, int $attempt ): Subscribe_Result {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
		$ctx    = array(
			'email'  => $email,
			'error'  => $error->get_error_code(),
			'status' => $status,
		);

		if ( 405 === $status ) {
			$this->repository->mark_failed( $email, 'bluefox 405: ' . $error->get_error_message() );
			$this->logger->warning( 'Signup rejected by bluefox (suppressed/bounced)', $ctx );
			// Visitors see the normal message: an error would tell anyone which addresses bounced or complained.
			return new Subscribe_Result( Subscribe_Result::SUPPRESSED, (string) Settings::get( 'form', 'msg_pending' ) );
		}

		if ( self::is_transient( $error, $status ) ) {
			$next = $attempt + 1;
			if ( isset( self::RETRY_DELAYS[ $next ] ) ) {
				$this->repository->record_attempt( $email, $error->get_error_message() );
				$this->jobs->schedule(
					self::RETRY_DELAYS[ $next ],
					self::RETRY_HOOK,
					array(
						'email'   => $email,
						'source'  => $source,
						'attempt' => $next,
					)
				);
				$this->logger->warning( 'bluefox unavailable, signup queued for retry ' . $next, $ctx );
				return $this->message_result( Subscribe_Result::QUEUED, 'msg_pending' );
			}
			$this->repository->mark_failed( $email, 'bluefox unavailable after retries: ' . $error->get_error_message() );
			$this->logger->error( 'Signup failed after all retries', $ctx );
			return $this->rejected();
		}

		$this->repository->mark_failed( $email, $error->get_error_message() );
		$this->logger->error( 'Signup rejected by bluefox', $ctx );
		return $this->rejected();
	}

	/**
	 * Transport errors, invalid JSON, 429 and 5xx are worth retrying.
	 *
	 * @param \WP_Error $error  Error.
	 * @param int       $status HTTP status (0 for transport errors).
	 */
	private static function is_transient( \WP_Error $error, int $status ): bool {
		return 0 === $status || 429 === $status || $status >= 500 || 'cfbe_bluefox_invalid_json' === $error->get_error_code();
	}

	/**
	 * Result with a configurable message.
	 *
	 * @param string $code Result code.
	 * @param string $key  `form` settings message key.
	 */
	private function message_result( string $code, string $key ): Subscribe_Result {
		return new Subscribe_Result( $code, (string) Settings::get( 'form', $key ) );
	}

	/**
	 * Generic failure (no reason disclosed to the visitor).
	 *
	 * @param int $http_status HTTP status.
	 */
	private function rejected( int $http_status = 422 ): Subscribe_Result {
		return new Subscribe_Result( Subscribe_Result::REJECTED, (string) Settings::get( 'form', 'msg_error' ), array(), $http_status );
	}

	/**
	 * Fires `cfbe_subscribed` and logs the outcome.
	 *
	 * @param string           $email  Email.
	 * @param string           $source Source.
	 * @param Subscribe_Result $result Result.
	 */
	private function finish( string $email, string $source, Subscribe_Result $result ): Subscribe_Result {
		$this->logger->info(
			'Signup ' . $result->code,
			array(
				'email'  => $email,
				'source' => $source,
			)
		);

		/**
		 * Fires after a signup was handed to bluefox (or failed).
		 *
		 * @param string           $email  Email.
		 * @param Subscribe_Result $result Result.
		 * @param string           $source Source.
		 */
		do_action( 'cfbe_subscribed', $email, $result, $source );
		return $result;
	}
}
