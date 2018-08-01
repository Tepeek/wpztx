<?php
/**
 * EDD Review Walker
 *
 * @package EDD_Reviews
 * @subpackage Core
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Walker_EDD_Review' ) ) :

class Walker_EDD_Review extends Walker_Comment {
	/**
	 * What the class handles.
	 *
	 * @since 2.0
	 * @access public
	 * @var string
	 *
	 * @see Walker::$tree_type
	 */
	public $tree_type = 'edd_review';

	/**
	 * Start the element output.
	 *
	 * @since 2.0
	 *
	 * @see Walker::start_el()
	 * @see wp_list_comments()
	 *
	 * @global int    $comment_depth
	 * @global object $comment
	 *
	 * @param string $output  Passed by reference. Used to append additional content.
	 * @param object $comment Comment data object.
	 * @param int    $depth   Depth of comment in reference to parents.
	 * @param array  $args    An array of arguments.
	 */
	public function start_el( &$output, $comment, $depth = 0, $args = array(), $id = 0 ) {
		$depth++;
		$GLOBALS['comment_depth'] = $depth;
		$GLOBALS['comment'] = $comment;

		if ( !empty( $args['callback'] ) ) {
			ob_start();
			call_user_func( $args['callback'], $comment, $args, $depth );
			$output .= ob_get_clean();
			return;
		}

		ob_start();
		do_action( 'edd_reviews_start_el' );
		$this->html5_comment( $comment, $depth, $args );
		$output .= ob_get_clean();
	}

	/**
	 * Output a comment in the HTML5 format.
	 *
	 * @access protected
	 * @since 2.0
	 *
	 * @see wp_list_comments()
	 *
	 * @param object $comment Comment to display.
	 * @param int    $depth   Depth of comment.
	 * @param array  $args    An array of arguments.
	 */
	protected function html5_comment( $comment, $depth, $args ) {
		global $post;
		global $user_ID;

		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

		if ( edd_reviews()->has_user_purchased( $comment->user_id, $comment->comment_post_ID ) ) {
			$verified = ' <span class="edd-reviews-verified-purchase">(' . __( 'Verified Purchase', 'edd-reviews' ) . ')</span> ';
		} else {
			$verified = ' ';
		}
		ob_start();
		do_action( 'edd_reviews_before_review' );
		?>

		<<?php echo $tag; ?> id="edd-review-<?php comment_ID(); ?>" <?php comment_class( $this->has_children ? 'parent' : '' ); ?>>
			<div id="div-edd-review-<?php comment_ID(); ?>" class="edd-review-body">
				<div class="edd-review-meta">
					<div class="edd-review-author vcard">
						<?php echo get_avatar( $comment->comment_author_email, 48 )  ?>
						<?php if ( 1 == $depth ) { ?>
						<b><?php echo get_comment_meta( $comment->comment_ID, 'edd_review_title', true ); ?></b>
						<span class="edd-review-meta-rating"><?php edd_reviews()->render_star_rating( get_comment_meta( $comment->comment_ID, 'edd_rating', true ) ); ?></span>
						<?php } ?>
					</div><!-- .comment-author -->

					<div class="edd-review-metadata">
						<p>
							<?php if ( 0 != $args['avatar_size'] ) echo get_avatar( $comment, $args['avatar_size'] ); ?>
							<?php echo sprintf( '<span class="author">By %s</span>', get_comment_author_link() ); ?> on
							<a href="<?php echo esc_url( get_comment_link( $comment->comment_ID, $args ) ); ?>">
								<time datetime="<?php comment_time( 'c' ); ?>">
									<?php printf( _x( '%1$s at %2$s', '1: date, 2: time', 'edd-reviews' ), get_comment_date(), get_comment_time() ); ?>
								</time>
							</a> <?php echo $verified; ?> <?php edit_comment_link( __( 'Edit', 'edd-reviews' ), '<span class="edit-link">', '</span>' ); ?>
						</p>
					</div><!-- .comment-metadata -->

					<?php if ( '0' == $comment->comment_approved ) : ?>
					<p class="edd-review-awaiting-moderation"><i><?php _e( 'Your review is awaiting moderation.', 'edd-reviews' ); ?></i></p>
					<?php endif; ?>

					<?php edd_reviews()->comment_rating( $comment ); ?>
				</div><!-- .comment-meta -->

				<div class="edd-review-content">
					<?php comment_text(); ?>
				</div><!-- .comment-content -->

				<?php echo edd_reviews()->get_comment_helpful_output( $comment ); ?>

				<?php
				edd_reviews()->reviews_reply_link( array_merge( $args, array(
					'depth'      => $depth,
					'max_depth'  => $args['max_depth'],
				) ) );
				?>
			</div><!-- .comment-body -->
	<?php
	do_action( 'edd_reviews_after_review' );
	$output = ob_get_contents();
	ob_end_clean();
	echo apply_filters( 'edd_reviews_body', $output, $comment, $depth, $args );
	}

	 /**
	 * Ends the element output, if needed.
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @see Walker::end_el()
	 * @see wp_list_comments()
	 *
	 * @param string     $output  Used to append additional content. Passed by reference.
	 * @param WP_Comment $comment The current comment object. Default current comment.
	 * @param int        $depth   Optional. Depth of the current comment. Default 0.
	 * @param array      $args    Optional. An array of arguments. Default empty array.
	 */
	public function end_el( &$output, $comment, $depth = 0, $args = array() ) {
		if ( ! empty( $args['end-callback'] ) ) {
			ob_start();
			call_user_func( $args['end-callback'], $comment, $args, $depth );
			$output .= ob_get_clean();
			return;
		}

		$tag = ( 'div' === $args['style'] ) ? 'div' : 'li';

		$output .= "</$tag><!-- #comment-## -->\n";
		$output = apply_filters( 'edd_reviews_end_el', $output, $comment, $depth, $args );
	}
}

endif;
