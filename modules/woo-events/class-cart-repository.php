<?php
/**
 * Abandoned cart storage.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Rows of `{prefix}cfbe_carts`. Status machine:
 * open → sent (reminder queued) → recovered (restored via the link, then ordered)
 * open|sent → ordered (checked out without the link) | expired (cleanup).
 * recovered / ordered / expired are final; new cart activity afterwards starts a new row.
 */
class Cart_Repository {

	const STATUS_OPEN      = 'open';
	const STATUS_SENT      = 'sent';
	const STATUS_RECOVERED = 'recovered';
	const STATUS_ORDERED   = 'ordered';
	const STATUS_EXPIRED   = 'expired';

	const ACTIVE = array( self::STATUS_OPEN, self::STATUS_SENT );

	const TRANSITIONS = array(
		self::STATUS_OPEN      => array( self::STATUS_SENT, self::STATUS_ORDERED, self::STATUS_RECOVERED, self::STATUS_EXPIRED ),
		self::STATUS_SENT      => array( self::STATUS_ORDERED, self::STATUS_RECOVERED, self::STATUS_EXPIRED ),
		self::STATUS_RECOVERED => array(),
		self::STATUS_ORDERED   => array(),
		self::STATUS_EXPIRED   => array(),
	);

	const TOKEN_LENGTH = 32;

	/**
	 * Database (wpdb or a test double).
	 *
	 * @var \wpdb|object
	 */
	private $db;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @param \wpdb|object $db Database.
	 */
	public function __construct( $db ) {
		$this->db    = $db;
		$this->table = $db->prefix . Installer::TABLE;
	}

