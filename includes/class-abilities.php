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

		$this->register_template_abilities( $permission );
	}

	/**
	 * Registers the Email templates abilities (the buttons of the Email templates tab).
	 *
	 * @param callable $permission Permission callback.
	 */
	private function register_template_abilities( $permission ): void {
		$keys  = array_keys( Email_Templates::definitions() );
		$texts = array(
			'type'                 => 'object',
			'description'          => __( 'Texts to use: subject, preview, heading, intro, button, footer (Handlebars variables such as {{orderNumber}} work). Omitted texts keep their current value.', 'connector-for-bluefox-email' ),
			'properties'           => array_fill_keys( Email_Templates::TEXT_FIELDS, array( 'type' => 'string' ) ),
			'additionalProperties' => false,
		);
		$key   = array(
			'type'        => 'string',
			'enum'        => $keys,
			'description' => __( 'Template key, see cfbe/list-email-templates.', 'connector-for-bluefox-email' ),
		);

		wp_register_ability(
			'cfbe/list-email-templates',
			array(
				'label'               => __( 'List bluefox email templates', 'connector-for-bluefox-email' ),
				'description'         => __( 'Returns the ready-made emails of the Email templates tab whose feature is on: key, label, what it is, why use it, when it is sent, the email types the admin may choose (triggered = marketing, only active subscribers of a list, with an unsubscribe link; transactional = every recipient), its texts, whether it was created in bluefox, where it is used and whether its design is older than the current one. Also returns the design source (woocommerce or form) and the sender identities of the bluefox project.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'design'    => array( 'type' => 'string' ),
						'senders'   => array( 'type' => 'array' ),
						'templates' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'list_templates' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( true, true ),
			)
		);

		wp_register_ability(
			'cfbe/create-email-template',
			array(
				'label'               => __( 'Create a bluefox email from a template', 'connector-for-bluefox-email' ),
				'description'         => __( 'Creates a ready-made email in the connected bluefox project ("Create in bluefox" on the Email templates tab) and, unless assign is false, selects it where the plugin uses it: confirmation = double opt-in email of the list, welcome_coupon = coupon email, order and cart emails = an Order emails / Abandoned carts rule. Triggered (marketing) emails need list_id (see cfbe/list-bluefox-lists). WooCommerce keeps sending its own order emails.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'template'  => $key,
						'texts'     => $texts,
						'type'      => array(
							'type'        => 'string',
							'enum'        => array( 'triggered', 'transactional' ),
							'description' => __( 'Email type, only where the template allows a choice (default: the first allowed type).', 'connector-for-bluefox-email' ),
						),
						'list_id'   => array(
							'type'        => 'string',
							'description' => __( 'Subscriber list: required for triggered emails; for the confirmation email the list that gets double opt-in.', 'connector-for-bluefox-email' ),
						),
						'sender_id' => array(
							'type'        => 'string',
							'description' => __( 'Sender identity ID from cfbe/list-email-templates. Omit for the project default sender.', 'connector-for-bluefox-email' ),
						),
						'assign'    => array(
							'type'        => 'boolean',
							'description' => __( 'Select the new email where the plugin uses it. Default true.', 'connector-for-bluefox-email' ),
						),
					),
					'required'             => array( 'template' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'email_id' => array( 'type' => 'string' ),
						'notes'    => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'create_template' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, false ),
			)
		);

		wp_register_ability(
			'cfbe/update-email-template',
			array(
				'label'               => __( 'Update a bluefox email from its template', 'connector-for-bluefox-email' ),
				'description'         => __( 'Sends new texts and the current design to an email created from a template ("Update in bluefox"). Refuses with cfbe_template_changed when the email was changed in bluefox after the plugin last wrote it: ask the admin before repeating with force true, which replaces those changes. Emails switched to the bluefox visual editor are never overwritten.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'template' => $key,
						'texts'    => $texts,
						'force'    => array(
							'type'        => 'boolean',
							'description' => __( 'Replace changes made in bluefox. Default false.', 'connector-for-bluefox-email' ),
						),
					),
					'required'             => array( 'template' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'update_template' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, true ),
			)
		);

		wp_register_ability(
			'cfbe/send-test-email',
			array(
				'label'               => __( 'Send a test of a template email', 'connector-for-bluefox-email' ),
				'description'         => __( 'Has bluefox send a created template email, filled with sample data, to the email address of the signed-in admin ("Send me a test"). It cannot send to any other address.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'template' => $key,
					),
					'required'             => array( 'template' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'sent_to' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'send_test' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, false ),
			)
		);

		wp_register_ability(
			'cfbe/set-email-design',
			array(
				'label'               => __( 'Set the design of the template emails', 'connector-for-bluefox-email' ),
				'description'         => __( 'Chooses the design of the ready-made emails: woocommerce = logo, colours, font and footer from WooCommerce → Settings → Emails (needs WooCommerce); form = the signup form colours. Emails already created keep their design until cfbe/update-email-template is run for them.', 'connector-for-bluefox-email' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'design' => array(
							'type' => 'string',
							'enum' => array( Email_Design::SOURCE_WOOCOMMERCE, Email_Design::SOURCE_FORM ),
						),
					),
					'required'             => array( 'design' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'design'   => array( 'type' => 'string' ),
						'outdated' => array(
							'type'        => 'array',
							'description' => __( 'Created templates whose design differs from the new one.', 'connector-for-bluefox-email' ),
							'items'       => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( $this, 'set_design' ),
				'permission_callback' => $permission,
				'meta'                => self::meta( false, true ),
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
		Settings::remember_lists( $cache );
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
	 * `cfbe/list-email-templates`.
	 *
	 * @return array
	 */
	public function list_templates(): array {
		$hash      = Email_Design::hash();
		$templates = array();
		foreach ( Email_Templates::available() as $key => $definition ) {
			$record      = Email_Templates::record( $key );
			$created     = '' !== (string) ( $record['email_id'] ?? '' );
			$usage       = Email_Templates::usage( $key );
			$templates[] = array(
				'key'             => $key,
				'label'           => (string) $definition['label'],
				'what'            => (string) $definition['what'],
				'why'             => (string) $definition['why'],
				'when'            => (string) $definition['when'],
				'types'           => Email_Templates::types( $key ),
				'needs_list'      => in_array( 'triggered', Email_Templates::types( $key ), true ) || 'confirm' === $key,
				'texts'           => Email_Templates::texts( $key ),
				'variables'       => (string) $definition['variables'],
				'created'         => $created,
				'email_id'        => $created ? (string) $record['email_id'] : '',
				'type'            => $created ? (string) ( $record['type'] ?? '' ) : '',
				'in_use'          => $usage['used'],
				'usage'           => $usage['text'],
				'design_outdated' => $created && '' !== (string) ( $record['design'] ?? '' ) && $record['design'] !== $hash,
			);
		}

		$senders = array();
		$client  = $this->client();
		$items   = is_wp_error( $client ) ? array() : $client->get_sender_identities();
		foreach ( is_wp_error( $items ) ? array() : (array) $items as $item ) {
			$id = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $item['_id'] ?? '' ) );
			if ( '' !== $id ) {
				$senders[] = array(
					'id'    => $id,
					'name'  => (string) ( $item['name'] ?? '' ),
					'email' => (string) ( $item['email'] ?? '' ),
				);
			}
		}

		return array(
			'design'    => Email_Design::source(),
			'senders'   => $senders,
			'templates' => $templates,
		);
	}

	/**
	 * `cfbe/create-email-template`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function create_template( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$key   = $this->template_key( $input );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		if ( '' !== (string) ( Email_Templates::record( $key )['email_id'] ?? '' ) ) {
			return new \WP_Error( 'cfbe_template_exists', __( 'This email was already created in bluefox: use cfbe/update-email-template to change it.', 'connector-for-bluefox-email' ), array( 'status' => 409 ) );
		}
		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : '';
		if ( '' !== $type && ! in_array( $type, Email_Templates::types( $key ), true ) ) {
			return new \WP_Error(
				'cfbe_template_type',
				/* translators: %s: allowed email types */
				sprintf( __( 'This template can only be created as: %s.', 'connector-for-bluefox-email' ), implode( ', ', Email_Templates::types( $key ) ) ),
				array( 'status' => 400 )
			);
		}
		$result = ( new Email_Template_Sync( $client ) )->create(
			$key,
			Email_Templates::clean_texts( $key, (array) ( $input['texts'] ?? array() ) ),
			(string) preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $input['sender_id'] ?? '' ) ),
			Form_Config::clean_list_id( (string) ( $input['list_id'] ?? '' ) ),
			! isset( $input['assign'] ) || ! empty( $input['assign'] ),
			$type
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'email_id' => (string) $result['email_id'],
			'notes'    => array_values( array_map( 'strval', (array) $result['notes'] ) ),
		);
	}

	/**
	 * `cfbe/update-email-template`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function update_template( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$key   = $this->template_key( $input );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$result = ( new Email_Template_Sync( $client ) )->update( $key, Email_Templates::clean_texts( $key, (array) ( $input['texts'] ?? array() ) ), ! empty( $input['force'] ) );
		return is_wp_error( $result ) ? $result : array( 'updated' => true );
	}

	/**
	 * `cfbe/send-test-email`: only to the signed-in user.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function send_test( $input = array() ) {
		$input = is_array( $input ) ? $input : array();
		$key   = $this->template_key( $input );
		if ( is_wp_error( $key ) ) {
			return $key;
		}
		$client = $this->client();
		if ( is_wp_error( $client ) ) {
			return $client;
		}
		$to = (string) wp_get_current_user()->user_email;
		if ( '' === $to ) {
			return new \WP_Error( 'cfbe_no_email', __( 'The signed-in user has no email address.', 'connector-for-bluefox-email' ), array( 'status' => 400 ) );
		}
		$result = ( new Email_Template_Sync( $client ) )->test( $key, $to );
		return is_wp_error( $result ) ? $result : array( 'sent_to' => $to );
	}

	/**
	 * `cfbe/set-email-design`.
	 *
	 * @param mixed $input Input.
	 * @return array|\WP_Error
	 */
	public function set_design( $input = array() ) {
		$input  = is_array( $input ) ? $input : array();
		$design = (string) ( $input['design'] ?? '' );
		if ( ! in_array( $design, array( Email_Design::SOURCE_WOOCOMMERCE, Email_Design::SOURCE_FORM ), true ) ) {
			return new \WP_Error( 'cfbe_design', __( 'Design must be woocommerce or form.', 'connector-for-bluefox-email' ), array( 'status' => 400 ) );
		}
		if ( Email_Design::SOURCE_WOOCOMMERCE === $design && ! Email_Design::woocommerce_available() ) {
			return new \WP_Error( 'cfbe_design', __( 'WooCommerce is not active, so its email design cannot be used.', 'connector-for-bluefox-email' ), array( 'status' => 409 ) );
		}
		Email_Design::set_source( $design );
		$hash     = Email_Design::hash();
		$outdated = array();
		foreach ( array_keys( Email_Templates::definitions() ) as $key ) {
			$record = Email_Templates::record( $key );
			if ( '' !== (string) ( $record['email_id'] ?? '' ) && '' !== (string) ( $record['design'] ?? '' ) && $record['design'] !== $hash ) {
				$outdated[] = $key;
			}
		}
		return array(
			'design'   => Email_Design::source(),
			'outdated' => $outdated,
		);
	}

	/**
	 * Template key from the input; only templates whose feature is on.
	 *
	 * @param array $input Input.
	 * @return string|\WP_Error
	 */
	private function template_key( array $input ) {
		$key = sanitize_key( (string) ( $input['template'] ?? '' ) );
		if ( ! isset( Email_Templates::available()[ $key ] ) ) {
			return new \WP_Error(
				'cfbe_template_unknown',
				/* translators: %s: template keys */
				sprintf( __( 'Unknown template or its feature is off. Available: %s.', 'connector-for-bluefox-email' ), implode( ', ', array_keys( Email_Templates::available() ) ) ),
				array( 'status' => 400 )
			);
		}
		return $key;
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
