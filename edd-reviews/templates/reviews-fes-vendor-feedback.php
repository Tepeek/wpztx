<?php
/**
 * This template is used to render each vendor feedback item
 */

$page = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$feedback = edd_reviews()->fes->get_vendor_feedback( $page );

if ( ! empty ( $feedback ) ) {
	foreach ( $feedback as $item ) {
		$rating = get_comment_meta( $item->comment_ID, 'edd_rating', true );
		$described = get_comment_meta( $item->comment_ID, 'edd_item_as_described', true );
		$post = get_post( $item->comment_post_ID );
		?>
	<div class="edd-reviews-vendor-feedback-item">
		<p><strong><?php _e( 'Item:', 'edd-reviews' ); ?></strong> <a href="<?php the_permalink( $item->comment_post_ID ) ?>"><?php echo get_the_title( $item->comment_post_ID ); ?></a></p>
		<p><strong><?php _e( 'Date:', 'edd-reviews' ); ?></strong> <?php comment_date( get_option('date_format'), $item->comment_ID ); ?></p>
		<p><strong><?php _e( 'Author:', 'edd-reviews' ); ?></strong> <a href="mailto:<?php echo comment_author_email( $item->comment_ID ); ?>"><?php comment_author( $item->comment_ID ); ?></a></p>
		<p><strong><?php _e( 'Rating:', 'edd-reviews' ); ?></strong> <?php echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', $rating ) . str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) ); ?></p>
		<p><strong><?php _e( 'Item as Described:', 'edd-reviews' ); ?></strong> <?php ($described == 0) ? _e( 'No', 'edd-reviews' ) : _e( 'Yes', 'edd-reviews' ); ?></p>
		<p><strong><?php _e( 'Comments', 'edd-reviews' ); ?></strong></p>
		<?php comment_text( $item->comment_ID ); ?>
	</div>
	<?php
	} // end foreach
} else { ?>
	<h3><?php printf( __( 'No %s Feedback Found', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name( false, true ) ); ?></h3>
<?php
} // end if

edd_reviews()->fes->pagination();
