<?php
/**
 * EDD Reviews Upgrades
 *
 * @package EDD_Reviews
 * @subpackage Admin
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Upgrades' ) ) :

/**
 * EDD_Reviews_Upgrades Class
 *
 * @package EDD_Reviews
 * @since 2.0
 * @version 1.0
 * @author Sunny Ratilal
 */
class EDD_Reviews_Upgrades {
	/**
	 * Constructor Function
	 *
	 * @since 1.0
	 * @access protected
	 * @see EDD_Reviews_Upgrades::hooks()
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Adds all the hooks/filters
	 *
	 * Actions are provided to hook on this function, before the hooks and filters
	 * are added and after they are added. The class object is passed via the action.
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'edd_reviews_upgrade_20_database', array( $this, 'v20_upgrades' ) );
		add_action( 'edd_reviews_upgrade_21_database', array( $this, 'v21_upgrades' ) );
	}

	/**
	 * Display Upgrade Notices
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function admin_notices() {
		global $wpdb;

		if ( isset( $_GET['page'] ) && $_GET['page'] == 'edd-upgrades' ) {
			return;
		}

		$edd_reviews_version = get_option( 'edd_reviews_version' );

		if ( version_compare( edd_reviews()->version, '2.0', '<' ) || ! edd_has_upgrade_completed( 'reviews_upgrade_20_database' ) ) {
			$has_reviews = $wpdb->get_var( "SELECT {$wpdb->comments}.comment_ID FROM {$wpdb->commentmeta} LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID WHERE {$wpdb->comments}.comment_type != 'edd_review' AND {$wpdb->commentmeta}.meta_key = 'edd_review_title' AND 1=1 LIMIT 1" );
			$needs_upgrade = ! empty( $has_reviews );

			printf(
				'<div class="updated"><p>' . __( 'Easy Digital Downloads needs to upgrade the reviews database, click <a href="%s">here</a> to start the upgrade.', 'edd-reviews' ) . '</p></div>',
				esc_url_raw( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=reviews_upgrade_20_database' ) )
			);
		}

		if ( version_compare( edd_reviews()->version, '2.1', '<' ) || ! edd_has_upgrade_completed( 'reviews_upgrade_21_database' ) ) {
			$has_downloads = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} AS posts INNER JOIN {$wpdb->postmeta} ON {$wpdb->postmeta}.post_id = posts.ID WHERE post_type = 'download' AND NOT EXISTS(SELECT * FROM {$wpdb->postmeta} WHERE {$wpdb->postmeta}.meta_key = 'edd_reviews_average_rating' AND {$wpdb->postmeta}.post_id = posts.ID) LIMIT 1" );
			$needs_upgrade = ! empty( $has_downloads );

			if ( ! $needs_upgrade ) {
				edd_set_upgrade_complete( 'reviews_upgrade_21_database' );
				update_option( 'edd_reviews_version', edd_reviews()->version );
			}

			if ( $needs_upgrade ) {
				printf(
					'<div class="updated"><p>' . __( 'Easy Digital Downloads needs to upgrade the reviews database, click <a href="%s">here</a> to start the upgrade.', 'edd-reviews' ) . '</p></div>',
					esc_url_raw( admin_url( 'index.php?page=edd-upgrades&edd-upgrade=reviews_upgrade_21_database' ) )
				);
			}
		}
	}

	/**
	 * Upgrade routine for v2.0
	 *
	 * @since 2.0
	 * @access public
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return void
	 */
	public function v20_upgrades() {
		global $wpdb, $edd_options;

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( __( 'You do not have permission to do shop upgrades', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit(0);
		}

		// Create the new Vendor Feedback page if it doesn't exist
		$options = get_option( 'edd_settings' );

		if ( ! isset( $options['edd_reviews_vendor_feedback_page'] ) && class_exists( 'EDD_Front_End_Submissions' ) ) {
			$feedback = wp_insert_post(
				array(
					'post_title'     => __( 'Vendor Feedback', 'edd-reviews' ),
					'post_content'   => '[edd_reviews_vendor_feedback]',
					'post_status'    => 'publish',
					'post_author'    => 1,
					'post_type'      => 'page',
					'comment_status' => 'closed'
				)
			);

			$options['edd_reviews_vendor_feedback_page'] = $feedback;
			update_option( 'edd_settings', $options );
		}

		$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		$number = isset( $_GET['number'] ) ? absint( $number ) : 10;
		$offset = $step == 1 ? 0 : ( $step - 1 ) * $number;

		$total = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;

		if ( $step < 2 ) {
			// Check we have reviews before progressing
			$sql = "
				SELECT {$wpdb->comments}.comment_ID
				FROM {$wpdb->commentmeta}
				LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
				WHERE {$wpdb->comments}.comment_type != 'edd_review'
				AND {$wpdb->commentmeta}.meta_key = 'edd_review_title'
				LIMIT 1
			";
			$has_reviews = $wpdb->get_col( $sql );

			if ( empty( $has_reviews ) ) {
				update_option( 'edd_reviews_version', preg_replace( '/[^0-9.].*/', '', edd_reviews()->version ) );
				edd_set_upgrade_complete( 'reviews_upgrade_20_database' );
				delete_option( 'edd_doing_upgrade' );
				wp_redirect( admin_url() );
				exit;
			}
		}

		if ( empty( $total ) || $total <= 1 ) {
			$results = $wpdb->get_row(
				"
				SELECT count({$wpdb->comments}.comment_ID) AS total_reviews
				FROM {$wpdb->commentmeta}
				LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
				WHERE {$wpdb->comments}.comment_type != 'edd_review'
				AND {$wpdb->commentmeta}.meta_key = 'edd_review_title'
				"
			);
			$total = $results->total_reviews;
		}

		$review_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT {$wpdb->comments}.comment_ID
				FROM {$wpdb->commentmeta}
				LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
				WHERE {$wpdb->comments}.comment_type != 'edd_review'
				AND {$wpdb->commentmeta}.meta_key = 'edd_review_title'
				LIMIT %d,%d;
			", $offset, $number)
		);

		if ( ! empty( $review_ids ) ) {
			$ids_to_query = implode( ',', $review_ids );

			$reply_ids = $wpdb->get_results(
				"
					SELECT {$wpdb->comments}.comment_ID, {$wpdb->comments}.comment_approved
					FROM {$wpdb->comments}
					WHERE {$wpdb->comments}.comment_parent IN( $ids_to_query );
				"
			);

			foreach ( $reply_ids as $reply ) {
				add_comment_meta( $reply->comment_ID, 'edd_review_approved', $reply->comment_approved );
				add_comment_meta( $reply->comment_ID, 'edd_review_reply', '1' );

				$update = array();
				$update['comment_ID'] = $reply->comment_ID;
				$update['comment_approved'] = 1;
				$update['comment_type'] = 'edd_review';
				wp_update_comment( $update );
			}

			foreach ( $review_ids as $review ) {
				$review_object = get_comment( $review );
				add_comment_meta( $review_object->comment_ID, 'edd_review_approved', $review_object->comment_approved );

				$update = array();
				$update['comment_ID'] = $review_object->comment_ID;
				$update['comment_approved'] = 1;
				$update['comment_type'] = 'edd_review';
				wp_update_comment( $update );
			}

			$step++;
			$redirect = esc_url_raw( add_query_arg( array(
				'page'        => 'edd-upgrades',
				'edd-upgrade' => 'reviews_upgrade_20_database',
				'step'        => $step,
				'number'      => $number,
				'total'       => $total
			), admin_url( 'index.php' ) ) );
			wp_redirect( $redirect );
		} else {
			update_option( 'edd_reviews_version', preg_replace( '/[^0-9.].*/', '', edd_reviews()->version ) );
			edd_set_upgrade_complete( 'reviews_upgrade_20_database' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Upgrade routine for v2.1
	 *
	 * @since  2.1
	 * @access public
	 */
	public function v21_upgrades() {
		global $wpdb;

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_die( __( 'You do not have permission to do shop upgrades', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		ignore_user_abort( true );

		if ( ! edd_is_func_disabled( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit(0);
		}

		$step   = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1;
		$number = isset( $_GET['number'] ) ? absint( $number ) : 10;
		$offset = $step == 1 ? 0 : ( $step - 1 ) * $number;

		$total = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : false;

		if ( $step < 2 ) {
			// Check we have reviews before progressing
			$sql = "
				SELECT ID
				FROM {$wpdb->posts}
				WHERE post_type = 'download'
				LIMIT 1
			";
			$has_downloads = $wpdb->get_col( $sql );

			if ( empty( $has_downloads ) ) {
				update_option( 'edd_reviews_version', preg_replace( '/[^0-9.].*/', '', edd_reviews()->version ) );
				edd_set_upgrade_complete( 'reviews_upgrade_21_database' );
				delete_option( 'edd_doing_upgrade' );
				wp_redirect( admin_url() );
				exit;
			}
		}

		if ( empty( $total ) || $total <= 1 ) {
			$results = $wpdb->get_row(
				"
				SELECT COUNT(ID) AS total_posts
				FROM {$wpdb->posts}
				WHERE post_type = 'download'
				"
			);
			$total = $results->total_posts;
		}

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT DISTINCT(ID)
				FROM {$wpdb->posts}
				WHERE post_type = 'download'
				LIMIT %d,%d;
			", $offset, $number)
		);

		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				$average_rating = edd_reviews()->average_rating( false, $post_id );

				add_post_meta( $post_id, 'edd_reviews_average_rating', $average_rating );
			}

			$step++;
			$redirect = esc_url_raw( add_query_arg( array(
				'page'        => 'edd-upgrades',
				'edd-upgrade' => 'reviews_upgrade_21_database',
				'step'        => $step,
				'number'      => $number,
				'total'       => $total
			), admin_url( 'index.php' ) ) );
			wp_redirect( $redirect );
		} else {
			update_option( 'edd_reviews_version', preg_replace( '/[^0-9.].*/', '', edd_reviews()->version ) );
			edd_set_upgrade_complete( 'reviews_upgrade_21_database' );
			delete_option( 'edd_doing_upgrade' );
			wp_redirect( admin_url() );
			exit;
		}
	}
}

endif;