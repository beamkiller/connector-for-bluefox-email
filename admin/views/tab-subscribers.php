<?php
/**
 * Subscribers tab view (read-only).
 *
 * @package CFBE
 *
 * @var array $cfbe_filters status, source, search, page, per_page.
 * @var array $cfbe_result  rows, total.
 */

use CFBE\Admin\Admin_Page;
use CFBE\Subscriber_Repository;

defined( 'ABSPATH' ) || exit;

$cfbe_statuses = array(
	Subscriber_Repository::STATUS_PENDING   => __( 'Awaiting confirmation', 'connector-for-bluefox-email' ),
	Subscriber_Repository::STATUS_CONFIRMED => __( 'Confirmed', 'connector-for-bluefox-email' ),
	Subscriber_Repository::STATUS_REWARDED  => __( 'Coupon sent', 'connector-for-bluefox-email' ),
	Subscriber_Repository::STATUS_EXPIRED   => __( 'Expired', 'connector-for-bluefox-email' ),
	Subscriber_Repository::STATUS_FAILED    => __( 'Failed', 'connector-for-bluefox-email' ),
	Subscriber_Repository::STATUS_EXISTING  => __( 'Already subscribed (no coupon)', 'connector-for-bluefox-email' ),
);
$cfbe_sources  = array(
	'popup'        => __( 'Popup', 'connector-for-bluefox-email' ),
	'promo'        => __( 'Promo', 'connector-for-bluefox-email' ),
	'shortcode'    => __( 'Shortcode', 'connector-for-bluefox-email' ),
	'block'        => __( 'Block', 'connector-for-bluefox-email' ),
	'checkout'     => __( 'Checkout', 'connector-for-bluefox-email' ),
	'account'      => __( 'My Account', 'connector-for-bluefox-email' ),
	'cf7'          => __( 'Contact Form 7', 'connector-for-bluefox-email' ),
	'wpforms'      => __( 'WPForms', 'connector-for-bluefox-email' ),
	'fluentforms'  => __( 'Fluent Forms', 'connector-for-bluefox-email' ),
	'gravityforms' => __( 'Gravity Forms', 'connector-for-bluefox-email' ),
	'elementor'    => __( 'Elementor', 'connector-for-bluefox-email' ),
);
$cfbe_pages    = (int) max( 1, ceil( $cfbe_result['total'] / $cfbe_filters['per_page'] ) );
$cfbe_base_url = add_query_arg(
	array_filter(
		array(
			'page'   => Admin_Page::SLUG,
			'tab'    => 'subscribers',
			'status' => $cfbe_filters['status'],
			'source' => $cfbe_filters['source'],
			's'      => $cfbe_filters['search'],
		)
	),
	admin_url( 'admin.php' )
);
// Notices from admin-post actions (read-only flags).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$cfbe_notice = isset( $_GET['cfbe_notice'] ) ? sanitize_key( wp_unslash( $_GET['cfbe_notice'] ) ) : '';
$cfbe_count  = isset( $_GET['cfbe_count'] ) ? absint( $_GET['cfbe_count'] ) : 0;
// phpcs:enable
$cfbe_notices = array(
	'resent'        => array( 'success', __( 'The coupon email has been resent.', 'connector-for-bluefox-email' ) ),
	'resend_failed' => array( 'error', __( 'Resending the coupon email failed (see the WooCommerce log, source: cfbe).', 'connector-for-bluefox-email' ) ),
	/* translators: %d: number of rewards queued */
	'polled'        => array( 'success', sprintf( __( 'Check complete, coupons queued for %d confirmed subscribers.', 'connector-for-bluefox-email' ), $cfbe_count ) ),
);
if ( isset( $cfbe_notices[ $cfbe_notice ] ) ) {
	printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $cfbe_notices[ $cfbe_notice ][0] ), esc_html( $cfbe_notices[ $cfbe_notice ][1] ) );
}
?>
<p>
	<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cfbe_poll_now' ), 'cfbe_poll_now' ) ); ?>"><?php esc_html_e( 'Check confirmations now', 'connector-for-bluefox-email' ); ?></a>
