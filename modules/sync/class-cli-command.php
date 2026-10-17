<?php
/**
 * Contact sync: WP-CLI command.
 *
 * @package CFBE
 */

namespace CFBE\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Syncs users and customers to bluefox contacts in the foreground.
 */
class Cli_Command {

	/**
	 * Syncer.
	 *
	 * @var Contact_Syncer
	 */
	private $syncer;

	/**
	 * Email source.
	 *
	 * @var Contact_Source
	 */
	private $source;

	/**
	 * Constructor.
	 *
	 * @param Contact_Syncer $syncer Syncer.
	 * @param Contact_Source $source Email source.
	 */
	public function __construct( Contact_Syncer $syncer, Contact_Source $source ) {
		$this->syncer = $syncer;
		$this->source = $source;
	}

	/**
	 * Syncs WordPress users and WooCommerce customers to bluefox contacts.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be sent without writing to bluefox (contacts are still read).
	 *
	 * [--limit=<n>]
	 * : Stop after this many emails.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfbe sync --dry-run --limit=10
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function __invoke( $args, $assoc_args ): void {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$limit   = isset( $assoc_args['limit'] ) ? max( 0, (int) $assoc_args['limit'] ) : 0;
		$pause   = (int) apply_filters( 'cfbe_sync_contact_pause_ms', Backfill::CONTACT_PAUSE_MS );
		$counts  = array();
		$done    = 0;

		foreach ( $this->emails() as $email ) {
			if ( $limit > 0 && $done >= $limit ) {
				break;
			}
			if ( $done > 0 && $pause > 0 ) {
				usleep( $pause * 1000 );
			}
			$result                       = $this->syncer->sync( $email, $dry_run );
			$counts[ $result['outcome'] ] = ( $counts[ $result['outcome'] ] ?? 0 ) + 1;
			++$done;
			$line = $email . ': ' . $result['outcome'];
			if ( $dry_run && $result['payload'] ) {
				$line .= ' ' . wp_json_encode( $result['payload'] );
			}
			\WP_CLI::log( $line );
			if ( Contact_Syncer::NOT_CONFIGURED === $result['outcome'] ) {
				\WP_CLI::error( 'bluefox is not configured.' );
			}
		}

		$summary = array();
		foreach ( $counts as $outcome => $count ) {
			$summary[] = $outcome . ': ' . $count;
		}
		\WP_CLI::success( sprintf( '%d processed%s. %s', $done, $dry_run ? ' (dry run)' : '', implode( ', ', $summary ) ) );
	}

	/**
	 * All emails: users, then guest customers.
	 *
	 * @return \Generator<string>
	 */
	private function emails(): \Generator {
		$offset = 0;
		do {
			$emails  = $this->source->user_emails( $offset, Backfill::BATCH_SIZE );
			$fetched = count( $emails );
			$offset += $fetched;
			foreach ( $emails as $email ) {
				yield $email;
			}
		} while ( Backfill::BATCH_SIZE === $fetched );

		$page = 1;
		do {
			$batch = $this->source->guest_emails( $page, Backfill::BATCH_SIZE );
			++$page;
			foreach ( $batch['emails'] as $email ) {
				yield $email;
			}
		} while ( Backfill::BATCH_SIZE === $batch['fetched'] );
	}
}
