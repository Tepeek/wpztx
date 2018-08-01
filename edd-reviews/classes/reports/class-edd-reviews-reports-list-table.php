<?php
/**
 * Base Class for Reports List Table.
 *
 * @package    EDD_Reviews
 * @subpackage Admin/Reports
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Load WP_List_Table if not loaded
if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * EDD_Reviews_Reports_List_Table class.
 *
 * @since 2.1
 * @abstract
 *
 * @see WP_List_Table
 */
abstract class EDD_Reviews_Reports_List_Table extends WP_List_Table {
	/**
	 * Default number of items to show per page.
	 *
	 * @access public
	 * @since  2.1
	 * @var    string
	 */
	public $per_page = 30;

	/**
	 * Total number of reviews found
	 *
	 * @access public
	 * @since  2.1
	 * @var    int
	 */
	public $total_count;

		/**
	 * Set up the list table.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @see WP_List_Table::__construct()
	 *
	 * @param array $args Optional. Arbitrary display and query arguments to pass through
	 *                    the list table. Default empty array.
	 */
	public function __construct( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'singular' => 'review',
			'plural'   => 'reviews'
		) );

		parent::__construct( $args );
	}

	/**
	 * Retrieve the table columns.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @return array $columns Array of all the list table columns.
	 */
	public function get_columns() {
		$category_labels = edd_get_taxonomy_labels( 'download_category' );
		$tag_labels      = edd_get_taxonomy_labels( 'download_tag' );

		$columns = array(
			'title'          => __( 'Name', 'edd-reviews' ),
			'num_reviews'    => __( 'Number of Reviews', 'edd-reviews' ),
			'average_rating' => __( 'Average Rating', 'edd-reviews' ),
			'price'          => __( 'Price', 'edd-reviews' ),
			'sales'          => __( 'Sales', 'edd-reviews' ),
			'earnings'       => __( 'Earnings', 'edd-reviews' ),
			'date'           => __( 'Date', 'edd-reviews' )
		);

		/**
		 * Filters the reports list table columns.
		 *
		 * @since 2.1
		 *
		 * @param array                                $columns Columns for this list table.
		 * @param EDD_Reviews_Most_Reviewed_List_Table $this    List table instance.
		 */
		return apply_filters( 'edd_reviews_reports_table_columns', $columns, $this );
	}

	/**
	 * Message to be displayed when there are no items.
	 *
	 * @access public
	 * @since  2.1
	 */
	public function no_items() {
		printf( __( 'No %s found.', 'edd-reviews' ), strtolower( edd_get_label_plural() ) );
	}

	/**
	 * Render the title column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_title( $download ) {
		echo '<a href="' . get_permalink( $download ) . '">' . apply_filters( 'the_title', $download->post_title, $download->ID ) . '</a>';
	}

	/**
	 * Render the number of reviews column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_num_reviews( $download ) {
		global $wpdb;

		echo '<a href="' . esc_url( add_query_arg( array( 'r' => $download->ID, 'review_status' => 'approved' ), admin_url( 'edit.php?post_type=download&page=edd-reviews' ) ) ) . '">' . $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*) AS count
			FROM {$wpdb->comments}
			INNER JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_ID
			WHERE {$wpdb->comments}.comment_type = %s
			AND {$wpdb->commentmeta}.meta_key = %s AND {$wpdb->commentmeta}.meta_value = 1
			AND {$wpdb->comments}.comment_parent = 0
			AND {$wpdb->comments}.comment_post_ID = %d
			GROUP BY {$wpdb->comments}.comment_post_ID
			ORDER BY count DESC", 'edd_review', 'edd_review_approved', $download->ID ) ) . '</a>';
	}

	/**
	 * Render the average rating column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_average_rating( $download ) {
		edd_reviews()->average_rating( true, $download->ID );
	}

	/**
	 * Render the price column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_price( $download ) {
		edd_render_download_columns( 'price', $download->ID );
	}

	/**
	 * Render the sales column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_sales( $download ) {
		edd_render_download_columns( 'sales', $download->ID );
	}

	/**
	 * Render the earnings column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_earnings( $download ) {
		edd_render_download_columns( 'earnings', $download->ID );
	}

	/**
	 * Render the date column.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @param WP_Post $download WP_Post object holding the download data.
	 */
	public function column_date( $download ) {
		global $mode;

		if ( '0000-00-00 00:00:00' === $download->post_date ) {
			$t_time = $h_time = __( 'Unpublished', 'edd-reviews' );
			$time_diff = 0;
		} else {
			$t_time = get_the_time( __( 'Y/m/d g:i:s a' ) );
			$m_time = $download->post_date;
			$time = get_post_time( 'G', true, $download );

			$time_diff = time() - $time;

			if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
				$h_time = sprintf( __( '%s ago', 'edd-reviews' ), human_time_diff( $time ) );
			} else {
				$h_time = mysql2date( __( 'Y/m/d', 'edd-reviews' ), $m_time );
			}
		}

		if ( 'publish' === $download->post_status ) {
			$status = __( 'Published' );
		} elseif ( 'future' === $download->post_status ) {
			if ( $time_diff > 0 ) {
				$status = '<strong class="error-message">' . __( 'Missed schedule', 'edd-reviews' ) . '</strong>';
			} else {
				$status = __( 'Scheduled', 'edd-reviews' );
			}
		} else {
			$status = __( 'Last Modified', 'edd-reviews' );
		}

		/**
		 * Filters the status text of the post.
		 *
		 * @since 4.8.0
		 *
		 * @param string  $status      The status text.
		 * @param WP_Post $download        Post object.
		 * @param string  $column_name The column name.
		 * @param string  $mode        The list display mode ('excerpt' or 'list').
		 */
		$status = apply_filters( 'post_date_column_status', $status, $download, 'date', $mode );

		if ( $status ) {
			echo $status . '<br />';
		}

		/** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
		echo '<abbr title="' . $t_time . '">' . apply_filters( 'post_date_column_time', $h_time, $download, 'date', $mode ) . '</abbr>';
	}

	/**
	 * Retrieve the reviews data.
	 *
	 * @access public
	 * @since  2.1
	 */
	abstract public function reviews_data();

	/**
	 * Setup the final data for the table.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @uses EDD_Reviews_Most_Reviewed_List_Table::get_columns()
	 * @uses EDD_Reviews_Most_Reviewed_List_Table::reviews_data()
	 * @uses WP_List_Table::get_pagenum()
	 * @uses WP_List_Table::set_pagination_args()
	 */
	public function prepare_items() {
		$this->get_column_info();
		$current_page = $this->get_pagenum();

		$columns  = $this->get_columns();
		$hidden   = array(); // No hidden columns
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $this->reviews_data();

		$this->set_pagination_args( array(
				'total_items' => $this->total_count,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $this->total_count / $this->per_page )
			)
		);
	}
}