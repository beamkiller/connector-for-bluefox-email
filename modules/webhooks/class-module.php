<?php
/**
 * Inbound webhooks module.
 *
 * @package CFBE
 */

namespace CFBE\Webhooks;

use CFBE\Plugin;
use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to `cfbe_webhook_received` (fired by the core endpoint), logs the event and applies it in a background
 * job: user meta, local list status, developer actions. Adds the Webhooks tab and the optional Users list column.
 */
final class Module {

	const AREA = 'webhooks';
	const JOB  = 'cfbe_webhook_process';

	/**
	 * Webhook config flags of the bluefox project webhook (PATCH /webhook).
	 */
	const EVENTS = array( 'subscription', 'bounce', 'complaint', 'click', 'open', 'sent', 'failed' );

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Event processor (lazy).
	 *
	 * @var Event_Processor|null
	 */
	private $processor = null;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks the module.
	 */
	public function register(): void {
		add_filter( 'cfbe_settings_areas', array( $this, 'add_area' ) );
		add_filter( 'cfbe_settings_defaults_' . self::AREA, array( $this, 'defaults' ) );
		add_action( 'cfbe_webhook_received', array( $this, 'on_received' ), 10, 2 );
		add_action( self::JOB, array( $this, 'run_job' ), 10, 2 );

		if ( is_admin() ) {
			( new Webhooks_Tab( $this->plugin->client(), $this->plugin->logger() ) )->register();
			if ( Settings::get( self::AREA, 'users_column' ) ) {
				( new Users_Column() )->register();
			}
		}
	}

	/**
	 * Adds the `webhooks` settings area (option `cfbe_webhooks`).
	 *
	 * @param array $areas Areas.
	 */
	public function add_area( array $areas ): array {
		$areas[] = self::AREA;
		return $areas;
	}

	/**
	 * Defaults: which events to request when registering the webhook in bluefox, Users list column.
	 *
	 * @param array $defaults Defaults.
	 */
	public function defaults( array $defaults ): array {
		return array_merge(
			$defaults,
			array(
				'event_subscription' => true,
				'event_bounce'       => true,
				'event_complaint'    => true,
				'event_click'        => false,
				'event_open'         => false,
				'event_sent'         => false,
				'event_failed'       => false,
				'users_column'       => false,
			)
		);
	}

	/**
	 * Selected events from the stored settings.
	 *
	 * @return string[]
	 */
	public static function selected_events(): array {
		$selected = array();
		foreach ( self::EVENTS as $event ) {
			if ( Settings::get( self::AREA, 'event_' . $event ) ) {
				$selected[] = $event;
			}
		}
		return $selected;
	}

	/**
	 * Request-time listener: records the event and queues the work (the endpoint must answer fast).
	 *
	 * @param string $type    Event type.
	 * @param array  $payload Payload.
	 */
	public function on_received( $type, $payload ): void {
		$type    = sanitize_key( (string) $type );
		$payload = is_array( $payload ) ? $payload : array();
		if ( '' === $type ) {
			return;
		}

		Event_Log::add( $type, Event_Processor::email( $type, $payload ) );

		if ( 0 === $this->plugin->jobs()->enqueue( self::JOB, array( $type, $payload ) ) ) {
			// No Action Scheduler: the work is local only (meta + one row), so do it now.
			$this->run_job( $type, $payload );
		}
	}

	/**
	 * Background job.
	 *
	 * @param string $type    Event type.
	 * @param array  $payload Payload.
	 */
	public function run_job( $type, $payload ): void {
		if ( null === $this->processor ) {
			$this->processor = new Event_Processor( $this->plugin->repository(), $this->plugin->logger() );
		}
		$this->processor->process( (string) $type, is_array( $payload ) ? $payload : array() );
	}
}
