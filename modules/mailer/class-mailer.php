<?php
/**
 * Routes wp_mail() through the bluefox transactional API.
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Bluefox_Client;
use CFBE\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks `pre_wp_mail`. Core applies the `wp_mail` filter *before* `pre_wp_mail`, so `$atts` is
 * already filtered and must not be filtered again here.
 *
 * The bluefox API can only send saved transactional emails: the "WordPress mail" template has subject
 * `{{{subject}}}` and document `{{{html}}}`; this class sends `data` = subject, html, previewText,
 * one API call per recipient (To, Cc and Bcc, deduplicated). From / Reply-To cannot be set per send
 * (the sender identity of the transactional email is used), so `wp_mail_from` is ignored.
 */
final class Mailer {

	/**
	 * Recursion guard: true while this class is sending (or re-sending natively).
	 *
	 * @var bool
	 */
	private static $sending = false;

	/**
	 * Returns the bluefox client or null.
	 *
	 * @var callable
	 */
	private $client_resolver;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Returns the `mailer` settings.
	 *
	 * @var callable
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param callable      $client_resolver fn(): ?Bluefox_Client.
	 * @param Logger        $logger          Logger.
	 * @param callable|null $options         fn(): array (defaults to Options::all()).
	 */
	public function __construct( callable $client_resolver, Logger $logger, ?callable $options = null ) {
		$this->client_resolver = $client_resolver;
		$this->logger          = $logger;
		$this->options         = $options ? $options : array( Options::class, 'all' );
	}

	/**
	 * Hooks the filter.
	 */
	public function register(): void {
		add_filter( 'pre_wp_mail', array( $this, 'pre_wp_mail' ), 10, 2 );
	}

	/**
	 * Settings.
	 */
	private function settings(): array {
		return array_merge( Options::DEFAULTS, (array) call_user_func( $this->options ) );
	}

	/**
	 * The bluefox client, if configured.
	 */
	private function client(): ?Bluefox_Client {
		$client = call_user_func( $this->client_resolver );
		return $client instanceof Bluefox_Client ? $client : null;
	}

	/**
	 * Whether the connection and the transactional email are set.
	 */
	public function is_configured(): bool {
		return null !== $this->client() && '' !== (string) $this->settings()['transactional_id'];
	}

	/**
	 * Whether wp_mail() is routed through bluefox.
	 */
	public function is_active(): bool {
		return ! empty( $this->settings()['enabled'] ) && $this->is_configured();
	}

