<?php
/**
 * Personal data exporter / eraser for stored carts.
 *
 * @package CFBE
 */

namespace CFBE\Woo_Events;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the abandoned cart table into Tools → Export / Erase Personal Data.
 */
class Privacy {

	const ID = 'cfbe-carts';

	/**
	 * Cart storage.
	 *
	 * @var Cart_Repository
	 */
	private $carts;

	/**
	 * Constructor.
	 *
	 * @param Cart_Repository $carts Cart storage.
	 */
	public function __construct( Cart_Repository $carts ) {
		$this->carts = $carts;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'add_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'add_eraser' ) );
		add_action( 'admin_init', array( $this, 'policy_text' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $exporters Exporters.
	 */
	public function add_exporter( $exporters ): array {
		$exporters             = (array) $exporters;
		$exporters[ self::ID ] = array(
			'exporter_friendly_name' => __( 'bluefox.email abandoned carts', 'connector-for-bluefox-email' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $erasers Erasers.
	 */
	public function add_eraser( $erasers ): array {
		$erasers             = (array) $erasers;
		$erasers[ self::ID ] = array(
			'eraser_friendly_name' => __( 'bluefox.email abandoned carts', 'connector-for-bluefox-email' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Exports the carts of an email.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 */
	public function export( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Single page.
		$data = array();
		foreach ( $this->carts->find_by_email( (string) $email ) as $cart ) {
			$names = array();
			foreach ( (array) $cart['cart'] as $item ) {
				$names[] = sprintf( '%s × %d', (string) ( $item['name'] ?? '' ), (int) ( $item['quantity'] ?? 0 ) );
			}
			$data[] = array(
				'group_id'    => self::ID,
				'group_label' => __( 'Abandoned carts', 'connector-for-bluefox-email' ),
				'item_id'     => 'cfbe-cart-' . (int) $cart['id'],
				'data'        => array(
					array(
						'name'  => __( 'Email', 'connector-for-bluefox-email' ),
						'value' => (string) $cart['email'],
					),
					array(
						'name'  => __( 'Products', 'connector-for-bluefox-email' ),
						'value' => implode( ', ', $names ),
					),
					array(
						'name'  => __( 'Cart total', 'connector-for-bluefox-email' ),
						'value' => (string) $cart['cart_total'] . ' ' . (string) $cart['currency'],
					),
					array(
						'name'  => __( 'Status', 'connector-for-bluefox-email' ),
						'value' => (string) $cart['status'],
					),
					array(
						'name'  => __( 'Last updated (UTC)', 'connector-for-bluefox-email' ),
						'value' => (string) $cart['updated_at'],
					),
				),
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erases the carts of an email.
	 *
	 * @param string $email Email.
	 * @param int    $page  Page.
	 */
	public function erase( $email, $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Single page.
		$removed = $this->carts->delete_by_email( (string) $email );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 */
	public function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			__( 'Connector for bluefox.email – abandoned carts', 'connector-for-bluefox-email' ),
			wp_kses_post( wpautop( __( 'When you enter your email address at checkout or are logged in, we store the contents of your cart together with your email address. If you do not complete the order, we may send you one reminder email (only if you are subscribed to our newsletter) with a link that restores your cart. Stored carts are deleted automatically after a while.', 'connector-for-bluefox-email' ) ) )
		);
	}
}
