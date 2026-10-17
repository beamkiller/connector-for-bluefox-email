<?php
/**
 * [cfbe_preferences] – subscription preferences page (bluefox customSubscriptionPreferencesUrl).
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Bluefox_Client;
use CFBE\Dates;
use CFBE\Logger;
use CFBE\Preferences_Token;
use CFBE\Rate_Limiter;
use CFBE\Settings;
use CFBE\Subscriber_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Works without JavaScript. GET ?token=… renders the subscriber's status on our list; the buttons POST back to the
 * same URL (the token travels in a hidden field: it is the only credential, so no nonce is needed and none could
 * survive page caching), the change is made at bluefox with the token as Bearer, then 303 → GET ?token=…&cfbe_done=….
 * The API key is never used here; the token is never stored or logged.
 */
final class Preferences_Page {

	const TAG        = 'cfbe_preferences';
	const ATTRS      = array( 'tag', 'show_title' );
	const ACTIONS    = array( 'pause', 'unsubscribe', 'resubscribe' );
	const MONTHS     = array( 1, 3, 6 );
	const DONE       = array( 'paused', 'unsubscribed', 'resubscribed', 'error', 'expired' );
	const RATE_LIMIT = 20;

	/**
	 * Builds the token-authorised client for a project ID.
	 *
	 * @var callable
	 */
	private $client_factory;

	/**
	 * Repository.
	 *
	 * @var Subscriber_Repository
	 */
	private $repository;

