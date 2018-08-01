<?php
/**
 * EDD Reviews Vendor Feedback List Table
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

if ( ! class_exists( 'EDD_Reviews_Vendor_Feedback_List_Table' ) ) :

/**
 * EDD_Reviews_Vendor_Feedback_List_Table Class
 *
 * @package EDD_Reviews
 * @since 2.0
 * @version 1.0
 * @author Sunny Ratilal
 * @see WP_List_Table
 */
class EDD_Reviews_Vendor_Feedback_List_Table extends WP_List_Table {
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

		if ( get_option('show_avatars') ) {
			add_filter( 'comment_author', 'floated_admin_avatar' );
		}

		parent::__construct( array(
			'singular'  => edd_get_label_singular(),    // Singular name of the listed records
			'plural'    => edd_get_label_plural(),    	// Plural name of the listed records
			'ajax'      => false             			// Does this table support ajax?
		) );

		$this->get_feedback_counts();
		$this->process_bulk_action();
		$this->base_url = admin_url( 'admin.php?page=fes-vendor-feedback' );
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
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
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
			<?php do_action( 'edd_reviews_vendor_feedback_search' ); ?>
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
		$total_count     = '&nbsp;<span class="count">(' . $this->total_count . ')</span>';
		$approved_count  = '&nbsp;<span class="count">(' . $this->approved_count . ')</span>';
		$pending_count   = '&nbsp;<span class="count">(' . $this->pending_count . ')</span>';
		$spam_count      = '&nbsp;<span class="count">(' . $this->spam_count . ')</span>';
		$trash_count     = '&nbsp;<span class="count">(' . $this->trash_count . ')</span>';

