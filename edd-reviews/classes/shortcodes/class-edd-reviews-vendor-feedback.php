<?php
/**
 * Vendor Feedback Shortcode Class
 *
 * @package EDD_Reviews
 * @subpackage Shortcodes
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Vendor_Feedback' ) ) :

/**
 * EDD_Reviews_Vendor_Feedback Class
 *
 * @package EDD_Reviews
 * @since 1.0
 * @version 1.1
 * @author Sunny Ratilal
 */
final class EDD_Reviews_Vendor_Feedback {
	/**
	 * Render the shortcode
	 *
	 * @since 1.0
	 * @access public
	 * @param array $atts Shortcode attributes
	 * @return void
	 */
	public static function render( $atts ) {
		global $post;

		if ( ! isset( $_GET['payment_key'] ) ) {
			ob_start();
			?>
			<div class="edd_errors edd-alert edd-alert-error">
				<?php _e( 'Sorry, there was trouble retrieving your purchase details.', 'edd-reviews' ); ?>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( isset( $_GET['payment_key'] ) ) {
			$payment_key = urldecode( $_GET['payment_key'] );
		}

		$edd_receipt_args = array();

		$edd_receipt_args['id'] = edd_get_purchase_id_by_key( $payment_key );
		$customer_id = edd_get_payment_user_id( $edd_receipt_args['id'] );

		$user_can_view = ( is_user_logged_in() && $customer_id == get_current_user_id() ) || ( ( $customer_id == 0 || $customer_id == '-1' ) && ! is_user_logged_in() && edd_get_purchase_session() ) || current_user_can( 'view_shop_sensitive_data' );

		ob_start();

		edd_get_template_part( 'shortcode', 'vendor-feedback' );

		$display = ob_get_clean();

		return $display;
	}
}

endif;