	/**
	 * `pre_wp_mail` callback.
	 *
	 * @param null|bool $short_circuit Short-circuit value of earlier callbacks.
	 * @param array     $atts          wp_mail() attributes (already passed through the `wp_mail` filter).
	 * @return null|bool Null = WordPress sends it; true / false = result.
	 */
	public function pre_wp_mail( $short_circuit, $atts ) {
		if ( null !== $short_circuit || self::$sending || ! is_array( $atts ) || ! $this->is_active() ) {
			return $short_circuit;
		}

		$message      = Message::from_atts( $atts );
		$content_type = $this->content_type( $message );
		if ( 0 === strpos( strtolower( $content_type ), 'multipart/' ) ) {
			// A hand-built MIME body cannot be turned into one HTML document; WordPress sends it.
			$this->logger->info( 'Mailer: multipart message left to WordPress.' );
			return $short_circuit;
		}

		$mail_data = array(
			'to'          => $message->to,
			'subject'     => $message->subject,
			'message'     => $message->body,
			'headers'     => $atts['headers'] ?? array(),
			'attachments' => $atts['attachments'] ?? array(),
		);

		$result = $this->deliver( $message, $content_type );
		$all    = array_merge( $result['sent'], array_keys( $result['failed'] ) );

		if ( null === $result['error'] && ! $result['failed'] ) {
			Delivery_Log::add( $all, $message->subject, 'sent' );
			/** This action is documented in wp-includes/pluggable.php */
			do_action( 'wp_mail_succeeded', $mail_data ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			return true;
		}

		$error = $result['error'];
		if ( null === $error ) {
			$first = reset( $result['failed'] );
			$error = new \WP_Error( 'cfbe_mailer_failed', (string) $first );
		}
		$this->logger->error(
			'Mailer: bluefox send failed.',
			array(
				'code'   => $error->get_error_code(),
				'error'  => Delivery_Log::mask_emails( $error->get_error_message() ),
				'failed' => count( $result['failed'] ),
				'sent'   => count( $result['sent'] ),
			)
		);

		/**
		 * Fires when the bluefox path could not deliver a message (before falling back / failing).
		 *
		 * @param \WP_Error $error     Error.
		 * @param array     $mail_data wp_mail() data.
		 */
		do_action( 'cfbe_mailer_failed', $error, $mail_data );

		if ( 'fail' === $this->settings()['failure_mode'] ) {
			Delivery_Log::add( $all ? $all : $message->to, $message->subject, 'failed', $error->get_error_message() );
			/** This action is documented in wp-includes/pluggable.php */
			do_action( 'wp_mail_failed', new \WP_Error( 'wp_mail_failed', $error->get_error_message(), $mail_data ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			return false;
		}

		Delivery_Log::add( $all ? $all : $message->to, $message->subject, 'fallback', $error->get_error_message() );
		if ( ! $result['sent'] ) {
			// Nothing went out: WordPress sends the original message. Its own wp_mail_succeeded / wp_mail_failed fire.
			return null;
		}
		return $this->resend_natively( $message, array_keys( $result['failed'] ), $atts );
	}

	/**
	 * Sends a test email through bluefox (works before the mailer is enabled).
	 *
	 * @param string $to Recipient.
	 * @return true|\WP_Error
	 */
	public function send_test( string $to ) {
		$message          = new Message();
		$message->to      = array( $to );
		$message->subject = sprintf(
			/* translators: %s: site name */
			__( 'Test email from %s', 'connector-for-bluefox-email' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$message->body = __( 'This is a test email sent by Connector for bluefox.email through the bluefox transactional API. If you can read it, WordPress emails can be delivered by bluefox.', 'connector-for-bluefox-email' );

		$result = $this->deliver( $message, 'text/plain' );
		$error  = $result['error'];
		if ( null === $error && $result['failed'] ) {
			$error = new \WP_Error( 'cfbe_mailer_failed', (string) reset( $result['failed'] ) );
		}
		Delivery_Log::add( array( $to ), $message->subject, null === $error ? 'test' : 'failed', null === $error ? '' : $error->get_error_message() );
		return null === $error ? true : $error;
	}

	/**
	 * Sends one API call per recipient.
	 *
	 * @param Message $message      Message.
	 * @param string  $content_type Effective content type.
	 * @return array{sent: string[], failed: array<string, string>, error: \WP_Error|null}
	 */
	public function deliver( Message $message, string $content_type ): array {
		$result = array(
			'sent'   => array(),
			'failed' => array(),
			'error'  => null,
		);

		$client = $this->client();
		$id     = (string) $this->settings()['transactional_id'];
		if ( null === $client || '' === $id ) {
			$result['error'] = new \WP_Error( 'cfbe_mailer_not_configured', __( 'The bluefox connection or the transactional email for WordPress mail is not set.', 'connector-for-bluefox-email' ) );
			return $result;
		}

		$recipients = $message->recipients();
		if ( ! $recipients ) {
			$result['error'] = new \WP_Error( 'cfbe_mailer_no_recipients', __( 'The email has no valid recipient.', 'connector-for-bluefox-email' ) );
			return $result;
		}

		$limit       = max( 1, (int) $this->settings()['max_attachment_mb'] ) * 1048576;
		$attachments = Attachments::encode( $message->attachments, $limit, $this->logger );
		if ( is_wp_error( $attachments ) ) {
			$result['error'] = $attachments;
			return $result;
		}

		if ( $message->reply_to ) {
			$this->logger->info( 'Mailer: Reply-To header ignored (bluefox uses the reply-to of the sender identity).' );
		}

		$charset = '' !== $message->charset ? $message->charset : (string) get_bloginfo( 'charset' );
		/** This filter is documented in wp-includes/pluggable.php */
		$charset = (string) apply_filters( 'wp_mail_charset', $charset ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

		$data = array(
			'subject'     => $message->subject,
			'html'        => $message->html( $content_type, $charset ),
			'previewText' => $message->preview_text( $content_type ),
		);

		/**
		 * Filters the template data sent with every WordPress email (keys are top-level in the template).
		 *
		 * @param array   $data    subject, html, previewText.
		 * @param Message $message Parsed message.
		 */
		$data = (array) apply_filters( 'cfbe_mailer_data', $data, $message );

		self::$sending = true;
		try {
			foreach ( $recipients as $email ) {
				$response = $client->send_transactional_with_attachments( $id, $email, $data, $attachments );
				if ( is_wp_error( $response ) ) {
					$result['failed'][ $email ] = $response->get_error_message();
				} else {
					$result['sent'][] = $email;
				}
			}
		} finally {
			self::$sending = false;
		}
		return $result;
	}

	/**
	 * Effective content type: the header value (default text/plain) through `wp_mail_content_type`, like core.
	 *
	 * @param Message $message Message.
	 */
	private function content_type( Message $message ): string {
		$type = '' !== $message->content_type ? $message->content_type : 'text/plain';
		/** This filter is documented in wp-includes/pluggable.php */
		return (string) apply_filters( 'wp_mail_content_type', $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
	}

	/**
	 * After a partial failure, sends the message natively to the recipients bluefox did not accept.
	 *
	 * @param Message  $message    Message.
	 * @param string[] $recipients Failed recipients.
	 * @param array    $atts       Original attributes.
	 */
	private function resend_natively( Message $message, array $recipients, array $atts ): bool {
		self::$sending = true;
		try {
			return (bool) wp_mail( $recipients, $message->subject, $message->body, $message->other_headers, $atts['attachments'] ?? array() );
		} finally {
			self::$sending = false;
		}
	}

	/**
	 * Whether a send is in progress (tests).
	 */
	public static function is_sending(): bool {
		return self::$sending;
	}
}