		$views = array(
			'all'      => sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ), $this->base_url ), $current === 'all' || $current == '' ? ' class="current"' : '', __( 'All', 'edd-reviews' ) . $total_count ),
			'pending'  => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'pending', 'paged' => FALSE ), $this->base_url ), $current === 'pending' ? ' class="current"' : '', __( 'Pending', 'edd-reviews' ) . $pending_count ),
			'approved' => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'approved', 'paged' => FALSE ), $this->base_url ), $current === 'approved' ? ' class="current"' : '', __( 'Approved', 'edd-reviews' ) . $approved_count ),
			'spam'     => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'spam', 'paged' => FALSE ), $this->base_url ), $current === 'spam' ? ' class="current"' : '', __( 'Spam', 'edd-reviews' ) . $spam_count ),
			'trash'    => sprintf( '<a href="%s"%s>%s</a>', add_query_arg( array( 'status' => 'trash', 'paged' => FALSE ), $this->base_url ), $current === 'trash' ? ' class="current"' : '', __( 'Trash', 'edd-reviews' ) . $trash_count ),
		);

		return apply_filters( 'edd_reviews_vendor_feedback_table_views', $views );
	}

	/**
	 * Retrieve the bulk actions
	 *
	 * @access public
	 * @since 1.4
	 * @return array $actions Array of the bulk actions
	 */
	public function get_bulk_actions() {
		$status = isset( $_GET['status'] ) ? $_GET['status'] : null;

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
			$action['delete'] = __( 'Delete Permanently', 'edd-reviews' );
		}

		return apply_filters( 'edd_reviews_vendor_feedback_table_bulk_actions', $actions );
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
			if ( 'approve' == $this->current_action() || 'unspam' == $this->current_action() ) {
				$args = array( 'comment_ID' => $id, 'comment_approved' => 'approve' );
				wp_update_comment( $args );
			}

			if ( 'unapprove' == $this->current_action() ) {
				$args = array( 'comment_ID' => $id, 'comment_approved' => 'hold' );
				wp_update_comment( $args );
			}

			if ( 'trash' == $this->current_action() ) {
				wp_set_comment_status( $id, 'trash' );
			}

			if ( 'spam' == $this->current_action() ) {
				wp_set_comment_status( $id, 'spam' );
			}

			if ( 'delete' == $this->current_action() ) {
				wp_delete_comment( $id );
			}
		}
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
			'feedback' => __( 'Feedback', 'edd-reviews' ),
			'download' => __( 'Download', 'edd-reviews' ),
			'vendor'   => __( 'Vendor', 'edd-reviews' ),
			'date'     => __( 'Date', 'edd-reviews' ),
		);

		return apply_filters( 'edd_reviews_vendor_feedback_table_columns', $columns );
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
			'rating' => 'rating',
			'date'   => 'date'
		);
	}

	protected function get_default_primary_column_name() {
		return 'feedback';
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

		$review_class = '';
		$review_class = join( ' ', get_comment_class( $review_class, $review->comment_ID, $review->comment_post_ID ) );
		$review_class .= ' edd-review-' . wp_get_comment_status( $review->comment_ID );

		echo "<tr id='edd-review-$review->comment_ID' class='$review_class'>";
		$this->single_row_columns( $review );
		echo "</tr>\n";
	}


	public function no_items() {
		_e( 'Nothing found.', 'edd-reviews' );
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

		echo "<strong>"; comment_author( $review->comment_ID ); echo '</strong><br />';

		if ( ! empty( $author_url ) ) {
			echo "<a title='$author_url' href='$author_url'>$author_url_display</a><br />";
		}

		if ( ! empty( $review->comment_author_email ) ) {
			$email = apply_filters( 'comment_email', $review->comment_author_email, $review );
	        if  ( ( ! empty( $email ) ) && ( $email != '@' ) ) {
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
		$rating = get_comment_meta( $review->comment_ID, 'edd_rating', true );
		echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', $rating );
		echo str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
	}

	/**
	 * Render the review column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the review column
	 * @return void
	 */
	public function column_feedback( $review ) {
		$review_url = esc_url( get_comment_link( $review->comment_ID ) );

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
					SELECT COUNT(*)
					FROM {$wpdb->commentmeta}
					LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
					WHERE comment_type = 'edd_review'
					AND meta_key = 'edd_review_approved'
					AND meta_value >= 0
					AND comment_post_ID = %d
					",
					$post->ID
				)
			);
			printf( '<a href="%1$s">%2$s %3$s</a>', $this->view_reviews_link( $post->ID ), number_format_i18n( $count ), _nx( __( 'Review', 'edd-reviews' ), __( 'Reviews', 'edd-reviews' ), number_format( $count ), 'review count', 'edd-reviews' ) );
		echo '</div>';
	}

	/**
	 * Render the vendor column
	 *
	 * @access public
	 * @since 2.0
	 * @param array $review Contains all the data for the date column
	 * @return void
	 */
	public function column_vendor( $review ) {
		echo the_author_meta( 'display_name' , get_post_field( 'post_author', $review->comment_post_ID ) );
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
	public function get_feedback_counts() {
		global $wpdb;

		$total_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_vendor_feedback'
			"
		);

		$this->total_count = $total_count;

		$pending_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_vendor_feedback'
			AND comment_approved = '0'
			"
		);

		$this->pending_count = $pending_count;

		$approved_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_vendor_feedback'
			AND comment_approved = '1'
			"
		);

		$this->approved_count = $approved_count;

		$spam_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_vendor_feedback'
			AND comment_approved = 'spam'
			"
		);

		$this->spam_count = $spam_count;

		$trash_count = $wpdb->get_var(
			"
			SELECT COUNT(DISTINCT {$wpdb->comments}.comment_ID)
			FROM {$wpdb->commentmeta}
			LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE comment_type = 'edd_vendor_feedback'
			AND comment_approved = 'trash'
			"
		);

		$this->trash_count = $trash_count;
	}

	/**
	 * Retrieve all the data for all the vendor feedback
	 *
	 * @access public
	 * @since 2.0
	 * @return array $data Array of all the data for the vendor feedback
	 */
	public function feedback_data() {
		$per_page = $this->per_page;
		$orderby = isset( $_GET['orderby'] ) ? urldecode( $_GET['orderby'] ) : 'ID';
		$order = isset( $_GET['order'] ) ? $_GET['order'] : 'DESC';
		$user = isset( $_GET['user'] ) ? $_GET['user'] : null;

		$pagenum = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$start = ( $pagenum - 1 ) * $per_page;

		$args = array(
			'type'   => array( 'edd_vendor_feedback' ),
			'search' => ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : '',
			'offset' => $start,
			'number' => $this->per_page,
			'order'  => $order
		);

		if ( 'r' == $orderby ) {
			$args['orderby'] = 'meta_value_num';
			$args['meta_key'] = 'edd_rating';
		} elseif ( 'd' == $orderby )  {
			$args['orderby'] = 'date';
		} else {
			$args['orderby'] = $orderby;
		}

		if ( isset( $_GET['r'] ) && is_numeric( $_GET['r'] ) && isset( $_GET['review_status'] ) ) {
			$args['post_id'] = trim( $_GET['r'] );
		}

		$review_status = isset( $_REQUEST['status'] ) ? $_REQUEST['status'] : 'all';

		if ( 'pending' == $review_status ) {
			$args['status'] = 'hold';
		} elseif( 'spam' == $review_status ) {
			$args['status'] = 'spam';
		} elseif ( 'trash' == $review_status ) {
			$args['status'] = 'trash';
		} elseif ( 'approved' == $review_status ) {
			$args['status'] = 'approve';
		}

		remove_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

		$data = get_comments( $args );

		$total_comments = get_comments( array_merge( $args, array(
			'count'  => true,
			'offset' => 0,
			'number' => 0
		) ) );

		add_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

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

		$url = esc_url( $this->base_url );

		$approve_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-feedback_$review->comment_ID" ) );
		$del_nonce     = esc_html( '_wpnonce=' . wp_create_nonce( "delete-feedback_$review->comment_ID" ) );

		$approve_url   = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=approve_vendor_feedback&$approve_nonce" );
		$unapprove_url = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=unapprove_vendor_feedback&$approve_nonce" );
		$edit_url      = esc_url_raw( $url . '&r=' . $review->comment_ID . '&edit=true' );
		$spam_url      = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=spam_vendor_feedback&$del_nonce" );
		$unspam_url    = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=unspam_vendor_feedback&$del_nonce" );
		$trash_url     = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=trash_vendor_feedback&$del_nonce" );
		$untrash_url   = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=restore_vendor_feedback&$del_nonce" );
		$delete_url    = esc_url_raw( $url . '&r=' . $review->comment_ID . "&edd_action=delete_vendor_feedback&$del_nonce" );

		$actions = array(
			'edd-reviews-vendor-feedback-approve'   => '',
			'edd-reviews-vendor-feedback-unapprove' => '',
			'edd-reviews-vendor-feedback-edit'      => '',
			'edd-reviews-vendor-feedback-spam'      => '',
			'edd-reviews-vendor-feedback-unspam'    => '',
			'edd-reviews-vendor-feedback-trash'     => '',
			'edd-reviews-vendor-feedback-untrash'   => '',
			'edd-reviews-vendor-feedback-delete'    => ''
		);

		$feedback_status = $review->comment_approved;

		if ( '0' == $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-approve'] = "<a href='$approve_url' class='edd-reviews-approve' title='" . esc_attr__( 'Approve this Feedback', 'edd-reviews' ) . "'>" . __( 'Approve', 'edd-reviews' ) . '</a>';
		} elseif ( '1' == $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-unapprove'] =  "<a href='$unapprove_url' class='edd-reviews-unapprove' title='" . esc_attr__( 'Unapprove this Feedback', 'edd-reviews' ) . "'>" . __( 'Unapprove', 'edd-reviews' ) . '</a>';
		}

		if ( 'spam' != $feedback_status && 'trash' != $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-edit'] = "<a href='$edit_url' class='edd-reviews-edit' title='" . esc_attr__( 'Edit this Feedback', 'edd-reviews' ) . "'>" . __( 'Edit', 'edd-reviews' ) . '</a>';
		}

		if ( 'spam' != $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-spam'] = "<a href='$spam_url' class='edd-reviews-spam' title='" . esc_attr__( 'Mark this Feedback as Spam', 'edd-reviews' ) . "'>" . /* translators: mark as spam link */ _x( 'Spam', 'verb', 'edd-reviews' ) . '</a>';
		} elseif ( 'spam' == $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-unspam'] = "<a href='$unspam_url' class='edd-reviews-spam' title='" . esc_attr__( 'Mark this Feedback as Not Spam', 'edd-reviews' ) . "'>" . /* translators: mark as not spam link */ _x( 'Not Spam', 'verb', 'edd-reviews' ) . '</a>';
		}

		if ( 'trash' == $feedback_status ) {
			$actions['edd-reviews-vendor-feedback-untrash'] = "<a href='$untrash_url' class='edd-reviews-untrash' title='" . esc_attr__( 'Restore this Feedback', 'edd-reviews' ) . "'>" . __( 'Restore', 'edd-reviews' ) . '</a>';
		}

		if ( 'spam' == $feedback_status || 'trash' == $feedback_status || !EMPTY_TRASH_DAYS ) {
			$actions['edd-reviews-vendor-feedback-delete'] = "<a href='$delete_url' class='edd-reviews-edit' title='" . esc_attr__( 'Delete this Feedback', 'edd-reviews' ) . "'>" . __( 'Delete Permanently', 'edd-reviews' ) . '</a>';
		} else {
			$actions['edd-reviews-vendor-feedback-trash'] = "<a href='$trash_url' class='edd-reviews-trash' title='" . esc_attr__( 'Send this Feedback to Trash', 'edd-reviews' ) . "'>" . __( 'Trash', 'edd-reviews' ) . '</a>';
		}

		$i = 0;
		$out = '<div class="row-actions">';
		foreach ( $actions as $action => $link ) {
			++$i;
			( ( ( ( 'edd-reviews-vendor-feedback-approve' == $action || 'edd-reviews-vendor-feedback-unapprove' == $action ) && 2 === $i ) || 1 === $i ) || empty( $link ) ) ? $sep = '' : $sep = ' | ';
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
		$data     = $this->feedback_data();
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
		return esc_url( add_query_arg( array( 'r' => $download_id, 'review_status' => 'approved' ), admin_url( 'edit.php?post_type=download&page=edd-reviews' ) ) );
	}
}

endif;