<?php
/**
 * Design of the ready-made emails: WooCommerce's email settings or the Form tab colours.
 *
 * @package CFBE
 */

namespace CFBE;

defined( 'ABSPATH' ) || exit;

/**
 * Colours, font, logo and footer of the email templates. With WooCommerce active the default source is
 * WooCommerce → Settings → Emails, so bluefox emails look like the shop's own emails.
 */
final class Email_Design {

	const OPTION = 'cfbe_email_design';

	const SOURCE_WOOCOMMERCE = 'woocommerce';
	const SOURCE_FORM        = 'form';

	/**
	 * WooCommerce's email fonts (WooCommerce → Settings → Emails → Font family): name => font stack.
	 */
	const FONTS = array(
		'Arial'           => "Arial, 'Helvetica Neue', Helvetica, sans-serif",
		'Comic Sans MS'   => "'Comic Sans MS', 'Marker Felt-Thin', Arial, sans-serif",
		'Courier New'     => "'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Typewriter', monospace",
		'Georgia'         => "Georgia, Times, 'Times New Roman', serif",
		'Helvetica'       => "'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif",
		'Lucida'          => "'Lucida Sans Unicode', 'Lucida Grande', sans-serif",
		'Tahoma'          => 'Tahoma, Verdana, Segoe, sans-serif',
		'Times New Roman' => "'Times New Roman', Times, Baskerville, Georgia, serif",
		'Trebuchet MS'    => "'Trebuchet MS', 'Lucida Grande', 'Lucida Sans Unicode', 'Lucida Sans', Tahoma, sans-serif",
		'Verdana'         => 'Verdana, Geneva, sans-serif',
	);

	const DEFAULT_FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

	/**
	 * WooCommerce's classic email colours, used when an option is empty and WooCommerce gives no default.
	 */
	const WC_FALLBACK = array(
		'base'        => '#720eec',
		'bg'          => '#f7f7f7',
		'body_bg'     => '#ffffff',
		'body_text'   => '#3c3c3c',
		'footer_text' => '#3c3c3c',
	);

	/**
	 * Whether WooCommerce is active (its email settings can be used).
	 */
	public static function woocommerce_available(): bool {
		return function_exists( 'WC' );
	}

	/**
	 * The chosen source (WooCommerce when available, unless the admin chose the Form tab).
	 */
	public static function source(): string {
		$stored = get_option( self::OPTION, '' );
		if ( self::SOURCE_FORM === $stored || ! self::woocommerce_available() ) {
			return self::SOURCE_FORM;
		}
		return self::SOURCE_WOOCOMMERCE;
	}

	/**
	 * Stores the source.
	 *
	 * @param string $source Source.
	 */
	public static function set_source( string $source ): void {
		update_option( self::OPTION, self::SOURCE_FORM === $source ? self::SOURCE_FORM : self::SOURCE_WOOCOMMERCE, false );
	}

	/**
	 * The design: colors (primary, button_text, accent, text, muted, border, card, page, body, footer), font, logo
	 * (url, width, align) and footer (HTML, '' = the site name).
	 *
	 * @param string|null $source Source (default: the chosen one).
	 */
	public static function get( ?string $source = null ): array {
		$design = self::SOURCE_WOOCOMMERCE === ( $source ?? self::source() ) ? self::from_woocommerce() : self::from_form();
		/**
		 * Filters the design of the ready-made emails.
		 *
		 * @param array $design Design.
		 */
		return (array) apply_filters( 'cfbe_email_design', $design );
	}

	/**
	 * Fingerprint of the design, to tell when an email in bluefox has an older design.
	 */
	public static function hash(): string {
		return md5( (string) wp_json_encode( self::get() ) );
	}

	/**
	 * Design from the Form tab colours and the site logo.
	 */
	private static function from_form(): array {
		$form  = Settings::get( 'form' );
		$pick  = static function ( string $key ) use ( $form ): string {
			$value = Style_Vars::hex( (string) ( $form[ 'color_' . $key ] ?? '' ) );
			return null !== $value ? $value : Style_Vars::DEFAULT_COLORS[ 'color_' . $key ];
		};
		$muted = $pick( 'muted' );
		return array(
			'colors' => array(
				'primary'     => $pick( 'primary' ),
				'button_text' => $pick( 'button_text' ),
				'accent'      => $pick( 'accent' ),
				'text'        => $pick( 'text' ),
				'muted'       => $muted,
				'border'      => $pick( 'border' ),
				'card'        => $pick( 'card' ),
				'page'        => '#f2f3f5',
				'body'        => '#ffffff',
				'footer'      => $muted,
			),
			'font'   => self::DEFAULT_FONT,
			'logo'   => array(
				'url'   => self::site_logo(),
				'width' => 0,
				'align' => 'center',
			),
			'footer' => '',
		);
	}

