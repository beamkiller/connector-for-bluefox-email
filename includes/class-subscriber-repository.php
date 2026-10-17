<?php
/**
 * Subscribers store.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

// The table name is built from $wpdb->prefix + a constant (kept interpolated, like the client plugin's queries).
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

/**
 * Data access for `{prefix}cfbe_subscribers`. All dates are UTC.
 *
 * Status flow: pending → (confirmed) → rewarded; pending → expired; any → failed.
 * claim_for_reward() is the idempotency lock that guarantees one coupon per email.
 */
class Subscriber_Repository {

	const STATUS_PENDING   = 'pending';
	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_REWARDED  = 'rewarded';
	const STATUS_EXPIRED   = 'expired';
	const STATUS_FAILED    = 'failed';
	const STATUS_EXISTING  = 'existing'; // Already subscribed before this signup: never rewarded.

	const LIST_STATUSES = array( 'active', 'paused', 'unsubscribed' );

	/**
	 * Database.
	 *
	 * @var \wpdb
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
	 * @param \wpdb $db Database (injected for tests).
	 */
	public function __construct( $db ) {
		$this->db    = $db;
		$this->table = $db->prefix . Installer::TABLE;
	}

	/**
	 * Lowercases and trims an email address.
	 *
	 * @param string $email Email address.
	 */
	public static function normalize_email( string $email ): string {
		return strtolower( trim( $email ) );
	}

