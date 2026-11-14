<?php
/**
 * "bluefox setup" on the General tab: checks the bluefox-side settings the plugin relies on and fixes them in one go.
 *
 * @package CFBE
 */

namespace CFBE\Admin;

use CFBE\Bluefox_Client;
use CFBE\Email_Templates;
use CFBE\Form_Config;
use CFBE\Modules;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Per used list: double opt-in on, a confirmation email that contains {{verifyLink}} (picked from the project's
 * transactional emails) and this site's thank-you page as redirect link. With the preferences page module on:
 * the project's custom subscription preferences URL. Shown with a status per row and one "Apply in bluefox" button.
 */
final class Bluefox_Setup {

	const ACTION = 'cfbe_bluefox_setup';
	const CACHE  = 'cfbe_setup_state';
	const RESULT = 'cfbe_setup_result_';

	/**
	 * Returns the bluefox client or null.
	 *
	 * @var callable
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param callable $client Returns ?Bluefox_Client.
	 */
	public function __construct( callable $client ) {
		$this->client = $client;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'cfbe_api_tab_after', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		foreach ( array( Settings::option_name( 'emails' ), Settings::option_name( 'api' ), Modules::OPTION, Email_Templates::OPTION ) as $option ) {
			add_action( 'update_option_' . $option, array( self::class, 'flush' ) );
		}
	}

	/**
	 * Forgets the cached bluefox state.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE );
	}

	/**
	 * Whether a bluefox email can confirm a subscription: its content holds {{verifyLink}}.
	 *
	 * @param array $email Transactional email from the API.
	 */
	public static function has_verify_link( array $email ): bool {
		$doc = $email['document'] ?? '';
		$doc = is_string( $doc ) ? $doc : (string) wp_json_encode( $doc );
		return 1 === preg_match( '/\{\{\{?\s*verifyLink\s*\}\}\}?/', $doc );
	}

	/**
	 * Transactional emails for the picker: id => [name, verify], confirmable ones first.
	 *
	 * @param array $items API items.
	 * @return array<string, array{name: string, verify: bool}>
	 */
	public static function email_options( array $items ): array {
		$ready = array();
		$other = array();
		foreach ( $items as $item ) {
			$id = is_array( $item ) ? (string) ( $item['_id'] ?? '' ) : '';
			if ( '' === $id ) {
				continue;
			}
			$verify = self::has_verify_link( $item );
			$entry  = array(
				'name'   => (string) ( $item['name'] ?? $id ),
				'verify' => $verify,
			);
			if ( $verify ) {
				$ready[ $id ] = $entry;
			} else {
				$other[ $id ] = $entry;
			}
		}
		return $ready + $other;
	}

