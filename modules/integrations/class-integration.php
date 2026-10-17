<?php
/**
 * Base class of a form plugin integration.
 *
 * @package CFBE
 */

namespace CFBE\Integrations;

use CFBE\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One third-party form plugin. Subclasses detect the plugin, hook its submission event,
 * normalize the submission and hand it to handle().
 */
abstract class Integration {

	/**
	 * Dispatcher.
	 *
	 * @var Dispatcher
	 */
	protected $dispatcher;

	/**
	 * Constructor.
	 *
	 * @param Dispatcher $dispatcher Dispatcher.
	 */
	public function __construct( Dispatcher $dispatcher ) {
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Slug, also the signup source (`cf7`, `wpforms`, ...).
	 */
	abstract public function slug(): string;

	/**
	 * Plugin name shown in the admin and in consent records.
	 */
	abstract public function label(): string;

	/**
	 * Whether the form plugin is installed and active.
	 */
	abstract public function is_active(): bool;

	/**
	 * Hooks the plugin's submission event.
	 */
	abstract public function register(): void;

	/**
	 * Admin help: what a field identifier looks like in this plugin.
	 */
	abstract public function field_help(): string;

	/**
	 * Forms of the plugin (id => title), or null when they cannot be listed (ID input instead).
	 *
	 * @return array<string, string>|null
	 */
	public function forms(): ?array {
		return null;
	}

	/**
	 * Whether this integration is configured with rule rows on the tab (Elementor is configured in its widget).
	 */
	public function has_rules(): bool {
		return Module::has_rules( $this->slug() );
	}

	/**
	 * Stored settings of this integration.
	 */
	public function settings(): array {
		$all  = (array) Settings::get( Module::AREA );
		$mine = $all[ $this->slug() ] ?? array();
		return is_array( $mine ) ? $mine : array();
	}

	/**
	 * Whether the integration is switched on (General tab).
	 */
	public function is_enabled(): bool {
		return \CFBE\Modules::enabled( 'integration_' . $this->slug() );
	}

	/**
	 * Rules configured for a form.
	 *
	 * @param string $form_id Form ID.
	 * @return array[]
	 */
	public function rules_for( string $form_id ): array {
		if ( '' === $form_id || ! $this->is_enabled() ) {
			return array();
		}
		$rules = array();
		foreach ( (array) ( $this->settings()['rules'] ?? array() ) as $rule ) {
			if ( is_array( $rule ) && (string) ( $rule['form'] ?? '' ) === $form_id ) {
				$rules[] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * Queues a signup for every rule of the submission's form.
	 *
	 * @param Submission $sub   Submission.
	 * @param array[]    $rules Rules (default: the stored rules of the form).
	 * @return int Number of queued signups.
	 */
	public function handle( Submission $sub, ?array $rules = null ): int {
		$rules  = null === $rules ? $this->rules_for( $sub->form_id ) : $rules;
		$queued = 0;
		foreach ( $rules as $rule ) {
			if ( $this->dispatcher->dispatch( $rule, $sub, $this->slug(), $this->label() ) ) {
				++$queued;
			}
		}
		return $queued;
	}

	/**
	 * Runs a submission callback without ever letting an error reach the form plugin.
	 *
	 * @param callable $callback Callback.
	 */
	protected function guard( callable $callback ): void {
		try {
			$callback();
		} catch ( \Throwable $e ) {
			$this->dispatcher->logger()->error(
				'Form integration error',
				array(
					'source' => $this->slug(),
					'error'  => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Visitor IP for the consent record (captured during the submission request).
	 */
	protected function ip(): string {
		return \CFBE\Consent_Ip::prepare( \CFBE\Rate_Limiter::client_ip() );
	}
}
