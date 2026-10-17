<?php
/**
 * A wp_mail() message parsed with core's own header semantics.
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

defined( 'ABSPATH' ) || exit;

/**
 * Parses the `$atts` of wp_mail() (to, subject, message, headers, attachments) like core does:
 * headers as string or array, `Name <address>` recipients, Content-Type with charset, Cc, Bcc,
 * Reply-To and From. bluefox cannot use From / Reply-To per send (the sender identity of the
 * transactional email wins), so they are only parsed for logging.
 */
final class Message {

	/**
	 * To addresses (bare emails).
	 *
	 * @var string[]
	 */
	public $to = array();

	/**
	 * Cc addresses.
	 *
	 * @var string[]
	 */
	public $cc = array();

	/**
	 * Bcc addresses.
	 *
	 * @var string[]
	 */
	public $bcc = array();

	/**
	 * Reply-To addresses (ignored by the bluefox path).
	 *
	 * @var string[]
	 */
	public $reply_to = array();

	/**
	 * From address (ignored by the bluefox path).
	 *
	 * @var string
	 */
	public $from_email = '';

	/**
	 * From name (ignored by the bluefox path).
	 *
	 * @var string
	 */
	public $from_name = '';

	/**
	 * Content type from the headers ('' = not set, core default text/plain).
	 *
	 * @var string
	 */
	public $content_type = '';

	/**
	 * Charset from the headers ('' = not set).
	 *
	 * @var string
	 */
	public $charset = '';

	/**
	 * Subject.
	 *
	 * @var string
	 */
	public $subject = '';

	/**
	 * Body.
	 *
	 * @var string
	 */
	public $body = '';

	/**
	 * Attachments: filename (custom name or '') => path, as list of [ path, name ].
	 *
	 * @var array<int, array{path: string, name: string}>
	 */
	public $attachments = array();

	/**
	 * Header lines other than Cc / Bcc (used to re-send to some recipients natively).
	 *
	 * @var string[]
	 */
	public $other_headers = array();

	/**
	 * Builds a message from wp_mail() attributes.
	 *
	 * @param array $atts to, subject, message, headers, attachments.
	 */
	public static function from_atts( array $atts ): self {
		$message          = new self();
		$message->subject = (string) ( $atts['subject'] ?? '' );
		$message->body    = (string) ( $atts['message'] ?? '' );
		$message->to      = self::addresses( $atts['to'] ?? array() );
		$message->parse_headers( $atts['headers'] ?? array() );
		$message->attachments = self::attachment_list( $atts['attachments'] ?? array() );
		return $message;
	}

	/**
	 * Parses headers exactly like wp_mail(): a string is split on newlines; lines without a colon are skipped.
	 *
	 * @param string|array $headers Headers.
	 */
	private function parse_headers( $headers ): void {
		if ( empty( $headers ) ) {
			return;
		}
		$lines = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );

		foreach ( $lines as $line ) {
			$line = (string) $line;
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $content ) = explode( ':', trim( $line ), 2 );
			$name                   = trim( $name );
			$content                = trim( $content );

