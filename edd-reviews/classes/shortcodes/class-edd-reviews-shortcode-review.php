<?php
/**
 * Reviews Shortcode Class
 *
 * @package EDD_Reviews
 * @subpackage Shortcodes
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Shortcode_Review' ) ) :

/**
 * EDD_Reviews_Shortcode_Review Class
 *
 * @package EDD_Reviews
 * @since 1.0
 * @version 1.1
 * @author Sunny Ratilal
 */
final class EDD_Reviews_Shortcode_Review {
	/**
	 * Render the shortcode
	 *
	 * @since 1.0
	 * @access public
	 * @param array $atts Shortcode attributes
	 * @return void
	 */
	public static function render( $atts ) {
		ob_start();

		if ( isset( $atts['multiple'] ) && 'true' == $atts['multiple'] && isset( $atts['number'] ) && isset( $atts['download'] ) ) {
			self::render_multiple_reviews( $atts );
		} else {
			remove_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

			$review = get_comment( $atts['id'], OBJECT );

			add_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

			if ( $review ) {
				?>
				<div class="edd-review-body edd-review-shortcode-body">
					<div class="edd-review-meta">
						<div class="edd-review-author vcard">
							<b><?php echo get_comment_meta( $review->comment_ID, 'edd_review_title', true ); ?></b> <span class="edd-review-meta-rating"><?php edd_reviews()->render_star_rating( get_comment_meta( $review->comment_ID, 'edd_rating', true ) ); ?></span>

							<div class="edd-review-metadata">
								<p>
									<?php echo sprintf( '<span class="author">By %s</span>', get_comment_author_link( $review->comment_ID ) ); ?> on
									<a href="<?php echo esc_url( get_comment_link( $review->comment_ID ) ); ?>"><?php echo get_comment_date( apply_filters( 'edd_reviews_widget_date_format', get_option( 'date_format' ) ), $review->comment_ID ); ?></a>
								</p>
							</div>
						</div>
					</div>
					<div class="edd-review-content">
						<?php echo apply_filters( 'get_comment_text', $review->comment_content ); ?>
					</div>
				</div>
				<?php
			} else {
				?>
				<p><strong><?php _e( 'Review not found.', 'edd-reviews' ); ?></strong></p>
				<?php
			}

		}

		return ob_get_clean();
	}

	/**
	 * Render the shortcode
	 */
	public static function render_multiple_reviews( $atts ) {
		$args = array(
			'post_id'    => $atts['download'],
			'number'     => $atts['number'],
			'type'       => 'edd_review',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'edd_review_approved',
					'value'   => '1',
					'compare' => '='
				),
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'spam',
					'compare' => '!='
				),
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'trash',
					'compare' => '!='
				)
			)
		);

		remove_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

		$reviews = get_comments( $args );

		add_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

		if ( $reviews ) {
			foreach ( $reviews as $review ) {
				?>
                <div class="<?php echo apply_filters( 'edd_reviews_shortcode_class', 'edd-review edd-review-body edd-review-shortcode-body' ); ?>">
                    <div class="edd-review-meta">
                        <div class="edd-review-author vcard">
                            <b><?php echo get_comment_meta( $review->comment_ID, 'edd_review_title', true ); ?></b> <span class="edd-review-meta-rating"><?php edd_reviews()->render_star_rating( get_comment_meta( $review->comment_ID, 'edd_rating', true ) ); ?></span>

                            <div class="edd-review-metadata">
                                <p>
									<?php echo sprintf( '<span class="author">By %s</span>', get_comment_author_link( $review->comment_ID ) ); ?> on
                                    <a href="<?php echo esc_url( get_comment_link( $review->comment_ID ) ); ?>"><?php echo get_comment_date( apply_filters( 'edd_reviews_widget_date_format', get_option( 'date_format' ) ), $review->comment_ID ); ?></a>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="edd-review-content">
						<?php echo apply_filters( 'get_comment_text', $review->comment_content ); ?>
                    </div>
                </div>
				<?php
			}
		}
	}
}

endif;