	/**
	 * Design from WooCommerce → Settings → Emails.
	 */
	private static function from_woocommerce(): array {
		$defaults = self::WC_FALLBACK;
		$colors   = '\Automattic\WooCommerce\Internal\Email\EmailColors';
		if ( class_exists( $colors ) && method_exists( $colors, 'get_default_colors' ) ) {
			try {
				$defaults = array_merge( $defaults, (array) $colors::get_default_colors() );
			} catch ( \Throwable $e ) {
				$defaults = self::WC_FALLBACK;
			}
		}
		$option = static function ( string $name, string $fallback ): string {
			$value = Style_Vars::hex( (string) get_option( 'woocommerce_email_' . $name, '' ) );
			$clean = Style_Vars::hex( $fallback );
			return null !== $value ? $value : ( null !== $clean ? $clean : '#000000' );
		};
		$base   = $option( 'base_color', (string) $defaults['base'] );
		$page   = $option( 'background_color', (string) $defaults['bg'] );
		$body   = $option( 'body_background_color', (string) $defaults['body_bg'] );
		$text   = $option( 'text_color', (string) $defaults['body_text'] );
		$footer = $option( 'footer_text_color', (string) $defaults['footer_text'] );

		$font  = (string) get_option( 'woocommerce_email_font_family', '' );
		$logo  = esc_url_raw( (string) get_option( 'woocommerce_email_header_image', '' ) );
		$align = (string) get_option( 'woocommerce_email_header_alignment', 'left' );

		return array(
			'colors' => array(
				'primary'     => $base,
				'button_text' => self::contrast( $base ),
				'accent'      => $base,
				'text'        => $text,
				'muted'       => self::mix( $text, $body, 0.4 ),
				'border'      => self::mix( $text, $body, 0.85 ),
				'card'        => self::mix( $base, $body, 0.94 ),
				// Same page and card colour (WooCommerce's newer default): a border keeps the card visible.
				'page'        => $page,
				'body'        => $body,
				'footer'      => $footer,
			),
			'font'   => self::FONTS[ $font ] ?? self::DEFAULT_FONT,
			'logo'   => array(
				'url'   => '' !== $logo ? $logo : self::site_logo(),
				'width' => '' !== $logo ? max( 0, min( 600, absint( get_option( 'woocommerce_email_header_image_width', 120 ) ) ) ) : 0,
				'align' => in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left',
			),
			'footer' => self::wc_footer(),
		);
	}

	/**
	 * WooCommerce's footer text with its placeholders filled in (safe HTML, no Handlebars braces).
	 */
	private static function wc_footer(): string {
		$text = (string) apply_filters( 'woocommerce_email_footer_text', (string) get_option( 'woocommerce_email_footer_text', '{site_title}' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, applied as WooCommerce does.
		if ( function_exists( 'WC' ) && is_object( WC() ) && method_exists( WC(), 'mailer' ) ) {
			$mailer = WC()->mailer();
			if ( is_object( $mailer ) && method_exists( $mailer, 'replace_placeholders' ) ) {
				$text = (string) $mailer->replace_placeholders( $text );
			}
		}
		$text = str_replace(
			array( '{site_title}', '{site_url}' ),
			array( html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ), (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
			$text
		);
		$text = (string) preg_replace( '/\{[A-Za-z_]+\}/', '', $text );
		// Braces would be read as Handlebars by bluefox.
		$text = str_replace( array( '{', '}' ), array( '&#123;', '&#125;' ), $text );
		return trim(
			wp_kses(
				$text,
				array(
					'a'      => array( 'href' => true ),
					'br'     => array(),
					'strong' => array(),
					'b'      => array(),
					'em'     => array(),
					'i'      => array(),
					'p'      => array(),
				)
			)
		);
	}

	/**
	 * Custom logo, else the site icon, else ''.
	 */
	private static function site_logo(): string {
		$url = '';
		if ( function_exists( 'get_theme_mod' ) && get_theme_mod( 'custom_logo' ) ) {
			$url = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
		}
		if ( '' === $url && function_exists( 'get_site_icon_url' ) ) {
			$url = (string) get_site_icon_url( 96 );
		}
		return $url;
	}

	/**
	 * Mixes two #rrggbb colours: 0 = $from, 1 = $to.
	 *
	 * @param string $from   Colour.
	 * @param string $to     Colour.
	 * @param float  $amount Share of $to.
	 */
	public static function mix( string $from, string $to, float $amount ): string {
		$a   = self::rgb( $from );
		$b   = self::rgb( $to );
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$out .= sprintf( '%02x', (int) round( $a[ $i ] + ( $b[ $i ] - $a[ $i ] ) * $amount ) );
		}
		return $out;
	}

	/**
	 * Readable text colour on a background (as WooCommerce does for its email header).
	 *
	 * @param string $background Colour.
	 */
	public static function contrast( string $background ): string {
		list( $r, $g, $b ) = self::rgb( $background );
		return ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) > 160 ? '#202020' : '#ffffff';
	}

	/**
	 * #rgb / #rrggbb → [r, g, b].
	 *
	 * @param string $hex Colour.
	 * @return int[]
	 */
	private static function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
	}
}
