<?php
/**
 * Attachments of wp_mail() → bluefox `attachments[{filename, content}]`.
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Reads local attachment files and base64-encodes them, within a total size limit.
 */
final class Attachments {

	/**
	 * Encodes the attachments. Unreadable files are skipped (logged); exceeding the limit is an error.
	 *
	 * @param array<int, array{path: string, name: string}> $files       Attachments from Message.
	 * @param int                                           $limit_bytes Max total raw size.
	 * @param Logger|null                                   $logger      Logger.
	 * @return array|\WP_Error List of [ filename, content ].
	 */
	public static function encode( array $files, int $limit_bytes, ?Logger $logger = null ) {
		$out   = array();
		$total = 0;
		foreach ( $files as $file ) {
			$path = (string) ( $file['path'] ?? '' );
			$name = '' !== (string) ( $file['name'] ?? '' ) ? (string) $file['name'] : basename( $path );
			if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
				if ( $logger ) {
					$logger->warning( 'Mailer: attachment not readable, skipped.', array( 'file' => basename( $path ) ) );
				}
				continue;
			}
			$total += (int) filesize( $path );
			if ( $total > $limit_bytes ) {
				return new \WP_Error(
					'cfbe_mailer_attachments_too_large',
					/* translators: %s: size limit in MB */
					sprintf( __( 'The attachments are larger than the %s MB limit.', 'connector-for-bluefox-email' ), number_format( $limit_bytes / 1048576, 0 ) )
				);
			}
			$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file attached by wp_mail().
			if ( false === $content ) {
				if ( $logger ) {
					$logger->warning( 'Mailer: attachment not readable, skipped.', array( 'file' => basename( $path ) ) );
				}
				continue;
			}
			$out[] = array(
				'filename' => $name,
				'content'  => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- the bluefox API expects base64 file content.
			);
		}
		return $out;
	}
}