			switch ( strtolower( $name ) ) {
				case 'from':
					$this->parse_from( $content );
					$this->other_headers[] = $name . ': ' . $content;
					break;
				case 'content-type':
					$this->parse_content_type( $content );
					$this->other_headers[] = $name . ': ' . $content;
					break;
				case 'cc':
					$this->cc = array_merge( $this->cc, self::addresses( $content ) );
					break;
				case 'bcc':
					$this->bcc = array_merge( $this->bcc, self::addresses( $content ) );
					break;
				case 'reply-to':
					$this->reply_to        = array_merge( $this->reply_to, self::addresses( $content ) );
					$this->other_headers[] = $name . ': ' . $content;
					break;
				default:
					$this->other_headers[] = $name . ': ' . $content;
			}
		}
	}

	/**
	 * From header: `Name <address>` or a bare address.
	 *
	 * @param string $content Header value.
	 */
	private function parse_from( string $content ): void {
		$bracket = strpos( $content, '<' );
		if ( false !== $bracket ) {
			$name             = trim( substr( $content, 0, $bracket ) );
			$this->from_name  = trim( str_replace( '"', '', $name ) );
			$this->from_email = trim( str_replace( '>', '', substr( $content, $bracket + 1 ) ) );
		} elseif ( '' !== $content ) {
			$this->from_email = $content;
		}
	}

	/**
	 * Content-Type header: `type; charset=…` (a boundary keeps the type, like core).
	 *
	 * @param string $content Header value.
	 */
	private function parse_content_type( string $content ): void {
		if ( false === strpos( $content, ';' ) ) {
			if ( '' !== $content ) {
				$this->content_type = trim( $content );
			}
			return;
		}
		list( $type, $rest ) = explode( ';', $content, 2 );
		$this->content_type  = trim( $type );
		if ( false !== stripos( $rest, 'charset=' ) ) {
			$this->charset = trim( str_replace( array( 'charset=', '"' ), '', strtolower( trim( $rest ) ) ) );
		}
	}

	/**
	 * Recipient list (string with commas or array) → bare email addresses.
	 *
	 * @param string|array $value Recipients.
	 * @return string[]
	 */
	public static function addresses( $value ): array {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$out   = array();
		foreach ( $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}
			if ( preg_match( '/<(.+)>/', $item, $match ) ) {
				$item = trim( $match[1] );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Attachments (string with newlines or array; string keys = custom file names, like core).
	 *
	 * @param string|array $value Attachments.
	 * @return array<int, array{path: string, name: string}>
	 */
	private static function attachment_list( $value ): array {
		if ( empty( $value ) ) {
			return array();
		}
		$items = is_array( $value ) ? $value : explode( "\n", str_replace( "\r\n", "\n", (string) $value ) );
		$out   = array();
		foreach ( $items as $key => $path ) {
			$path = trim( (string) $path );
			if ( '' === $path ) {
				continue;
			}
			$out[] = array(
				'path' => $path,
				'name' => is_string( $key ) ? $key : '',
			);
		}
		return $out;
	}

	/**
	 * All valid recipients (To, Cc, Bcc), each once (case-insensitive).
	 *
	 * @return string[]
	 */
	public function recipients(): array {
		$seen = array();
		foreach ( array_merge( $this->to, $this->cc, $this->bcc ) as $email ) {
			$email = trim( $email );
			$key   = strtolower( $email );
			if ( '' === $email || isset( $seen[ $key ] ) || ! is_email( $email ) ) {
				continue;
			}
			$seen[ $key ] = $email;
		}
		return array_values( $seen );
	}

	/**
	 * Whether the (filtered) content type is HTML.
	 *
	 * @param string $content_type Effective content type.
	 */
	public static function is_html( string $content_type ): bool {
		return 'text/html' === strtolower( trim( $content_type ) );
	}

	/**
	 * Body as HTML: HTML is passed through; plain text is escaped, linkified and paragraphed.
	 *
	 * @param string $content_type Effective content type.
	 * @param string $charset      Effective charset.
	 */
	public function html( string $content_type, string $charset = 'UTF-8' ): string {
		if ( self::is_html( $content_type ) ) {
			return $this->body;
		}
		return self::plain_to_html( $this->body, $charset );
	}

	/**
	 * Plain text → minimal HTML document.
	 *
	 * @param string $text    Plain text.
	 * @param string $charset Charset.
	 */
	public static function plain_to_html( string $text, string $charset = 'UTF-8' ): string {
		$body = wpautop( make_clickable( esc_html( $text ) ) );
		return '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=' . esc_attr( '' !== $charset ? $charset : 'UTF-8' ) . '">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1"></head>'
			. '<body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;color:#1d2327;">'
			. $body . '</body></html>';
	}

	/**
	 * Preview text: the first 100 characters of the plain text, whitespace collapsed.
	 *
	 * @param string $content_type Effective content type.
	 */
	public function preview_text( string $content_type ): string {
		$text = self::is_html( $content_type ) ? wp_strip_all_tags( $this->body ) : $this->body;
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 100 ) : substr( $text, 0, 100 );
	}
}
