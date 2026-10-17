<?php
/**
 * Mailer module entry.
 *
 * @package CFBE
 */

namespace CFBE\Mailer;

use CFBE\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Loaded only while the "Send WordPress emails via bluefox" module switch is on.
 */
final class Module {

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Wires the settings area, the wp_mail() routing and the admin tab.
	 */
	public function register(): void {
		Options::register();

		$plugin = $this->plugin;
		$mailer = new Mailer(
			static function () use ( $plugin ) {
				return $plugin->client();
			},
			$plugin->logger()
		);
		$mailer->register();

		if ( is_admin() ) {
			( new Admin( $mailer, $plugin ) )->register();
		}
	}
}
