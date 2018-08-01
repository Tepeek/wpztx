<?php
/**
 * EDD Reviews List Table
 *
 * @package EDD_Reviews
 * @subpackage Admin
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'EDD_Reviews_List_Table' ) ) :

/**
 * EDD_Reviews_List_Table Class
 *
 * @package EDD_Reviews
 * @since 2.0
 * @version 1.0
 * @author Sunny Ratilal
 * @see WP_List_Table
 */
class EDD_Reviews_List_Table extends WP_List_Table {
	/**
	 * Number of reviews to show per page
	 *
	 * @var string
	 * @since 2.0
	 */
	public $per_page = 30;

	/**
	 * URL of this page
	 *
	 * @var string
	 * @since 2.0
	 */
	public $base_url;

	/**
	 * Total reviews returned
	 *
	 * @var int
	 * @since 2.0
	 */
	private $total_count;

	/**
	 * Total number of pending reviews
	 *
	 * @var int
	 * @since 2.0
	 */
	public $pending_count;

	/**
	 * Total number of approved reviews
	 *
	 * @var int
	 * @since 2.0
	 */
	public $approved_count;

	/**
	 * Total number of spam reviews
	 *
	 * @var int
	 * @since 2.0
	 */
	public $spam_count;

	/**
	 * Total number of reviews in trash
	 *
	 * @var int
	 * @since 2.0
	 */
	public $trash_count;

	/**
	 * User permissions
	 *
	 * @var bool
	 * @since 2.0
	 */
	public $user_can;

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 * @access public
	 * @see WP_List_Table::__construct() for more information on default arguments.
	 * @global int $post_id
	 * @param array $args An associative array of arguments.
	 */
	public function __construct( $args = array() ) {
		global $post_id;

		$post_id = isset( $_REQUEST['p'] ) ? absint( $_REQUEST['p'] ) : 0;

		if ( get_option('show_avatars') )
			add_filter( 'comment_author', 'floated_admin_avatar' );

		parent::__construct( array(
			'singular'  => edd_get_label_singular(),    // Singular name of the listed records
			'plural'    => edd_get_label_plural(),    	// Plural name of the listed records
			'ajax'      => true             			// Does this table support ajax?
		) );

		$this->get_review_counts();
		$this->process_bulk_action();
		$this->base_url = admin_url( 'edit.php?post_type=download&page=edd-reviews' );
	}

