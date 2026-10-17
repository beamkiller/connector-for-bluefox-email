<?php
/**
 * [cfbe_form] shortcode ([cfbe_newsletter] kept as an alias).
 *
 * @package CFBE
 */

namespace CFBE\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the signup form anywhere (page content, page builder shortcode elements).
 */
final class Newsletter_Shortcode {

	const TAG   = 'cfbe_form';
	const ALIAS = 'cfbe_newsletter';

	// Accepted attributes (documented on the Shortcodes admin tab).
	const ATTRS = array( 'list', 'tags', 'variant', 'heading', 'heading_tag', 'intro', 'benefits', 'badge', 'button', 'offer', 'layout', 'show_name', 'show_interests', 'class', 'place' );

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
	 * Registers the shortcode and its legacy alias.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_shortcode( self::ALIAS, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback. Attributes: list (bluefox list ID), tags ("a,b"), variant (card|banner|plain), heading,
	 * heading_tag (h2|h3|h4), intro, benefits ("a|b|c"), badge="0", button, offer, layout, show_name, show_interests,
	 * class, place (adds bluefox tag placement-<place>). list/tags are signed into the form (see Form_Config).
	 * Omitted text attributes use the settings; an empty heading/intro/offer/benefits hides that element.
	 *
	 * @param array|string $atts Attributes.
	 */
	public function render( $atts ): string {
		$atts = is_array( $atts ) ? $atts : array();
		$args = array( 'source' => 'shortcode' );
		foreach ( self::ATTRS as $key ) {
			if ( array_key_exists( $key, $atts ) ) {
				$args[ $key ] = (string) $atts[ $key ];
			}
		}
		Assets::enqueue();
		return $this->renderer->render( $args );
	}
}
