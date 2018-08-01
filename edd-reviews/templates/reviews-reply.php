<?php
/**
 * Reviews Reply Template
 *
 * This template is used for displaying the reply form to a review. It can be overriden by placing this
 * file in the edd_templates folder in your theme root
 */

global $post;
$user = wp_get_current_user();
$user_id = ( isset( $user->ID ) ? (int) $user->ID : 0 );
?>
<div id="edd-reviews-reply" style="display: none">
	<div class="edd-reviews-form" id="edd-reviews-reply-form">
		<h3 id="edd-reviews-reply-heading" class="edd-reviews-heading"><?php _e( 'Leave a reply', 'edd-reviews' ) ?> <small><a rel="nofollow" id="cancel-comment-reply-link" href="#"><?php _e( 'Cancel reply', 'edd-reviews' ); ?></a></small></h3>

		<?php echo edd_reviews()->review_form_args( 'logged_in_as' ); ?>

		<form action="<?php echo esc_url( add_query_arg( 'review_submitted', true ) ); ?>" method="post" name="<?php echo edd_reviews()->review_form_args( 'name_form' ) ?>" id="<?php echo edd_reviews()->review_form_args( 'id_form' ) ?>" class="<?php echo edd_reviews()->review_form_args( 'class_form' ) ?>">
			<fieldset>
					<p class="edd-reviews-review-form-review">
						<label for="edd-reviews-review"><?php _e( 'Comment', 'edd-reviews' ); ?> <span class="required">*</span></label>
						<textarea id="edd-reviews-reply" name="edd-reviews-reply" cols="45" rows="8" aria-required="true" required="required"></textarea>
					</p><!-- /.edd-reviews-review-form-review -->

					<?php do_action( 'edd_reviews_reply_form_before_submit' ); ?>

					<p class="edd-reviews-review-form-submit">
						<input type="submit" class="edd-reviews-review-form-submit" id="edd-reviews-reply-form-submit" name="edd-reviews-reply-form-submit" value="<?php _e( 'Post Reply', 'edd-reviews' ) ?>" />
					</p><!-- /.edd-reviews-review-form-submit -->

					<?php do_action( 'edd_reviews_reply_form_after' ); ?>
			</fieldset>
		</form><!-- /#edd-reviews-reply-form -->
	</div><!-- /.edd-reviews-form -->
</div><!-- /#edd-reviews-reply -->