	/**
	 * Show the search field
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string $text Label for the search box
	 * @param string $input_id ID of the search box
	 *
	 * @return void
	 */
	public function search_box( $text, $input_id ) {
		if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
		}
		?>
		<p class="search-box">
			<?php do_action( 'edd_reviews_search' ); ?>
			<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
			<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, 'button', false, false, array('ID' => 'search-submit') ); ?><br/>
		</p>
		<?php
	}

	/**
	 * Retrieve the view types
	 *
	 * @access public
	 * @since 2.0
	 * @return array $views All the views available
	 */
	public function get_views() {
		$current         = isset( $_GET['status'] ) ? $_GET['status'] : '';
		$total_count     = '&nbsp;<span class="count">(' . $this->total_count  . ')</span>';
		$approved_count  = '&nbsp;<span class="count">(' . $this->approved_count  . ')</span>';
		$pending_count   = '&nbsp;<span class="count">(' . $this->pending_count  . ')</span>';
		$spam_count      = '&nbsp;<span class="count">(' . $this->spam_count  . ')</span>';
		$trash_count     = '&nbsp;<span class="count">(' . $this->trash_count  . ')</span>';

		$views = array(
			'all'      => sprintf( '<a href="%s"%s>%s</a>', esc_url( remove_query_arg( array( 'status', 'paged' ), $this->base_url ) ), $current === 'all' || $current == '' ? ' class="current"' : '', __( 'All', 'edd-reviews' ) . $total_count ),
			'pending'  => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( array( 'status' => 'pending', 'paged' => FALSE ), $this->base_url ) ), $current === 'pending' ? ' class="current"' : '', __( 'Pending', 'edd-reviews' ) . $pending_count ),
			'approved' => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( array( 'status' => 'approved', 'paged' => FALSE ), $this->base_url ) ), $current === 'approved' ? ' class="current"' : '', __( 'Approved', 'edd-reviews' ) . $approved_count ),
			'spam'     => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( array( 'status' => 'spam', 'paged' => FALSE ), $this->base_url ) ), $current === 'spam' ? ' class="current"' : '', __( 'Spam', 'edd-reviews' ) . $spam_count ),
			'trash'    => sprintf( '<a href="%s"%s>%s</a>', esc_url( add_query_arg( array( 'status' => 'trash', 'paged' => FALSE ), $this->base_url ) ), $current === 'trash' ? ' class="current"' : '', __( 'Trash', 'edd-reviews' ) . $trash_count ),
		);

		return apply_filters( 'edd_reviews_table_views', $views );
	}

	/**
	 * Retrieve the table columns
	 *
	 * @access public
	 * @since 2.0
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />', //Render a checkbox instead of text
			'author'   => __( 'Author', 'edd-reviews' ),
			'rating'   => __( 'Rating', 'edd-reviews' ),
			'review'   => __( 'Review', 'edd-reviews' ),
			'download' => __( 'Download', 'edd-reviews' ),
			'date'     => __( 'Date', 'edd-reviews' ),
		);

		return apply_filters( 'edd_reviews_table_columns', $columns );
	}

	/**
	 * Retrieve the table's sortable columns
	 *
	 * @access public
	 * @since 2.0
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'author' => 'review_author',
			'rating' => 'rating'
		);
	}

	protected function get_default_primary_column_name() {
		return 'review';
	}

	/**
	 * Render each row
	 *
	 * @access public
	 * @since 2.0
	 * @param object $review
	 */
	public function single_row( $review ) {
		$this->user_can = current_user_can( 'edit_comment', $review->comment_ID );

		$status = $this->get_review_status( $review->comment_ID );

		$review_class = '';
		$review_class = join( ' ', get_comment_class( $review_class, $review->comment_ID, $review->comment_post_ID ) );
		$review_class .= ' edd-review-' . $status;

		echo "<tr id='edd-review-$review->comment_ID' class='$review_class' data-review-status='$status' data-review-id='$review->comment_ID'>";
		$this->single_row_columns( $review );
		echo "</tr>\n";
	}


	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 2.0
	 * @access public
	 */
	public function no_items() {
		_e( 'No reviews found.', 'edd-reviews' );
	}

	/**
	 * Retrieve the bulk actions
	 *
	 * @access public
	 * @since 2.0
	 * @return array $actions Array of the bulk actions
	 */
	public function get_bulk_actions() {
		$status = isset( $_GET['status'] ) ? $_GET['status'] : null;
		$action = $this->current_action();

		$actions = array(
			'spam'  => _x( 'Mark as Spam', 'vendor_feedback', 'edd-reviews' ),
			'trash' => __( 'Move to Trash', 'edd-reviews' )
		);

		if ( empty( $status ) || 'approved' == $status ) {
			$actions = array( 'unapprove' => __( 'Unapprove', 'edd-reviews' ) ) + $actions;
		}

		if ( empty( $status ) || 'pending' == $status ) {
			$actions = array( 'approve' => __( 'Approve', 'edd-reviews' ) ) + $actions;
		}

		if ( 'spam' == $status ) {
			unset( $actions['spam'] );
			$actions['unspam'] = _x( 'Not Spam', 'vendor_feedback', 'edd-reviews' );
		}

		if ( 'trash' == $status ) {
			unset( $actions['trash'] );
			$actions['restore'] = __( 'Restore', 'edd-reviews' );
			$actions['delete'] = __( 'Delete Permanently', 'edd-reviews' );
		}

		return apply_filters( 'edd_reviews_table_bulk_actions', $actions );
	}

	/**
	 * Process Bulk Actions
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function process_bulk_action() {
		if ( empty( $_REQUEST['_wpnonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		$ids = isset( $_GET['download'] ) ? $_GET['download'] : false;

		if ( ! $ids ) {
			return;
		}

		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}

		foreach ( $ids as $id ) {
			if ( 'delete' == $this->current_action() ) {
				remove_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

				wp_delete_comment( $id, true );

				add_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );
			}

			if ( 'approve' == $this->current_action() || 'unspam' == $this->current_action() || 'restore' == $this->current_action() ) {
				update_comment_meta( $id, 'edd_review_approved', '1' );
				edd_reviews()->create_reviewer_discount( $id, get_comment( $id, ARRAY_A ) );
			}

			if ( 'unapprove' == $this->current_action() ) {
				update_comment_meta( $id, 'edd_review_approved', '0' );
			}

			if ( 'trash' == $this->current_action() ) {
				update_comment_meta( $id, 'edd_review_approved', 'trash' );
			}

			if ( 'spam' == $this->current_action() ) {
				update_comment_meta( $id, 'edd_review_approved', 'spam' );
			}
		}
	}

	/**
	 * Render the checkbox column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the checkbox column
	 * @return void
	 */
	public function column_cb( $review ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $review->comment_ID )
		);
	}

	/**
	 * Render the author column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the author column
	 * @return string Contains the author details
	 */
	public function column_author( $review ) {
		$author_url = get_comment_author_url( $review->comment_ID );

		if ( 'http://' == $author_url ) {
			$author_url = '';
		}

		$author_url_display = preg_replace( '|http://(www\.)?|i', '', $author_url );

		if ( strlen( $author_url_display ) > 50 ) {
			$author_url_display = substr( $author_url_display, 0, 49 ) . '&hellip;';
		}

		echo "<strong>";
		comment_author( $review->comment_ID );
		echo '</strong><br />';

		if ( ! empty( $author_url ) ) {
			echo "<a title='$author_url' href='$author_url'>$author_url_display</a><br />";
		}

		if ( ! empty( $review->comment_author_email ) ) {
			$email = apply_filters( 'comment_email', $review->comment_author_email, $review );
	        if  ( ( ! empty( $email ) ) && ( '@' != $email ) ) {
	        	$display = $email;
	        	$return = "<a href='mailto:$email'>$display</a>";
	        	return $return;
	        } else {
	        	return '';
	        }
			echo '<br />';
		}
	}

	/**
	 * Render the rating column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the rating column
	 * @return void
	 */
	public function column_rating( $review ) {
		if ( 1 == get_comment_meta( $review->comment_ID, 'edd_review_reply', true ) ) {
			echo '-';
		} else {
			$rating = get_comment_meta( $review->comment_ID, 'edd_rating', true );
			echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', absint( $rating ) );
			echo str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
		}
	}

	/**
	 * Render the review column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the review column
	 * @return void
	 */
	public function column_review( $review ) {
		$review_url = esc_url( get_comment_link( $review->comment_ID ) );
		echo '<div class="review-author">';
		$this->column_author( $review );
		echo '</div>';

		echo '<div class="submitted-on">';
		$review_url = esc_url( get_comment_link( $review->comment_ID ) );

		printf( '%1$s <a href="%2$s">%3$s at %4$s</a>', __( 'Submitted on', 'edd-reviews' ), $review_url,
			/* translators: comment date format. See http://php.net/date */
			get_comment_date( __( 'Y/m/d', 'edd-reviews' ), $review->comment_ID ),
			get_comment_date( get_option( 'time_format' ), $review->comment_ID )
		);

		echo '</div>';

		if ( $review->comment_parent ) {
			$parent = get_comment( $review->comment_parent );
			if ( $parent ) {
				$parent_link = esc_url( get_comment_link( $parent ) );
				$name = get_comment_author( $parent );
				printf(
					/* translators: %s: comment link */
					__( 'In reply to a review by %s', 'edd-reviews' ),
					'<a href="' . $parent_link . '">' . $name . '</a>'
				);
			}
		}


		comment_text( $review->comment_ID );
	}

	/**
	 * Render the download column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the download column
	 * @return void
	 */
	public function column_download( $review ) {
		global $wpdb;

		$post = get_post( $review->comment_post_ID );

		if ( current_user_can( 'edit_post', $post->ID ) ) {
			$post_link = "<a href='" . get_edit_post_link( $post->ID ) . "' class='comments-edit-item-link'>";
			$post_link .= esc_html( get_the_title( $post->ID ) ) . '</a>';
		} else {
			$post_link = esc_html( get_the_title( $post->ID ) );
		}

		echo '<div class="response-links">';
			if ( 'attachment' == $post->post_type && ( $thumb = wp_get_attachment_image( $post->ID, array( 80, 60 ), true ) ) ) {
				echo $thumb;
			}
			echo $post_link;
			$post_type_object = get_post_type_object( $post->post_type );
			echo "<a href='" . get_permalink( $post->ID ) . "' class='comments-view-item-link'>" . $post_type_object->labels->view_item . '</a>';
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"
					SELECT COUNT(DISTINCT({$wpdb->comments}.comment_ID)) AS count
					FROM {$wpdb->comments}
					INNER JOIN {$wpdb->commentmeta} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
					LEFT JOIN {$wpdb->commentmeta} AS t1 ON {$wpdb->commentmeta}.comment_ID = t1.comment_id AND t1.meta_key = 'edd_review_reply'
					WHERE {$wpdb->comments}.comment_post_ID = %d AND t1.comment_id IS NULL AND {$wpdb->commentmeta}.meta_key = 'edd_review_approved' AND {$wpdb->commentmeta}.meta_value > 0
					",
					$post->ID
				)
			);
			printf( '<a href="%1$s">%2$s %3$s</a>', $this->view_reviews_link( $post->ID ), number_format_i18n( $count ), _nx( __( 'Review', 'edd-reviews' ), __( 'Reviews', 'edd-reviews' ), number_format( $count ), 'review count', 'edd-reviews' ) );
		echo '</div>';
	}

	/**
	 * Render the date column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the date column
	 * @return void
	 */
	public function column_date( $review ) {
		$review_url = esc_url( get_comment_link( $review->comment_ID ) );

		printf( '<a href="%1$s">%2$s %3$s %4$s</a>', $review_url,
			/* translators: comment date format. See http://php.net/date */
			get_comment_date( __( 'Y/m/d', 'edd-reviews' ), $review->comment_ID ),
			__( 'at', 'edd-reviews' ),
			get_comment_date( get_option( 'time_format' ), $review->comment_ID )
		);
	}

	/**
	 * Retrieve the review counts
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function get_review_counts() {
		global $wpdb;

		$total_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_review'
			"
		);

		$this->total_count = $total_count;

		$pending_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_review'
			AND meta_key = 'edd_review_approved'
			AND meta_value = '0'
			"
		);

		$this->pending_count = $pending_count;

		$approved_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_review'
			AND meta_key = 'edd_review_approved'
			AND meta_value = '1'
			"
		);

		$this->approved_count = $approved_count;

		$spam_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_review'
			AND meta_key = 'edd_review_approved'
			AND meta_value = 'spam'
			"
		);

		$this->spam_count = $spam_count;

		$trash_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_review'
			AND meta_key = 'edd_review_approved'
			AND meta_value = 'trash'
			"
		);

		$this->trash_count = $trash_count;
	}

	/**
	 * Retrieve all the data for all the reviews
	 *
	 * @access public
	 * @since 2.0
	 * @return array $reviews_data Array of all the data for the reviews
	 */
	public function reviews_data() {
		$per_page = $this->per_page;
		$orderby  = isset( $_GET['orderby'] ) ? urldecode( $_GET['orderby'] ) : 'ID';
		$order    = isset( $_GET['order'] ) ? $_GET['order'] : 'DESC';
		$user     = isset( $_GET['user'] ) ? $_GET['user'] : null;

		$pagenum  = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$start    = ( $pagenum - 1 ) * $per_page;

		$args = array(
			'type'   => array( 'edd_review' ),
			'search' => ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : '',
			'offset' => $start,
			'number' => $this->per_page
		);

		if ( isset( $_GET['r'] ) && is_numeric( $_GET['r'] ) && isset( $_GET['review_status'] ) ) {
			$args['post_id'] = trim( $_GET['r'] );
		}

		$review_status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : 'all';

		if ( 'all' == $review_status ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
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
			);
		} elseif ( 'pending' == $review_status ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'spam',
					'compare' => '!='
				),
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'trash',
					'compare' => '!='
				),
				array(
					'key'     => 'edd_review_approved',
					'value'   => '0',
					'compare' => '='
				)
			);
		} elseif( 'spam' == $review_status ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'spam',
					'compare' => '='
				)
			);
		} elseif ( 'trash' == $review_status ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'edd_review_approved',
					'value'   => 'trash',
					'compare' => '='
				)
			);
		}

		remove_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

		$data = get_comments( $args );

		$total_comments = get_comments( array_merge( $args, array(
			'count' => true,
			'offset' => 0,
			'number' => 0
		) ) );

		add_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );

		$this->total_count = $total_comments;

		return $data;
	}

	/**
 	 * Generate and display row actions links.
 	 *
 	 * @since 2.0
 	 * @access protected
 	 *
 	 * @param object $review      Review being acted upon.
 	 * @param string $column_name Current column name.
 	 * @param string $primary     Primary column name.
 	 * @return string|void Review row actions output.
 	 */
 	protected function handle_row_actions( $review, $column_name, $primary ) {
 		if ( $primary !== $column_name ) {
			return '';
		}

		if ( ! $this->user_can ) {
 			return;
		}

		$del_nonce     = esc_html( '_wpnonce=' . wp_create_nonce( "delete-review_$review->comment_ID" ) );
		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-review_$review->comment_ID" ) );

		$approve_url   = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=approve_review&$approve_nonce" );
		$unapprove_url = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=unapprove_review&$approve_nonce" );
		$edit_url      = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . '&edit=true' );
		$spam_url      = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=spam_review&$del_nonce" );
		$unspam_url    = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=unspam_review&$del_nonce" );
		$trash_url     = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=trash_review&$del_nonce" );
		$untrash_url   = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=restore_review&$del_nonce" );
		$delete_url    = esc_url_raw( $this->base_url . '&r=' . $review->comment_ID . "&edd_action=delete_review&$del_nonce" );

		$actions = array(
			'edd-reviews-approve'   => '',
			'edd-reviews-unapprove' => '',
			'edd-reviews-reply'     => '',
			'edd-reviews-edit'      => '',
			'edd-reviews-spam'      => '',
			'edd-reviews-unspam'    => '',
			'edd-reviews-trash'     => '',
			'edd-reviews-untrash'   => '',
			'edd-reviews-delete'    => ''
		);

		$review_status = get_comment_meta( $review->comment_ID, 'edd_review_approved', true );

		$actions['edd-reviews-approve'] = "<a href='$approve_url' data-edd-reviews-action='approve' class='edd-reviews-approve' title='" . esc_attr__( 'Approve this Review', 'edd-reviews' ) . "'>" . __( 'Approve', 'edd-reviews' ) . '</a>';
		$actions['edd-reviews-unapprove'] =  "<a href='$unapprove_url' data-edd-reviews-action='unapprove' class='edd-reviews-unapprove' title='" . esc_attr__( 'Unapprove this Review', 'edd-reviews' ) . "'>" . __( 'Unapprove', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-reply'] =  "<a href='#' data-edd-reviews-action='reply' class='edd-reviews-reply' title='" . esc_attr__( 'Reply to this Review', 'edd-reviews' ) . "'>" . __( 'Reply', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-edit'] = "<a href='$edit_url' class='edd-reviews-edit' title='" . esc_attr__( 'Edit this Review', 'edd-reviews' ) . "'>" . __( 'Edit', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-spam'] = "<a href='$spam_url' data-edd-reviews-action='spam' class='edd-reviews-spam' title='" . esc_attr__( 'Mark this Review as Spam', 'edd-reviews' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb', 'edd-reviews' ) . '</a>';
		$actions['edd-reviews-unspam'] = "<a href='$unspam_url' data-edd-reviews-action='spam' class='edd-reviews-spam' title='" . esc_attr__( 'Mark this Review as Not Spam', 'edd-reviews' ) . "'>" . /* translators: mark as not spam link */ _x( 'Not Spam', 'verb', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-untrash'] = "<a href='$untrash_url' data-edd-reviews-action='untrash' class='edd-reviews-untrash' title='" . esc_attr__( 'Restore this Review', 'edd-reviews' ) . "'>" . __( 'Restore', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-delete'] = "<a href='$delete_url' class='edd-reviews-edit' title='" . esc_attr__( 'Delete this Review', 'edd-reviews' ) . "'>" . __( 'Delete Permanently', 'edd-reviews' ) . '</a>';

		$actions['edd-reviews-trash'] = "<a href='$trash_url' data-edd-reviews-action='trash' class='edd-reviews-trash' title='" . esc_attr__( 'Send this Review to Trash', 'edd-reviews' ) . "'>" . __( 'Trash', 'edd-reviews' ) . '</a>';

		$i = 0;
		$out = '<div class="row-actions">';
		foreach ( $actions as $action => $link ) {
			++$i;
			( ( ( ( 'edd-reviews-approve' == $action || 'edd-reviews-unapprove' == $action ) && 2 === $i ) || 1 === $i ) || empty( $link ) ) ? $sep = '' : $sep = ' | ';
			$out .= "<span class='$action'>$sep$link</span>";
		}
		$out .= '</div>';

		$out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details', 'edd-reviews' ) . '</span></button>';

		return $out;
 	}

	/**
	 * Setup the final data for the table
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();
		$data     = $this->reviews_data();
		$status   = isset( $_GET['status'] ) ? $_GET['status'] : 'any';

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $this->total_count,
			'per_page'    => $this->per_page,
			'total_pages' => ceil( $this->total_count / $this->per_page )
		) );
	}

	/**
	 * Render the list table
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function display() {
		wp_nonce_field( "fetch-list-" . get_class( $this ), '_ajax_fetch_list_nonce' );

		$this->display_tablenav( 'top' );
		?>
		<table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
			<thead id="edd-reviews-list-header">
				<tr><?php $this->print_column_headers(); ?></tr>
			</thead>

			<tbody id="edd-reviews-list">
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
				<tr><?php $this->print_column_headers( false ); ?></tr>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Generate a link to view reviews for a download
	 *
	 * @access private
	 * @since 2.0
	 * @return void
	 */
	private function view_reviews_link( $download_id ) {
		return esc_url( add_query_arg( array( 'r' => $download_id, 'review_status' => 'approved' ), $this->base_url ) );
	}

	/**
	 * Get the status of the review
	 *
	 * @access public
	 * @since 2.0
	 * @return string Review status
	 */
	public function get_review_status( $review_id ) {
		$status = get_comment_meta( $review_id, 'edd_review_approved', true );

		$out = '';

		switch ( $status ) {
			case '1':
				$out = 'approved';
				break;
			case '0':
				$out = 'unapproved';
				break;
			default:
				$out = $status;
				break;
		}

		return $out;
	}

	/**
	 * Hidden form used for inline editing functionality.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function inline_edit() {
		global $screen;

		?>
		<form method="get">
			<table style="display:none;">
				<tbody id="com-reply">
					<tr id="replyrow" class="inline-edit-row" style="display:none;">
						<td colspan="<?php echo $this->get_column_count(); ?>" class="colspanchange">
							<fieldset class="comment-reply edd-reviews-review-reply">
								<legend>
									<span><?php _e( 'Reply to Review', 'edd-reviews' ); ?></span>
								</legend>

								<div id="replycontainer">
									<label for="replycontent" class="screen-reader-text"><?php _e( 'Reply', 'edd-reviews' ); ?></label>
									<?php
									$quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
									wp_editor( '', 'replycontent', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings ) );
									?>
								</div>

								<p id="replysubmit" class="submit">
									<a href="#comments-form" class="save button button-primary alignright"><span id="replybtn"><?php _e( 'Submit Reply', 'edd-reviews' ); ?></span></a>
									<a href="#comments-form" class="cancel button alignleft"><?php _e( 'Cancel', 'edd-reviews' ); ?></a>
									<span class="waiting spinner"></span>
									<span class="error" style="display:none;"></span>
								</p>

								<input type="hidden" name="review_ID" id="review_ID" value="" />
								<input type="hidden" name="status" id="status" value="" />
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
		</form>
		<?php
	}

	public function ajax_user_can() {
		return current_user_can( 'edit_posts' );
	}
}

endif;
