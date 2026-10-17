<?php
/**
 * Gutenberg block cfbe/signup-form.
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

use CFBE\Form_Config;
use CFBE\Settings;
use CFBE\Style_Vars;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic block rendered through the same Form_Renderer as [cfbe_form] (source tag `source-block`).
 * The editor script ships without a build step (blocks/signup-form/index.js).
 */
final class Form_Block {

	const NAME          = 'cfbe/signup-form';
	const EDITOR_SCRIPT = 'cfbe-signup-form-editor';
	const EDITOR_STYLE  = 'cfbe-form-editor';

	/**
	 * Renderer.
	 *
	 * @var Form_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param Form_Renderer $renderer Renderer.
	 */
	public function __construct( Form_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_data' ) );
	}

	/**
	 * Registers the editor assets and the block type.
	 */
	public function register_block(): void {
		wp_register_script(
			self::EDITOR_SCRIPT,
			CFBE_URL . 'blocks/signup-form/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			\CFBE\Plugin::asset_version( 'blocks/signup-form/index.js' ),
			true
		);
		wp_set_script_translations( self::EDITOR_SCRIPT, 'connector-for-bluefox-email', CFBE_DIR . 'languages' );

		// The preview in the (iframed) editor needs the form CSS and the colour variables.
		wp_register_style( self::EDITOR_STYLE, CFBE_URL . 'assets/css/cfbe-form.css', array(), \CFBE\Plugin::asset_version( 'assets/css/cfbe-form.css' ) );
		wp_add_inline_style( self::EDITOR_STYLE, Style_Vars::css( Settings::get( 'form' ) ) );

		register_block_type(
			CFBE_DIR . 'blocks/signup-form',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Cached bluefox lists for the list dropdown (editor only; no extra REST endpoint).
	 */
	public function editor_data(): void {
		$lists = array();
		foreach ( Form_Config::known_lists() as $id => $name ) {
			$lists[] = array(
				'id'   => (string) $id,
				'name' => $name,
			);
		}
		wp_add_inline_script( self::EDITOR_SCRIPT, 'window.cfbeSignupForm = ' . wp_json_encode( array( 'lists' => $lists ) ) . ';', 'before' );
	}

	/**
	 * Render callback.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render( $attributes ): string {
		Assets::enqueue();
		return $this->renderer->render( self::args( is_array( $attributes ) ? $attributes : array() ) );
	}

	/**
	 * Block attributes → Form_Renderer args. Empty texts fall back to the Form tab (unlike the shortcode,
	 * where an empty attribute hides the element).
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function args( array $attributes ): array {
		$args = array(
			'source' => 'block',
			'list'   => (string) ( $attributes['list'] ?? '' ),
			'tags'   => (string) ( $attributes['tags'] ?? '' ),
			'place'  => (string) ( $attributes['place'] ?? '' ),
		);
		foreach ( array( 'heading', 'intro', 'button', 'variant' ) as $key ) {
			if ( '' !== trim( (string) ( $attributes[ $key ] ?? '' ) ) ) {
				$args[ $key ] = (string) $attributes[ $key ];
			}
		}
		if ( isset( $attributes['showName'] ) && false === $attributes['showName'] ) {
			$args['show_name'] = '0';
		}
		if ( isset( $attributes['showInterests'] ) && false === $attributes['showInterests'] ) {
			$args['show_interests'] = '0';
		}

		$classes = array();
		if ( ! empty( $attributes['align'] ) && in_array( $attributes['align'], array( 'wide', 'full' ), true ) ) {
			$classes[] = 'align' . $attributes['align'];
		}
		if ( ! empty( $attributes['className'] ) ) {
			$classes[] = (string) $attributes['className'];
		}
		if ( $classes ) {
			$args['class'] = implode( ' ', $classes );
		}
		return $args;
	}
}
