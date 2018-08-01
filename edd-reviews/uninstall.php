<?php
/**
 * Uninstall Easy Digital Downloads Reviews
 *
 * Deletes all the plugin data i.e.
 * 		1. Reviews.
 * 		2. Replies.
 * 		3. Vendor feedback.
 * 		4. Plugin options.
 *
 * @package EDD_Reviews
 * @subpackage Core
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Load Reviews
include_once( 'edd-reviews.php' );

global $wpdb;

$options = get_option( 'edd_settings' );

if ( $options['edd_reviews_uninstall_on_delete'] ) {
	// Delete all the reviews & replies
	remove_action( 'pre_get_comments', array( edd_reviews(), 'hide_reviews' ) );
	remove_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

	$reviews = get_comments( array(
		'fields' => 'ids',
		'type'   => 'edd_review',
	) );

	if ( $reviews ) {
		foreach ( $reviews as $review ) {
			wp_delete_comment( $review, true );
		}
	}

	// Delete Vendor Feedback.
	$vendor_feedback = get_comments(  array(
		'fields' => 'ids',
		'type'   => 'edd_vendor_feedback',
	) );

	if ( $vendor_feedback ) {
		foreach ( $vendor_feedback as $feedback ) {
			wp_delete_comment( $feedback, true );
		}
	}

	// Delete Vendor Feedback page.
	$page = isset( $options['edd_reviews_vendor_feedback_page'] ) ? $options['edd_reviews_vendor_feedback_page'] : false;
	if ( $page ) {
		wp_delete_post( $page, true );
	}

	// Delete plugin options
	foreach ( $options as $key => $value ) {
		if ( 0 === strpos( $key, 'edd_reviews_' ) ) {
			unset( $options[ $key ] );
		}
	}
	update_option( 'edd_settings', $options );
}