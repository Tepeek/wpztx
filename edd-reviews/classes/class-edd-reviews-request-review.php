<?php
/**
 * Request a Review.
 *
 * @package EDD_Reviews
 * @copyright Copyright (c) 2017, Sunny Ratilal
 * @since 2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Request_Review' ) ) :

/**
 * EDD_Reviews_Request_Review Class.
 *
 * @package EDD_Reviews
 * @since 2.1
 * @version 1.0
 * @author Sunny Ratilal
 */
class EDD_Reviews_Request_Review {
	/**
	 * Constructor Function
	 *
	 * @since 2.1
	 * @access protected
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
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function hooks() {
		do_action_ref_array( 'edd_reviews_request_review_before_setup_actions', array( &$this ) );

		/** Actions */
		add_action( 'edd_add_email_tags',                          array( $this, 'add_email_tag'   ) );
		add_action( 'edd_complete_purchase',                       array( $this, 'trigger_email'   ), 999, 1 );
		add_action( 'wp',                                          array( $this, 'schedule_events' ) );
		add_action( 'edd_reviews_request_review_scheduled_events', array( $this, 'trigger_events'  ) );

		/** Filters */
		add_filter( 'edd_reviews_email_settings',                  array( $this, 'settings'        ) );
		add_filter( 'cron_schedules',                              array( $this, 'add_schedules'   ) );

