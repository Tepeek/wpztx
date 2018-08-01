<?php
/**
 * Average Rating Shortcode Class
 *
 * @package    EDD_Reviews
 * @subpackage Shortcodes
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Reviews_Shortcode_Average_Rating {
	/**
	 * Render the shortcode
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $atts Shortcode attributes.
	 * @return void
	 */
	public static function render( $atts ) {
		ob_start();

		$download_obj = null;

		if ( ! isset( $atts['download'] ) ) {
			_e( 'No download found.', 'edd-reviews' );
		} else {
			$download_obj = edd_get_download( $atts['download'] );

			if ( ! is_null( $download_obj ) ) {
			?>
			<div class="edd-review-shortcode-average-rating">
				<p><b><?php echo apply_filters( 'the_title', $download_obj->post_title ); ?></b></p>
				<p><?php _e( 'Average rating:', 'edd-reviews' ); ?> <span class="average-rating"><?php edd_reviews()->average_rating( true, $download_obj->ID ); ?> <?php _e( 'out of 5 stars', 'edd-reviews' ); ?></span>
			</div>
			<?php
			}
		}

		$output = ob_get_contents();
		ob_end_clean();

		/**
		 * Filter the shortcode output.
		 *
		 * @since 2.1
		 *
		 * @param string       $output       Rendered shortcode output.
		 * @param EDD_Download $download_obj EDD_Download object.
		 */
		return apply_filters( 'edd_reviews_shortcode_average_rating_output', $output, $download_obj );
	}
}