	/**
	 * Status of each list the plugin uses.
	 *
	 * @param array    $lists    bluefox lists.
	 * @param string[] $used     Used list IDs.
	 * @param array    $emails   Output of email_options().
	 * @param string   $thankyou This site's thank-you page.
	 * @return array<string, array> list ID => name, found, doi, active, email_id, email_ok, redirect, redirect_ok, ok.
	 */
	public static function list_rows( array $lists, array $used, array $emails, string $thankyou ): array {
		$by_id = array();
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && '' !== (string) ( $list['_id'] ?? '' ) ) {
				$by_id[ (string) $list['_id'] ] = $list;
			}
		}
		$rows = array();
		foreach ( $used as $id ) {
			$list     = $by_id[ $id ] ?? null;
			$doi      = is_array( $list ) && is_array( $list['doubleOptIn'] ?? null ) ? $list['doubleOptIn'] : array();
			$email_id = (string) ( $doi['emailId'] ?? '' );
			$redirect = (string) ( $doi['redirectLink'] ?? '' );
			$active   = ! empty( $doi['active'] );
			// An email we cannot see (e.g. more than 100 emails) is trusted: bluefox accepted it.
			$email_ok    = '' !== $email_id && ( ! isset( $emails[ $email_id ] ) || $emails[ $email_id ]['verify'] );
			$redirect_ok = Redirect_Check::same_url( $redirect, $thankyou );
			unset( $doi['_id'] );
			$rows[ $id ] = array(
				'name'        => is_array( $list ) ? (string) ( $list['name'] ?? $id ) : $id,
				'found'       => is_array( $list ),
				'doi'         => $doi,
				'active'      => $active,
				'email_id'    => $email_id,
				'email_ok'    => $email_ok,
				'redirect'    => $redirect,
				'redirect_ok' => $redirect_ok,
				'ok'          => is_array( $list ) && $active && $email_ok && $redirect_ok,
			);
		}
		return $rows;
	}

	/**
	 * The email preselected for a list: its current one, else the first that can confirm subscriptions.
	 *
	 * @param array $row    A list_rows() row.
	 * @param array $emails Output of email_options().
	 */
	public static function default_email( array $row, array $emails ): string {
		if ( '' !== $row['email_id'] && $row['email_ok'] ) {
			return $row['email_id'];
		}
		foreach ( $emails as $id => $email ) {
			if ( $email['verify'] ) {
				return (string) $id;
			}
		}
		return '';
	}

	/**
	 * The doubleOptIn object to send: on, with the email and the thank-you page; the confirmation texts kept.
	 * bluefox replaces the whole object, so every field we want to keep is sent back.
	 *
	 * @param array  $current  Current doubleOptIn (without `_id`).
	 * @param string $email_id Confirmation email.
	 * @param string $thankyou Thank-you page.
	 */
	public static function double_opt_in( array $current, string $email_id, string $thankyou ): array {
		$doi = array(
			'active'       => true,
			'emailId'      => $email_id,
			'redirectLink' => $thankyou,
		);
		foreach ( array( 'confirmationTitle', 'confirmationMessage' ) as $key ) {
			if ( isset( $current[ $key ] ) && is_string( $current[ $key ] ) && '' !== $current[ $key ] ) {
				$doi[ $key ] = $current[ $key ];
			}
		}
		return $doi;
	}

	/**
	 * Whether the preferences row applies (module on).
	 */
	private static function prefs_on(): bool {
		return Modules::enabled( 'preferences' );
	}

	/**
	 * State in bluefox (lists, emails, project), cached ten minutes; one minute after an error.
	 *
	 * @param Bluefox_Client $client Client.
	 * @return array{lists?: array, emails?: array, prefs_url?: string, error?: string}
	 */
	public function state( Bluefox_Client $client ): array {
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$lists   = $client->get_lists();
		$emails  = is_wp_error( $lists ) ? $lists : $client->get_transactional_emails();
		$project = is_wp_error( $emails ) ? $emails : $client->get_project();
		if ( is_wp_error( $project ) ) {
			$state = array( 'error' => $project->get_error_message() );
			set_transient( self::CACHE, $state, MINUTE_IN_SECONDS );
			return $state;
		}
		$names = array();
		foreach ( $lists as $list ) {
			if ( is_array( $list ) && '' !== (string) ( $list['_id'] ?? '' ) ) {
				$names[ (string) $list['_id'] ] = (string) ( $list['name'] ?? $list['_id'] );
			}
		}
		if ( $names ) {
			Settings::remember_lists( $names );
		}
		$state = array(
			'lists'     => $lists,
			'emails'    => self::email_options( $emails ),
			'prefs_url' => (string) ( $project['customSubscriptionPreferencesUrl'] ?? '' ),
		);
		set_transient( self::CACHE, $state, 10 * MINUTE_IN_SECONDS );
		return $state;
	}

	/**
	 * The section below the General tab form.
	 */
	public function render(): void {
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			return;
		}
		echo '<h2 id="cfbe-setup">' . esc_html__( 'bluefox setup', 'connector-for-bluefox-email' ) . '</h2>';
		echo '<p>' . esc_html__( 'These settings live in your bluefox project. Check them here and apply them with one click.', 'connector-for-bluefox-email' ) . '</p>';
		$this->render_result();

		$client = call_user_func( $this->client );
		$used   = Redirect_Check::used_list_ids();
		if ( ! $client instanceof Bluefox_Client ) {
			echo '<p class="description">' . esc_html__( 'Save the project ID and API key above first.', 'connector-for-bluefox-email' ) . '</p>';
			return;
		}
		if ( array() === $used && ! self::prefs_on() ) {
			echo '<p class="description">' . esc_html__( 'Choose a default subscriber list above first.', 'connector-for-bluefox-email' ) . '</p>';
			return;
		}
		$state = $this->state( $client );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );
		if ( isset( $state['error'] ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html(
				/* translators: %s: error message */
				sprintf( __( 'bluefox could not be read: %s', 'connector-for-bluefox-email' ), $state['error'] )
			) . '</p></div>';
			echo '<p><button type="submit" name="do" value="refresh" class="button">' . esc_html__( 'Check again', 'connector-for-bluefox-email' ) . '</button></p></form>';
			return;
		}

		$emails   = $state['emails'];
		$thankyou = Settings::thankyou_url();
		$rows     = self::list_rows( $state['lists'], $used, $emails, $thankyou );
		$ready    = array_filter( array_column( $emails, 'verify' ) );

		echo '<table class="widefat striped cfbe-setup"><thead><tr><th>' . esc_html__( 'Setting', 'connector-for-bluefox-email' ) . '</th><th>' . esc_html__( 'Status', 'connector-for-bluefox-email' ) . '</th><th>' . esc_html__( 'Change to', 'connector-for-bluefox-email' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $id => $row ) {
			$this->render_list_row( (string) $id, $row, $emails, (bool) $ready, $thankyou );
		}
		if ( self::prefs_on() ) {
			$this->render_prefs_row( $state['prefs_url'] );
		}
		echo '</tbody></table>';
		echo '<p><button type="submit" name="do" value="apply" class="button button-primary">' . esc_html__( 'Apply in bluefox', 'connector-for-bluefox-email' ) . '</button> ';
		echo '<button type="submit" name="do" value="refresh" class="button">' . esc_html__( 'Check again', 'connector-for-bluefox-email' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Status badge.
	 *
	 * @param bool   $ok    Good.
	 * @param string $label Text.
	 */
	private static function badge( bool $ok, string $label ): string {
		return '<span class="cfbe-status cfbe-status-' . ( $ok ? 'rewarded' : 'failed' ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * One list row.
	 *
	 * @param string $id       List ID.
	 * @param array  $row      list_rows() row.
	 * @param array  $emails   email_options().
	 * @param bool   $ready    Any email can confirm subscriptions.
	 * @param string $thankyou Thank-you page.
	 */
	private function render_list_row( string $id, array $row, array $emails, bool $ready, string $thankyou ): void {
		echo '<tr><td><strong>' . esc_html(
			/* translators: %s: list name */
			sprintf( __( 'Double opt-in of “%s”', 'connector-for-bluefox-email' ), $row['name'] )
		) . '</strong></td><td>';
		if ( ! $row['found'] ) {
			echo self::badge( false, __( 'List not found in bluefox', 'connector-for-bluefox-email' ) ) . '</td><td></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
			return;
		}
		if ( $row['ok'] ) {
			echo self::badge( true, __( 'Ready', 'connector-for-bluefox-email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		} elseif ( ! $row['active'] ) {
			echo self::badge( false, __( 'Off', 'connector-for-bluefox-email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		} else {
			$problems = array();
			if ( ! $row['email_ok'] ) {
				$problems[] = __( 'confirmation email without {{verifyLink}}', 'connector-for-bluefox-email' );
			}
			if ( ! $row['redirect_ok'] ) {
				$problems[] = '' === $row['redirect']
					? __( 'no redirect to this site', 'connector-for-bluefox-email' )
					/* translators: %s: URL */
					: sprintf( __( 'redirects to %s', 'connector-for-bluefox-email' ), $row['redirect'] );
			}
			echo self::badge( false, implode( '; ', $problems ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}
		echo '</td><td>';

		$name     = 'email[' . $id . ']';
		$selected = self::default_email( $row, $emails );
		echo '<label class="screen-reader-text" for="cfbe-setup-' . esc_attr( $id ) . '">' . esc_html__( 'Confirmation email', 'connector-for-bluefox-email' ) . '</label>';
		echo '<select id="cfbe-setup-' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
		echo '<option value="">' . esc_html__( '— Leave unchanged —', 'connector-for-bluefox-email' ) . '</option>';
		foreach ( $emails as $email_id => $email ) {
			$label = $email['verify']
				? $email['name']
				/* translators: %s: email name */
				: sprintf( __( '%s (no {{verifyLink}})', 'connector-for-bluefox-email' ), $email['name'] );
			printf(
				'<option value="%1$s"%2$s%3$s>%4$s</option>',
				esc_attr( (string) $email_id ),
				selected( $selected, (string) $email_id, false ),
				disabled( ! $email['verify'], true, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html(
			/* translators: %s: thank-you page URL */
			sprintf( __( 'Double opt-in on with this confirmation email; confirmed subscribers land on %s.', 'connector-for-bluefox-email' ), $thankyou )
		) . '</p>';
		if ( ! $ready ) {
			echo '<p class="description">' . esc_html__( 'None of your bluefox transactional emails contains {{verifyLink}}. Create the confirmation email on the Email templates tab, or add {{verifyLink}} to an email in bluefox.', 'connector-for-bluefox-email' ) . ' <a href="' . esc_url( add_query_arg( 'tab', Email_Templates_Tab::TAB, admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ) ) . '">' . esc_html__( 'Email templates', 'connector-for-bluefox-email' ) . '</a></p>';
		}
		echo '</td></tr>';
	}

	/**
	 * The preferences page row.
	 *
	 * @param string $current URL set in bluefox.
	 */
	private function render_prefs_row( string $current ): void {
		$expected = Settings::preferences_url();
		$ok       = '' !== $current && Redirect_Check::same_url( $current, $expected );
		echo '<tr><td><strong>' . esc_html__( 'Subscription preferences page', 'connector-for-bluefox-email' ) . '</strong></td><td>';
		if ( $ok ) {
			echo self::badge( true, __( 'Ready', 'connector-for-bluefox-email' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		} else {
			$label = '' === $current
				? __( 'bluefox’s own page is used', 'connector-for-bluefox-email' )
				/* translators: %s: URL */
				: sprintf( __( 'set to %s', 'connector-for-bluefox-email' ), $current );
			echo self::badge( false, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		}
		echo '</td><td><label><input type="checkbox" name="prefs" value="1"' . checked( ! $ok, true, false ) . '> ' . esc_html(
			/* translators: %s: preferences page URL */
			sprintf( __( 'Unsubscribe and pause links lead to %s', 'connector-for-bluefox-email' ), $expected )
		) . '</label></td></tr>';
	}

	/**
	 * Result of the last apply.
	 */
	private function render_result(): void {
		$key    = self::RESULT . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( array(
			'done'   => 'success',
			'failed' => 'error',
		) as $group => $type ) {
			if ( ! empty( $result[ $group ] ) ) {
				echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><ul class="ul-disc">';
				foreach ( (array) $result[ $group ] as $line ) {
					echo '<li>' . esc_html( (string) $line ) . '</li>';
				}
				echo '</ul></div>';
			}
		}
	}

	/**
	 * Applies the chosen settings.
	 *
	 * @param Bluefox_Client $client  Client.
	 * @param array          $choices list ID => email ID ('' = leave unchanged).
	 * @param bool           $prefs   Set the preferences page URL.
	 * @return array{done: string[], failed: string[]}
	 */
	public function apply( Bluefox_Client $client, array $choices, bool $prefs ): array {
		$result  = array(
			'done'   => array(),
			'failed' => array(),
		);
		$used    = Redirect_Check::used_list_ids();
		$choices = array_filter(
			$choices,
			static function ( $email_id, $list_id ) use ( $used ): bool {
				return '' !== $email_id && in_array( (string) $list_id, $used, true );
			},
			ARRAY_FILTER_USE_BOTH
		);

		$rows = array();
		if ( $choices ) {
			$lists = $client->get_lists();
			if ( is_wp_error( $lists ) ) {
				$result['failed'][] = $lists->get_error_message();
				$choices            = array();
			} else {
				$rows = self::list_rows( $lists, array_map( 'strval', array_keys( $choices ) ), array(), Settings::thankyou_url() );
			}
			foreach ( $choices as $list_id => $email_id ) {
				$row = $rows[ (string) $list_id ];
				if ( ! $row['found'] || ( $row['active'] && $row['email_id'] === $email_id && $row['redirect_ok'] ) ) {
					continue; // Missing, or already set up like this.
				}
				$updated = $client->update_list( (string) $list_id, array( 'doubleOptIn' => self::double_opt_in( $row['doi'], $email_id, Settings::thankyou_url() ) ) );
				if ( is_wp_error( $updated ) ) {
					/* translators: 1: list name, 2: error message */
					$result['failed'][] = sprintf( __( '“%1$s”: %2$s', 'connector-for-bluefox-email' ), $row['name'], $updated->get_error_message() );
				} else {
					/* translators: %s: list name */
					$result['done'][] = sprintf( __( 'Double opt-in of “%s” is on, with the chosen email and this site’s thank-you page.', 'connector-for-bluefox-email' ), $row['name'] );
				}
			}
		}

		if ( $prefs && self::prefs_on() ) {
			$updated = $client->update_project( array( 'customSubscriptionPreferencesUrl' => Settings::preferences_url() ) );
			if ( is_wp_error( $updated ) ) {
				/* translators: %s: error message */
				$result['failed'][] = sprintf( __( 'Preferences page: %s', 'connector-for-bluefox-email' ), $updated->get_error_message() );
			} else {
				$result['done'][] = __( 'Unsubscribe and pause links now lead to this site’s preferences page.', 'connector-for-bluefox-email' );
			}
		}

		if ( array() === $result['done'] && array() === $result['failed'] ) {
			$result['done'][] = __( 'Nothing to change: bluefox is already set up like this.', 'connector-for-bluefox-email' );
		}
		self::flush();
		Redirect_Check::flush();
		return $result;
	}

	/**
	 * Form handler (admin-post, nonce + capability).
	 */
	public function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( Admin_Page::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'connector-for-bluefox-email' ), '', array( 'response' => 403 ) );
		}
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'apply' === $do ) {
			$choices = array();
			$raw     = isset( $_POST['email'] ) && is_array( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per item below.
			foreach ( $raw as $list_id => $email_id ) {
				$choices[ Form_Config::clean_list_id( (string) $list_id ) ] = Form_Config::clean_list_id( (string) $email_id );
			}
			$client = call_user_func( $this->client );
			$result = $client instanceof Bluefox_Client
				? $this->apply( $client, $choices, ! empty( $_POST['prefs'] ) )
				: array(
					'done'   => array(),
					'failed' => array( __( 'Connect bluefox on the General tab first.', 'connector-for-bluefox-email' ) ),
				);
			set_transient( self::RESULT . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		} else {
			self::flush();
		}
		wp_safe_redirect( add_query_arg( 'tab', 'api', admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ) . '#cfbe-setup' );
		exit;
	}
}