	/**
	 * Rate limiter.
	 *
	 * @var Rate_Limiter
	 */
	private $limiter;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Subscriber_Repository $repository     Repository.
	 * @param Rate_Limiter          $limiter        Rate limiter.
	 * @param Logger                $logger         Logger.
	 * @param callable|null         $client_factory fn( string $project_id ): Bluefox_Client.
	 */
	public function __construct( Subscriber_Repository $repository, Rate_Limiter $limiter, Logger $logger, ?callable $client_factory = null ) {
		$this->repository     = $repository;
		$this->limiter        = $limiter;
		$this->logger         = $logger;
		$this->client_factory = $client_factory ?? static function ( string $project_id ): Bluefox_Client {
			return new Bluefox_Client( $project_id, '' ); // Token calls only: the Bearer is the link token.
		};
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'on_template_redirect' ) );
	}

	/**
	 * Before output: never cache a token page, and handle the button POST (then redirect).
	 */
	public function on_template_redirect(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- the bluefox token is the credential (see class doc).
		$has_token = isset( $_GET['token'] ) || isset( $_POST['cfbe_token'] );
		if ( ! $has_token ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared convention honoured by page-cache plugins.
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' ); // Keep the token out of Referer headers to linked sites.

		if ( ! isset( $_POST['cfbe_pref_action'], $_POST['cfbe_token'] ) ) {
			return;
		}
		$url = $this->handle_post( wp_unslash( $_POST ), Rate_Limiter::client_ip() );
		// phpcs:enable WordPress.Security.NonceVerification
		wp_safe_redirect( $url, 303 );
		exit;
	}

	/**
	 * Applies a button press; returns the URL to redirect to.
	 *
	 * @param array  $post Unslashed POST data.
	 * @param string $ip   Client IP.
	 */
	public function handle_post( array $post, string $ip ): string {
		$raw   = $this->clean_token( (string) ( $post['cfbe_token'] ?? '' ) );
		$token = Preferences_Token::parse( $raw );
		if ( null === $token || ! $this->project_matches( $token ) ) {
			return $this->result_url( $raw, 'expired' );
		}
		if ( ! $this->limiter->hit( 'prefs', $ip, self::RATE_LIMIT, 600 ) ) {
			return $this->result_url( $raw, 'error' );
		}

		$action = sanitize_key( (string) ( $post['cfbe_pref_action'] ?? '' ) );
		$list   = (string) Settings::get( 'api', 'list_id' );
		if ( ! in_array( $action, self::ACTIONS, true ) || '' === $list ) {
			return $this->result_url( $raw, 'error' );
		}

		$paused_until = null;
		if ( 'pause' === $action ) {
			$months       = (int) ( $post['cfbe_months'] ?? 0 );
			$months       = in_array( $months, self::MONTHS, true ) ? $months : 1;
			$paused_until = Dates::iso_utc( (int) strtotime( '+' . $months . ' months' ) );
		}
		$status = array(
			'pause'       => 'paused',
			'unsubscribe' => 'unsubscribed',
			'resubscribe' => 'active',
		)[ $action ];

		$client = ( $this->client_factory )( $token->project_id );
		$result = $client->update_subscriber_with_token( $list, $token->email, $token->raw, $status, $paused_until );
		if ( is_wp_error( $result ) ) {
			$code = (int) ( $result->get_error_data()['status'] ?? 0 );
			$this->logger->warning(
				'Preferences change failed',
				array(
					'email'  => $token->email,
					'action' => $action,
					'error'  => $result->get_error_message(),
				)
			);
			return $this->result_url( $raw, 403 === $code ? 'expired' : 'error' );
		}

		$this->repository->set_list_status( $token->email, $status );
		$this->logger->info(
			'Preferences changed',
			array(
				'email'  => $token->email,
				'status' => $status,
			)
		);
		return $this->result_url(
			$raw,
			array(
				'pause'       => 'paused',
				'unsubscribe' => 'unsubscribed',
				'resubscribe' => 'resubscribed',
			)[ $action ]
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Attributes: tag (h1|h2|h3|h4), show_title (0|1).
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'tag'        => 'h1',
				'show_title' => '1',
			),
			(array) $atts,
			self::TAG
		);
		$tag  = in_array( $atts['tag'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $atts['tag'] : 'h1';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view, the token is the credential.
		$raw  = $this->clean_token( isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '' );
		$done = isset( $_GET['cfbe_done'] ) ? sanitize_key( wp_unslash( $_GET['cfbe_done'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$html = '<div class="cfbe-prefs">';
		if ( '0' !== (string) $atts['show_title'] ) {
			$html .= sprintf( '<%1$s class="cfbe-prefs__title">%2$s</%1$s>', $tag, esc_html( (string) Settings::get( 'emails', 'prefs_title' ) ) );
		}
		$intro = trim( (string) Settings::get( 'emails', 'prefs_intro' ) );
		if ( '' !== $intro ) {
			$html .= '<div class="cfbe-prefs__intro">' . wpautop( wp_kses_post( $intro ) ) . '</div>';
		}
		$html .= $this->body( $raw, in_array( $done, self::DONE, true ) ? $done : '' );

		Assets::enqueue();
		return $html . '</div>';
	}

	/**
	 * Status + actions for a token.
	 *
	 * @param string $raw  Raw token ('' when missing).
	 * @param string $done Result of the last action.
	 */
	private function body( string $raw, string $done ): string {
		$token = Preferences_Token::parse( $raw );
		if ( null === $token || ! $this->project_matches( $token ) || 'expired' === $done ) {
			return $this->notice(
				'' === $raw
					? __( 'Open this page with the “Unsubscribe” or “Pause” link at the bottom of our newsletters.', 'connector-for-bluefox-email' )
					: __( 'This link is invalid or has expired. Please use the link at the bottom of our latest newsletter.', 'connector-for-bluefox-email' ),
				'error'
			);
		}

		$html = '';
		if ( '' !== $done ) {
			$html .= $this->done_notice( $done );
		}

		$client = ( $this->client_factory )( $token->project_id );
		$lists  = $client->get_public_lists_with_token( $token->email, $token->raw );
		if ( is_wp_error( $lists ) ) {
			$code = (int) ( $lists->get_error_data()['status'] ?? 0 );
			if ( 403 === $code ) {
				return $this->notice( __( 'This link is invalid or has expired. Please use the link at the bottom of our latest newsletter.', 'connector-for-bluefox-email' ), 'error' );
			}
			if ( 404 !== $code ) {
				return $html . $this->notice( __( 'We can’t load your preferences right now. Please try again in a few minutes.', 'connector-for-bluefox-email' ), 'error' );
			}
			$lists = array();
		}

		$subscriber = null;
		foreach ( (array) ( $lists['items'] ?? array() ) as $item ) {
			if ( is_array( $item ) && (string) Settings::get( 'api', 'list_id' ) === (string) ( $item['_id'] ?? '' ) ) {
				$subscriber = is_array( $item['subscriber'] ?? null ) ? $item['subscriber'] : null;
			}
		}

		$html .= '<p class="cfbe-prefs__email">' . sprintf(
			/* translators: %s: email address */
			esc_html__( 'Email address: %s', 'connector-for-bluefox-email' ),
			'<strong>' . esc_html( $token->email ) . '</strong>'
		) . '</p>';

		$status = null === $subscriber ? '' : (string) ( $subscriber['status'] ?? '' );
		switch ( $status ) {
			case 'active':
				$html .= '<p class="cfbe-prefs__status">' . esc_html__( 'You are subscribed to our newsletter.', 'connector-for-bluefox-email' ) . '</p>';
				$html .= $this->pause_form( $token );
				$html .= $this->button_form( $token, 'unsubscribe', __( 'Unsubscribe from the newsletter', 'connector-for-bluefox-email' ), 'pause-subscription' === $token->type ? 'secondary' : 'primary' );
				break;
			case 'paused':
				$until = strtotime( (string) ( $subscriber['pausedUntil'] ?? '' ) );
				$html .= '<p class="cfbe-prefs__status">' . esc_html(
					false !== $until
						/* translators: %s: date */
						? sprintf( __( 'Your newsletter is paused until %s', 'connector-for-bluefox-email' ), Dates::hu_date( $until ) )
						: __( 'Your newsletter is paused.', 'connector-for-bluefox-email' )
				) . '</p>';
				$html .= $this->button_form( $token, 'resubscribe', __( 'Resume now', 'connector-for-bluefox-email' ), 'primary' );
				$html .= $this->button_form( $token, 'unsubscribe', __( 'Unsubscribe from the newsletter', 'connector-for-bluefox-email' ), 'secondary' );
				break;
			case 'unsubscribed':
				$html .= '<p class="cfbe-prefs__status">' . esc_html__( 'We don’t send newsletters to this address.', 'connector-for-bluefox-email' ) . '</p>';
				$html .= $this->button_form( $token, 'resubscribe', __( 'Subscribe again', 'connector-for-bluefox-email' ), 'primary' );
				break;
			case 'unverified':
				$html .= '<p class="cfbe-prefs__status">' . esc_html__( 'Your subscription is not confirmed yet. Please click the button in the confirmation email.', 'connector-for-bluefox-email' ) . '</p>';
				break;
			default:
				$html .= '<p class="cfbe-prefs__status">' . esc_html__( 'This email address is not subscribed to our newsletter.', 'connector-for-bluefox-email' ) . '</p>';
		}
		return $html;
	}

	/**
	 * Pause form: 1 / 3 / 6 months.
	 *
	 * @param Preferences_Token $token Token.
	 */
	private function pause_form( Preferences_Token $token ): string {
		$html  = '<form method="post" class="cfbe-prefs__form cfbe-prefs__pause">';
		$html .= '<fieldset><legend>' . esc_html__( 'Take a break', 'connector-for-bluefox-email' ) . '</legend>';
		foreach ( self::MONTHS as $i => $months ) {
			$html .= '<label class="cfbe-prefs__option"><input type="radio" name="cfbe_months" value="' . (int) $months . '"' . ( 0 === $i ? ' checked' : '' ) . '> ' . esc_html(
				/* translators: %d: number of months */
				sprintf( _n( 'For %d month', 'For %d months', $months, 'connector-for-bluefox-email' ), $months )
			) . '</label>';
		}
		$html .= '</fieldset>';
		$html .= $this->hidden( $token, 'pause' );
		$html .= '<button type="submit" class="cfbe-prefs__button cfbe-prefs__button--' . ( 'pause-subscription' === $token->type ? 'primary' : 'secondary' ) . '">' . esc_html__( 'Pause', 'connector-for-bluefox-email' ) . '</button>';
		return $html . '</form>';
	}

	/**
	 * Single-button form.
	 *
	 * @param Preferences_Token $token  Token.
	 * @param string            $action Action.
	 * @param string            $label  Button text.
	 * @param string            $kind   primary | secondary.
	 */
	private function button_form( Preferences_Token $token, string $action, string $label, string $kind ): string {
		return '<form method="post" class="cfbe-prefs__form">' . $this->hidden( $token, $action )
			. '<button type="submit" class="cfbe-prefs__button cfbe-prefs__button--' . esc_attr( $kind ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Hidden fields of a form.
	 *
	 * @param Preferences_Token $token  Token.
	 * @param string            $action Action.
	 */
	private function hidden( Preferences_Token $token, string $action ): string {
		return '<input type="hidden" name="cfbe_token" value="' . esc_attr( $token->raw ) . '">'
			. '<input type="hidden" name="cfbe_pref_action" value="' . esc_attr( $action ) . '">';
	}

	/**
	 * Result message after a redirect.
	 *
	 * @param string $done Result key.
	 */
	private function done_notice( string $done ): string {
		$messages = array(
			'paused'       => __( 'Done. We won’t send you newsletters for the chosen period; after that they resume automatically.', 'connector-for-bluefox-email' ),
			'unsubscribed' => __( 'You have unsubscribed and won’t receive our newsletter at this address any more. You will still get emails about your orders.', 'connector-for-bluefox-email' ),
			'resubscribed' => __( 'Thank you, you will receive our newsletter again!', 'connector-for-bluefox-email' ),
			'error'        => __( 'The change could not be saved. Please try again in a few minutes.', 'connector-for-bluefox-email' ),
		);
		return isset( $messages[ $done ] ) ? $this->notice( $messages[ $done ], 'error' === $done ? 'error' : 'success' ) : '';
	}

	/**
	 * Status box.
	 *
	 * @param string $text Text.
	 * @param string $kind success | error.
	 */
	private function notice( string $text, string $kind ): string {
		return '<p class="cfbe-prefs__notice cfbe-prefs__notice--' . esc_attr( $kind ) . '" role="' . ( 'error' === $kind ? 'alert' : 'status' ) . '">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Redirect target: the current page with the token and the result.
	 *
	 * @param string $raw  Raw token.
	 * @param string $done Result key.
	 */
	private function result_url( string $raw, string $done ): string {
		$base = get_permalink();
		$base = is_string( $base ) && '' !== $base ? $base : Settings::preferences_url();
		$args = array( 'cfbe_done' => $done );
		if ( '' !== $raw ) {
			$args['token'] = rawurlencode( $raw );
		}
		return add_query_arg( $args, $base );
	}

	/**
	 * Whether the token belongs to the configured bluefox project.
	 *
	 * @param Preferences_Token $token Token.
	 */
	private function project_matches( Preferences_Token $token ): bool {
		$project = (string) Settings::get( 'api', 'project_id' );
		return '' !== $project && hash_equals( $project, $token->project_id );
	}

	/**
	 * Keeps only JWT characters.
	 *
	 * @param string $raw Raw input.
	 */
	private function clean_token( string $raw ): string {
		return substr( (string) preg_replace( '/[^A-Za-z0-9_.\-]/', '', $raw ), 0, Preferences_Token::MAX_LENGTH );
	}
}
