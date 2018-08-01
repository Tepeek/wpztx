<?php
/**
 * Reviews Template
 *
 * This template is used for displaying the reviews. It can be overriden by placing this
 * file in the edd_templates folder in your theme root
 */

global $post;
$user = wp_get_current_user();
$user_id = ( isset( $user->ID ) ? (int) $user->ID : 0 );

if ( edd_reviews()->is_review_status( 'disabled' ) ) {
	return;
}

?>
<div id="edd-reviews" class="edd-reviews-area">
	<?php if ( edd_reviews()->have_reviews() ) { ?>
	<div class="edd-reviews-list">
		<?php
		edd_reviews()->reviews_title();
		edd_reviews()->maybe_show_review_breakdown();
		edd_reviews()->render_reviews();
		?>
	</div>
	<?php } ?>

    <?php  if ( ! edd_reviews()->is_review_status( 'closed' ) ) { ?>
	<div class="edd-reviews-form" id="edd-reviews-respond">
		<?php if ( ! edd_reviews()->maybe_restrict_form() ) { ?>
			<?php if ( is_user_logged_in() || ( ! is_user_logged_in() && edd_reviews()->is_guest_reviews_enabled() ) ) { ?>
				<h3 id="edd-reviews-heading" class="edd-reviews-heading"><?php echo edd_reviews()->review_form_args( 'title_review' ) ?></h3>

				<?php echo edd_reviews()->review_form_args( 'logged_in_as' ); ?>

				<form method="post" name="<?php echo edd_reviews()->review_form_args( 'name_form' ) ?>" id="<?php echo edd_reviews()->review_form_args( 'id_form' ) ?>" class="<?php echo edd_reviews()->review_form_args( 'class_form' ) ?>">
					<fieldset>
						<div class="edd-reviews-form-inner">
							<?php echo edd_reviews()->review_form_args( 'guest_form_fields' ); ?>

							<p class="edd-reviews-review-form-review-title">
								<label for="edd-reviews-review-title"><?php _e( 'Review Title', 'edd-reviews' ) ?> <span class="required">*</span></label>
								<input type="text" id="edd-reviews-review-title" class="edd-reviews-review-title" name="edd-reviews-review-title" value="" size="30" aria-required="true" required="required" />
							</p><!-- /.edd-reviews-review-form-review-title -->

							<p class="edd-reviews-review-form-rating">
								<label for="edd-reviews-review"><?php _e( 'Rating', 'edd-reviews' ) ?> <span class="required">*</span></label>
								<?php edd_reviews()->render_star_rating_html(); ?>
							</p><!-- /.edd-reviews-review-form-rating -->

							<p class="edd-reviews-review-form-review">
								<label for="edd-reviews-review"><?php _e( 'Review', 'edd-reviews' ); ?> <span class="required">*</span></label>
								<textarea id="edd-reviews-review" name="edd-reviews-review" cols="45" rows="8" aria-required="true" required="required"></textarea>
							</p><!-- /.edd-reviews-review-form-review -->

							<?php do_action( 'edd_reviews_form_before_submit' ); ?>

							<p class="edd-reviews-review-form-submit">
								<input type="submit" class="edd-reviews-review-form-submit" id="edd-reviews-review-form-submit" name="edd-reviews-review-form-submit" value="<?php _e( 'Post Review', 'edd-reviews' ) ?>" />
							</p><!-- /.edd-reviews-review-form-submit -->

							<?php do_action( 'edd_reviews_form_after' ); ?>
						</div><!-- /.edd-reviews-form-inner -->
					</fieldset>
				</form><!-- /#edd-reviews-form -->

			<?php } else { ?>

				<p class="edd-reviews-not-allowed"><?php echo apply_filters( 'edd_reviews_user_logged_out_message', __( 'You must log in to submit a review.', 'edd-reviews' ) ); ?></p>
				<?php wp_login_form( array( 'echo' => true ) ); ?>

			<?php } // end if ?>

		<?php } else { ?>
			<?php if ( ! is_user_logged_in() ) { ?>
				<p class="edd-reviews-not-allowed"><?php echo apply_filters( 'edd_reviews_user_logged_out_message', sprintf( __( 'You must log in and be a buyer of this %s to submit a review.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ) ); ?></p><!-- /.edd-reviews-not-allowed -->
			<?php } elseif ( ! edd_has_user_purchased( $user_id, $post->ID ) ) { ?>
				<p class="edd-reviews-not-allowed"><?php echo apply_filters( 'edd_reviews_user_non_buyer_message', sprintf( __( 'You must be a buyer of this %s to submit a review.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ) ); ?></p><!-- /.edd-reviews-not-allowed -->
			<?php } // end if ?>

			<?php
			if ( ! is_user_logged_in() ) {
				$output = wp_login_form( array( 'echo' => true ) );
				echo apply_filters( 'edd_reviews_user_not_buyer', $output );
			} // end if
			?>

		<?php } // end if ?>
	</div><!-- /.edd-reviews-form -->

    <?php } // end if ?>
</div><!-- /#edd-reviews -->
