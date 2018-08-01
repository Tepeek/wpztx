<?php
/**
 * EDD Reviews Reports.
 *
 * @package    EDD_Reviews
 * @subpackage Admin/Reports
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Reports' ) ) :

/**
 * EDD_Reviews_Reports Class
 *
 * @package EDD_Reviews
 * @since	2.1
 * @version	1.0
 * @author 	Sunny Ratilal
 */
class EDD_Reviews_Reports {
	/**
	 * Constructor.
	 *
	 * @access protected
	 * @since 2.1
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Adds all the hooks/filters
	 *
	 * The plugin relies heavily on the use of hooks and filters and modifies
	 * default WordPress behaviour by the use of actions and filters which are
	 * provided by WordPress.
	 *
	 * Actions are provided to hook on this function, before the hooks and filters
	 * are added and after they are added. The class object is passed via the action.
	 *
	 * @since 2.1
	 * @access private
	 * @return void
	 */
	private function hooks() {
		/** Actions */
		add_action( 'edd_reports_view_reviews_highest_average_rating', array( $this, 'render_highest_average_rating_view' ) );
		add_action( 'edd_reports_view_reviews_lowest_average_rating',  array( $this, 'render_lowest_average_rating_view'  ) );
		add_action( 'edd_reports_view_reviews_most_reviewed',          array( $this, 'render_most_reviewed_view'          ) );

		/** Filters */
		add_filter( 'edd_report_views',                                array( $this, 'add_views'                          ) );
	}

	/**
	 * Add reviews reports to report views.
	 *
	 * @since 2.1
	 * @access public
	 * @param array $views The existing report views.
	 * @return array $views The views updated with reviews reports.
	 */
	public function add_views( $views ) {
		$views['reviews_highest_average_rating'] = __( 'Highest Average Rating', 'edd-reviews' );
		$views['reviews_lowest_average_rating']  = __( 'Lowest Average Rating', 'edd-reviews' );
		$views['reviews_most_reviewed']          = __( 'Most Reviewed', 'edd-reviews' );

		return $views;
	}

	/**
	 * Show Highest Average Rating Report.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function render_highest_average_rating_view() {
		ob_start(); ?>
		<div class="tablenav top">
			<div class="alignleft actions"><?php edd_report_views(); ?></div>
		</div>

		<div class="metabox-holder" style="padding-top: 0;">
			<div class="postbox">
			<h3><span><?php _e( 'Highest Average Rating', 'edd' ); ?></span></h3>

			<div class="inside">
				<?php
				$list_table = new EDD_Reviews_Highest_Average_Rating_List_Table();
				$list_table->prepare_items();
				$list_table->display();
				?>
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Show Lowest Average Rating Report.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function render_lowest_average_rating_view() {
		ob_start(); ?>
		<div class="tablenav top">
			<div class="alignleft actions"><?php edd_report_views(); ?></div>
		</div>

		<div class="metabox-holder" style="padding-top: 0;">
			<div class="postbox">
			<h3><span><?php _e( 'Lowest Average Rating', 'edd' ); ?></span></h3>

			<div class="inside">
				<?php
				$list_table = new EDD_Reviews_Lowest_Average_Rating_List_Table();
				$list_table->prepare_items();
				$list_table->display();
				?>
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}

	/**
	 * Show Most Reviewed Report.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function render_most_reviewed_view() {
		ob_start(); ?>
		<div class="tablenav top">
			<div class="alignleft actions"><?php edd_report_views(); ?></div>
		</div>

		<div class="metabox-holder" style="padding-top: 0;">
			<div class="postbox">
			<h3><span><?php _e( 'Most Reviewed', 'edd' ); ?></span></h3>

			<div class="inside">
				<?php
				$list_table = new EDD_Reviews_Most_Reviewed_List_Table();
				$list_table->prepare_items();
				$list_table->display();
				?>
			</div>
		</div>
		<?php
		echo ob_get_clean();
	}
}

endif;