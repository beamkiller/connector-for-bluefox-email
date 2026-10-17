<?php
/**
 * Autoloader for the CFBE namespace (no Composer at runtime).
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Maps `CFBE\Foo_Bar` → includes/class-foo-bar.php,
 * `CFBE\Admin\Foo_Bar` → admin/class-foo-bar.php,
 * `CFBE\Frontend\Foo_Bar` → public/class-foo-bar.php,
 * `CFBE\Sync\Foo_Bar` → modules/sync/class-foo-bar.php (one folder per feature module).
 */
final class Autoloader {

	const PREFIX = 'CFBE\\';

	const DIRS = array(
		''             => 'includes',
		'Admin'        => 'admin',
		'Frontend'     => 'public',
		'Sync'         => 'modules/sync',
		'Mailer'       => 'modules/mailer',
		'Woo_Events'   => 'modules/woo-events',
		'Integrations' => 'modules/integrations',
		'Webhooks'     => 'modules/webhooks',
	);

	/**
	 * Plugin root directory with trailing slash.
	 *
	 * @var string
	 */
	private static $base_dir = '';

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Plugin root directory.
	 */
	public static function register( string $base_dir ): void {
		self::$base_dir = rtrim( $base_dir, '/\\' ) . '/';
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolves a class name to its file path, or null if the class is not ours.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @param string $base_dir   Plugin root directory with trailing slash.
	 */
	public static function path_for( string $class_name, string $base_dir ): ?string {
		if ( 0 !== strpos( $class_name, self::PREFIX ) ) {
			return null;
		}

		$parts = explode( '\\', substr( $class_name, strlen( self::PREFIX ) ) );
		$class = array_pop( $parts );
		$ns    = implode( '\\', $parts );

		if ( '' === $class || ! isset( self::DIRS[ $ns ] ) ) {
			return null;
		}

		return $base_dir . self::DIRS[ $ns ] . '/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	}

	/**
	 * SPL autoload callback.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function autoload( string $class_name ): void {
		$path = self::path_for( $class_name, self::$base_dir );
		if ( null !== $path && is_readable( $path ) ) {
			require_once $path;
		}
	}
}
