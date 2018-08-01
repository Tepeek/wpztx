<?php
/**
 * Highest Average Rating List Table.
 *
 * @package    EDD_Reviews
 * @subpackage Admin/Reports
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_Reviews_Highest_Average_Rating_List_Table Class
 *
 * Renders the Highest Average Rating table on the Reports page.
 *
 * @since 2.1
 *
 * @see EDD_Reviews_Reports_List_Table
 */
class EDD_Reviews_Highest_Average_Rating_List_Table extends EDD_Reviews_Reports_List_Table {
	/**
	 * Retrieve the data to display on the list table.
	 *
	 * @access public
	 * @since  2.1
	 *
	 * @return array $data List table table.
	 */
	public function reviews_data() {
		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "
			SELECT comment_post_ID, AVG(meta_value) AS rating
			FROM {$wpdb->commentmeta}
			INNER JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
			WHERE meta_key = %s AND {$wpdb->commentmeta}.comment_id IN (
				SELECT comment_id
				FROM {$wpdb->commentmeta}
				WHERE meta_key = %s AND meta_value = 1
			)
			GROUP BY {$wpdb->comments}.comment_post_ID
			ORDER BY rating DESC", 'edd_rating', 'edd_review_approved' ), 0 );

		if ( $post_ids ) {
			$this->total_count = count( $post_ids );

			$post_ids = array_map( 'intval', $post_ids );

			$pagenum  = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
			$start    = ( $pagenum - 1 ) * $this->per_page;

			$args = array(
				'numberposts' => $this->per_page,
				'offset'      => $start,
				'post__in'     => $post_ids,
				'orderby'     => 'post__in',
				'post_type'   => 'download'
			);

			$posts = get_posts( $args );

			$this->items = $posts;

			return $posts;
		}

		return null;
	}
}