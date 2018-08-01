<?php
/**
 * Most Reviewed List Table.
 *
 * @package    EDD_Reviews
 * @subpackage Admin/Reports
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * EDD_Reviews_Most_Reviewed_List_Table Class
 *
 * Renders the Most Reviewed table on the Reports page.
 *
 * @since 2.1
 *
 * @see EDD_Reviews_Reports_List_Table
 */
class EDD_Reviews_Most_Reviewed_List_Table extends EDD_Reviews_Reports_List_Table {
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
			SELECT comment_post_ID, COUNT(*) AS count
			FROM {$wpdb->comments}
			INNER JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_ID
			WHERE {$wpdb->comments}.comment_type = %s
			AND {$wpdb->commentmeta}.meta_key = %s AND {$wpdb->commentmeta}.meta_value = 1
			AND {$wpdb->comments}.comment_parent = 0
			GROUP BY {$wpdb->comments}.comment_post_ID
			ORDER BY count DESC", 'edd_review', 'edd_review_approved' ), 0 );

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