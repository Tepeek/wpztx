<?php
/**
 * EDD Reviews - Privacy Integrations
 *
 * @package EDD_Reviews
 * @subpackage Integrations
 * @copyright Copyright (c) 2018, Chris Klosowski
 * @since 2.1.9
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * EDD_Reviews_Privacy Class
 *
 * @package EDD_Reviews
 * @since 2.1.9
 * @author Chris Klosowski
 */
class EDD_Reviews_Privacy {
	/**
	 * Constructor.
	 *
	 * @since 2.1.9
	 * @access public
	 * @uses EDD_Reviews_FES_Integration::hooks() Setup hooks and actions
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Adds all the hooks/filters
	 *
	 * @since 2.1.9
	 * @access private
	 * @return void
	 */
	private function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporter' ), 10, 1 );

		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_eraser' ), 10, 2 );

		add_action( 'edd_reviews_form_before_submit', array( $this, 'display_comment_privacy_consent' ) );
		add_action( 'edd_reviews_reply_form_before_submit', array( $this, 'display_comment_privacy_consent' ) );
		add_action( 'edd_reviews_vendor_feedback_form_before_submit', array( $this, 'display_comment_privacy_consent' ) );
	}

	/**
	 * Register the data exporter for EDD Reviews
	 *
	 * @since 2.1.9
	 * @access public
	 * @return array  Registered data exporters
	 */
	public function register_privacy_exporter( $exporters ) {
		$exporters[] = array(
			'exporter_friendly_name' => __( 'Customer Reviews', 'edd-reviews' ),
			'callback'               => array( $this, 'reviews_privacy_exporter' ),
		);

		return $exporters;
	}

	/**
	 * Register the data eraser for EDD Reviews
	 *
	 * @since 2.1.9
	 * @access public
	 * @return array  Registered data exporters
	 */
	public function register_privacy_eraser( $erasers ) {
		$erasers[] = array(
			'eraser_friendly_name' => __( 'Customer Reviews', 'edd-reviews' ),
			'callback'             => array( $this, 'anonymize_reviews' ),
		);

		return $erasers;
	}

	/**
	 * Add the review data to the WP Core data exporter
	 *
	 * @since 2.1.9
	 * @access public
	 *
	 * @param string $email_address The email address to export data for.
	 * @param int    $page          What page of data to retrieve.
	 * @return array
	 */
	public function reviews_privacy_exporter( $email_address, $page = 1 ) {

		global $wpdb;

		$offset  = 25 * ( $page - 1 );
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_date, comment_author_email, comment_author, comment_content, comment_post_ID, comment_author_IP, comment_author_url, user_id, comment_agent
				 FROM $wpdb->comments
				 WHERE comment_author_email = '%s'
				 LIMIT 25 OFFSET %d",
				$email_address, $offset
			)
		);

		if ( empty( $reviews ) ) {
			return array( 'data' => array(), 'done' => true );
		}

		$export_items = array();

		foreach ( $reviews as $review ) {
			$product = new EDD_Download( $review->comment_post_ID );
			$data_points = array(
				array(
					'name'  => __( 'Review ID', 'edd-reviews' ),
					'value' => $review->comment_ID,
				),
				array(
					'name'  => __( 'Date', 'edd-reviews' ),
					'value' => $review->comment_date,
				),
				array(
					'name'  => __( 'Email', 'edd-reviews' ),
					'value' => $review->comment_author_email,
				),
				array(
					'name'  => __( 'Name', 'edd-reviews' ),
					'value' => $review->comment_author,
				),
				array(
					'name'  => __( 'Title', 'edd-reviews' ),
					'value' => get_comment_meta( $review->comment_ID, 'edd_review_title', true ),
				),
				array(
					'name'  => __( 'Review', 'edd-reviews' ),
					'value' => $review->comment_content,
				),
				array(
					'name'  => __( 'Rating', 'edd-reviews' ),
					'value' => get_comment_meta( $review->comment_ID, 'edd_rating', true ),
				),
				array(
					'name'  => __( 'Product', 'edd-reviews' ),
					'value' => ! empty( $product->ID ) ? $product->get_name() : $review->comment_post_ID,
				),
				array(
					'name'  => __( 'IP Address', 'edd-reviews' ),
					'value' => $review->comment_author_IP,
				),
				array(
					'name'  => __( 'Reviewer URL', 'edd-reviews' ),
					'value' => $review->comment_author_url,
				),
				array(
					'name'  => __( 'User ID', 'edd-reviews' ),
					'value' => $review->user_id,
				),
				array(
					'name'  => __( 'User Agent', 'edd-reviews' ),
					'value' => $review->comment_agent,
				),
			);

			$export_items[] = array(
				'group_id'    => 'edd-reviews',
				'group_label' => __( 'Customer Reviews', 'edd-reviews' ),
				'item_id'     => "edd-reviews-{$review->comment_ID}",
				'data'        => $data_points,
			);

		}


		// Add the data to the list, and tell the exporter to come back for the next page of payments.
		return array(
			'data' => $export_items,
			'done' => false,
		);

	}

	/**
	 * Anonymize reviews with the WordPress core personal data eraser.
	 *
	 * @since 2.9.1
	 *
	 * @param     $email_address
	 * @param int $page
	 *
	 * @return array
	 */
	public function anonymize_reviews( $email_address, $page = 1 ) {
		global $wpdb;

		if ( empty( $email_address ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;

		$offset  = 25 * ( $page - 1 );
		$reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_ID, comment_date, comment_author_email, comment_author, comment_content, comment_post_ID, comment_author_IP, comment_author_url, user_id, comment_agent
				 FROM $wpdb->comments
				 WHERE comment_author_email = '%s'
				 LIMIT 25 OFFSET %d",
				$email_address, $offset
			)
		);

		$anon_author = __( 'Anonymous Reviewer', 'edd-reviews' );
		$messages    = array();

		$items_retained = false;

		foreach ( (array) $reviews as $review ) {
			$anonymized_review                         = array();
			$anonymized_review['comment_agent']        = '';
			$anonymized_review['comment_author']       = $anon_author;
			$anonymized_review['comment_author_email'] = wp_privacy_anonymize_data( 'email', $review->comment_author_email );
			$anonymized_review['comment_author_IP']    = wp_privacy_anonymize_data( 'ip', $review->comment_author_IP );
			$anonymized_review['comment_author_url']   = wp_privacy_anonymize_data( 'url', $review->comment_author_url );
			$anonymized_review['user_id']              = 0;

			$review_id = (int) $review->comment_ID;

			/**
			 * Filters whether to anonymize the review.
			 *
			 * @since 4.9.6
			 *
			 * @param bool|string                    Whether to apply the review anonymization (bool).
			 *                                       Custom prevention message (string). Default true.
			 * @param WP_Comment $comment            WP_Comment object.
			 * @param array      $anonymized_comment Anonymized comment data.
			 */
			$anon_message = apply_filters( 'edd_anonymize_review', true, $review, $anonymized_review );

			if ( true !== $anon_message ) {
				if ( $anon_message && is_string( $anon_message ) ) {
					$messages[] = esc_html( $anon_message );
				} else {
					/* translators: %d: Comment ID */
					$messages[] = sprintf( __( 'Review %d contains personal data but could not be anonymized.' ), $review_id );
				}

				$items_retained = true;

				continue;
			}

			$args = array(
				'comment_ID' => $review_id,
			);

			$updated = $wpdb->update( $wpdb->comments, $anonymized_review, $args );

			if ( $updated ) {
				$items_removed = true;
				clean_comment_cache( $review_id );
			} else {
				$items_retained = true;
			}
		}

		$done = count( $reviews ) < 25;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	public function display_comment_privacy_consent() {
		$consent = is_user_logged_in() ? ' checked="checked"' : '';
		?>
		<p class="comment-form-cookies-consent">
			<input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"<?php echo $consent; ?> />
			<label for="wp-comment-cookies-consent">
				<?php _e( 'Save my name, email, and website in this browser for the next time I comment.', 'edd-reviews' ); ?>
			</label>
		</p>
		<?php
	}

}