	/**
	 * The mailbox an address delivers to: aliases (`name+tag@`) and Gmail's ignored dots and googlemail.com
	 * collapse to one value. Used for rate limits and, optionally, for "one welcome coupon per mailbox".
	 *
	 * @param string $email Email address.
	 */
	public static function mailbox( string $email ): string {
		$email = self::normalize_email( $email );
		$at    = strrpos( $email, '@' );
		if ( false === $at ) {
			return $email;
		}
		$local  = substr( $email, 0, $at );
		$domain = substr( $email, $at + 1 );
		$plus   = strpos( $local, '+' );
		if ( false !== $plus && $plus > 0 ) {
			$local = substr( $local, 0, $plus );
		}
		if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
			$local  = str_replace( '.', '', $local );
			$domain = 'gmail.com';
		}
		return $local . '@' . $domain;
	}

	/**
	 * Finds a row by email.
	 *
	 * @param string $email Email address.
	 * @return array|null Row (tags decoded) or null.
	 */
	public function find_by_email( string $email ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE email = %s", self::normalize_email( $email ) ),
			ARRAY_A
		);
		return $row ? $this->hydrate( $row ) : null;
	}

	/**
	 * Inserts a pending signup; returns the existing row if the email is already stored. An expired or failed
	 * row starts over as a new pending signup with this request's data and consent.
	 *
	 * @param array $data Keys: email, name, source, list_id, tags, consent_text, consent_ip, order_id.
	 * @return array|null Stored row.
	 */
	public function insert_pending( array $data ): ?array {
		$email    = self::normalize_email( (string) ( $data['email'] ?? '' ) );
		$existing = $this->find_by_email( $email );
		if ( $existing && in_array( $existing['status'] ?? '', array( self::STATUS_EXPIRED, self::STATUS_FAILED ), true ) ) {
			$now = $this->now();
			$this->db->update(
				$this->table,
				array(
					'name'          => isset( $data['name'] ) ? (string) $data['name'] : null,
					'source'        => (string) ( $data['source'] ?? '' ),
					'list_id'       => (string) ( $data['list_id'] ?? '' ),
					'tags'          => wp_json_encode( array_values( (array) ( $data['tags'] ?? array() ) ) ),
					'status'        => self::STATUS_PENDING,
					'consent_text'  => isset( $data['consent_text'] ) ? (string) $data['consent_text'] : null,
					'consent_ip'    => isset( $data['consent_ip'] ) ? (string) $data['consent_ip'] : null,
					'consent_at'    => $now,
					'attempts'      => 0,
					'last_error'    => null,
					'merge_pending' => 0,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( 'email' => $email ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ),
				array( '%s' )
			);
			return $this->find_by_email( $email );
		}
		if ( $existing ) {
			return $existing;
		}

		$now = $this->now();
		$this->db->insert(
			$this->table,
			array(
				'email'        => $email,
				'mailbox'      => self::mailbox( $email ),
				'name'         => isset( $data['name'] ) ? (string) $data['name'] : null,
				'source'       => (string) ( $data['source'] ?? '' ),
				'list_id'      => (string) ( $data['list_id'] ?? '' ),
				'tags'         => wp_json_encode( array_values( (array) ( $data['tags'] ?? array() ) ) ),
				'status'       => self::STATUS_PENDING,
				'consent_text' => isset( $data['consent_text'] ) ? (string) $data['consent_text'] : null,
				'consent_ip'   => isset( $data['consent_ip'] ) ? (string) $data['consent_ip'] : null,
				'consent_at'   => $now,
				'order_id'     => isset( $data['order_id'] ) ? (int) $data['order_id'] : null,
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		// A concurrent insert of the same email loses on the UNIQUE key; both callers get the stored row.
		return $this->find_by_email( $email );
	}

	/**
	 * Marks a pending signup as confirmed (DOI done, coupon not yet issued).
	 *
	 * @param string $email Email address.
	 */
	public function mark_confirmed( string $email ): bool {
		$now = $this->now();
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, confirmed_at = COALESCE(confirmed_at, %s), updated_at = %s WHERE email = %s AND status = %s",
				self::STATUS_CONFIRMED,
				$now,
				$now,
				self::normalize_email( $email ),
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * Marks a fresh pending row as an already-existing subscriber (welcome coupon is for new subscribers only).
	 *
	 * @param string $email Email address.
	 */
	public function mark_existing( string $email ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, updated_at = %s WHERE email = %s AND status = %s",
				self::STATUS_EXISTING,
				$this->now(),
				self::normalize_email( $email ),
				self::STATUS_PENDING
			)
		);
	}

	/**
	 * Flags a signup whose name and tags wait for the confirmation: the address already existed in bluefox, and
	 * an unconfirmed request must not change an existing contact.
	 *
	 * @param string $email Email address.
	 * @param bool   $on    Flag.
	 */
	public function set_merge_pending( string $email, bool $on ): bool {
		return false !== $this->db->update(
			$this->table,
			array( 'merge_pending' => $on ? 1 : 0 ),
			array( 'email' => self::normalize_email( $email ) ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Closes a confirmed signup without a coupon (e.g. an alias of an address that already got one).
	 *
	 * @param string $email  Email address.
	 * @param string $reason Reason (shown on the Subscribers tab).
	 */
	public function skip_reward( string $email, string $reason ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, last_error = %s, updated_at = %s WHERE email = %s AND status IN (%s, %s)",
				self::STATUS_EXISTING,
				$this->truncate( $reason ),
				$this->now(),
				self::normalize_email( $email ),
				self::STATUS_PENDING,
				self::STATUS_CONFIRMED
			)
		);
	}

	/**
	 * Whether another address of the same mailbox already received a welcome coupon.
	 *
	 * @param string $email Email address.
	 */
	public function mailbox_rewarded( string $email ): bool {
		$email = self::normalize_email( $email );
		return null !== $this->db->get_var(
			$this->db->prepare(
				"SELECT id FROM {$this->table} WHERE mailbox = %s AND email <> %s AND ( status = %s OR coupon_id IS NOT NULL ) LIMIT 1",
				self::mailbox( $email ),
				$email,
				self::STATUS_REWARDED
			)
		);
	}

	/**
	 * Records the bluefox list status (active | paused | unsubscribed) reported by the preferences page or a
	 * project webhook event. Separate from `status`, which tracks the signup / coupon lifecycle.
	 *
	 * @param string $email  Email address.
	 * @param string $status bluefox subscription status.
	 */
	public function set_list_status( string $email, string $status ): bool {
		if ( ! in_array( $status, self::LIST_STATUSES, true ) ) {
			return false;
		}
		$now = $this->now();
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET list_status = %s, list_status_at = %s, updated_at = %s WHERE email = %s",
				$status,
				$now,
				$now,
				self::normalize_email( $email )
			)
		);
	}

	/**
	 * Atomically claims the right to issue the coupon. True exactly once per email.
	 *
	 * @param string $email Email address.
	 */
	public function claim_for_reward( string $email ): bool {
		$now = $this->now();
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, confirmed_at = COALESCE(confirmed_at, %s), updated_at = %s WHERE email = %s AND status IN (%s, %s)",
				self::STATUS_REWARDED,
				$now,
				$now,
				self::normalize_email( $email ),
				self::STATUS_PENDING,
				self::STATUS_CONFIRMED
			)
		);
	}

	/**
	 * Gives back a claim after a failed coupon/email step so a later run can retry.
	 *
	 * @param string $email Email address.
	 * @param string $error Error message (truncated to 255 chars).
	 */
	public function release_claim( string $email, string $error ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, attempts = attempts + 1, last_error = %s, updated_at = %s WHERE email = %s AND status = %s",
				self::STATUS_CONFIRMED,
				$this->truncate( $error ),
				$this->now(),
				self::normalize_email( $email ),
				self::STATUS_REWARDED
			)
		);
	}

	/**
	 * Stores the issued coupon ID.
	 *
	 * @param string $email     Email address.
	 * @param int    $coupon_id Coupon post ID.
	 */
	public function set_coupon( string $email, int $coupon_id ): bool {
		return false !== $this->db->update(
			$this->table,
			array(
				'coupon_id'  => $coupon_id,
				'last_error' => null,
				'updated_at' => $this->now(),
			),
			array( 'email' => self::normalize_email( $email ) ),
			array( '%d', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Marks a signup as permanently failed (e.g. suppressed address).
	 *
	 * @param string $email Email address.
	 * @param string $error Error message.
	 */
	public function mark_failed( string $email, string $error ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, attempts = attempts + 1, last_error = %s, updated_at = %s WHERE email = %s",
				self::STATUS_FAILED,
				$this->truncate( $error ),
				$this->now(),
				self::normalize_email( $email )
			)
		);
	}

	/**
	 * Records a failed poll/send attempt without changing status.
	 *
	 * @param string      $email Email address.
	 * @param string|null $error Error message.
	 */
	public function record_attempt( string $email, ?string $error = null ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET attempts = attempts + 1, last_error = %s, updated_at = %s WHERE email = %s",
				null === $error ? '' : $this->truncate( $error ),
				$this->now(),
				self::normalize_email( $email )
			)
		);
	}

	/**
	 * Bumps updated_at only (poller rotation; does not count as a failed attempt).
	 *
	 * @param string $email Email address.
	 */
	public function touch( string $email ): bool {
		return 1 === $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET updated_at = %s WHERE email = %s",
				$this->now(),
				self::normalize_email( $email )
			)
		);
	}

	/**
	 * Rows awaiting confirmation or reward, oldest-touched first.
	 *
	 * @param int $limit        Max rows.
	 * @param int $max_age_days Ignore signups older than this.
	 * @return array[]
	 */
	public function pending_batch( int $limit, int $max_age_days ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE status IN (%s, %s) AND created_at >= %s ORDER BY updated_at ASC LIMIT %d",
				self::STATUS_PENDING,
				self::STATUS_CONFIRMED,
				$this->days_ago( $max_age_days ),
				$limit
			),
			ARRAY_A
		);
		return array_map( array( $this, 'hydrate' ), (array) $rows );
	}

	/**
	 * Stored consent IPs that are not in their final form yet: id => ip. Only rows whose value is a full
	 * IP (anonymised ones end in .0 or ::) are returned, so a rewrite run always makes progress.
	 *
	 * @param int  $limit     Max rows.
	 * @param bool $only_full Skip values that are already anonymised (false = every stored IP).
	 * @return array<int, string>
	 */
	public function consent_ips( int $limit, bool $only_full = true ): array {
		$filter = $only_full ? " AND consent_ip NOT LIKE '%.0' AND consent_ip NOT LIKE '%::'" : '';
		$rows   = $this->db->get_results(
			$this->db->prepare(
				"SELECT id, consent_ip FROM {$this->table} WHERE consent_ip IS NOT NULL AND consent_ip <> ''{$filter} ORDER BY id ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $filter is a constant fragment.
				$limit
			),
			ARRAY_A
		);
		$out    = array();
		foreach ( (array) $rows as $row ) {
			$out[ (int) $row['id'] ] = (string) $row['consent_ip'];
		}
		return $out;
	}

	/**
	 * Replaces one row's consent IP ('' = remove).
	 *
	 * @param int    $id Row ID.
	 * @param string $ip New value.
	 */
	public function set_consent_ip( int $id, string $ip ): bool {
		return false !== $this->db->update(
			$this->table,
			array( 'consent_ip' => '' === $ip ? null : $ip ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Erases consent IPs of signups given before a date (retention).
	 *
	 * @param string $before_gmt UTC datetime (Y-m-d H:i:s).
	 * @return int Rows changed.
	 */
	public function clear_consent_ip_before( string $before_gmt ): int {
		return (int) $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET consent_ip = NULL WHERE consent_ip IS NOT NULL AND consent_ip <> '' AND consent_at < %s",
				$before_gmt
			)
		);
	}

	/**
	 * Expires pending signups older than the given age.
	 *
	 * @param int $days Age in days.
	 * @return int Rows expired.
	 */
	public function expire_older_than( int $days ): int {
		return (int) $this->db->query(
			$this->db->prepare(
				"UPDATE {$this->table} SET status = %s, updated_at = %s WHERE status = %s AND created_at < %s",
				self::STATUS_EXPIRED,
				$this->now(),
				self::STATUS_PENDING,
				$this->days_ago( $days )
			)
		);
	}

	/**
	 * Paginated rows for the admin log, newest first.
	 *
	 * @param array $filters status, source, search (email substring), page (1-based), per_page.
	 * @return array{rows: array[], total: int}
	 */
	public function paginate( array $filters ): array {
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}
		if ( ! empty( $filters['source'] ) ) {
			$where[]  = 'source = %s';
			$params[] = (string) $filters['source'];
		}
		if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
			$where[]  = 'email LIKE %s';
			$params[] = '%' . $this->db->esc_like( self::normalize_email( (string) $filters['search'] ) ) . '%';
		}

		$per_page = max( 1, min( 100, (int) ( $filters['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$sql      = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$sql}";
		$total     = (int) $this->db->get_var( $params ? $this->db->prepare( $count_sql, ...$params ) : $count_sql );

		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM {$this->table} WHERE {$sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				...array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) )
			),
			ARRAY_A
		);

		return array(
			'rows'  => array_map( array( $this, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * All rows of an email address (personal data export). One per email today; a list for future-proofing.
	 *
	 * @param string $email Email address.
	 * @return array[] Rows (tags decoded).
	 */
	public function export_by_email( string $email ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( "SELECT * FROM {$this->table} WHERE email = %s ORDER BY id ASC", self::normalize_email( $email ) ),
			ARRAY_A
		);
		return array_map( array( $this, 'hydrate' ), (array) $rows );
	}

	/**
	 * Deletes the rows of an email address (personal data eraser).
	 * Afterwards the address counts as new again (a later signup could earn another welcome coupon).
	 *
	 * @param string $email Email address.
	 * @return int Rows deleted.
	 */
	public function delete_by_email( string $email ): int {
		$deleted = $this->db->query(
			$this->db->prepare( "DELETE FROM {$this->table} WHERE email = %s", self::normalize_email( $email ) )
		);
		return max( 0, (int) $deleted );
	}

	/**
	 * Decodes JSON columns.
	 *
	 * @param array $row Raw row.
	 */
	private function hydrate( array $row ): array {
		$tags        = isset( $row['tags'] ) ? json_decode( (string) $row['tags'], true ) : array();
		$row['tags'] = is_array( $tags ) ? $tags : array();
		return $row;
	}

	/**
	 * Current UTC datetime.
	 */
	private function now(): string {
		return current_time( 'mysql', true );
	}

	/**
	 * UTC datetime N days ago.
	 *
	 * @param int $days Days.
	 */
	private function days_ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * Truncates to the column length.
	 *
	 * @param string $text Text.
	 */
	private function truncate( string $text ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 255 ) : substr( $text, 0, 255 );
	}
}
