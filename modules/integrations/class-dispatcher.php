<?php
/**
 * Turns matched form submissions into background signup jobs.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Jobs;
use CFBE\Logger;
use CFBE\Subscription_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the signup input from a rule + submission, queues it, and runs the queued job.
 * The third-party form request never waits for bluefox and never sees an exception from us.
 */
class Dispatcher {

	const JOB = 'cfbe_integration_subscribe';

	/**
	 * Posted values that mean "not ticked".
	 */
	const FALSY = array( '', '0', 'false', 'off', 'no' );

	/**
	 * Background jobs.
	 *
	 * @var Jobs
	 */
	private $jobs;

	/**
	 * Signup service.
	 *
	 * @var Subscription_Service
	 */
	private $service;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Jobs                 $jobs    Jobs.
	 * @param Subscription_Service $service Signup service.
	 * @param Logger               $logger  Logger.
	 */
	public function __construct( Jobs $jobs, Subscription_Service $service, Logger $logger ) {
		$this->jobs    = $jobs;
		$this->service = $service;
		$this->logger  = $logger;
	}

	/**
	 * Hooks the job callback.
	 */
	public function register(): void {
		add_action( self::JOB, array( $this, 'run_job' ), 10, 1 );
	}

	/**
	 * Logger (for integrations catching their own errors).
	 */
	public function logger(): Logger {
		return $this->logger;
	}

	/**
	 * Queues a signup for one matched rule.
	 *
	 * @param array      $rule   Sanitized rule (see Rules::sanitize_rule()).
	 * @param Submission $sub    Submission.
	 * @param string     $source Integration slug.
	 * @param string     $label  Integration label (for the consent record).
	 * @return bool Whether a job was queued.
	 */
	public function dispatch( array $rule, Submission $sub, string $source, string $label ): bool {
		$input = self::build_input( $rule, $sub, $label );
		if ( is_string( $input ) ) {
			if ( 'no_consent' !== $input ) {
				$this->logger->warning(
					'Form integration skipped a submission',
					array(
						'source' => $source,
						'form'   => $sub->form_id,
						'reason' => $input,
					)
				);
			}
			return false;
		}

		/**
		 * Filters a signup coming from a third-party form; return false to skip it.
		 *
		 * @param array  $input   Signup input (email, name, consent, consent_text, ip, list_id, tags, fields, skip_form_rules).
		 * @param string $source  Integration slug: cf7 | wpforms | fluentforms | gravityforms | elementor.
		 * @param string $form_id Form ID.
		 */
		$input = apply_filters( 'cfbe_integration_signup', $input, $source, $sub->form_id );
		if ( ! is_array( $input ) ) {
			return false;
		}

		$id = $this->jobs->enqueue(
			self::JOB,
			array(
				array(
					'input'   => $input,
					'source'  => $source,
					'form_id' => $sub->form_id,
				),
			)
		);
		if ( 0 === $id ) {
			// No Action Scheduler: run inline but still never let it break the form.
			$this->run_job(
				array(
					'input'   => $input,
					'source'  => $source,
					'form_id' => $sub->form_id,
				)
			);
		}
		return true;
	}

	/**
	 * Signup input for a rule, or the reason (string) it does not apply.
	 *
	 * @param array      $rule  Rule.
	 * @param Submission $sub   Submission.
	 * @param string     $label Integration label.
	 * @return array|string
	 */
	public static function build_input( array $rule, Submission $sub, string $label ) {
		$email_field = (string) ( $rule['email_field'] ?? '' );
		if ( '' === $email_field ) {
			$email_field = $sub->detect_email_field();
		}
		$email = sanitize_email( $sub->text( $email_field ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return 'no_email';
		}

		$implied       = ! empty( $rule['implied_consent'] );
		$consent_field = (string) ( $rule['consent_field'] ?? '' );
		if ( ! $implied ) {
			if ( '' === $consent_field ) {
				return 'no_consent_rule';
			}
			if ( ! self::consent_given( $sub->raw( $consent_field ) ) ) {
				return 'no_consent';
			}
		}

		$fields = array();
		foreach ( (array) ( $rule['fields'] ?? array() ) as $map ) {
			$from = (string) ( $map['form_field'] ?? '' );
			$to   = (string) ( $map['bluefox_field'] ?? '' );
			if ( '' === $from || '' === $to ) {
				continue;
			}
			$value = $sub->text( $from );
			if ( '' !== $value ) {
				$fields[ $to ] = $value;
			}
		}

		$tags = array_filter( array_map( 'trim', explode( ',', (string) ( $rule['tags'] ?? '' ) ) ) );

		return array(
			'email'           => $email,
			'name'            => sanitize_text_field( $sub->text( (string) ( $rule['name_field'] ?? '' ) ) ),
			'consent'         => true,
			'consent_text'    => self::consent_text( $label, $sub, $implied ? '' : $consent_field, $implied ),
			'ip'              => $sub->ip,
			'list_id'         => (string) ( $rule['list_id'] ?? '' ),
			'tags'            => array_values( $tags ),
			'fields'          => $fields,
			'skip_form_rules' => true,
		);
	}

	/**
	 * Whether a checkbox / acceptance value means "ticked".
	 *
	 * @param mixed $value Posted value.
	 */
	public static function consent_given( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( self::consent_given( $item ) ) {
					return true;
				}
			}
			return false;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( ! is_scalar( $value ) ) {
			return false;
		}
		return ! in_array( strtolower( trim( (string) $value ) ), self::FALSY, true );
	}

	/**
	 * Consent record: "Form: <plugin> #<id> (<title>)" plus the consent field label when known.
	 *
	 * @param string     $label         Integration label.
	 * @param Submission $sub           Submission.
	 * @param string     $consent_field Consent field identifier ('' when implied).
	 * @param bool       $implied       Implied consent.
	 */
	public static function consent_text( string $label, Submission $sub, string $consent_field, bool $implied ): string {
		$text = sprintf( 'Form: %1$s #%2$s (%3$s)', $label, $sub->form_id, '' !== $sub->form_title ? $sub->form_title : '-' );
		if ( $implied ) {
			return $text . ' - implied consent (newsletter signup form)';
		}
		$field_label = isset( $sub->labels[ $consent_field ] ) ? trim( wp_strip_all_tags( (string) $sub->labels[ $consent_field ] ) ) : '';
		return '' !== $field_label ? $text . ' - ' . $field_label : $text;
	}

	/**
	 * Job callback: subscribes the queued signup. Idempotent through the signup service (one row per email).
	 *
	 * @param mixed $payload { input, source, form_id }.
	 */
	public function run_job( $payload ): void {
		if ( ! is_array( $payload ) || ! is_array( $payload['input'] ?? null ) ) {
			return;
		}
		$source = sanitize_key( (string) ( $payload['source'] ?? '' ) );
		if ( ! in_array( $source, Module::SOURCES, true ) ) {
			return;
		}
		$input                    = $payload['input'];
		$input['skip_form_rules'] = true;

		try {
			$result = $this->service->subscribe( $input, $source );
			if ( ! $result->is_success() ) {
				$this->logger->warning(
					'Form integration signup not accepted',
					array(
						'source' => $source,
						'form'   => (string) ( $payload['form_id'] ?? '' ),
						'code'   => $result->code,
						'email'  => (string) ( $input['email'] ?? '' ),
					)
				);
			}
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Form integration signup failed',
				array(
					'source' => $source,
					'form'   => (string) ( $payload['form_id'] ?? '' ),
					'error'  => $e->getMessage(),
				)
			);
		}
	}
}