	/**
	 * Whether a status change is allowed.
	 *
	 * @param string $from Current status.
	 * @param string $to   New status.
	 */
	public static function can_transition( string $from, string $to ): bool {
		return in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true );
	}

	/**
	 * Whether a token has the expected format.
	 *
	 * @param string $token Token.
	 */
	public static function valid_token( string $token ): bool {
		return 1 === preg_match( '/^[A-Za-z0-9]{' . self::TOKEN_LENGTH . '}$/', $token );
	}

	/**
	 * New random restore token.
	 */
	public static function new_token(): string {
		return wp_generate_password( self::TOKEN_LENGTH, false, false );
	}

	/**
	 * Current UTC time in MySQL format.
	 */
	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * The active (open/sent) cart of a session or email, newest first.
	 *
	 * @param string $session_key WooCommerce session customer ID ('' = unknown).
	 * @param string $email       Email ('' = unknown).
	 */
	public function find_active( string $session_key, string $email ): ?array {
		if ( '' === $session_key && '' === $email ) {
			return null;
		}
		$row = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status IN ('open','sent') AND ( ( session_key <> '' AND session_key = %s ) OR ( email <> '' AND email = %s ) ) ORDER BY updated_at DESC LIMIT 1",
				$session_key,
				self::normalize_email( $email )
			),
			ARRAY_A
		);
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * A cart by restore token.
	 *
	 * @param string $token Token.
	 */
	public function find_by_token( string $token ): ?array {
		if ( ! self::valid_token( $token ) ) {
			return null;
		}
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table} WHERE token = %s", $token ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * A cart by ID.
	 *
	 * @param int $id Row ID.
	 */
	public function find( int $id ): ?array {
		$row = $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Stores the current cart contents: updates the active row of the session/email or inserts a new open row.
	 *
	 * @param array $data email, user_id, session_key, items (array), cart_total, currency.
	 * @return int Row ID (0 on failure).
	 */
	public function save( array $data ): int {
		$email       = self::normalize_email( (string) ( $data['email'] ?? '' ) );
		$session_key = substr( (string) ( $data['session_key'] ?? '' ), 0, 100 );
		$fields      = array(
			'user_id'     => absint( $data['user_id'] ?? 0 ),
			'session_key' => $session_key,
			'cart'        => (string) wp_json_encode( array_values( (array) ( $data['items'] ?? array() ) ) ),
			'cart_total'  => (float) ( $data['cart_total'] ?? 0 ),
			'currency'    => substr( (string) ( $data['currency'] ?? '' ), 0, 8 ),
			'updated_at'  => self::now(),
		);
		if ( '' !== $email ) {
			$fields['email'] = $email;
		}

		// Only the shopper's own session updates a cart: typing someone else's address must not take over their row.
		$existing = '' !== $session_key ? $this->find_active( $session_key, '' ) : null;
		if ( ! $existing && '' !== $email ) {
			$other = $this->find_active( '', $email );
			if ( $other && '' !== $session_key && '' !== (string) $other['session_key'] ) {
				if ( self::STATUS_OPEN === ( $other['status'] ?? '' ) ) {
					return 0; // An open cart of another session already uses this address.
				}
				$other = null; // Its reminder went out already: this visit starts a new cart.
			}
			$existing = $other;
		}
		if ( $existing ) {
			$this->db->update( $this->table, $fields, array( 'id' => (int) $existing['id'] ), self::formats( $fields ), array( '%d' ) );
			return (int) $existing['id'];
		}

		$fields += array(
			'email'      => $email,
			'token'      => self::new_token(),
			'status'     => self::STATUS_OPEN,
			'created_at' => self::now(),
		);
		$ok      = $this->db->insert( $this->table, $fields, self::formats( $fields ) );
		return $ok ? (int) ( $this->db->insert_id ?? 0 ) : 0;
	}

	/**
	 * Sets the email of a session's active cart (guest typed it at checkout).
	 *
	 * @param string $session_key Session key.
	 * @param string $email       Email.
	 */
	public function set_email( string $session_key, string $email ): bool {
		if ( '' === $session_key ) {
			return false;
		}
		return (bool) $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET email = %s WHERE session_key = %s AND status IN ('open','sent')",
				self::normalize_email( $email ),
				$session_key
			)
		);
	}

	/**
	 * Removes the open (not yet reminded) cart of a session, e.g. when it was emptied.
	 *
	 * @param string $session_key Session key.
	 */
	public function delete_open( string $session_key ): int {
		if ( '' === $session_key ) {
			return 0;
		}
		return (int) $this->db->query( $this->db->prepare( "DELETE FROM {$this->table} WHERE session_key = %s AND status = 'open'", $session_key ) );
	}

	/**
	 * Changes the status of a row if the state machine allows it (atomic: only from the expected status).
	 *
	 * @param array  $row Row.
	 * @param string $to  New status.
	 * @return bool True if this call changed the row.
	 */
	public function transition( array $row, string $to ): bool {
		$from = (string) ( $row['status'] ?? '' );
		if ( ! self::can_transition( $from, $to ) ) {
			return false;
		}
		if ( self::STATUS_SENT === $to ) {
			$sql = $this->db->prepare(
				"UPDATE {$this->table} SET status = %s, sent_at = %s WHERE id = %d AND status = %s",
				$to,
				self::now(),
				(int) $row['id'],
				$from
			);
		} else {
			$sql = $this->db->prepare(
				"UPDATE {$this->table} SET status = %s WHERE id = %d AND status = %s",
				$to,
				(int) $row['id'],
				$from
			);
		}
		return 1 === (int) $this->db->query( $sql );
	}

	/**
	 * Marks the active carts of a buyer as ordered.
	 *
	 * @param string $session_key Session key.
	 * @param string $email       Email.
	 */
	public function mark_ordered( string $session_key, string $email ): int {
		if ( '' === $session_key && '' === $email ) {
			return 0;
		}
		return (int) $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = 'ordered' WHERE status IN ('open','sent') AND ( ( session_key <> '' AND session_key = %s ) OR ( email <> '' AND email = %s ) )",
				$session_key,
				self::normalize_email( $email )
			)
		);
	}

	/**
	 * Open carts with an email that have been idle for at least `$minutes`.
	 *
	 * @param int $minutes Idle minutes.
	 * @param int $limit   Max rows.
	 * @return array[]
	 */
	public function idle( int $minutes, int $limit = 50 ): array {
		$before = gmdate( 'Y-m-d H:i:s', time() - max( 1, $minutes ) * 60 );
		$rows   = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status = 'open' AND email <> '' AND updated_at <= %s ORDER BY updated_at ASC LIMIT %d",
				$before,
				$limit
			),
			ARRAY_A
		);
		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Expires active carts idle for `$days`, deletes any row older than twice that.
	 *
	 * @param int $days Days.
	 * @return int Expired rows.
	 */
	public function cleanup( int $days ): int {
		$days    = max( 1, $days );
		$expire  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$delete  = gmdate( 'Y-m-d H:i:s', time() - 2 * $days * DAY_IN_SECONDS );
		$expired = (int) $this->db->query(
			$this->db->prepare( "UPDATE {$this->table} SET status = 'expired' WHERE status IN ('open','sent') AND updated_at < %s", $expire )
		);
		$this->db->query( $this->db->prepare( "DELETE FROM {$this->table} WHERE updated_at < %s", $delete ) );
		return $expired;
	}

	/**
	 * Every row of an email (privacy export).
	 *
	 * @param string $email Email.
	 * @return array[]
	 */
	public function find_by_email( string $email ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE email = %s ORDER BY id ASC", self::normalize_email( $email ) ),
			ARRAY_A
		);
		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Deletes every row of an email (privacy erasure).
	 *
	 * @param string $email Email.
	 */
	public function delete_by_email( string $email ): int {
		return (int) $this->db->query( $this->db->prepare( "DELETE FROM {$this->table} WHERE email = %s", self::normalize_email( $email ) ) );
	}

	/**
	 * Recent rows for the admin tab.
	 *
	 * @param int $limit Max rows.
	 * @return array[]
	 */
	public function recent( int $limit = 20 ): array {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT * FROM {$this->table} ORDER BY updated_at DESC LIMIT %d", $limit ), ARRAY_A );
		return array_map( array( self::class, 'hydrate' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * Lower-cased, trimmed email.
	 *
	 * @param string $email Email.
	 */
	public static function normalize_email( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * Decodes the stored item list.
	 *
	 * @param array $row Raw row.
	 */
	public static function hydrate( array $row ): array {
		$items       = json_decode( (string) ( $row['cart'] ?? '' ), true );
		$row['cart'] = is_array( $items ) ? $items : array();
		$row['id']   = (int) ( $row['id'] ?? 0 );
		return $row;
	}

	/**
	 * Column formats (wpdb) for a field set.
	 *
	 * @param array $fields Fields.
	 * @return string[]
	 */
	private static function formats( array $fields ): array {
		$formats = array();
		foreach ( $fields as $key => $value ) {
			$formats[] = 'user_id' === $key ? '%d' : ( 'cart_total' === $key ? '%f' : '%s' );
		}
		return $formats;
	}
}