		do_action_ref_array( 'edd_reviews_request_review_after_setup_actions', array( &$this ) );
	}

	/**
	 * Register Reviews settings.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $settings Registered email settings.
	 * @return array $settings New settings.
	 */
	public function settings( $settings ) {
		$new = array(
			array(
				'id'   => 'edd_reviews_request_review_settings',
				'name' => '<h3>' . __( 'Request a Review', 'edd-reviews' ) . '</h3>',
				'type' => 'header'
			),
			array(
				'id'   => 'edd_reviews_request_review_toggle',
				'name' => __( 'Enable Request a Review', 'edd-reviews' ),
				'desc' => __( 'Enable the request a review feature to send an email to customers to leave a review.', 'edd-reviews' ),
				'type' => 'checkbox'
			),
			array(
				'id'      => 'edd_reviews_request_review_time_period',
				'name'    => __( 'When to send?', 'edd-reviews' ),
				'desc'    => __( 'How long after the purchase should the email be sent', 'edd-reviews' ),
				'type'    => 'select',
				'options' => array(
					'now'   => __( 'Immediately', 'edd-reviews' ),
					'6hrs'  => __( '6 Hours Later', 'edd-reviews' ),
					'1day'  => __( 'Next Day', 'edd-reviews' ),
					'3days' => __( '3 Days Later', 'edd-reviews' ),
					'1week' => __( '1 Week Later', 'edd-reviews' )
				)
			),
			array(
				'id'          => 'edd_reviews_request_back_date',
				'name'        => __( 'Start Date', 'edd-reviews' ),
				'desc'        => __( 'When enabled, how old should payments be to trigger the review request. If this field is left blank, the request reviews function will not run.', 'edd-reviews' ),
				'type'        => 'text',
				'field_class' => 'edd_datepicker'
			),
			array(
				'id' => 'edd_reviews_request_review_email',
				'name' => __( 'Email Content', 'edd-reviews' ),
				'desc' => __( 'Enter the text that is sent to the customer requesting a review. HTML is accepted.', 'edd-reviews' ) . '<br />' . edd_get_emails_tags_list(),
				'type' => 'rich_editor',
				'std'  => $this->get_email_body_contents( 0, null ),
			)
		);

		$settings = array_merge( $settings, $new );

		return $settings;
	}

	/**
	 * Add {review_request_email_tag} Email Tag.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function add_email_tag() {
		edd_add_email_tag( 'review_request', sprintf( __( 'Adds links to request a review for all %s', 'edd-reviews' ), strtolower( edd_get_label_plural() ) ), array( $this, 'review_request_email_tag' ) );
	}

	/**
	 * Parse {review_request_email_tag} Email Tag.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function review_request_email_tag( $payment_id ) {
		$payment = new EDD_Payment( $payment_id );

		$cart_items = $payment->cart_details;

		if ( $cart_items ) {
			$list = '<ul>';

			foreach ( $cart_items as $item ) {
				if ( edd_reviews()->is_review_status( 'closed', $item['id'] ) || edd_reviews()->is_review_status( 'disabled', $item['id'] ) ) {
					continue;
				}

				/**
				 * Filter the link displayed to leave a review.
				 *
				 * @since 2.1
				 */
				$title = apply_filters( 'edd_reviews_request_review_email_title', '<strong><a href="' . edd_reviews()->get_form_link( $item['id'] ) . '">' . get_the_title( $item['id'] ) . '</a></strong>' );

				$list .= '<li>' . $title . '</li>';
			}

			$list .= '</ul>';
		}

		return isset( $list ) ? $list : '';
	}

	/**
	 * Default email body.
	 *
	 * @access public
	 * @since  2.1
	 * @return string Email body.
	 */
	public function get_email_body_contents() {
		$default_body = __( 'Dear', 'edd-reviews' ) . ' {name},' . "\n\n";
		$default_body .= sprintf( __( 'Thank you for your recent purchase. We would appreciate a review to let other customers know about your experience with our %s. Please click on the link(s) below to leave a review.', 'edd-reviews' ), strtolower( edd_get_label_plural() ) ) . "\n\n";
		$default_body .= '{review_request}' . "\n\n";
		$default_body .= '{sitename}';

		$email = edd_get_option( 'edd_reviews_request_review_email', false );
		$email = $email ? stripslashes( $email ) : $default_body;

		$email_body = apply_filters( 'edd_email_template_wpautop', true ) ? wpautop( $email ) : $email;

		/**
		 * Filter the email contents.
		 *
		 * @since 2.1
		 *
		 * @param string $email_body Email body.
		 */
		return apply_filters( 'edd_reviews_request_review_email_contents', $email_body );
	}

	/**
	 * Trigger the email to be sent.
	 *
	 * @since 2.1
	 * @access public
	 *
	 * @param  int  $payment_id Payment ID.
	 * @param  bool $force      Force the email to be sent (true via cron).
	 * @return void
	 */
	public function trigger_email( $payment_id, $force = false ) {
		$request_back_date = edd_get_option( 'edd_reviews_request_back_date', false );

		if ( ! edd_get_option( 'edd_reviews_request_review_toggle', false ) || empty( $request_back_date ) ) {
			return;
		}

		if ( isset( $_POST['edd-action'] ) && 'edit_payment' == $_POST['edd-action'] ) {
			return;
		}

		$payment = edd_get_payment( $payment_id );
		$cart_items = $payment->cart_details;

		$should_send = false;

		foreach ( $cart_items as $item ) {
			if ( ! edd_reviews()->is_review_status( 'closed', $item['id'] ) && ! edd_reviews()->is_review_status( 'disabled', $item['id'] ) ) {
				$should_send = true;
				break;
			}
		}

		if ( $should_send ) {
			$time_period  = edd_get_option( 'edd_reviews_request_review_time_period', false );
			$payment_date = $payment->completed_date;

			switch ( $time_period ) {
				case '6hrs':
					$comparison_time = date( 'Y-m-d H:i:s', strtotime( '-6 hours' ) );

					if ( $payment_date <= $comparison_time ) {
						$this->send_email( $payment_id );
						edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 1 );
					}

					break;
				case '1day':
					$comparison_time = date( 'Y-m-d H:i:s', strtotime( '-1 day' ) );

					if ( $payment_date <= $comparison_time ) {
						$this->send_email( $payment_id );
						edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 1 );
					}

					break;
				case '3days':
					$comparison_time = date( 'Y-m-d H:i:s', strtotime( '-3 days' ) );

					if ( $payment_date <= $comparison_time ) {
						$this->send_email( $payment_id );
						edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 1 );
					}

					break;
				case '1week':
					$comparison_time = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

					if ( $payment_date <= $comparison_time ) {
						$this->send_email( $payment_id );
						edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 1 );
					}

					break;
				case 'now':
					// $this->send_email( $payment_id );
					edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 1 );

					break;
				default:
					edd_update_payment_meta( $payment_id, 'edd_reviews_request_review', 0 );

					break;
			}
		}
	}

	/**
	 * Setup and send the email.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param int $payment_id Payment ID.
	 * @return void|bool False if payment not found, void otherwise.
	 */
	public function send_email( $payment_id = 0 ) {
		$payment_id = absint( $payment_id );

		$payment = edd_get_payment( $payment_id );

		if ( ! $payment ) {
			return false;
		}

		$subject = edd_get_option( 'edd_reviews_request_review_subject', __( 'Leave a review for your recent purchase', 'edd-reviews' ) );
		$subject = apply_filters( 'edd_reviews_request_review_subject', wp_strip_all_tags( $subject ), $payment_id );
		$subject = edd_do_email_tags( $subject, $payment_id );

		$message = $this->get_email_body_contents();
		$message = edd_do_email_tags( $message, $payment_id );

		$emails = EDD()->emails;
		$emails->__set( 'heading', __( 'Leave a Review', 'edd-reviews' ) );

		$emails->send( $payment->email, $subject, $message );

		do_action( 'edd_reviews_request_review_send_email', $payment_id, $payment );
	}

	/**
	 * Add cron schedules.
	 *
	 * @since 2.1
	 * @access public
	 * @return array $schedules CRON schedules.
	 */
	public function add_schedules( $schedules = array() ) {
		$schedules['6hrs'] = array(
			'interval' => 21600,
			'display' => __( '6 Hours Later', 'edd-reviews' )
		);

		$schedules['1day'] = array(
			'interval' => 86400,
			'display' => __( 'Next Day', 'edd-reviews' )
		);

		$schedules['3days'] = array(
			'interval' => 259200,
			'display' => __( 'Three Days Later', 'edd-reviews' )
		);

		$schedules['1week'] = array(
			'interval' => 604800,
			'display'  => __( 'Next Week', 'edd-reviews' )
		);

		return $schedules;
	}

	/**
	 * Schedule cron events.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( 'edd_reviews_request_review_scheduled_events' ) ) {
			wp_schedule_event( current_time( 'timestamp', true ), 'daily', 'edd_reviews_request_review_scheduled_events' );
		}
	}

	/**
	 * Trigger the scheduled events.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function trigger_events() {
		$request_back_date = edd_get_option( 'edd_reviews_request_back_date', false );

		if ( ! edd_get_option( 'edd_reviews_request_review_toggle', false ) || empty( $request_back_date ) ) {
			return;
		}

		$query = new EDD_Payments_Query( array(
			'number' => -1,
			'start_date'=> edd_get_option( 'edd_reviews_request_back_date' ),
			'end_date' => current_time( 'timestamp' ),
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'     => 'edd_reviews_request_review',
					'value'   => '0'
				),
				array(
					'key'     => 'edd_reviews_request_review',
					'compare' => 'NOT EXISTS'
				)
			)
		) );

		$payments = $query->get_payments();

		foreach ( $payments as $payment ) {
			$this->trigger_email( $payment->ID, true );
		}
	}
}

endif;
