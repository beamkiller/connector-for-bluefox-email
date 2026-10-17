<?php
/**
 * Abilities API: the plugin settings as abilities, so AI agents (e.g. through the MCP Adapter) can read and change them.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `cfbe/*` abilities (WordPress 6.9+; on older versions the hooks never fire).
 * Writes go through the same sanitizers as the settings screens. Secrets (API key, webhook secret) are never
 * returned and cannot be changed here: they stay a wp-admin task.
 */
final class Abilities {

	const CATEGORY = 'bluefox-email';

	/**
	 * Area keys that are never returned or written: area => keys.
	 */
	const SECRETS = array(
		'api' => array( 'api_key', 'webhook_secret' ),
	);

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Returns the bluefox client (null while not configured).
	 *
	 * @var callable
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 * @param callable $client   Returns ?Bluefox_Client.
	 */
	public function __construct( Settings $settings, callable $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/**
	 * Hooks into the Abilities API.
	 */
	public function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'bluefox.email', 'connector-for-bluefox-email' ),
				'description' => __( 'Settings of the Connector for bluefox.email plugin: signup forms, popup, checkout opt-in, welcome coupon, emails and feature modules.', 'connector-for-bluefox-email' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$areas      = Settings::areas();
		$permission = array( $this, 'can_manage' );

		wp_register_ability(
			'cfbe/get-settings',
			array(
				'label'               => __( 'Get bluefox settings', 'connector-for-bluefox-email' ),
				'description'         => __( 'Returns the current settings of the Connector for bluefox.email plugin, grouped by area (api = connection and default list, form = signup form texts, fields and colours, popup = popup triggers and targeting, checkout = checkout opt-in, coupon = welcome coupon, emails = thank-you and preferences page texts, plus one area per enabled feature module), and the on/off state of every feature module. Read this before changing settings: the keys and value types shown are the ones cfbe/update-settings accepts. The API key and webhook secret are never returned.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'area' => array(
							'type'        => 'string',
							'enum'        => $areas,
							'description' => __( 'Only this area. Omit for all areas.', 'connector-for-bluefox-email' ),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'area => key => value.', 'connector-for-bluefox-email' ),
						),
						'modules'  => array(
							'type'        => 'object',
							'description' => __( 'module => { label, description, enabled, available }.', 'connector-for-bluefox-email' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'get_settings' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( true, true ),
			)
		);

		wp_register_ability(
			'cfbe/update-settings',
			array(
				'label'               => __( 'Update bluefox settings', 'connector-for-bluefox-email' ),
				'description'         => __( 'Changes settings of one area of the Connector for bluefox.email plugin. Pass only the keys to change; the others keep their value. A list value (e.g. form.interests, woo_events.rules) replaces the whole list, so send the complete list. Values are validated like on the settings screen: invalid values fall back to a safe value, and the response lists every key whose saved value differs from what was sent (adjusted). Use cfbe/list-bluefox-lists to find list IDs and cfbe/list-bluefox-emails to find email IDs. The API key and webhook secret can only be changed in wp-admin.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'area'   => array(
							'type'        => 'string',
							'enum'        => $areas,
							'description' => __( 'Settings area to change.', 'connector-for-bluefox-email' ),
						),
						'values' => array(
							'type'                 => 'object',
							'description'          => __( 'key => new value, keys as returned by cfbe/get-settings.', 'connector-for-bluefox-email' ),
							'additionalProperties' => true,
						),
					),
					'required'             => array( 'area', 'values' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'area'     => array( 'type' => 'string' ),
						'settings' => array( 'type' => 'object' ),
						'changed'  => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'adjusted' => array(
							'type'        => 'object',
							'description' => __( 'key => value actually saved, where it differs from the value sent.', 'connector-for-bluefox-email' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'update_settings' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, true ),
			)
		);

		wp_register_ability(
			'cfbe/set-modules',
			array(
				'label'               => __( 'Switch bluefox features on or off', 'connector-for-bluefox-email' ),
				'description'         => __( 'Switches feature modules of the Connector for bluefox.email plugin on or off (e.g. forms, checkout, coupon, sync, mailer, order_emails, abandoned_cart, integration_cf7, preferences, webhooks). Pass only the modules to change. A module whose required plugin (WooCommerce, a form plugin) is not active stays off. Settings areas of a newly enabled module appear from the next request.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'modules' => array(
							'type'                 => 'object',
							'description'          => __( 'module => true (on) / false (off).', 'connector-for-bluefox-email' ),
							'additionalProperties' => array( 'type' => 'boolean' ),
						),
					),
					'required'             => array( 'modules' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'modules' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => array( $this, 'set_modules' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, true ),
			)
		);

		wp_register_ability(
			'cfbe/list-bluefox-lists',
			array(
				'label'               => __( 'List bluefox subscriber lists', 'connector-for-bluefox-email' ),
				'description'         => __( 'Returns the subscriber lists of the connected bluefox project (id, name, whether double opt-in is on). Use an id as list_id in the api, popup, checkout or coupon settings.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'   => array( 'type' => 'string' ),
							'name' => array( 'type' => 'string' ),
							'doi'  => array( 'type' => 'boolean' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'list_lists' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( true, true ),
			)
		);

		wp_register_ability(
			'cfbe/list-bluefox-emails',
			array(
				'label'               => __( 'List bluefox emails', 'connector-for-bluefox-email' ),
				'description'         => __( 'Returns the transactional and triggered emails of the connected bluefox project (id, name). Use an id as coupon.email_id (transactional) or as email_id of an order email / abandoned cart rule (transactional or triggered, matching the rule type).', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'transactional' => array( 'type' => 'array' ),
						'triggered'     => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'list_emails' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( true, true ),
			)
		);
	}

	/**
	 * Ability meta: annotations, REST and MCP exposure.
	 *
	 * @param bool $read_only  Whether the ability only reads.
	 * @param bool $idempotent Whether repeating the call changes nothing more.
	 */
	private static function meta( bool $read_only, bool $idempotent ): array {
		return array(
			'annotations'  => array(
				'readonly'    => $read_only,
				'destructive' => false,
				'idempotent'  => $idempotent,
			),
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
				'type'   => 'tool',
			),
		);
	}

	/**
	 * Permission callback: the plugin's admin capability.
	 */
	public function can_manage(): bool {
		return current_user_can( Admin\Admin_Page::capability() );
	}

	/**
	 * `cfbe/get-settings`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function get_settings( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$areas = Settings::areas();
		if ( ! empty( $input['area'] ) ) {
			if ( ! in_array( $input['area'], $areas, true ) ) {
				return self::unknown_area( (string) $input['area'] );
			}
			$areas = array( (string) $input['area'] );
		}

		$settings = array();
		foreach ( $areas as $area ) {
			$settings[ $area ] = self::visible( $area, Settings::get( $area ) );
		}
		return array(
			'settings' => $settings,
			'modules'  => self::module_states(),
		);
	}

	/**
	 * `cfbe/update-settings`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function update_settings( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$area   = (string) ( $input['area'] ?? '' );
		$values = is_array( $input['values'] ?? null ) ? $input['values'] : array();
		if ( ! in_array( $area, Settings::areas(), true ) ) {
			return self::unknown_area( $area );
		}

		$current = Settings::get( $area );
		$unknown = array_diff( array_keys( $values ), array_keys( $current ) );
		if ( $unknown ) {
			return new \WP_Error(
				'cfbe_unknown_keys',
				/* translators: 1: setting keys, 2: settings area */
				sprintf( __( 'Unknown settings %1$s in area %2$s. Call cfbe/get-settings for the valid keys.', 'connector-for-bluefox-email' ), implode( ', ', $unknown ), $area ),
				array( 'status' => 400 )
			);
		}
		$secrets = array_intersect( array_keys( $values ), self::SECRETS[ $area ] ?? array() );
		if ( $secrets ) {
			return new \WP_Error(
				'cfbe_secret_setting',
				/* translators: %s: setting keys */
				sprintf( __( '%s can only be changed in wp-admin.', 'connector-for-bluefox-email' ), implode( ', ', $secrets ) ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Fires before settings are saved outside the settings screens, so the screens' sanitizers can hook in.
		 */
		do_action( 'cfbe_load_settings_sanitizers' );

		// The sanitizers expect a complete form submission: unsent keys keep their current value.
		$clean = $this->settings->sanitize( $area, array_merge( $current, $values ) );
		update_option( Settings::option_name( $area ), $clean );
		$saved = Settings::get( $area );

		$changed = array();
		foreach ( $saved as $key => $value ) {
			if ( ( $current[ $key ] ?? null ) !== $value ) {
				$changed[] = (string) $key;
			}
		}
		$adjusted = array();
		foreach ( $values as $key => $value ) {
			if ( ! self::same( $value, $saved[ $key ] ?? null ) ) {
				$adjusted[ $key ] = $saved[ $key ] ?? null;
			}
		}

		return array(
			'area'     => $area,
			'settings' => self::visible( $area, $saved ),
			'changed'  => array_values( array_diff( $changed, self::SECRETS[ $area ] ?? array() ) ),
			'adjusted' => (object) $adjusted,
		);
	}

	/**
	 * `cfbe/set-modules`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function set_modules( $input = array() ) {
		$input   = is_array( $input ) ? $input : array();
		$changes = is_array( $input['modules'] ?? null ) ? $input['modules'] : array();
		$states  = Modules::states();
		$unknown = array_diff( array_keys( $changes ), array_keys( $states ) );
		if ( $unknown ) {
			return new \WP_Error(
				'cfbe_unknown_modules',
				/* translators: 1: module slugs, 2: valid module slugs */
				sprintf( __( 'Unknown modules %1$s. Valid modules: %2$s.', 'connector-for-bluefox-email' ), implode( ', ', $unknown ), implode( ', ', array_keys( $states ) ) ),
				array( 'status' => 400 )
			);
		}
		foreach ( $changes as $slug => $on ) {
			$states[ $slug ] = (bool) $on;
		}
		update_option( Modules::OPTION, Modules::sanitize( $states ) );

		return array( 'modules' => self::module_states() );
	}

	/**
	 * `cfbe/list-bluefox-lists`: also refreshes the list cache that validates list_id.
	 *
	 * @return array|\WP_Error
	 */
	public function list_lists() {
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$lists = $client->get_lists();
		if ( is_wp_error( $lists ) ) {
			return $lists;
		}

		$cache = array();
		$out   = array();
		foreach ( $lists as $list ) {
			$id = (string) ( $list['_id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$cache[ $id ] = (string) ( $list['name'] ?? $id );
			$out[]        = array(
				'id'   => $id,
				'name' => $cache[ $id ],
				'doi'  => ! empty( $list['doubleOptIn']['active'] ),
			);
		}
		set_transient( Settings::LISTS_CACHE, $cache, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * `cfbe/list-bluefox-emails`.
	 *
	 * @return array|\WP_Error
	 */
	public function list_emails() {
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$out = array();
		foreach ( array(
			'transactional' => 'get_transactional_emails',
			'triggered'     => 'get_triggered_emails',
		) as $type => $method ) {
			$items = $client->$method();
			if ( is_wp_error( $items ) ) {
				return $items;
			}
			$out[ $type ] = array();
			foreach ( Woo_Events\Settings_Tab::map_emails( (array) $items ) as $id => $name ) {
				$out[ $type ][] = array(
					'id'   => (string) $id,
					'name' => (string) $name,
				);
			}
		}
		return $out;
	}

	/**
	 * The bluefox client, or an error while the connection is not configured.
	 *
	 * @return Bluefox_Client|\WP_Error
	 */
	private function client() {
		$client = call_user_func( $this->client );
		if ( $client instanceof Bluefox_Client ) {
			return $client;
		}
		return new \WP_Error( 'cfbe_not_configured', __( 'bluefox is not connected yet: set the project ID and API key on the General tab in wp-admin.', 'connector-for-bluefox-email' ), array( 'status' => 409 ) );
	}

	/**
	 * Module states with labels.
	 */
	private static function module_states(): array {
		$states = Modules::states();
		$out    = array();
		foreach ( Modules::all() as $slug => $module ) {
			$out[ $slug ] = array(
				'label'       => (string) $module['label'],
				'description' => (string) $module['description'],
				'enabled'     => ! empty( $states[ $slug ] ) && Modules::available( $slug ),
				'available'   => Modules::available( $slug ),
			);
		}
		return $out;
	}

	/**
	 * Values without secrets; a secret is reported as whether it is set.
	 *
	 * @param string $area   Area.
	 * @param array  $values Values.
	 */
	private static function visible( string $area, array $values ): array {
		foreach ( self::SECRETS[ $area ] ?? array() as $key ) {
			$set = '' !== (string) ( $values[ $key ] ?? '' ) || ( 'api_key' === $key && Settings::api_key_from_constant() );
			unset( $values[ $key ] );
			$values[ $key . '_set' ] = $set;
		}
		return $values;
	}

	/**
	 * Whether a sent value equals the saved one (loosely for scalars: "10" equals 10, "1" equals true).
	 *
	 * @param mixed $sent  Sent value.
	 * @param mixed $saved Saved value.
	 */
	private static function same( $sent, $saved ): bool {
		if ( is_scalar( $sent ) && is_scalar( $saved ) ) {
			if ( is_bool( $saved ) ) {
				return (bool) $sent === $saved;
			}
			return (string) $sent === (string) $saved;
		}
		return $sent == $saved; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Lists compare by value.
	}

	/**
	 * Unknown area error.
	 *
	 * @param string $area Area.
	 */
	private static function unknown_area( string $area ): \WP_Error {
		return new \WP_Error(
			'cfbe_unknown_area',
			/* translators: 1: settings area, 2: valid areas */
			sprintf( __( 'Unknown settings area "%1$s". Valid areas: %2$s.', 'connector-for-bluefox-email' ), $area, implode( ', ', Settings::areas() ) ),
			array( 'status' => 400 )
		);
	}
}
