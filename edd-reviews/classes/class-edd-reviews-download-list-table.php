<?php
/**
 * EDD Reviews Download List Table
 *
 * This is the class for the list table shown on the edit.php screen
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

if ( ! class_exists( 'EDD_Reviews_Download_List_Table' ) ) :

/**
 * EDD_Reviews_Download_List_Table Class
 *
 * @package EDD_Reviews
 * @since 2.0
 * @version 1.0
 * @author Sunny Ratilal
 * @see WP_List_Table
 */
class EDD_Reviews_Download_List_Table extends EDD_Reviews_List_Table {
	/**
	 * Number of reviews to show per page
	 *
	 * @var string
	 * @since 2.0
	 */
	public $per_page = 10;

	/**
	 *
	 * @return array
	 */
	protected function get_column_info() {
		return array(
			array(
				'review'  => _x( 'Review', 'column name', 'edd-reviews' ),
				'rating' => _x( 'Rating', 'column name', 'edd-reviews' )
			),
			array(),
			array(),
			'review',
		);
	}

	/**
	 *
	 * @return array
	 */
	protected function get_table_classes() {
		$classes = parent::get_table_classes();
		$classes[] = 'wp-list-table';
		$classes[] = 'edd-reviews-box';
		return $classes;
	}

	/**
	 * Retrieve all the data for all the reviews
	 *
	 * @access public
	 * @since 2.0
	 * @return array $reviews_data Array of all the data for the reviews
	 */
	public function reviews_data() {
		$args = array(
			'type'   => array( 'edd_review' ),
			'number' => $this->per_page,
			'post_id' => trim( absint( $_GET['post'] ) )
		);

		$args['meta_query'] = array(
			'relation' => 'AND',
			array(
				'key'     => 'edd_review_approved',
				'value'   => '1',
				'compare' => '='
			),
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
				'key'     => 'edd_review_reply',
				'compare' => 'NOT EXISTS'
			)
		);

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
	 *
	 * @param bool $output_empty
	 */
	public function display( $output_empty = false ) {
		$singular = $this->_args['singular'];
?>
<table class="<?php echo implode( ' ', $this->get_table_classes() ); ?>">
	<tbody>
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>
</table>
<?php
	}
}

endif;