</p>
<form method="get" class="cfbe-filters">
	<input type="hidden" name="page" value="<?php echo esc_attr( Admin_Page::SLUG ); ?>">
	<input type="hidden" name="tab" value="subscribers">
	<select name="status" aria-label="<?php esc_attr_e( 'Status', 'connector-for-bluefox-email' ); ?>">
		<option value=""><?php esc_html_e( 'All statuses', 'connector-for-bluefox-email' ); ?></option>
		<?php foreach ( $cfbe_statuses as $cfbe_key => $cfbe_label ) : ?>
			<option value="<?php echo esc_attr( $cfbe_key ); ?>" <?php selected( $cfbe_filters['status'], $cfbe_key ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<select name="source" aria-label="<?php esc_attr_e( 'Source', 'connector-for-bluefox-email' ); ?>">
		<option value=""><?php esc_html_e( 'All sources', 'connector-for-bluefox-email' ); ?></option>
		<?php foreach ( $cfbe_sources as $cfbe_key => $cfbe_label ) : ?>
			<option value="<?php echo esc_attr( $cfbe_key ); ?>" <?php selected( $cfbe_filters['source'], $cfbe_key ); ?>><?php echo esc_html( $cfbe_label ); ?></option>
		<?php endforeach; ?>
	</select>
	<input type="search" name="s" value="<?php echo esc_attr( $cfbe_filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search email', 'connector-for-bluefox-email' ); ?>">
	<?php submit_button( __( 'Filter', 'connector-for-bluefox-email' ), 'secondary', '', false ); ?>
	<span class="cfbe-count">
		<?php
		/* translators: %d: number of subscribers */
		echo esc_html( sprintf( _n( '%d subscriber', '%d subscribers', $cfbe_result['total'], 'connector-for-bluefox-email' ), $cfbe_result['total'] ) );
		?>
	</span>
</form>

<?php if ( ! $cfbe_result['rows'] ) : ?>
	<p class="cfbe-empty"><?php esc_html_e( 'No subscribers match the filter yet.', 'connector-for-bluefox-email' ); ?></p>
<?php else : ?>
	<table class="widefat striped cfbe-subscribers">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Email', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Tags', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Subscribed (UTC)', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Confirmed (UTC)', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Coupon / order', 'connector-for-bluefox-email' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Last error', 'connector-for-bluefox-email' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $cfbe_result['rows'] as $cfbe_row ) : ?>
				<tr>
					<td><?php echo esc_html( $cfbe_row['email'] ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_row['name'] ); ?></td>
					<td><?php echo esc_html( $cfbe_sources[ $cfbe_row['source'] ] ?? $cfbe_row['source'] ); ?></td>
					<td><?php echo esc_html( implode( ', ', $cfbe_row['tags'] ) ); ?></td>
					<td>
						<span class="cfbe-status cfbe-status-<?php echo esc_attr( $cfbe_row['status'] ); ?>"><?php echo esc_html( $cfbe_statuses[ $cfbe_row['status'] ] ?? $cfbe_row['status'] ); ?></span>
						<?php if ( ! empty( $cfbe_row['list_status'] ) && 'active' !== $cfbe_row['list_status'] ) : ?>
							<br><small>
								<?php
								$cfbe_list_labels = array(
									'paused'       => __( 'paused', 'connector-for-bluefox-email' ),
									'unsubscribed' => __( 'unsubscribed', 'connector-for-bluefox-email' ),
								);
								/* translators: 1: bluefox list status, 2: UTC datetime */
								echo esc_html( sprintf( __( 'bluefox: %1$s (%2$s)', 'connector-for-bluefox-email' ), $cfbe_list_labels[ $cfbe_row['list_status'] ] ?? $cfbe_row['list_status'], (string) $cfbe_row['list_status_at'] ) );
								?>
							</small>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $cfbe_row['created_at'] ); ?></td>
					<td><?php echo esc_html( (string) $cfbe_row['confirmed_at'] ); ?></td>
					<td>
						<?php if ( ! empty( $cfbe_row['coupon_id'] ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( (int) $cfbe_row['coupon_id'] ) ); ?>">
								<?php
								/* translators: %d: coupon ID */
								echo esc_html( sprintf( __( 'Coupon #%d', 'connector-for-bluefox-email' ), (int) $cfbe_row['coupon_id'] ) );
								?>
							</a>
							<?php if ( Subscriber_Repository::STATUS_REWARDED === $cfbe_row['status'] ) : ?>
								<br><a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'email', rawurlencode( $cfbe_row['email'] ), admin_url( 'admin-post.php?action=cfbe_resend_coupon' ) ), 'cfbe_resend_coupon_' . $cfbe_row['email'] ) ); ?>"><?php esc_html_e( 'Resend coupon email', 'connector-for-bluefox-email' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php
						$cfbe_order = ! empty( $cfbe_row['order_id'] ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $cfbe_row['order_id'] ) : null;
						?>
						<?php if ( $cfbe_order ) : ?>
							<a href="<?php echo esc_url( $cfbe_order->get_edit_order_url() ); ?>">
								<?php
								/* translators: %d: order ID */
								echo esc_html( sprintf( __( 'Order #%d', 'connector-for-bluefox-email' ), (int) $cfbe_row['order_id'] ) );
								?>
							</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $cfbe_row['last_error'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $cfbe_pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%', $cfbe_base_url ),
						'format'  => '',
						'current' => $cfbe_filters['page'],
						'total'   => $cfbe_pages,
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
<?php endif; ?>
