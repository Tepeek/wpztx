<?php
/**
 * This template is used to render the Vendor Feedback page
 */

global $edd_options;

$payment_key    = urldecode( $_GET['payment_key'] );
$edd_receipt_id = edd_get_purchase_id_by_key( $payment_key );
$customer_id    = edd_get_payment_user_id( $edd_receipt_id );
$payment        = get_post( $edd_receipt_id );
?>

<?php if ( isset( $_GET['vendor_feedback_submitted'] ) ) { ?>
	<p class="edd-reviews-vendor-feedback-submitted"><strong><?php _e( 'Your feedback has been successfully submitted.', 'edd-reviews' ); ?></strong></p>
<?php } ?>

<?php if ( empty( $payment ) ) { ?>
	<div class="edd_errors edd-alert edd-alert-error">
		<?php _e( 'The specified receipt ID appears to be invalid', 'edd-reviews' ); ?>
	</div>
<?php } ?>

<?php
$meta   = edd_get_payment_meta( $payment->ID );
$cart   = edd_get_payment_meta_cart_details( $payment->ID, true );
$user   = edd_get_payment_meta_user_info( $payment->ID );
$email  = edd_get_payment_user_email( $payment->ID );
$status = edd_get_payment_status( $payment, true );

if ( $cart ) {
	foreach ( $cart as $key => $item ) {
		if ( ! apply_filters( 'edd_user_can_view_receipt_item', true, $item ) ) {
			continue; // Skip this item if can't view it
		}

		$post = get_post( $item['id'] );
		$author = get_userdata( $post->post_author );

		if ( ! EDD_FES()->vendors->user_is_vendor( $post->post_author ) ) {
			continue; // Skip if the user for this download isn't a vendor
		}

		if ( empty( $item['in_bundle'] ) ) { ?>
			<div class="edd-reviews-vendor-feedback-item">
				<h3><a href="<?php echo get_permalink( $item['id'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a></h3>

				<?php
				if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post->ID ) ) {
					echo get_the_post_thumbnail( $post->ID, 'thumbnail' );
				}

				$heading = isset( $edd_options['edd_reviews_vendor_feedback_form_heading'] ) ? $edd_options['edd_reviews_vendor_feedback_form_heading'] : __( 'Rate Your Experience', 'edd-reviews' );
				?>

				<p><strong><?php _e( 'Date:', 'edd-reviews' ); ?></strong> <?php echo date_i18n( get_option( 'date_format' ), strtotime( $meta['date'] ) ); ?></p>
				<p><a href="<?php echo esc_url( add_query_arg( 'payment_key', $payment_key, edd_get_success_page_uri() ) ); ?>"><?php _e( 'Receipt', 'edd-reviews' ); ?></a></p>
				<p><strong><?php _e( 'Vendor:', 'edd-reviews' ); ?></strong> <?php echo $author->display_name; ?></p>

				<h4><?php echo $heading; ?></h4>
				<form action="<?php echo esc_url( add_query_arg( 'vendor_feedback_submitted', true ) ); ?>" method="post" name="<?php echo edd_reviews()->vendor_feedback_form_args( 'name_form' ) ?>" id="<?php echo edd_reviews()->vendor_feedback_form_args( 'id_form' ) ?>" class="<?php echo edd_reviews()->vendor_feedback_form_args( 'class_form' ) ?>">

					<?php wp_nonce_field( 'edd-review-vendor-feedback_' . $post->ID ); ?>
					<input type="hidden" name="download_id" value="<?php echo $post->ID; ?>" />
					<input type="hidden" name="edd_action" value="process_vendor_review" />

					<div class="edd-reviews-vendor-feedback-item-wrap">
						<p class="edd-reviews-vendor-feedback-rating">
							<label for="edd-reviews-rating"><?php _e( 'Rating', 'edd-reviews' ) ?></label>
							<?php edd_reviews()->render_star_rating_html(); ?>
						</p><!-- /.edd-reviews-review-form-rating -->

						<p class="edd-reviews-vendor-feedback-item-as-described">
							<label for="edd-reviews-item-as-described"><?php echo sprintf( __( '%1$s as described by the vendor?', 'edd-reviews' ), ucwords( edd_get_label_singular() ) ); ?></label>
							<label>
								<input type="radio" name="edd-reviews-item-as-described" value="1" />
								<span><?php _e( 'Yes', 'edd-reviews' ); ?></span>
							</label>

							<label>
								<input type="radio" name="edd-reviews-item-as-described" value="0" />
								<span><?php _e( 'No' ,'edd-reviews' ); ?></span>
							</label>
						</p><!-- /.edd-reviews-vendor-feedback-item-as-described -->

						<p class="edd-reviews-vendor-feedback-comment">
							<label for="edd-reviews-vendor-feedback-comments"><?php _e( 'Comment', 'edd-reviews' ); ?></label>
							<textarea id="edd-reviews-vendor-feedback-comments" name="edd-reviews-vendor-feedback-comments" cols="45" rows="8" aria-required="true" required="required"></textarea>
						</p><!-- /.edd-reviews-vendor-feedback-comment -->

						<?php do_action( 'edd_reviews_vendor_feedback_form_before_submit' ); ?>

						<p class="edd-reviews-vendor-feedback-form-submit">
							<input type="submit" class="edd-reviews-vendor-feedback-form-submit" id="edd-reviews-vendor-feedback-form-submit" name="edd-reviews-vendor-feedback-form-submit" value="<?php _e( 'Submit Feedback', 'edd-reviews' ) ?>" />
						</p><!-- /.edd-reviews-vendor-feedback-form-submit -->
					</div><!-- /.edd-reviews-vendor-feedback-item-wrap -->
				</form>
			</div>
		<?php
		} // end if
	} // end foreach
} // end if
