<?php
/**
 * Plugin Name: Easy Digital Downloads - Reviews
 * Plugin URI: http://www.floryu.com/downloads/reviews/
 * Description: 简易数字下载的多功能评分系统.
 * Author: floryu
 * Author URI: http://www.floryu.com/
 * Version: 2.1.9
 * Requires at least: 4.0
 * Tested up to: 4.9
 *
 * Text Domain: edd-reviews
 * Domain Path: languages
 *
 * Copyright (c) 2018 Sunny Ratilal.
 *
 * @package  EDD_Reviews
 * @category Core
 * @author   Sunny Ratilal
 * @version  2.1.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews' ) ) :

/**
 * EDD_Reviews Class
 *
 * @package	EDD_Reviews
 * @since	1.0
 * @version	2.1.8
 * @author 	Sunny Ratilal
 */
final class EDD_Reviews {
	/**
	 * EDD Reviews uses many variables, several of which can be filtered to
	 * customize the way it operates. Most of these variables are stored in a
	 * private array that gets updated with the help of PHP magic methods.
	 *
	 * @var array
	 * @see EDD_Reviews::setup_globals()
	 * @since 1.0
	 */
	private $data;

	/**
	 * Holds the instance
	 *
	 * Ensures that only one instance of EDD Reviews exists in memory at any one
	 * time and it also prevents needing to define globals all over the place.
	 *
	 * TL;DR This is a static property property that holds the singleton instance.
	 *
	 * @var object
	 * @static
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * Boolean whether or not to use the singleton, comes in handy
	 * when doing testing
	 *
	 * @var bool
	 * @static
	 * @since 1.0
	 */
	public static $testing = false;

	/**
	 * Holds the version number
	 *
	 * @var string
	 * @since 1.0
	 */
	public $version = '2.1.9';

	/**
	 * FES Integration class instance
	 *
	 * @var object
	 * @since 2.0
	 */
	public $fes = null;

	/**
	 * Request Review class instance
	 *
	 * @var object
	 * @since 2.1
	 */
	public $request_review = null;

	/**
	 * Email tags class instance.
	 *
	 * @var object
	 * @since 2.1
	 */
	public $email_tags = null;

	/**
	 * JSON-LD class instance.
	 *
	 * @var object
	 * @since 2.1
	 */
	public $json_ld = null;

	/**
	 * Reports class instance.
	 *
	 * @var object
	 * @since 2.1
	 */
	public $reports = null;

	/**
	 * Privacy class instance.
	 * @var object
	 */
	public $privacy = null;

	/**
	 * Get the instance and store the class inside it. This plugin utilises
	 * the PHP singleton design pattern.
	 *
	 * @since 1.0
	 * @access public
	 * @return object Instance of EDD_Reviews
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Reviews ) || self::$testing ) {
			self::$instance = new EDD_Reviews;
			self::$instance->setup_globals();
			self::$instance->load_classes();
			self::$instance->hooks();
			self::$instance->updater();
			self::$instance->request_review = new EDD_Reviews_Request_Review;
			self::$instance->reports = new EDD_Reviews_Reports;

			if ( class_exists( 'Easy_Digital_Downloads', false ) ) {
				self::$instance->upgrade();
			}
		}

		return self::$instance;
	}

	/**
	 * Constructor Function
	 *
	 * @since 1.0
	 * @access protected
	 * @see EDD_Reviews::init()
	 * @see EDD_Reviews::activation()
	 */
	public function __construct() {
		if ( ! class_exists( 'Easy_Digital_Downloads', false ) ) {
			return;
		}

		self::$instance = $this;

		add_action( 'init', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
	}

	/**
	 * Sets up the constants/globals used
	 *
	 * @since 1.0
	 * @access public
	 */
	private function setup_globals() {
		// File Path and URL Information
		$this->file        = __FILE__;
		$this->basename    = apply_filters( 'edd_reviews_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_url  = plugin_dir_url( __FILE__ );
		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->lang_dir    = apply_filters( 'edd_reviews_lang_dir',         trailingslashit( $this->plugin_path . 'languages' ) );

		// Assets
		$this->assets_dir  = apply_filters( 'edd_reviews_assets_dir',       trailingslashit( $this->plugin_path . 'assets'    ) );
		$this->assets_url  = apply_filters( 'edd_reviews_assets_url',       trailingslashit( $this->plugin_url  . 'assets'    ) );

		// Classes
		$this->classes_dir = apply_filters( 'edd_reviews_classes_dir',      trailingslashit( $this->plugin_path . 'classes'   ) );
		$this->classes_url = apply_filters( 'edd_reviews_classes_url',      trailingslashit( $this->plugin_url  . 'classes'   ) );
	}

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'edd-reviews' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'edd-reviews' ), '1.0' );
	}

	/**
	 * Magic method for checking if custom variables have been set
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __isset( $key ) {
		return isset( $this->data[ $key ] );
	}

	/**
	 * Magic method for getting define_syslog_variables(oid)
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __get( $key ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null;
	}

	/**
	 * Magic method for setting variables
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->data[ $key ] = $value;
	}

	/**
	 * Magic method for unsetting variables
	 *
	 * @since 1.0
	 * @access protected
	 * @return void
	 */
	public function __unset( $key ) {
		if ( isset( $this->data[ $key ] ) ) {
			unset( $this->data[ $key ] );
		}
	}

	/**
	 * Magic method to prevent notices and errors from invalid method calls
	 *
	 * @since 1.0
	 * @access public
	 * @param string $name
	 * @param array  $args
	 * @return void
	 */
	public function __call( $name = '', $args = array() ) {
		unset( $name, $args );
		return null;
	}

	/**
	 * Reset the instance of the class
	 *
	 * @since 1.0
	 * @access public
	 * @static
	 */
	public static function reset() {
		self::$instance = null;
	}

	/**
	 * Function fired on init
	 *
	 * This function is called on WordPress 'init'. It's triggered from the
	 * constructor function.
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function init() {
		do_action( 'edd_reviews_before_init' );

		$this->load_plugin_textdomain();

		$this->add_shortcodes();

		do_action( 'edd_reviews_after_init' );
	}

	/**
	 * Loads Classes
	 *
	 * @since 1.0
	 * @access private
	 * @return void
	 */
	private function load_classes() {
		require $this->classes_dir . 'shortcodes/class-edd-reviews-shortcode-average-rating.php';
		require $this->classes_dir . 'shortcodes/class-edd-reviews-shortcode-review.php';
		if ( $this->is_fes_installed() ) {
			require $this->classes_dir . 'shortcodes/class-edd-reviews-vendor-feedback.php';
			require $this->classes_dir . 'class-edd-reviews-vendor-feedback-list-table.php';
			require $this->classes_dir . 'class-edd-reviews-fes-integration.php';
			$this->fes = new EDD_Reviews_FES_Integration;
		}
		require $this->classes_dir . 'reports/class-edd-reviews-reports.php';
		require $this->classes_dir . 'reports/class-edd-reviews-reports-list-table.php';
		require $this->classes_dir . 'reports/class-edd-reviews-highest-average-rating-list-table.php';
		require $this->classes_dir . 'reports/class-edd-reviews-lowest-average-rating-list-table.php';
		require $this->classes_dir . 'reports/class-edd-reviews-most-reviewed-list-table.php';
		require $this->classes_dir . 'widgets/class-reviews-widget.php';
		require $this->classes_dir . 'widgets/class-featured-review-widget.php';
		require $this->classes_dir . 'widgets/class-per-product-reviews-widget.php';
		require $this->classes_dir . 'class-edd-reviews-json-ld.php';
		require $this->classes_dir . 'class-edd-reviews-list-table.php';
		require $this->classes_dir . 'class-edd-reviews-request-review.php';
		require $this->classes_dir . 'class-edd-reviews-download-list-table.php';
		require $this->classes_dir . 'class-edd-reviews-upgrades.php';
		require $this->classes_dir . 'class-walker-edd-review.php';
		require $this->classes_dir . 'class-edd-reviews-email-template-tags.php';
		require $this->classes_dir . 'class-edd-reviews-privacy.php';

		$this->email_tags = new EDD_Reviews_Email_Template_Tags;
		$this->json_ld = new EDD_Reviews_JSON_LD;
		$this->privacy = new EDD_Reviews_Privacy;
	}

	/**
	 * Load Plugin Textdomain
	 *
	 * Looks for the plugin translation files in certain directories and loads
	 * them to allow the plugin to be localised
	 *
	 * @since 1.0
	 * @access public
	 * @return bool True on success, false on failure
	 */
	public function load_plugin_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale',  get_locale(), 'edd-reviews' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'edd-reviews', $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;

		if ( file_exists( $mofile_local ) ) {
			// Look in the /wp-content/plugins/edd-reviews/languages/ folder
			load_textdomain( 'edd-reviews', $mofile_local );
		} else {
			// Load the default language files
			load_plugin_textdomain( 'edd-reviews', false, $this->lang_dir );
		}

		return false;
	}

	/**
	 * Activation function fires when the plugin is activated.
	 *
	 * This function is fired when the activation hook is called by WordPress,
	 * it flushes the rewrite rules and disables the plugin if EDD isn't active
	 * and throws an error.
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function activation() {
		global $wpdb;

		edd_reviews();

		flush_rewrite_rules();

		if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
			if ( is_plugin_active( $this->basename ) ) {
				deactivate_plugins( $this->basename );
				unset( $_GET[ 'activate' ] );
				add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}
		}

		// Create the Vendor Feedback page if it doesn't exist
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

		// Initialise upgrade routine
		$version = get_option( 'edd_reviews_version' );

		if ( empty( $version ) ) {
			$has_reviews = $wpdb->get_var( "SELECT {$wpdb->comments}.comment_ID FROM {$wpdb->commentmeta} LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID WHERE {$wpdb->comments}.comment_type != 'edd_review' AND {$wpdb->commentmeta}.meta_key = 'edd_review_title' AND 1=1 LIMIT 1" );
			$needs_upgrade = ! empty( $has_reviews );
			if ( ! $needs_upgrade ) {
				edd_set_ugrade_complete( 'reviews_upgrade_20_database' );
				update_option( 'edd_reviews_version', preg_replace( '/[^0-9.].*/', '', $this->version ) );
				delete_option( 'edd_doing_upgrade' );
			}
		}

		if ( is_admin() ) {
			require_once EDD_PLUGIN_DIR . 'includes/admin/upgrades/upgrade-functions.php';
		}
	}

	/**
	 * Adds all the shortcodes
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function add_shortcodes() {
		add_shortcode( 'review',         array( 'EDD_Reviews_Shortcode_Review',         'render' ) );
		add_shortcode( 'average_rating', array( 'EDD_Reviews_Shortcode_Average_Rating', 'render' ) );
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
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function hooks() {
		do_action_ref_array( 'edd_reviews_before_setup_actions', array( &$this ) );

		/** Actions */
		add_action( 'comment_post',                            array( $this, 'save_review_meta'                       ) );
		add_action( 'wp_enqueue_scripts',                      array( $this, 'load_styles'                            ) );
		add_action( 'wp_enqueue_scripts',                      array( $this, 'load_scripts'                           ) );
		add_action( 'admin_enqueue_scripts',                   array( $this, 'admin_scripts'                          ) );
		add_action( 'the_content',                             array( $this, 'microdata'                              ) );
		add_action( 'wp_before_admin_bar_render',              array( $this, 'admin_bar_menu'                         ) );
		add_action( 'edd_reviews_review_display',              array( $this, 'render_review'                          ), 10, 3 );
		add_action( 'widgets_init',                            array( $this, 'register_widgets'                       ) );
		add_action( 'init',                                    array( $this, 'process_vote'                           ) );
		add_action( 'wp_ajax_edd_reviews_process_vote',        array( $this, 'process_ajax_vote'                      ) );
		add_action( 'wp_ajax_nopriv_edd_reviews_process_vote', array( $this, 'process_ajax_vote'                      ) );
		add_action( 'wp_dashboard_setup',                      array( $this, 'dashboard_widgets'                      ) );
		add_action( 'pre_get_comments',                        array( $this, 'hide_reviews'                           ) );
		add_action( 'edd_reviews_process_review',              array( $this, 'process_review'                         ) );
		add_action( 'edd_reviews_process_reply',               array( $this, 'process_reply'                          ) );
		add_action( 'admin_menu',                              array( $this, 'admin_menu'                             ) );
		add_action( 'init',                                    array( $this, 'insert_review'                          ) );
		add_action( 'edd_spam_review',                         array( $this, 'set_review_status'                      ) );
		add_action( 'edd_unspam_review',                       array( $this, 'set_review_status'                      ) );
		add_action( 'edd_unapprove_review',                    array( $this, 'set_review_status'                      ) );
		add_action( 'edd_approve_review',                      array( $this, 'set_review_status'                      ) );
		add_action( 'edd_trash_review',                        array( $this, 'set_review_status'                      ) );
		add_action( 'edd_restore_review',                      array( $this, 'set_review_status'                      ) );
		add_action( 'edd_delete_review',                       array( $this, 'set_review_status'                      ) );
		add_action( 'edd_update_review',                       array( $this, 'update_review'                          ) );
		add_action( 'edd_reviews_form_after',                  array( $this, 'review_form_after'                      ) );
		add_action( 'edd_reviews_reply_form_after',            array( $this, 'reply_form_after'                       ) );
		add_action( 'edd_download_after_title',                array( $this, 'display_average_rating'                 ) );
		add_action( 'media_buttons',                           array( $this, 'media_buttons'                          ), 11 );
		add_action( 'admin_footer',                            array( $this, 'admin_footer_for_thickbox'              ) );
		add_action( 'add_meta_boxes',                          array( $this, 'add_meta_boxes'                         ), 100 );
		add_action( 'edd_disable_reviews',                     array( $this, 'update_reviews_status'                  ) );
		add_action( 'edd_close_reviews',                       array( $this, 'update_reviews_status'                  ) );
		add_action( 'edd_open_reviews',                        array( $this, 'update_reviews_status'                  ) );
		add_action( 'edd_enable_reviews',                      array( $this, 'update_reviews_status'                  ) );
		add_action( 'wp_enqueue_scripts',                      array( $this, 'reviews_reply_script'                   ) );
		add_action( 'wp_ajax_edd_reviews_change_status',       array( $this, 'ajax_change_status'                     ) );
		add_action( 'wp_ajax_edd_reviews_reply_to_review',     array( $this, 'ajax_reply_to_review'                   ) );
		add_action( 'edd_reviews_title_after',                 array( $this, 'moderation_message'                     ) );
		add_action( 'edd_save_download',                       array( $this, 'add_postmeta'                           ) );

		/** Filters */
		add_filter( 'preprocess_comment',                      array( $this, 'check_author'                           ) );
		add_filter( 'edd_settings_sections_extensions',        array( $this, 'register_reviews_section'               ) );
		add_filter( 'edd_settings_sections_emails',            array( $this, 'register_emails_section'                ) );
		add_filter( 'edd_settings_extensions',                 array( $this, 'misc_settings'                          ) );
		add_filter( 'edd_settings_emails',                     array( $this, 'settings_emails'                        ) );
		add_filter( 'edd_api_valid_query_modes',               array( $this, 'register_api_mode'                      ) );
		add_filter( 'edd_api_output_data',                     array( $this, 'api_output'                             ), 10, 3 );
		add_filter( 'query_vars',                              array( $this, 'query_vars'                             ) );
		add_filter( 'comment_feed_where',                      array( $this, 'hide_reviews_from_comment_feeds'        ), 10, 2 );
		add_filter( 'comments_clauses',                        array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
		add_filter( 'the_content',                             array( $this, 'load_frontend'                          ) );
		add_filter( 'edd_template_paths',                      array( $this, 'add_template_path'                      ) );
		add_filter( 'admin_title',                             array( $this, 'admin_title'                            ), 10, 2 );
		add_filter( 'edd_downloads_query',                     array( $this, 'downloads_query'                        ), 10, 2 );
		add_filter( 'edd_email_receipt_download_title',        array( $this, 'email_receipt_download_title'           ), 10, 4 );
		add_filter( 'get_comment_link',                        array( $this, 'get_comment_link'                       ), 10, 2 );

		do_action_ref_array( 'edd_reviews_after_setup_actions', array( &$this ) );
	}

	/**
	 * Register Widgets
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function register_widgets() {
		register_widget( 'EDD_Reviews_Widget_Reviews' );
		register_widget( 'EDD_Reviews_Widget_Featured_Review' );
		register_widget( 'EDD_Reviews_Per_Product_Reviews_Widget' );
	}

	/**
	 * Get current commenter's name, email, and URL.
	 *
	 * @since 1.2
	 * @access public
	 * @return array Comment author, email, url respectively.
	 */
	public function get_current_reviewer() {
		$user = wp_get_current_user();

		$comment_author = $user->exists() ? $user->display_name : '';
		$comment_author_email = $user->user_email;

		return array( 'comment_author' => $comment_author, 'comment_author_email' => $comment_author_email );
	}

	/**
	 * Exclude reviews from showing in comment feeds (backwards compabitility)
	 *
	 * @since 2.0
	 * @access public
	 * @param  array  $clauses          Query clauses
	 * @param  object $wp_comment_query Instance of WP_Comment_Query
	 * @return array Query clauses
	 */
	public function hide_reviews_from_comment_feeds_compat( $clauses, $wp_comment_query ) {
		global $wpdb, $wp_version;

		if ( version_compare( floatval( $wp_version ), '4.1', '<' ) ) {
			$clauses['where'] .= ' AND comment_type != "edd_review"';
		}

		return $clauses;
	}

	/**
	 * Exclude reviews from showing in comment feeds.
	 *
	 * @since 2.0
	 * @access public
	 * @param $where SQL where clauses
	 * @param object $wp_comment_query Instance of WP_Comment_Query
	 * @return string SQL where clauses
	 */
	public function hide_reviews_from_comment_feeds( $where, $wp_comment_query ) {
		global $wpdb;

		$where .= $wpdb->prepare( " AND comment_type != %s", 'edd_review' );
		return $where;
	}

	/**
	 * Exclude reviews from WP_Query and Recent Comments widget in the WordPress dashboard
	 *
	 * @since  2.0
	 * @access public
	 * @param object $query Instance of WP_Query
	 * @return void
	 */
	public function hide_reviews( $query ) {
		global $wp_version;

		if ( version_compare( floatval( $wp_version ), '4.1', '>=' ) ) {
			$types = isset( $query->query_vars['type__not_in'] ) ? $query->query_vars['type__not_in'] : array();

			if ( ! is_array( $types ) ) {
				$types = array( $types );
			}

			$types[] = 'edd_review';
			$query->query_vars['type__not_in'] = $types;
		}
	}

	/**
	 * Checks if multiple reviews have been disabled and then verifies
	 * if the author has already posted a review for this download (product).
	 * This function queries the database for any reviews by taking the
	 * comment_post_ID and comment_author_email and if anything is returned, execution
	 * of the comment addition will fail with wp_die().
	 *
	 * @since 1.2
	 * @access public
	 * @param array $commentdata Comment data sent via HTTP POST
	 * @return object Returns comments if checkes pass
	 */
	public function check_author( $commentdata ) {
		if ( edd_get_option( 'edd_reviews_disable_multiple_reviews', false ) && isset( $_POST['edd_action'] ) && 'reviews_process_review' == $_POST['edd_action'] ) {
			$args = array(
				'author_email' => $commentdata['comment_author_email'],
				'post_id'      => $commentdata['comment_post_ID'],
				'meta_key'     => 'edd_review_title'
			);

			remove_action( 'pre_get_comments',   array( $this, 'hide_reviews'                           ) );
			remove_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
			remove_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds'        ), 10, 2 );

			$comments = get_comments( $args );

			add_action( 'pre_get_comments',   array( $this, 'hide_reviews'                           ) );
			add_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
			add_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds'        ), 10, 2 );

			if ( $comments ) {
				wp_die(
					sprintf( __( 'You are only allowed to post one review for this %s. Multiple reviews have been disabled.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ),
					__( 'Multiple Reviews Not Allowed', 'edd-reviews' ),
					array( 'back_link' => true )
				);
			} else {
				return $commentdata;
			}
		} else {
			return $commentdata;
		}
	}

	/**
	 * Save the review meta data into the database.
	 *
	 * @since 1.0
	 * @access public
	 * @param int $comment_id Comment ID
	 * @return void
	 */
	public function save_review_meta( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return; // Get out if not a valid comment
		}

		if ( ! empty( $comment->comment_parent ) ) {
			return; // Get out if this is a comment reply
		}

		$_POST['edd_rating'] = ( ! empty( $_POST['edd_rating'] ) ) ? $_POST['edd_rating'] : '5';

		/** Check if a rating has been submitted */
		if ( isset( $_POST['edd_review'] ) && isset( $_POST['edd_rating'] ) && ! empty( $_POST['edd_review_title'] ) ) {
			$rating = wp_filter_nohtml_kses( $_POST['edd_rating'] );
			add_comment_meta( $comment_id, 'edd_rating', $rating );
		}

		/** Check if a review title has been submitted */
		if ( isset( $_POST['edd_review'] ) && isset( $_POST['edd_review_title'] ) && ! empty( $_POST['edd_review_title'] ) ) {
			$review_title = sanitize_text_field( wp_filter_nohtml_kses( esc_html( $_POST['edd_review_title'] ) ) );
			add_comment_meta( $comment_id, 'edd_review_title', $review_title );
		}
	}

	/**
	 * Build the HTML 5 microdata based on Schema.org
	 *
	 * @since 1.0
	 * @access public
	 * @param string $content Content of the post
	 * @return string $content Content of the post with the microdata
	 */
	public function microdata( $content ) {
		global $post;

		// Bail if we're not on a download page
		if ( ! is_singular( 'download' ) ) {
			return $content;
		}

		if ( $this->count_reviews() < 1 ) {
			return $content;
		}

		do_action( 'edd_reviews_microdata_before' );
		?>
		<div style="display:none" class="edd-review-microdata" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
			<span itemprop="ratingValue"><?php echo $this->average_rating(); ?></span>
			<span itemprop="reviewCount"><?php echo $this->count_reviews(); ?></span>
		</div>
		<?php
		do_action( 'edd_reviews_microdata_after' );
		return $content;
	}

	/**
	 * Retrieve the average rating for the post.
	 *
	 * @since 1.0
	 * @since 2.1 - Updated method to use direct SQL queries.
	 *
	 * @access public
	 *
	 * @param bool $echo    Whether to echo the result or return it.
	 * @param int  $post_id Manually specify a post ID (optional).
	 * @return string $average Returns the average rating.
	 */
	public function average_rating( $echo = true, $post_id = null ) {
		global $post, $wpdb;

		$post_id = empty( $post_id ) ? $post->ID : $post_id;

		if ( ! $average = wp_cache_get( $post_id, 'edd_reviews_average_rating' ) ) {
			$average = $wpdb->get_var( $wpdb->prepare(
				"
				SELECT ROUND(AVG({$wpdb->commentmeta}.meta_value),2) AS average
				FROM {$wpdb->comments}
				LEFT JOIN {$wpdb->commentmeta} ON ({$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID)
				LEFT JOIN {$wpdb->posts} ON ({$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID)
				LEFT JOIN {$wpdb->commentmeta} AS mt2 ON ({$wpdb->comments}.comment_ID = mt2.comment_id AND mt2.meta_key = 'edd_review_approved')
				LEFT JOIN {$wpdb->commentmeta} AS mt3 ON ({$wpdb->comments}.comment_ID = mt3.comment_id AND mt3.meta_key = 'edd_review_reply')
				WHERE {$wpdb->commentmeta}.meta_key = 'edd_rating'
				AND {$wpdb->comments}.comment_type = 'edd_review'
				AND mt2.meta_value NOT IN ('spam', 'trash')
				AND mt3.comment_id IS NULL
				AND {$wpdb->comments}.comment_post_ID = %d
				GROUP BY {$wpdb->comments}.comment_post_ID
				", $post_id ) );

			wp_cache_set( $post_id, $average, 'edd_reviews_average_rating', 300 );
		}

		if ( $echo ) {
			echo $average;
		} else {
			return $average;
		}
	}

	/**
	 * Load Styles
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function load_styles() {
		global $post;

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		if ( edd_get_option( 'edd_reviews_disable_css', false ) ) {
			return;
		}

		wp_register_style( 'edd-reviews', $this->assets_url . 'css/edd-reviews' . $suffix .
		                                  '.css', array( 'dashicons' ), $this->version );
		wp_enqueue_style( 'edd-reviews' );
	}

	/**
	 * Load Scripts
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function load_scripts() {
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_script( 'edd-reviews-js', $this->assets_url . 'js/edd-reviews' . $suffix . '.js', array( 'jquery' ), $this->version );

		if ( is_singular( 'download' ) || ( $this->is_fes_installed() && edd_get_option( 'edd_reviews_vendor_feedback_page', false ) && is_page( edd_get_option( 'edd_reviews_vendor_feedback_page' ) ) ) ) {
			wp_enqueue_script( 'edd-reviews-js' );
		}

		$edd_reviews_params = array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'edd_voting_nonce' => wp_create_nonce( 'edd_reviews_voting_nonce' ),
			'thank_you_msg'    => apply_filters( 'edd_reviews_thank_you_for_voting_message', __( 'Thank you for your feedback.', 'edd-reviews' ) ),
			'ajax_loader'      => set_url_scheme( EDD_PLUGIN_URL . 'assets/images/loading.gif', 'relative' ), // Ajax loading image
		);

		wp_localize_script( 'edd-reviews-js', 'edd_reviews_params', apply_filters( 'edd_reviews_js_params', $edd_reviews_params ) );
	}

	/**
	 * Load Admin Scripts/Styles
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function admin_scripts() {
		global $current_screen, $post;

		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_register_style( 'edd-reviews-admin', $this->assets_url . 'css/edd-reviews-admin' . $suffix . '.css', array( ), filemtime( $this->assets_dir . 'css/edd-reviews-admin.css' ) );

		wp_register_script( 'edd-reviews-admin', $this->assets_url . 'js/edd-reviews-admin' . $suffix . '.js', array( 'jquery', 'wp-ajax-response' ), filemtime( $this->assets_dir . 'js/edd-reviews-admin.js' ) );
		wp_enqueue_script( 'edd-reviews-admin' );

		wp_enqueue_style( 'edd-reviews-admin' );
		wp_enqueue_style( 'wp-color-picker' );

		$edd_reviews_admin_params = array(
			'security' => wp_create_nonce( 'edd-reviews-admin-nonce' )
		);

		wp_localize_script( 'edd-reviews-admin', 'edd_reviews_admin_params', apply_filters( 'edd_reviews_admin_params', $edd_reviews_admin_params ) );
	}

	/**
	 * Register section for Reviews settings.
	 *
	 * @since 2.0
	 * @access public
	 * @return array $sections Settings sections
	 */
	public function register_reviews_section( $sections ) {
		$sections['reviews'] = __( 'Reviews', 'edd-reviews' );
		return $sections;
	}

	/**
	 * Register sections for Reviews settings on Emails tab.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $sections Email sections.
	 * @return array $sections Email sections.
	 */
	public function register_emails_section( $sections ) {
		$sections['reviews'] = __( 'Reviews', 'edd-reviews' );
		return $sections;
	}

	/**
	 * Register Reviews settings.
	 *
	 * @since 1.0
	 * @access public
	 * @param array $settings Registered EDD settings
	 * @return array New settings
	 */
	public function misc_settings( $settings ) {
		$new = array(
			array(
				'id'   => 'edd_reviews_settings',
				'name' => '<h3>' . __( 'Reviews', 'edd-reviews' ) . '</h3>',
				'type' => 'header'
			),
			array(
				'id'   => 'edd_reviews_enable_breakdown',
				'name' => __( 'Enable Review Breakdown', 'edd-reviews' ),
				'desc' => __( 'This will show how many people have rated the download at each star rating. It will display at the top of the Reviews section on the download page.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_disable_multiple_reviews',
				'name' => __( 'Disable Multiple Reviews By Same Author', 'edd-reviews' ),
				'desc' => __( 'This will disallow authors to post multiple reviews on the same download.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_disable_voting',
				'name' => __( 'Disable Voting On Reviews', 'edd-reviews' ),
				'desc' => __( 'Disables the voting feature that is displayed under each review.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_only_allow_reviews_by_buyer',
				'name' => __( 'Only Allow Reviews By Buyers', 'edd-reviews' ),
				'desc' => __( 'This will only allow customers who have purchased the download to review it. It will require them to login in order for the purchase to be verified.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_display_review_link_in_purchase_receipt',
				'name' => __( 'Display Review Link in Purchase Receipt', 'edd-reviews' ),
				'desc' => sprintf( __( 'This will display a link next to the name of a %s to allow customers to leave a review .', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_disable_own_reviews',
				'name' => sprintf( __( 'Disable Reviews By %s Author', 'edd-reviews' ), edd_get_label_singular() ),
				'desc' => sprintf( __( 'This will only allow the %s author to reply to reviews and not post reviews themselves.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_delay_posting',
				'name' => __( 'Delay Review Posting', 'edd-reviews' ),
				'desc' => sprintf( __( 'Enter the number of days that a customer will have to wait before posting a review of the %s purchased. Enter 0 for no delay.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ),
				'type' => 'text',
				'size' => 'small',
				'std'  => '0'
			),
			array(
				'id'   => 'edd_reviews_enable_guest_reviews',
				'name' => __( 'Enable Guest Reviews', 'edd-reviews' ),
				'desc' => __( 'Check this box to allow reviews to be left by guests (unauthenticated visitors).', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_minimum_word_count',
				'name' => __( 'Minimum Word Count', 'edd-reviews' ),
				'desc' => __( 'This will enforce a minimum word count for reviews.', 'edd-reviews' ),
				'type' => 'text',
				'size' => 'small',
				'std'  => ''
			),
			array(
				'id'   => 'edd_reviews_maximum_word_count',
				'name' => __( 'Maximum Word Count', 'edd-reviews' ),
				'desc' => __( 'This will enforce a maximum word count for reviews.', 'edd-reviews' ),
				'type' => 'text',
				'size' => 'small',
				'std'  => ''
			),
			array(
				'id'   => 'edd_reviews_disable_css',
				'name' => __( 'Disable EDD Reviews CSS', 'edd-reviews' ),
				'desc' => __( 'Check this to disable styling for the reviews provided by the EDD Reviews plugin.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id'   => 'edd_reviews_uninstall_on_delete',
				'name' => __( 'Remove Data on Uninstall?', 'edd-reviews' ),
				'desc' => __( 'Check this box if you would like Reviews to completely remove all of its data when the plugin is deleted.', 'edd-reviews' ),
				'type' => 'checkbox'
			),
			array(
				'id'   => 'edd_reviews_settings_reviewer_discount',
				'name' => '<h3>' . __( 'Reviewer Discount', 'edd-reviews' ) . '</h3>',
				'type' => 'header'
			),
			array(
				'id'   => 'edd_reviews_reviewer_discount',
				'name' => __( 'Enable Reviewer Discount', 'edd-reviews' ),
				'desc' => __( 'This will email each reviewer a discount code to use once their review has been approved. The discount they are given is a one-time use discount.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular',
			),
			array(
				'id'   => 'edd_reviews_reviewer_discount_amount',
				'name' => __( 'Reviewer Discount Amount', 'edd-reviews' ),
				'desc' => __( 'The percentage discount amount that will be provided to each reviewer. For example: for 10%, enter 10. (Only applicable if the reviewer discount option is enabled.)', 'edd-reviews' ),
				'type' => 'text',
				'size' => 'small',
				'std'  => ''
			),
		);

		if ( $this->is_fes_installed() ) {
			$fes_settings = array(
				array(
					'id'   => 'edd_reviews_vendor_feedback_settings',
					'name' => '<h3>' . __( 'Vendor Feedback', 'edd-reviews' ) . '</h3>',
					'type' => 'header'
				),
				array(
					'id'          => 'edd_reviews_vendor_feedback_page',
					'name'        => __( 'Vendor Feedback Page', 'edd-reviews' ),
					'desc'        => __( 'The page where customers can go to leave feedback for vendors of downloads.', 'edd-reviews' ),
					'type'        => 'select',
					'options'     => edd_get_pages(),
					'chosen'      => true,
					'placeholder' => __( 'Select a page', 'edd-reviews' )
				),
				array(
					'id'   => 'edd_reviews_vendor_feedback_table_heading',
					'name' => __( 'Vendor Feedback Heading', 'edd-reviews' ),
					'desc' => __( 'The heading label used on the Purchase History table', 'edd-reviews' ),
					'type' => 'text',
					'std'  => __( 'Feedback', 'edd-reviews' ),
					'size' => 'regular'
				),
				array(
					'id'   => 'edd_reviews_vendor_feedback_table_label',
					'name' => __( 'Vendor Feedback Link Text', 'edd-reviews' ),
					'desc' => __( 'The text for the link to provide Vendor Feedback displayed on the Purchase History table', 'edd-reviews' ),
					'type' => 'text',
					'std'  => __( 'Give Feedback', 'edd-reviews' ),
					'size' => 'regular'
				),
				array(
					'id'   => 'edd_reviews_vendor_feedback_form_heading',
					'name' => __( 'Vendor Feedback Form Heading', 'edd-reviews' ),
					'desc' => __( 'The text displayed above the Vendor Feedback form', 'edd-reviews' ),
					'type' => 'text',
					'std'  => __( 'Rate Your Experience', 'edd-reviews' ),
					'size' => 'regular'
				),
			);

			$new = array_merge( $new, $fes_settings );
		}

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$new = array( 'reviews' => $new );
		}

		return array_merge( $settings, $new );
	}

	/**
	 * Register Email Settings for Reviews.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $settings Registered EDD settings
	 * @return array New settings
	 */
	public function settings_emails( $settings ) {
		$reviews_settings = array(
			array(
				'id'   => 'edd_reviews_settings_emails',
				'name' => '<h3>' . __( 'Review Notification Emails', 'edd-reviews' ) . '</h3>',
				'type' => 'header'
			),
			array(
				'id'   => 'edd_reviews_settings_emails_toggle',
				'name' => __( 'Send Notifications For All Reviews', 'edd-reviews' ),
				'desc' => __( 'Check this to send an email notification for all reviews. Uncheck to only send an email notification when a posted review needs moderated.', 'edd-reviews' ),
				'type' => 'checkbox',
				'size' => 'regular'
			),
			array(
				'id' => 'edd_reviews_settings_emails_subject',
				'name' => __( 'Review Notification Subject', 'edd-reviews' ),
				'desc' => __( 'Enter the subject line for the review notification email', 'edd-reviews' ),
				'type' => 'text',
				'size' => 'regular',
				'std' => ''
			),
			array(
				'id'   => 'edd_reviews_settings_emails_notification',
				'name' => __( 'Review Notification', 'edd-reviews' ),
				'desc' => __( 'Enter the text that is sent as review notification email after completion of a purchase. HTML is accepted.', 'edd-reviews' ) . '<br />' . $this->email_tags->get_tags_list(),
				'type' => 'rich_editor',
				'std'  => $this->get_default_review_notification_email(),
			),
			array(
				'id'   => 'edd_reviews_settings_emails_admin_emails',
				'name' => __( 'Review Notification Emails', 'edd-reviews' ),
				'desc' => __( 'Enter the email address(es) that should receive a notification anytime a review is posted, one per line.', 'edd-reviews' ),
				'type' =>'textarea',
				'std'  => get_bloginfo( 'admin_email' ),
			),
			array(
				'id'   => 'edd_reviews_settings_emails_disable_notifications',
				'name' => __( 'Disable Review Notifications', 'edd-reviews' ),
				'desc' => __( 'Check this box if you do not want to receive review notification emails.', 'edd-reviews' ),
				'type' => 'checkbox',
			),
			array(
				'id'   => 'edd_reviews_settings_reviewer_discount',
				'name' => '<h3>' . __( 'Reviewer Discount', 'edd-reviews' ) . '</h3>',
				'type' => 'header'
			),
			array(
				'id' => 'edd_reviews_reviewer_discount_subject',
				'name' => __( 'Reviewer Discount Email Subject', 'edd-reviews' ),
				'desc' => __( 'Enter the subject line for the reviewer discount email', 'edd-reviews' ),
				'type' => 'text',
				'size' => 'regular',
				'std' => ''
			),
			array(
				'id'   => 'edd_reviews_reviewer_discount_email',
				'name' => __( 'Reviewer Discount Email', 'edd-reviews' ),
				'desc' => __( 'Enter the text that will be sent to a reviewer giving them a discount. HTML is accepted.', 'edd-reviews' ) . '<br />' . $this->email_tags->get_tags_list( 'discount' ),
				'type' => 'rich_editor',
				'std'  => $this->get_reviewer_discount_email(),
			),
		);

		if ( $this->is_fes_installed() ) {
			add_filter( 'edd_reviews_email_tags', array( $this->fes, 'email_tags' ) );

			$fes_settings = array(
				array(
					'id'   => 'edd_reviews_vendor_feedback_settings',
					'name' => '<h3>' . __( 'Vendor Feedback', 'edd-reviews' ) . '</h3>',
					'type' => 'header'
				),
				array(
					'id'   => 'edd_reviews_vendor_feedback_subject',
					'name' => __( 'Vendor Feedback Email Subject', 'edd-reviews' ),
					'desc' => __( 'Enter the subject line for the vendor feedback email', 'edd-reviews' ),
					'type' => 'text',
					'size' => 'regular'
				),
				array(
					'id'   => 'edd_reviews_vendor_feedback_email',
					'name' => __( 'Vendor Feedback Email', 'edd-reviews' ),
					'desc' => __( 'Enter the text that is sent to a vendor when feedback is posted. HTML is accepted.', 'edd-reviews' ) . '<br />' . $this->email_tags->get_tags_list(),
					'type' => 'rich_editor',
					'std'  => $this->fes->get_default_email(),
				),
			);

			$reviews_settings = array_merge( $reviews_settings, $fes_settings );

			remove_filter( 'edd_reviews_email_tags', array( $this->fes, 'email_tags' ) );
		}

		/**
		 * Filter the email settings.
		 *
		 * @since 2.1
		 *
		 * @param array $reviews_settings Email settings.
		 */
		$reviews_settings = apply_filters( 'edd_reviews_email_settings', $reviews_settings );

		if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
			$reviews_settings = array( 'reviews' => $reviews_settings );
		}

		return array_merge( $settings, $reviews_settings );
	}

	/**
	 * Adds "View Reviews" Link to Admin Bar
	 *
	 * @since 1.0
	 * @access public
	 * @global object $wp_admin_bar Used to add nodes to the WordPress Admin Bar
	 * @global object $post Used to access the post data
	 * @return void
	 */
	public function admin_bar_menu() {
		global $wp_admin_bar, $post;

		if ( is_admin() && current_user_can( 'moderate_comments' ) ) {
			$current_screen = get_current_screen();

			if ( 'post' == $current_screen->base && 'add' != $current_screen->action && ( $post_type_object = get_post_type_object( $post->post_type ) ) && 'download' == $post->post_type && current_user_can( $post_type_object->cap->read_post, $post->ID ) && ( $post_type_object->public ) && ( $post_type_object->show_in_admin_bar ) && current_user_can( 'moderate_comments' ) ) {
				if ( wp_count_comments( $post->ID )->total_comments > 0 ) {
					$wp_admin_bar->add_node( array(
						'id'    => 'edd-view-reviews',
						'title' => __( 'View Reviews', 'edd-reviews' ) . ' (' . wp_count_comments( $post->ID )->total_comments . ')',
						'href'  => admin_url( 'edit-comments.php?p=' . $post->ID )
					) );
				}
			}
		} elseif ( is_singular( 'download' ) && current_user_can( 'moderate_comments' ) ) {
			if ( wp_count_comments( $post->ID )->total_comments > 0 ) {
				$wp_admin_bar->add_node( array(
					'id'    => 'edd-view-reviews',
					'title' => __( 'View Reviews', 'edd-reviews' ) . ' (' . $this->count_reviews() . ')',
					'href'  => admin_url( 'edit.php?post_type=download&page=edd-reviews&review_status=approved&r=' . $post->ID )
				) );
			}
		}
	}

	/**
	 * Add Reviews page to admin menu
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		$reviews_page = add_submenu_page( 'edit.php?post_type=download', __( 'Reviews', 'edd-reviews' ), __( 'Reviews', 'edd-reviews' ), 'edit_posts', 'edd-reviews', array( $this, 'admin_page' ) );
	}

	/**
	 * Adjust the title of the custom admin page when on the Reviews page (Downloads > Reviews)
	 *
	 * @since 2.0
	 * @access public
	 * @return $admin_title New admin title
	 */
	public function admin_title( $admin_title, $title ) {
		$parent = get_admin_page_parent();

		if ( 'edit.php?post_type=download' == $parent && isset( $_GET['page'] ) && 'edd-reviews' == $_GET['page'] && isset( $_GET['edit'] ) && 'true' == $_GET['edit'] ) {
			$edit_review_title = __(  'Edit Review', 'edd-reviews' );
			return str_replace( 'Reviews', $edit_review_title, $admin_title );
		} else {
			return $admin_title;
		}
	}

	/**
	 * UI for the admin page.
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function admin_page() {
		if ( isset( $_GET['r'] ) && isset( $_GET['edit'] ) && is_numeric( $_GET['r'] ) && 'true' == $_GET['edit'] ) {
			$review_id = absint( $_GET['r'] );
			$review    = get_comment( $review_id, OBJECT );
			?>
			<form name="edd-reviews-edit" method="post" id="edd-reviews-edit-form">
				<?php wp_nonce_field( 'edd-update-review_' . $review->comment_ID ); ?>
				<div class="wrap">
					<h1><?php _e( 'Edit Review', 'edd-reviews' ); ?></h1>

					<?php
					if ( isset( $_REQUEST['edd_status_updated'] ) && 'true' == $_REQUEST['edd_status_updated'] ) {
						echo '<div id="moderated" class="updated notice is-dismissible"><p>' . __( 'Review updated successfully', 'edd-reviews' ) . '</p></div>';
					}
					?>

					<div id="poststuff">
						<input type="hidden" name="edd_action" value="update_review" />
						<input type="hidden" name="review_ID" value="<?php echo $review->comment_ID; ?>" />
						<input type="hidden" name="review_post_ID" value="<?php echo $review->comment_post_ID; ?>" />
						<div id="post-body" class="metabox-holder columns-2">
							<div id="post-body-content" class="edit-form-section edit-comment-section">
								<div id="namediv" class="stuffbox">
									<div class="inside">
										<fieldset>
											<legend class="edit-comment-author"><?php _e( 'Author', 'edd-reviews' ); ?></legend>
											<table class="form-table editcomment">
												<tbody>
													<tr>
														<td class="first"><label for="name"><?php _e( 'Name:', 'edd-reviews' ); ?></label></td>
														<td><input type="text" name="review_author" size="30" value="<?php echo esc_attr( $review->comment_author ); ?>" id="name" /></td>
													</tr>
													<tr>
														<td class="first"><label for="email"><?php _e( 'E-mail:', 'edd-reviews' ); ?></label></td>
														<td>
															<input type="email" name="review_author_email" size="30" value="<?php echo $review->comment_author_email; ?>" id="email" />
														</td>
													</tr>
													<tr>
														<td class="first"><label for="review_author_url"><?php _e( 'URL:', 'edd-reviews' ); ?></label></td>
														<td>
															<input type="url" id="review_author_url" name="review_author_url" size="30" class="code" value="<?php echo esc_attr($review->comment_author_url); ?>" />
														</td>
													</tr>
													<?php if ( 1 != get_comment_meta( $review->comment_ID, 'edd_review_reply', true ) ) { ?>
													<tr>
														<td class="first"><label for="review_edd_rating"><?php _e( 'Rating:', 'edd-reviews' ); ?></label></td>
														<td>
															<?php
															$rating = get_comment_meta( $review->comment_ID, 'edd_rating', true );
															echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', absint( $rating ) );
															echo str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
															?>
														</td>
													</tr>
													<?php } ?>
												</tbody>
											</table>
										</fieldset>
									</div><!-- /.inside -->
								</div><!-- /#namediv -->

								<div id="postdiv" class="postarea">
									<label for="content" class="screen-reader-text"><?php _e( 'Review', 'edd-reviews' ); ?></label>
									<?php
									$quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
									wp_editor( $review->comment_content, 'content', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings ) );
									wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
									?>
								</div><!-- /#postdiv -->
							</div><!-- /#post-body-content -->

							<div id="postbox-container-1" class="postbox-container">
								<div id="submitdiv" class="stuffbox">
									<h3><span class="hndle"><?php _e( 'Status', 'edd-reviews' ); ?></span></h3>
									<div class="inside">
										<div id="submitcomment" class="submitbox">
											<div id="minor-publishing">
												<div id="minor-publishing-actions">
													<div id="preview-action">
														<?php
														$link = get_permalink( $review->comment_post_ID );
														$link = $link . '#edd-review-' . $review->comment_ID;
														?>
														<a class="preview button" href="<?php echo $link; ?>" target="_blank"><?php _e( 'View Review', 'edd-reviews' ); ?></a>
													</div><!-- /#preview-action -->
													<div class="clear"></div>
												</div><!-- /#misc-publishing-actions -->

												<?php $status = get_comment_meta( $review->comment_ID, 'edd_review_approved', true ); ?>

												<div id="misc-publishing-actions">
													<fieldset class="misc-pub-section misc-pub-comment-status" id="comment-status-radio">
														<legend class="screen-reader-text"><?php _e( 'Review status', 'edd-reviews' ); ?></legend>
														<label class="approved"><input type="radio" <?php checked( $status, '1' ); ?> name="review_status" value="1"><?php _e( 'Approved', 'edd-reviews' ); ?></label><br />
														<label class="waiting"><input type="radio" <?php checked( $status, '0' ); ?> name="review_status" value="0"><?php _e( 'Pending', 'edd-reviews' ); ?></label><br />
														<label class="spam"><input type="radio" <?php checked( $status, 'spam' ); ?> name="review_status" value="spam"><?php _e( 'Spam', 'edd-reviews' ); ?></label><br />
													</fieldset>
													<div class="misc-pub-section curtime misc-pub-curtime">
														<?php
														$datef = __( 'M j, Y @ H:i' );
														$stamp = __('Submitted on: <b>%1$s</b>');
														$date = date_i18n( $datef, strtotime( $review->comment_date ) );
														?>
														<span id="timestamp"><?php printf( $stamp, $date ); ?></span>
													</div>
													<div class="misc-pub-section misc-pub-response-to">
														<?php
														$post_id = $review->comment_post_ID;
														if ( current_user_can( 'edit_post', $post_id ) ) {
															$post_link = "<a href='" . esc_url( get_edit_post_link( $post_id ) ) . "'>";
															$post_link .= esc_html( get_the_title( $post_id ) ) . '</a>';
														} else {
															$post_link = esc_html( get_the_title( $post_id ) );
														}
														?>
														<?php echo ucwords( edd_get_label_singular() ); ?>: <b><?php echo $post_link; ?></b>
													</div>
												</div><!-- /#misc-publishing-actions -->
											<div class="clear"></div>
											</div><!-- /#minor-publishing -->

											<div id="major-publishing-actions">
												<div id="delete-action">
													<a href="<?php echo esc_url( add_query_arg( array( 'edd_action' => 'update_review', 'review_status' => 'trash', '_wpnonce' => wp_create_nonce( 'edd-update-review_' . $review->comment_ID ) ) ) ); ?>" class="submitdelete deletion"><?php _e( 'Move to Trash', 'edd-reviews' ); ?></a>
												</div><!-- /#delete-action -->
												<div id="publishing-action">
													<?php submit_button( __( 'Update' ), 'primary', 'save', false ); ?>
												</div><!-- /#publishing-action -->
												<div class="clear"></div>
											</div><!-- /#major-publishing-actions -->
										</div><!-- /#submitcomment -->
									</div><!-- /.inside -->
								</div><!-- /#submitdiv -->
							</div><!-- /#postbox-container-1 -->

							<div id="postbox-container-2" class="postbox-container">

							</div><!-- /#postbox-container-2 -->

							<?php $referer = wp_get_referer(); ?>

							<input type="hidden" name="r" value="<?php echo esc_attr( $review->comment_ID ); ?>" />
							<input type="hidden" name="p" value="<?php echo esc_attr( $review->comment_post_ID ); ?>" />
							<?php wp_original_referer_field( true, 'previous' ); ?>
							<input name="referredby" type="hidden" id="referredby" value="<?php echo $referer ? esc_url( $referer ) : ''; ?>" />
						</div><!-- /#post-body -->
					</div><!-- /#poststuff -->
				</div><!-- /.wrap -->
			</form>
			<?php
			return;
		}

		$reviews_table = new EDD_Reviews_List_Table();
		$reviews_table->prepare_items();
		?>
		<div class="wrap">
			<?php if ( isset( $_GET['r'] ) && isset( $_GET['review_status'] ) && is_numeric( $_GET['r'] ) ) : ?>
				<h1>
					<?php
					echo sprintf( __( 'Reviews on &#8220;%s&#8221;' ),
						sprintf( '<a href="%s">%s</a>',
							get_edit_post_link( absint( $_GET['r'] ) ),
							wp_html_excerpt( _draft_or_post_title( absint( $_GET['r'] ) ), 50, '&hellip;' )
						)
					);
					?>
				</h1>
				<?php else : ?>
				<h1>
					<?php
					_e( 'Reviews', 'edd-reviews' );

					if ( isset($_REQUEST['s']) && $_REQUEST['s'] ) {
						echo '<span class="subtitle">' . sprintf( __( 'Search results for &#8220;%s&#8221;' ), wp_html_excerpt( esc_html( wp_unslash( $_REQUEST['s'] ) ), 50, '&hellip;' ) ) . '</span>';
					}
					?>
				</h1>
			<?php endif; ?>

			<?php
			if ( isset( $_REQUEST['approved'] ) || isset( $_REQUEST['deleted'] ) || isset( $_REQUEST['trashed'] ) || isset( $_REQUEST['restored'] ) || isset( $_REQUEST['spammed'] ) || isset( $_REQUEST['unspammed'] ) ) {
				$approved  = isset( $_REQUEST['approved']  ) ? (int) $_REQUEST['approved']  : 0;
				$deleted   = isset( $_REQUEST['deleted']   ) ? (int) $_REQUEST['deleted']   : 0;
				$trashed   = isset( $_REQUEST['trashed']   ) ? (int) $_REQUEST['trashed']   : 0;
				$untrashed = isset( $_REQUEST['restored']  ) ? (int) $_REQUEST['restored']  : 0;
				$spammed   = isset( $_REQUEST['spammed']   ) ? (int) $_REQUEST['spammed']   : 0;
				$unspammed = isset( $_REQUEST['unspammed'] ) ? (int) $_REQUEST['unspammed'] : 0;

				if ( $approved > 0 || $deleted > 0 || $trashed > 0 || $untrashed > 0 || $spammed > 0 || $unspammed > 0 || $same > 0 ) {
					if ( $approved > 0 ) {
						$messages[] = sprintf( _n( '%s review approved', '%s reviews approved', $approved ), $approved );
					}

					if ( $spammed > 0 ) {
						$messages[] = sprintf( _n( '%s review marked as spam.', '%s reviews marked as spam.', $spammed ), $spammed );
					}

					if ( $unspammed > 0 ){
						$messages[] = sprintf( _n( '%s review restored from the spam', '%s reviews restored from the spam', $unspammed ), $unspammed );
					}

					if ( $trashed > 0 ) {
						$messages[] = sprintf( _n( '%s review moved to the Trash.', '%s reviews moved to the Trash.', $trashed ), $trashed );
					}

					if ( $untrashed > 0 ) {
						$messages[] = sprintf( _n( '%s review restored from the Trash', '%s reviews restored from the Trash', $untrashed ), $untrashed );
					}

					if ( $deleted > 0 ) {
						$messages[] = sprintf( _n( '%s review permanently deleted', '%s reviews permanently deleted', $deleted ), $deleted );
					}

					echo '<div id="moderated" class="updated notice is-dismissible"><p>' . implode( "<br/>\n", $messages ) . '</p></div>';
				}
			}
			?>
			<form id="edd-reviews-form" method="get" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-reviews' ); ?>">
				<?php $reviews_table->search_box( __( 'Search Reviews', 'edd-reviews' ), 'edd-review' ); ?>
				<input type="hidden" name="post_type" value="download" />
				<input type="hidden" name="page" value="edd-reviews" />
				<?php
				$reviews_table->views();
				$reviews_table->display();
				?>
			</form>
			<?php
			if ( $reviews_table->has_items() ) {
				$reviews_table->inline_edit();
			}
			?>

			<div id="ajax-response"></div>
			<br class="clear" />
			</div>
		</div><!-- /.wrap -->
		<?php
	}

	/**
	 * Action fired when updated review data is sent via HTTP POST from the "Edit Review" page.
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function update_review() {
		if ( 'GET' == $_SERVER['REQUEST_METHOD'] && isset( $_GET['edd_action'] ) && 'update_review' == $_GET['edd_action'] ) {
			$review_id = absint( $_REQUEST['r'] );

			$review = get_comment( $review_id );

			check_admin_referer( 'edd-update-review_' . $review_id );

			$redirect = admin_url( 'edit.php?post_type=download&page=edd-reviews' );
			$redirect = add_query_arg( array( 'edit' => 'true', 'r' => $review->comment_ID ), $redirect );

			if ( isset( $_GET['review_status'] ) && 'trash' == $_GET['review_status'] ) {
				update_comment_meta( $review_id, 'edd_review_approved', 'trash' );
				$redirect = add_query_arg( array( 'edd_status_updated' => 'true' ), $redirect );
			}

			wp_safe_redirect( esc_url( $redirect ) );
		}

		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['edd_action'] ) && 'update_review' == $_POST['edd_action'] ) {
			$review_id = absint( $_REQUEST['r'] );

			$actions = array( '1', '0', 'spam' );

			$action = trim( $_POST['review_status'] );

			if ( in_array( $action, $actions ) ) {
				check_admin_referer( 'edd-update-review_' . $review_id );
			}

			if ( ! $review = get_comment( $review_id ) ) {
				wp_die( __( 'Invalid Review ID', 'edd-reviews' ) . ' ' . sprintf( '<a href="%s">' . __( 'Go Back' ) . '</a>', admin_url( 'edit.php?post_type=download&page=edd-reviews' ) ) );
			}

			if ( ! current_user_can( 'edit_comment', $review->comment_ID ) ) {
				wp_die( __( 'You are not allowed to edit reviews for this post.' ) );
			}

			$review_id = $review->comment_ID;

			$redirect = admin_url( 'edit.php?post_type=download&page=edd-reviews' );
			$redirect = add_query_arg( array( 'edit' => 'true', 'r' => $review->comment_ID ), $redirect );

			if ( '1' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '1' );
				$redirect = add_query_arg( array( 'edd_status_updated' => 'true', 'approved' => '1' ), $redirect );
			}

			if ( '0' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '0' );
				$redirect = add_query_arg( array( 'edd_status_updated' => 'true' ), $redirect );
			}

			if ( 'spam' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', 'spam' );
				$redirect = add_query_arg( array( 'edd_status_updated' => 'true', 'spammed' => 1 ), $redirect );
			}

			$updated_data = array();
			$updated_data['comment_ID'] = (int) $review_id;

			if ( isset( $_POST['review_author'] ) ) {
				$updated_data['comment_author'] = sanitize_text_field( $_POST['review_author'] );
			}

			if ( isset( $_POST['review_author_email'] ) ) {
				$updated_data['comment_author_email'] = sanitize_text_field( $_POST['review_author_email'] );
			}

			if ( isset( $_POST['review_author_url'] ) ) {
				$updated_data['comment_author_url'] = esc_url( $_POST['review_author_url'] );
			}

			if ( isset( $_POST['content'] ) ) {
				$updated_data['comment_content'] = esc_textarea( $_POST['content'] );
			}

			wp_update_comment( $updated_data );

			wp_redirect( esc_url_raw( $redirect ) );
		}
	}

	/**
	 * Handles the displaying of any notices in the admin area
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function admin_notices() {
		echo '<div class="error"><p>' . sprintf( __( 'You must install %sEasy Digital Downloads%s for the Reviews Add-On to work.', 'edd-reviews' ), '<a href="http://easydigitaldownloads.com" title="Easy Digital Downloads">', '</a>' ) . '</p></div>';
	}

	/**
	 * Count the number of reviews from the database.
	 *
	 * @since 1.0
	 * @access public
	 * @return string $count Number of reviews
	 */
	public function count_reviews() {
		global $wpdb, $post;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT({$wpdb->commentmeta}.meta_value)
				FROM {$wpdb->comments}
				LEFT JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt1 ON {$wpdb->comments}.comment_ID = mt1.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt2 ON ({$wpdb->comments}.comment_ID = mt2.comment_id AND mt2.meta_key = 'edd_review_reply')
				WHERE ((comment_approved = '0' OR comment_approved = '1'))
				AND {$wpdb->comments}.comment_post_ID = %d
				AND {$wpdb->comments}.comment_type IN ('edd_review')
				AND (
					({$wpdb->commentmeta}.meta_key = 'edd_review_approved' AND {$wpdb->commentmeta}.meta_value = '1')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'spam')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'trash')
					AND mt2.comment_id IS NULL
				)",
				$post->ID
			)
		);

		return $count;
	}

	/**
	 * Checks whether any reviews have been posted
	 *
	 * @since 2.0
	 * @access public
	 * @return bool
	 */
	public function have_reviews() {
		global $wpdb, $post;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*) AS count
				FROM {$wpdb->comments}
				WHERE comment_type = 'edd_review'
				AND comment_post_ID = %d
				AND comment_approved = '1'
				",
				$post->ID
			)
		);

		return $count;
	}


	/**
	 * Count the number of ratings from the database
	 *
	 * @since 1.0
	 * @access public
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @global object $post Used to access the post data
	 * @return string $count Number of reviews
	 */
	public function count_ratings() {
		global $wpdb, $post;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT SUM({$wpdb->commentmeta}.meta_value)
				FROM {$wpdb->comments}
				LEFT JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt1 ON {$wpdb->comments}.comment_ID = mt1.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt2 ON ({$wpdb->comments}.comment_ID = mt2.comment_id AND mt2.meta_key = 'edd_review_reply')
				WHERE ((comment_approved = '0' OR comment_approved = '1'))
				AND comment_post_ID = %d
				AND comment_type IN ('edd_review')
				AND (
					({$wpdb->commentmeta}.meta_key = 'edd_review_approved' AND {$wpdb->commentmeta}.meta_value = '1')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'spam')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'trash')
					AND mt2.comment_id IS NULL
				)",
				$post->ID
			)
		);

		return $count;
	}

	/**
	 * Gets the number of the reviews by a rating
	 *
	 * @since 1.0
	 * @access public
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @param int $rating Rating (1 - 5)
	 * @return int $number Number of reviews
	 */
	public function get_review_count_by_rating( $rating ) {
		global $wpdb, $post;

		$rating = (int) $rating;

		if ( $rating < 1 && $rating > 5 ) {
			return;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT SUM({$wpdb->commentmeta}.meta_value)
				FROM {$wpdb->comments}
				LEFT JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt1 ON {$wpdb->comments}.comment_ID = mt1.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt2 ON {$wpdb->comments}.comment_ID = mt2.comment_id
				LEFT JOIN {$wpdb->commentmeta} mt3 ON ({$wpdb->comments}.comment_ID = mt3.comment_id AND mt3.meta_key = 'edd_review_reply')
				WHERE ((comment_approved = '0' OR comment_approved = '1'))
				AND comment_post_ID = %d
				AND comment_type IN ('edd_review')
				AND (
					({$wpdb->commentmeta}.meta_key = 'edd_review_approved' AND {$wpdb->commentmeta}.meta_value = '1')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'spam')
					AND (mt1.meta_key = 'edd_review_approved'
					AND mt1.meta_value != 'trash')
					AND (mt2.meta_key = 'edd_rating'
					AND mt2.meta_value = %d)
					AND mt3.comment_id IS NULL
				)",
				$post->ID, $rating
			)
		);

		return $count;
	}

	/**
	 * Build Reviews (comments) title
	 *
	 * @since 1.0
	 * @access public
	 * @global object $post Used to access the post data
	 *
	 * @uses EDD_Reviews::count_reviews()
	 *
	 * @param int $average Average ratings for reviews
	 * @return void
	 */
	public function reviews_title( $average = null ) {
		global $post;

		$have_reviews = $this->have_reviews();

		if ( $average && ! empty( $have_reviews ) ) :
			do_action( 'edd_reviews_title_before' );
			?>
			<div itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
				<span itemprop="ratingValue" style="display:none"><?php echo $average; ?></span>
				<h2 class="edd-reviews-title" id="edd-reviews-title"><?php echo sprintf( apply_filters( 'edd_reviews_reviews_title', __( '%s Reviews for %s', 'edd-reviews' ) ), '<span itemprop="reviewCount" class="edd-review-count">' . $this->count_reviews() . '</span>', get_the_title( $post->ID ) ); ?></h2>
			</div>
		<?php elseif ( ! $average && ! empty( $have_reviews ) ) :?>
			<h2 class="edd-reviews-title" id="edd-reviews-title"><?php apply_filters( 'edd_reviews_review_title_default',  _e( 'Reviews', 'edd-reviews' ) ); ?></h2>
		<?php endif; ?>

		<?php
		if ( $this->is_review_status( 'closed' ) ) {
			echo '<p class="edd-reviews-closed-message"><strong>'. sprintf( __( 'Submitting new reviews to this %s has been closed.' ), strtolower( edd_get_label_singular() ) ) .'</strong></p>';
		}
		do_action( 'edd_reviews_title_after' );
	}

	/**
	 * Add a message to let the user know that their review is pending moderation.
	 *
	 * @since 2.1
	 * @access public
	 * @return void
	 */
	public function moderation_message() {
		if ( isset( $_GET['edd_review_submitted'] ) && 1 == $_GET['edd_review_submitted'] ) {
			?>
			<p class="edd-reviews-awaiting-moderation"><?php _e( 'Your review has been successfully submitted and is awaiting moderation.', 'edd-reviews' ) ?></p>
			<?php
		}
	}

	/**
	 * Checks if the reviewer has purchased the download
	 *
	 * @since 1.0
	 * @access public
	 * @global object $post Used to access the post data
	 * @return bool Whether reviews has purchased download or not
	 */
	public function reviewer_has_purchased_download() {
		global $post;

		$current_user = wp_get_current_user();

		if ( edd_has_user_purchased( $current_user->user_email, $post->ID ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Add Classes to the Reviews
	 *
	 * @since 1.0
	 * @access public
	 * @param array $classes Comment classes
	 * @return array $classes Comment (reviews) classes with 'review' added
	 */
	public function review_classes( $classes ) {
		$classes[] = 'review';

		return $classes;
	}

	/**
	 * Get the HTML to display the 'helpful' output to the user
	 *
	 * @since 1.3
	 * @access public
	 * @global $comment
	 * @return string $output Generated HTML output
	 */
	public function get_comment_helpful_output( $review ) {
		if ( edd_get_option( 'edd_reviews_disable_voting', false ) ) {
			return;
		}

		$comment = $review;

		ob_start();
		?>

		<?php if ( ! $this->is_review_poster() ) : ?>
			<?php if ( ( isset( $_GET['edd_reviews_vote'] ) && $_GET['edd_reviews_vote'] == 'success' && isset( $_GET['edd_c'] ) && is_numeric( $_GET['edd_c'] ) && $_GET['edd_c'] == $review->comment_ID ) || EDD()->session->get( 'wordpress_edd_reviews_voted_' . $review->comment_ID ) ) : ?>
				<div class="edd-review-vote edd-yellowfade">
					<p style="margin:0;padding:0;"><?php echo apply_filters( 'edd_reviews_thank_you_for_voting_message', __( 'Thank you for your feedback.', 'edd-reviews' ) ); ?></p>
					<?php $this->voting_info(); ?>
				</div>
			<?php else : ?>
				<div class="edd-review-review-helpful">
					<div class="edd-review-vote">
						<?php do_action( 'edd_reviews_voting_box_before' ); ?>
						<?php $this->voting_info( $review ); ?>
						<p><?php echo apply_filters( 'edd_reviews_voting_intro_text', __( 'Help other customers find the most helpful reviews', 'edd-reviews' ) ); ?></p>
						<p>
							<?php echo apply_filters( 'edd_reviews_review_helpful_text', __( 'Did you find this review helpful?', 'edd-reviews' ) ); ?>
							<span class="edd-reviews-voting-buttons">
								<a class="vote-yes" data-edd-reviews-comment-id="<?php echo get_comment_ID(); ?>" data-edd-reviews-vote="yes" rel="nofollow" href="<?php echo esc_url( add_query_arg( array( 'edd_review_vote' => 'yes', 'edd_c' => get_comment_ID() ) ) ); ?>"><?php _e( 'Yes', 'edd-reviews' ); ?></a>&nbsp;<a class="vote-no" data-edd-reviews-comment-id="<?php echo get_comment_ID(); ?>" data-edd-reviews-vote="no" rel="nofollow" href="<?php echo esc_url( add_query_arg( array( 'edd_review_vote' => 'no', 'edd_c' => get_comment_ID() ) ) ); ?>"><?php _e( 'No', 'edd-reviews' ); ?></a>
							</span>
						</p>
						<?php do_action( 'edd_reviews_voting_box_after' ); ?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$output = ob_get_contents();
		ob_end_clean();

		return $output;
	}

	/**
	 * Generate the output to display the comment rating
	 *
	 * @since 1.3
	 * @access public
	 * @global object $post
	 * @param  string $comment_text
	 * @param  array $comment
	 * @return string $output Generated HTML output
	 */
	public function comment_rating( $review ) {
		$rating  = $this->get_comment_rating_output( $review );
		$output  = $rating;
		$output .= $this->get_comment_helpful_output( $review );

		return $output;
	}

	/**
	 * Conditional whether or not the current logged in user is the poster of
	 * the review being displayed. This function runs throughout the comment
	 * loop and is called for each comment.
	 *
	 * @since 1.2
	 * @access public
	 * @global object $GLOBALS['comment'] Current comment
	 * @return bool Whether or not the current comment in the loop is by the current user logged in
	 */
	public function is_review_poster() {
		$comment = $GLOBALS['comment'];

		$user = wp_get_current_user();
		$user_email = ( isset( $user->user_email ) ? $user->user_email : null );

		if ( $comment->comment_author_email == $user_email ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Display Voting Info
	 *
	 * Example output: 2 of 8 people found this review helpful
	 *
	 * @since 1.0
	 * @access public
	 * @global object $GLOBALS['comment'] Current comment
	 * @return void
	 */
	public function voting_info( $review = false ) {

		if( empty( $review ) || ! is_object( $review ) ) {
			return;
		}

		$votes = array(
			'yes' => get_comment_meta( $review->comment_ID, 'edd_review_vote_yes', true ),
			'no'  => get_comment_meta( $review->comment_ID, 'edd_review_vote_no',  true ),
		);

		if ( ! empty( $votes['yes'] ) && $votes['yes'] >= 1 ) {
			if ( ! is_numeric( $votes['no'] ) ) {
				$votes['no'] = 0;
			}

			$total = $votes['yes'] + $votes['no'];

			echo '<p class="edd-review-voting-feedback">' . sprintf( __( '%s of %s people found this review helpful.', 'edd-reviews' ), $votes['yes'], $total ) . '</p>';
		}
	}

	/**
	 * Conditional whether or not to display review breakdown
	 *
	 * @since 1.0
	 * @access public
	 * @uses EDD_Reviews::review_breakdown()
	 * @return void
	 */
	public function maybe_show_review_breakdown() {
		if ( $this->is_review_status( 'closed' ) || $this->is_review_status( 'disabled' ) ) {
			return;
		}

		if ( edd_get_option( 'edd_reviews_enable_breakdown', false ) ) {
			$this->review_breakdown();
		}
	}

	/**
	 * Displays the login form
	 *
	 * @since 1.3
	 * @access public
	 * @return string $output Login form
	 */
	public function display_login_form() {
		$output = '';

		$output .= '<div class="edd-reviews-must-log-in comment-form" id="commentform">';
		$output .= '<p class="edd-reviews-not-logged-in">' . apply_filters( 'edd_reviews_user_logged_out_message', sprintf( __( 'You must log in and be a buyer of this %s to submit a review.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ) ) . '</p>';
		$output .= wp_login_form( array( 'echo' => false ) );
		$output .= '</div><!-- /.edd-reviews-must-log-in -->';

		return apply_filters( 'edd_reviews_login_form', $output );
	}

	/**
	 * Conditional whether or not the review submission form should remain
	 * restricted
	 *
	 * @since 1.2
	 * @access public
	 * @global object $post
	 * @return bool Whether the user has purchased the download
	 */
	public function maybe_restrict_form() {
		global $post;

		if ( $this->is_review_status( 'closed' ) || $this->is_review_status( 'disabled' ) ) {
			return true;
		}

		if ( $this->is_guest_reviews_enabled() ) {
			return false;
		}

		$user = wp_get_current_user();
		$user_id = ( isset( $user->ID ) ? (int) $user->ID : 0 );

		if ( ( edd_get_option( 'edd_reviews_only_allow_reviews_by_buyer', false ) && edd_has_user_purchased( $user_id, $post->ID ) ) || ( ! edd_get_option( 'edd_reviews_only_allow_reviews_by_buyer', false ) ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Conditional whether guest reviews are enabled or not.
	 *
	 * @since 2.1
	 * @access public
	 * @return bool
	 */
	public function is_guest_reviews_enabled() {
		return edd_get_option( 'edd_reviews_enable_guest_reviews', false );
	}

	/**
	 * Conditional whether the currently logged in user has purchased the product
	 * before
	 *
	 * @since 1.2
	 * @access public
	 * @global array $post Used to access the current post
	 * @return void
	 */
	public function is_user_buyer() {
		global $post;

		if ( edd_has_user_purchased( get_current_user_id(), $post->post_ID ) ) {
			return true;
		} else {
			return false;
		} // end if
	}

	/**
	 * Reviews Breakdown
	 *
	 * Shows a breakdown of all the reviews and the number of people that given
	 * each rating for each download
	 *
	 * Example: 8 people gave a 5 star rating; 10 people have a 2 star rating
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses EDD_Reviews::display_total_reviews_count()
	 * @uses EDD_Reviews::display_review_counts()
	 *
	 * @return void
	 */
	public function review_breakdown() {
		$rating = $this->average_rating( false );

		if ( ! $rating > 0 ) {
			return;
		}

		echo '<div class="edd_reviews_breakdown">';
		echo '<div class="edd-reviews-average-rating"><strong><span class="edd-reviews-average-rating-label">'. __( 'Average rating:', 'edd-reviews' ) .'</span></strong> ' . $this->average_rating(false) . ' ' . __( 'out of 5 stars', 'edd-reviews' ) . '</div>';
		$this->display_total_reviews_count();
		$this->display_review_counts();
		echo '</div><!-- /.edd_reviews_breakdown -->';
	}

	/**
	 * Displays the total reviews count
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses EDD_Reviews::count_reviews()
	 *
	 * @return void
	 */
	public function display_total_reviews_count() {
		echo '<div class="edd-reviews-total-count">' . $this->count_reviews() . ' ' . _n( 'review', 'reviews', $this->count_reviews(), 'edd-reviews' ) . '</div>';
	}

	/**
	 * Displays reviews count for each rating by looping through 1 - 5
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses EDD_Reviews::get_review_count_by_rating()
	 * @uses EDD_Reviews::count_reviews()
	 *
	 * @return void
	 */
	public function display_review_counts() {
		$output = '';

		for ( $i = 5; $i >= 1; $i-- ) {
			$number = $this->get_review_count_by_rating( $i );

			$all = $this->count_reviews();

			( $all == 0 ) ? $all = 1 : $all;

			$number_format = number_format( ( $number / $all ) * 100, 1 ) . '%';

			$output .= '<div class="edd-counter-container edd-counter-container-'. $i .'">';
			$output .= '<div class="edd-counter-label">'. $i . ' ' . _n( 'star', 'stars', $i, 'edd-reviews' ) . '</div>';
			$output .= '<div class="edd-counter-back"><span class="edd-counter-front" style="width: '. $number_format .'"></span></div>';
			$output .= '<div class="edd-review-count">'. $number .'</div>';
			$output .= '</div>';
		}

		echo '<div class="edd-reviews-breakdown-ratings">' . $output . '</div>';
	}

	/**
	 * Display an aggregate review score across all reviews.
	 *
	 * @since   1.3.7
	 * @access  public
	 * @return  void
	 */
	public function display_aggregate_rating() {
		$average = $this->average_rating( false );
		?>
		<div class="edd_reviews_aggregate_rating_display">
			<div class="edd_reviews_rating_box" role="img" aria-label="<?php echo $average . ' ' . __( 'stars', 'edd-reviews' ); ?>">
				<div class="edd_star_rating" style="width: <?php echo ( 19 * $average ); ?>px"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Process Vote from Review
	 *
	 * This function is called if a JavaScript isn't enabled
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function process_vote() {
		if ( isset( $_GET['edd_review_vote'] ) && isset( $_GET['edd_c'] ) && is_numeric( $_GET['edd_c'] ) ) {
			$this->add_comment_vote_meta( $_GET['edd_c'], $_GET['edd_review_vote'] );

			// Remove the query arguments to prevent multiple votes
			$url = esc_url_raw( remove_query_arg( array( 'edd_c', 'edd_review_vote' ) ) );
			wp_safe_redirect( $url . '?edd_reviews_vote=success&edd_c=' . $_GET['edd_c'] .'#comment-' . intval( $_GET['edd_c'] ) );
			die();
		}
	}

	/**
	 * Add Comment Vote
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param int $comment_id Comment ID
	 * @param string $vote Whether the vote was yes or no
	 * @return void
	 */
	public function add_comment_vote_meta( $comment_id, $vote ) {
		if ( 'yes' == $vote ) {
			$value = get_comment_meta( $comment_id, 'edd_review_vote_yes', true );

			if ( empty( $value ) && ! $value > 0 ) {
				$number = 1;
				add_comment_meta( $comment_id, 'edd_review_vote_yes', $number );
			} elseif ( ! empty( $value ) && $value > 0 ) {
				$number = $value++;
				update_comment_meta( $comment_id, 'edd_review_vote_yes', $number );
			}
		} elseif ( 'no' == $vote ) {
			$value = get_comment_meta( $comment_id, 'edd_review_vote_no', true );

			if ( empty( $value ) && ! $value > 0 ) {
				$number = 1;
				add_comment_meta( $comment_id, 'edd_review_vote_yes', $number );
			} elseif ( ! empty( $value ) && $value > 0 ) {
				$number = $value++;
				update_comment_meta( $comment_id, 'edd_review_vote_no', $number );
			}

			add_comment_meta( $comment_id, 'edd_review_vote_no', $number );
		}
	}

	/**
	 * Checks whether an AJAX request has been sent
	 *
	 * @since 1.0
	 * @access public
	 * @return bool Whether or not AJAX $_GET header has been passed
	 */
	public function is_ajax_request() {
		return (bool) ( isset( $_POST['edd_reviews_ajax'] ) && ! empty( $_REQUEST['action'] ) );
	}

	/**
	 * Process Voting for the Reviews via AJAX
	 *
	 * Processes the voting button appended to the bottom of each review by adding
	 * or updating the comment meta via AJAX.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses EDD_Reviews::is_ajax_request()
	 *
	 * @return mixed returns if AJAX check fails
	 */
	public function process_ajax_vote() {
		// Bail if an AJAX request isn't sent
		if ( ! $this->is_ajax_request() ) {
			return;
		}

		check_ajax_referer( 'edd_reviews_voting_nonce', 'security', true );

		if ( ! isset( $_POST['review_vote'] ) ) {
			return;
		}

		if ( isset( $_POST['review_vote'] ) && isset( $_POST['comment_id'] ) && is_numeric( $_POST['comment_id'] ) ) {
			$this->add_comment_vote_meta( $_POST['comment_id'], $_POST['review_vote'] );

			EDD()->session->set( 'wordpress_edd_reviews_voted_' . $_POST['comment_id'], 'yes' );

			echo 'success';
		} else {
			echo 'fail';
		}

		die();
	}

	/**
	 * Register Dashboard Widgets
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function dashboard_widgets() {
		if ( is_blog_admin() && current_user_can( 'moderate_comments' ) ) {
			$recent_reviews_title = apply_filters( 'edd_reviews_recent_reviews_dashboard_widget_title', __( 'Easy Digital Downloads Recent Reviews', 'edd-reviews' ) );
			wp_add_dashboard_widget(
				'edd_reviews_dashboard_recent_reviews',
				$recent_reviews_title,
				array( edd_reviews(), 'render_dashboard_widget' )
			);
		}
	}

	/**
	 * Render the Dashboard Widget
	 *
	 * @since 1.0
	 * @access public
	 * @global object $wpdb Used to query the database using the WordPress Database API
	 * @return void
	 */
	public static function render_dashboard_widget() {
		global $wpdb;

		$reviews = array();

		remove_action( 'pre_get_comments',   array( edd_reviews(), 'hide_reviews' ) );

		$reviews_query = array(
			'type'       => 'edd_review',
			'meta_query' => array(
				array(
					'key'     => 'edd_review_reply',
					'compare' => 'NOT EXISTS'
				)
			)
		);

		if ( ! current_user_can( 'edit_posts' ) ) {
			$reviews_query['meta_query'] = array(
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
		}

		$reviews = get_comments( $reviews_query );

		add_action( 'pre_get_comments',   array( edd_reviews(), 'hide_reviews' ) );

		if ( ! $reviews ) {
			echo '<div id="edd-reviews-list">';

			foreach ( $reviews as $review ) :
				if ( ! current_user_can( 'read_post', $review->comment_post_ID ) ) {
					continue;
				}
				$rating = get_comment_meta( $review->comment_ID, 'edd_rating', true );
				$post = get_post( $review->comment_post_ID );
			?>
				<div id="edd-review-<?php echo $review->comment_ID; ?>" class="review-item">
					<?php echo get_option( 'show_avatars' ) ? get_avatar( $review->comment_author_email, 50 ) : ''; ?>
					<div class="edd-dashboard-review-wrap">
						<h4 class="meta">
						<?php
						printf(
							'<a href="%1$s">%2$s</a> %5$s <a href="%3$s">%4$s</a>',
							get_comment_link( $review ),
							esc_html__( get_comment_meta( $review->comment_ID, 'edd_review_title', true ) ),
							get_permalink( $review->comment_post_ID ),
							esc_html( get_the_title( $review->comment_post_ID ) ),
							__( 'on', 'edd-reviews' )
						);
						?>
						</h4>
						<?php
						echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', absint( $rating ) );
						echo str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
						?>
						<p>
						<?php
						__( 'By', 'edd-reviews' ) . ' ' . esc_html( $review->comment_author ) . ', ' . get_comment_date( get_option( 'date_format()' ), $review->comment_ID )
						?>
						</p>
						<blockquote><?php comment_excerpt( $review->comment_ID )  ?></blockquote>
					</div>
				</div>
			<?php
			endforeach;

			echo '</div>';

			if ( current_user_can( 'edit_posts' ) ) {
				$list_table = new EDD_Reviews_List_Table();
				$list_table->views();
			}
		} else {
			echo '<p class="edd-reviews-no-reviews-yet">' . __( 'There are no reviews yet.', 'edd-reviews' ) . '</p>';
		}
	}

	/**
	 * Add the Meta Boxes
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function add_meta_boxes() {

		global $pagenow;

		if( 'post-new.php' == $pagenow ) {
			return;
		}

		$tooltip = '<span alt="f223" class="edd-help-tip dashicons dashicons-editor-help" title="' . __( 'Disabling reviews will not show any reviews whatsoever. However, closing reviews will stop any new reviews from being accepted.', 'edd-reviews' ) . '"></span>';
		add_meta_box( 'edd-reviews-status', sprintf( __( 'Reviews Status %s', 'edd-reviews' ), $tooltip ), array( $this, 'reviews_status_meta_box' ), 'download', 'side', 'low' );
		add_meta_box( 'edd-reviews', __( 'Reviews', 'edd-reviews' ), array( $this, 'reviews_admin_meta_box' ), 'download', 'normal', 'core' );
	}

	/**
	 * Render Disbale Reviews Meta Box
	 *
	 * @since 2.0
	 * @return void
	 * @access public
	 */
	public function reviews_status_meta_box() {
		$status = get_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', true );
		ob_start();
		?>
		<?php if ( empty( $status ) || $status == 'enabled' || $status == 'closed' || $status == 'opened' ) { ?>
		<p style="text-align: center;"><a href="<?php echo esc_url( add_query_arg( 'edd_action', 'disable_reviews' ) ); ?>" class="button-secondary"><?php _e( 'Disable Reviews', 'edd-reviews' ); ?></a></p>
		<?php } ?>

		<?php if ( $status == 'disabled' ) { ?>
		<p style="text-align: center;"><a href="<?php echo esc_url( add_query_arg( 'edd_action', 'enable_reviews' ) ); ?>" class="button-secondary"><?php _e( 'Enable Reviews', 'edd-reviews' ); ?></a></p>
		<?php } ?>

		<?php if ( $status == 'closed' ) { ?>
		<p style="text-align: center;"><a href="<?php echo esc_url( add_query_arg( 'edd_action', 'open_reviews' ) ); ?>" class="button-secondary"><?php _e( 'Open Reviews', 'edd-reviews' ); ?></a></p>
		<?php } ?>

		<?php if ( empty( $status ) ||  ( ! empty( $status ) && $status !== 'closed' ) || $status == 'open'  ) { ?>
		<p style="text-align: center;"><a href="<?php echo esc_url( add_query_arg( 'edd_action', 'close_reviews' ) ); ?>" class="button-secondary"><?php _e( 'Close Reviews', 'edd-reviews' ); ?></a></p>
		<?php } ?>
		<?php
		$rendered = ob_get_contents();
		ob_end_clean();

		echo $rendered;
	}

	/**
	 * Render Reviews Meta Box
	 *
	 * @since 2.0
	 * @return void
	 * @access public
	 */
	public function reviews_admin_meta_box() {
		$reviews_table = new EDD_Reviews_Download_List_Table;
		$reviews_table->prepare_items();
		$reviews_table->display( true );
		if ( $reviews_table->has_items() ) {
			$reviews_table->inline_edit();
		}
		?>

		<div id="ajax-response"></div>
		<br class="clear" />

		<?php
		if ( $reviews_table->has_items() ) {
			ob_start(); ?>
			<p id="show-reviews"><a class="button-secondary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-reviews&r='. absint( $_GET['post'] ) .'&review_status=approved' ) ); ?>"><?php _e( 'Show all reviews', 'edd-reviews' ); ?></a></p>
			<?php
			$rendered = ob_get_contents();
			ob_end_clean();

			echo $rendered;
		}
	}

	/**
	 * Update Reviews status on a specific download
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function update_reviews_status() {
		if ( $_GET['edd_action'] == 'close_reviews' ) {
			update_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', 'closed' );
		} elseif ( $_GET['edd_action'] == 'open_reviews' ) {
			update_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', 'opened' );
		} elseif ( $_GET['edd_action'] == 'disable_reviews' ) {
			update_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', 'disabled' );
		} elseif ( $_GET['edd_action'] == 'enable_reviews' ) {
			update_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', 'enabled' );
		}
	}

	/**
	 * Get Reviews Status
	 *
	 * @since 2.0
	 * @access public
	 *
	 * @param string $status Review status to check against.
	 * @param int    $id     Post ID.
	 * @return bool
	 */
	public function is_review_status( $status, $post_id = 0 ) {
		global $post;

		$id = ! empty( $post_id ) ? $post_id : $post->ID;

		return ( $status == get_post_meta( $id, 'edd_reviews_status', true ) );
	}

	/**
	 * Close Reviews on Download
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function close_reviews() {
		update_post_meta( absint( $_GET['post'] ), 'edd_reviews_status', 'closed' );
	}

	/**
	 * Save the Meta Data from the Meta Box on the Edit Comment Screen
	 *
	 * @since 1.0
	 * @access public
	 * @param int $comment_id Comment ID
	 * @return void
	 */
	public function update_review_meta( $comment_id ) {
		if ( $this->has_review_meta( $comment_id ) ) {
			$review_title = sanitize_text_field( $_POST['edd_reviews_review_title'] );
			$rating       = intval( $_POST['edd_reviews_rating'] );

			if ( empty ( $review_title ) ) {
				wp_die( sprintf( __( '%sError%s: Please add a review title.', 'edd-reviews' ), '<strong>', '</strong>' ), __( 'Error', 'edd-reviews' ), array( 'back_link' => true ) );
			}

			if ( ! ( $rating > 0 && $rating <= 5 ) ) {
				wp_die( sprintf( __( '%sError%s: Please add a valid rating between 1 and 5.', 'edd-reviews' ), '<strong>', '</strong>' ), __( 'Error', 'edd-reviews' ), array( 'back_link' => true ) );
			}

			update_comment_meta( $comment_id, 'edd_review_title', $review_title );
			update_comment_meta( $comment_id, 'edd_rating', $rating );
		}
	}

	/**
	 * Register API Query Mode
	 *
	 * @since 1.0
	 * @access public
	 * @param array $modes Whitelisted query modes
	 * @return array $modes Updated list of query modes
	 */
	public function register_api_mode( $modes ) {
		$modes[] = 'reviews';
		return $modes;
	}

	/**
	 * Add 'review_id' Query Var into WordPress Whitelisted Query Vars
	 *
	 * @since 1.0
	 * @access public
	 * @param array $vars Array of WordPress allowed query vars
	 * @return array $vars Updated array of WordPress query vars to allow
	 *  Reviews to integrate with the EDD API
	 */
	public function query_vars( $vars ) {
		$vars[] = 'review_id';
		$vars[] = 'download_id';
		return $vars;
	}

	/**
	 * Processes the Data Outputted when an API Call for Reviews is Triggered
	 *
	 * @since 1.0
	 * @access public
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @global object $wp_query Used to access the query vars
	 *
	 * @param array $data Array to hold the output
	 * @param array $query_mode Query mode (i.e. reviews)
	 * @param object $api_object EDD_API Object
	 *
	 * @return array $data All the data for when the API call for reviews is fired
	 */
	public function api_output( $data, $query_mode, $api_object ) {
		global $wpdb, $wp_query;

		remove_action( 'pre_get_comments',   array( $this, 'hide_reviews' ) );
		remove_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
		remove_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds' ), 10, 2 );

		// Bail if the query mode isn't reviews
		if ( 'reviews' !== $query_mode ) {
			return $data;
		} // end if

		// Get the review_id query var
		$review_id   = isset( $wp_query->query_vars['review_id'] )   ? $wp_query->query_vars['review_id']   : null;
		$download_id = isset( $wp_query->query_vars['download_id'] ) ? $wp_query->query_vars['download_id'] : null;
		$numbers     = isset( $wp_query->query_vars['number'] )      ? $wp_query->query_vars['number']      : 1;

		if ( $review_id && ! $download_id ) {
			// Get the review from the database
			$review = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT *
					FROM {$wpdb->comments}
					INNER JOIN {$wpdb->posts} ON {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID
					WHERE comment_ID = '%d' AND comment_type = 'edd_review'
					LIMIT 1
					",
					$review_id
				)
			);

			if ( $review[0]->comment_parent > 0 ) {
				$type = 'reply';
			} else {
				$type = 'review';
			}

			if ( $review ) {
				$data['reviews']['id']             = $review[0]->comment_ID;
				$data['reviews']['title']          = get_comment_meta( $review[0]->comment_ID, 'edd_review_title', true ) ? get_comment_meta( $review[0]->comment_ID, 'edd_review_title', true ) : null;
				$data['reviews']['parent']         = $review[0]->comment_parent;
				$data['reviews']['download_id']    = $review[0]->comment_post_ID;
				$data['reviews']['download_title'] = $review[0]->post_title;
				$data['reviews']['rating']         = get_comment_meta( $review[0]->comment_ID, 'edd_rating', true ) ? get_comment_meta( $review[0]->comment_ID, 'edd_rating', true ) : null;
				$data['reviews']['author']         = $review[0]->comment_author;
				$data['reviews']['email']          = $review[0]->comment_author_email;
				$data['reviews']['IP']             = $review[0]->comment_author_IP;
				$data['reviews']['date']           = $review[0]->comment_date;
				$data['reviews']['date_gmt']       = $review[0]->comment_date_gmt;
				$data['reviews']['content']        = $review[0]->comment_content;
				$data['reviews']['status']         = get_comment_meta( $review[0]->comment_ID, 'edd_review_approved', true );
				$data['reviews']['user_id']        = $review[0]->user_id;
				$data['reviews']['type']           = $type;

				if ( get_comment_meta( $review[0]->comment_ID, 'edd_review_vote_yes', true ) || get_comment_meta( $review[0]->comment_ID, 'edd_review_vote_no', true ) ) {
					$data['reviews']['votes']['yes']   = get_comment_meta( $review[0]->comment_ID, 'edd_review_vote_yes', true );
					$data['reviews']['votes']['no']    = get_comment_meta( $review[0]->comment_ID, 'edd_review_vote_no', true );
				} elseif ( $review[0]->comment_parent > 0 ) {
					$data['reviews']['votes'] = null;
				} else {
					$data['reviews']['votes']['yes']   = '0';
					$data['reviews']['votes']['no']    = '0';
				}
			} else {
				$error['error'] = sprintf( __( 'Review %s not found!', 'edd-reviews' ), $review_id );
				return $error;
			} // end if
		} elseif ( ! $download_id && ! $review_id ) {
			// Get total reviews count from the database
			$total_reviews = $wpdb->get_var(
				"
				SELECT COUNT(meta_value)
				FROM {$wpdb->commentmeta}
				LEFT JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID
				WHERE meta_key = 'edd_rating'
				AND comment_type = 'edd_review'
				AND comment_approved = '1'
				AND meta_value > 0
				"
			);

			$data['reviews']['total'] = $total_reviews;

			$most_recent_reviews = get_comments( array(
				'post_type' => 'download',
				'number' => $number,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'edd_review_approved',
						'value'   => '1',
						'compare' => '='
					),
					array(
						'key' => 'edd_review_approved',
						'value' => 'spam',
						'compare' => '!='
					),
					array(
						'key' => 'edd_review_approved',
						'value' => 'trash',
						'compare' => '!='
					)
				)
			) );

			$i = 0;

			foreach ( $most_recent_reviews as $most_recent_review ) {
				if ( $most_recent_review->comment_parent > 0 ) {
					$type = 'reply';
				} else {
					$type = 'review';
				}

				$data['reviews']['most_recent'][ $i ]['id']             = $most_recent_review->comment_ID;
				$data['reviews']['most_recent'][ $i ]['title']          = get_comment_meta( $most_recent_review->comment_ID, 'edd_review_title', true ) ? get_comment_meta( $most_recent_review->comment_ID, 'edd_review_title', true ) : null;
				$data['reviews']['most_recent'][ $i ]['parent']         = $most_recent_review->comment_parent;
				$data['reviews']['most_recent'][ $i ]['download_id']    = $most_recent_review->comment_post_ID;
				$data['reviews']['most_recent'][ $i ]['download_title'] = get_the_title( $most_recent_review->comment_post_ID );
				$data['reviews']['most_recent'][ $i ]['rating']         = get_comment_meta( $most_recent_review->comment_ID, 'edd_rating', true ) ? get_comment_meta( $most_recent_review->comment_ID, 'edd_rating', true ) : null;
				$data['reviews']['most_recent'][ $i ]['author']         = $most_recent_review->comment_author;
				$data['reviews']['most_recent'][ $i ]['email']          = $most_recent_review->comment_author_email;
				$data['reviews']['most_recent'][ $i ]['IP']             = $most_recent_review->comment_author_IP;
				$data['reviews']['most_recent'][ $i ]['date']           = $most_recent_review->comment_date;
				$data['reviews']['most_recent'][ $i ]['date_gmt']       = $most_recent_review->comment_date_gmt;
				$data['reviews']['most_recent'][ $i ]['content']        = $most_recent_review->comment_content;
				$data['reviews']['most_recent'][ $i ]['status']         = get_comment_meta( $most_recent_review->comment_ID, 'edd_review_approved', true );
				$data['reviews']['most_recent'][ $i ]['user_id']        = $most_recent_review->user_id;
				$data['reviews']['most_recent'][ $i ]['type']           = $type;

				if ( get_comment_meta( $most_recent_review->comment_ID, 'edd_review_vote_yes', true ) || get_comment_meta( $most_recent_review->comment_ID, 'edd_review_vote_no', true ) ) {
					$data['reviews']['most_recent'][ $i ]['votes']['yes']   = get_comment_meta( $most_recent_review->comment_ID, 'edd_review_vote_yes', true );
					$data['reviews']['most_recent'][ $i ]['votes']['no']    = get_comment_meta( $most_recent_review->comment_ID, 'edd_review_vote_no', true );
				} elseif ( $most_recent_review->comment_parent > 0 ) {
					$data['reviews']['most_recent'][ $i ]['votes'] = null;
				} else {
					$data['reviews']['most_recent'][ $i ]['votes']['yes']   = '0';
					$data['reviews']['most_recent'][ $i ]['votes']['no']    = '0';
				}

				$i++;
			}
		} elseif ( $download_id ) {
			$reviews = get_comments( array(
				'post_type'  => 'download',
				'type'       => 'edd_review',
				'meta_query' => array(
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
					)
				)
			) );

			if ( $reviews ) {
				$i = 0;

				$data['reviews'] = array();

				foreach ( $reviews as $review ) {
					if ( $review->comment_parent > 0 ) {
						$type = 'reply';
					} else {
						$type = 'review';
					}

					$data['reviews'][ $i ]['id']             = $review->comment_ID;
					$data['reviews'][ $i ]['title']          = get_comment_meta( $review->comment_ID, 'edd_review_title', true ) ? get_comment_meta( $review->comment_ID, 'edd_review_title', true ) : null;
					$data['reviews'][ $i ]['parent']         = $review->comment_parent;
					$data['reviews'][ $i ]['download_id']    = $review->comment_post_ID;
					$data['reviews'][ $i ]['download_title'] = get_the_title( $review->comment_post_ID );
					$data['reviews'][ $i ]['rating']         = get_comment_meta( $review->comment_ID, 'edd_rating', true ) ? get_comment_meta( $review->comment_ID, 'edd_rating', true ) : null;
					$data['reviews'][ $i ]['author']         = $review->comment_author;
					$data['reviews'][ $i ]['email']          = $review->comment_author_email;
					$data['reviews'][ $i ]['IP']             = $review->comment_author_IP;
					$data['reviews'][ $i ]['date']           = $review->comment_date;
					$data['reviews'][ $i ]['date_gmt']       = $review->comment_date_gmt;
					$data['reviews'][ $i ]['content']        = $review->comment_content;
					$data['reviews'][ $i ]['status']         = get_comment_meta( $review->comment_ID, 'edd_review_approved', true );
					$data['reviews'][ $i ]['user_id']        = $review->user_id;
					$data['reviews'][ $i ]['type']           = $type;
					if ( get_comment_meta( $review->comment_ID, 'edd_review_vote_yes', true ) || get_comment_meta( $review->comment_ID, 'edd_review_vote_no', true ) ) {
						$data['reviews'][ $i ]['votes']['yes']   = get_comment_meta( $review->comment_ID, 'edd_review_vote_yes', true );
						$data['reviews'][ $i ]['votes']['no']    = get_comment_meta( $review->comment_ID, 'edd_review_vote_no', true );
					} elseif ( $review->comment_parent > 0 ) {
						$data['reviews'][ $i ]['votes'] = null;
					} else {
						$data['reviews'][ $i ]['votes']['yes']   = '0';
						$data['reviews'][ $i ]['votes']['no']    = '0';
					}
					$i++;
				}
			}
		} // end if

		// Allow extensions to add to the data outpt
		$data = apply_filters( 'edd_reviews_api_output_data', $data );

		add_action( 'pre_get_comments',   array( $this, 'hide_reviews' ) );
		add_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds' ), 10, 2 );

		return $data;
	}

	/**
	 * Loads the Updater
	 *
	 * Instantiates the Software Licensing Plugin Updater and passes the plugin
	 * data to the class.
	 *
	 * @since 1.0
	 * @access public
	 * @return void
	 */
	public function updater() {
		if ( class_exists( 'EDD_License' ) ) {
			$license = new EDD_License( $this->file, 'Reviews', $this->version, 'Sunny Ratilal', 'edd_reviews_licensing_license_key' );
		}
	}

	/**
	 * Loads the upgrades
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function upgrade() {
		if ( class_exists( 'EDD_Reviews_Upgrades' ) ) {
			new EDD_Reviews_Upgrades();
		}
	}

	/**
	 * Review Form Arguments
	 *
	 * These strings can be changed by using WordPress filters
	 *
	 * @since 2.0
	 * @access public
	 * @param  $key Array key to find string
	 * @return string || bool
	 */
	public function review_form_args( $key ) {
		$user = wp_get_current_user();

		$post_id = get_the_ID();

		$args = apply_filters( 'edd_reviews_strings', array(
			'name_form'         => 'edd-reviews-form',
			'id_form'           => 'edd-reviews-form',
			'id_submit'         => 'edd-reviews-submit',
			'class_submit'      => 'edd-reviews-submit',
			'title_review'      => __( 'Write a Review', 'edd-reviews' ),
			'label_submit'      => __( 'Submit Review', 'edd-reviews' ),
			'logged_in_as'      => is_user_logged_in() ? '<p class="logged-in-as">' .
														 sprintf( __( 'Logged in as <a href="%1$s">%2$s</a>. <a href="%3$s" title="%4$s">%5$s</a>', 'edd-reviews' ), get_edit_user_link(), $user->display_name, wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ) ) ), esc_attr( __( 'Log out of this account', 'edd-reviews' ) ), __( 'Log out?', 'edd-reviews' ) ) .
														 '</p>' : '',
			'guest_form_fields' => ! is_user_logged_in() && $this->is_guest_reviews_enabled() ?  $this->render_guest_form_fields() : '',
		) );

		if ( array_key_exists( $key, $args ) ) {
			return $args[ $key ];
		} else {
			return false;
		}
	}

	/**
	 * Render the form fields for guest reviews.
	 *
	 * @since 2.1
	 * @access private
	 * @return string
	 */
	private function render_guest_form_fields() {
		ob_start();
		?>
		<p class="edd-reviews-review-form-reviewer-name">
			<label for="edd-reviews-reviewer-name"><?php _e( 'Name', 'edd-reviews' ) ?> <span class="required">*</span></label>
			<input type="text" id="edd-reviews-reviewer-name" class="edd-reviews-reviewer-name" name="edd-reviews-reviewer-name" value="" size="30" aria-required="true" required="required" />
		</p><!-- /.edd-reviews-review-form-review-title -->

		<p class="edd-reviews-review-form-reviewer-email">
			<label for="edd-reviews-reviewer-email"><?php _e( 'Email', 'edd-reviews' ) ?> <span class="required">*</span></label>
			<input type="email" id="edd-reviews-reviewer-email" class="edd-reviews-reviewer-email" name="edd-reviews-reviewer-email" value="" size="30" aria-required="true" required="required" />
		</p><!-- /.edd-reviews-review-form-review-title -->

		<p class="edd-reviews-review-form-reviewer-url">
			<label for="edd-reviews-reviewer-url"><?php _e( 'Website', 'edd-reviews' ) ?></label>
			<input type="url" id="edd-reviews-reviewer-url" class="edd-reviews-reviewer-url" name="edd-reviews-reviewer-url" value="" size="30" aria-required="true" />
		</p><!-- /.edd-reviews-review-form-review-title -->
		<?php
		$output = ob_get_clean();
		return $output;
	}

	/**
	 * Vendor Feedback Form Arguments
	 *
	 * These strings can be changed by using WordPress filters
	 *
	 * @since 2.0
	 * @access public
	 * @param  $key Array key to find string
	 * @return string || bool
	 */
	public function vendor_feedback_form_args( $key ) {
		$args = array(
			'name_form'    => 'edd-reviews-vendor-feedback-form',
			'id_form'      => 'edd-reviews-vendor-feedback-form',
			'id_submit'    => 'edd-reviews-submit',
			'class_submit' => 'edd-reviews-submit',
			'label_submit' => __( 'Submit Feedback', 'edd-reviews' )
		);

		if ( array_key_exists( $key, $args ) ) {
			return $args[ $key ];
		} else {
			return false;
		}
	}

	/**
	 * Display the hidden inputs
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function review_form_after() {
		ob_start();
		?>
		<input type="hidden" name="edd-reviews-review-post-ID" value="<?php echo get_the_ID(); ?>" />
		<input type="hidden" name="edd_action" value="reviews_process_review" />
		<?php wp_comment_form_unfiltered_html_nonce(); ?>
		<input type="hidden" name="edd_reviews_nonce" value="<?php echo wp_create_nonce( 'edd_reviews_nonce' ); ?>"/>
		<?php
		$rendered = ob_get_contents();
		ob_end_clean();

		echo $rendered;
	}

	/**
	 * Display the hidden inputs after the reply form
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function reply_form_after() {
		ob_start();
		?>
		<input type="hidden" name="edd-reviews-review-post-ID" value="<?php echo get_the_ID(); ?>" />
		<input type="hidden" name="edd_action" value="reviews_process_reply" />
		<input type='hidden' name="comment_post_ID" value="<?php echo get_the_ID(); ?>" id="comment_post_ID" />
		<input type='hidden' name="comment_parent" id="comment_parent" value="0" />
		<?php wp_comment_form_unfiltered_html_nonce(); ?>
		<input type="hidden" name="edd_reviews_reply_nonce" value="<?php echo wp_create_nonce( 'edd_reviews_reply_nonce' ); ?>"/>
		<?php
		$rendered = ob_get_contents();
		ob_end_clean();

		echo $rendered;
	}

	/**
	 * Returns the path to the Reviews templates directory
	 *
	 * @since 2.0
	 * @return string
	 */
	function get_templates_dir() {
		return $this->plugin_path . 'templates';
	}

	/**
	 * Template Loader
	 *
	 * @since 2.0
	 * @access public
	 * @param  string $template Template file to load
	 * @return void
	 */
	public function add_template_path( $file_paths ) {
		$file_paths[101] = $this->get_templates_dir();
		return $file_paths;
	}

	/**
	 * Load and render the frontend (reviews and form)
	 *
	 * @since 2.0
	 * @access public
	 * @param string $content
	 * @return void
	 */
	public function load_frontend( $content ) {
		global $post;

		if ( $post && $post->post_type == 'download' && is_singular( 'download' ) && is_main_query() && ! post_password_required() ) {
			ob_start();
			edd_get_template_part( 'reviews' );
			if ( get_option( 'thread_comments' ) ) {
				edd_get_template_part( 'reviews-reply' );
			}
			$content .= ob_get_contents();
			ob_end_clean();
		}

		return $content;
	}

	/**
	 * Query Reviews for Display
	 *
	 * @since 2.0
	 * @access public
	 */
	public function query_reviews() {
		global $post;

		remove_action( 'pre_get_comments',   array( $this, 'hide_reviews' ) );
		remove_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
		remove_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds' ), 10, 2 );

		$reviews = get_comments( array(
			'post_id'    => $post->ID,
			'type'       => 'edd_review',
			'meta_query' => array(
				'relation' => 'AND',
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
				)
			)
		) );

		add_action( 'pre_get_comments',   array( $this, 'hide_reviews' ) );
		add_filter( 'comments_clauses',   array( $this, 'hide_reviews_from_comment_feeds_compat' ), 10, 2 );
		add_filter( 'comment_feed_where', array( $this, 'hide_reviews_from_comment_feeds' ), 10, 2 );

		return $reviews;
	}

	/**
	 * Format reviews and render them
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function render_reviews() {
		if ( $this->is_review_status( 'disabled' ) ) {
			return;
		}

		$reviews = $this->query_reviews();

		$num_reviews = count( $reviews );

		$pages = ceil( $num_reviews / get_option( 'comments_per_page' ) );
		$cpage = get_query_var( 'cpage' ) ? get_query_var( 'cpage' ) : 1;

		if ( ! empty( $num_reviews ) ) {
			?>
			<div class="edd-review-list">
				<?php
				echo wp_list_comments( apply_filters( 'edd_reviews_render_reviews_args', array( 'walker' => new Walker_EDD_Review() ) ), $reviews );

				if ( $pages > 1 && get_option( 'page_comments' ) ) {
					?>
					<nav class="edd-review-navigation" role="navigation">
						<?php
						echo paginate_comments_links( array( 'base' => add_query_arg( 'cpage', '%#%' ), 'total' => $pages, 'current' => $cpage, 'echo' => true, 'add_fragment' => '#edd-reviews' ) );
						?>
					</nav>
					<?php
				}
				?>
			</div>
			<?php
		}
	}

	/**
	 * Render Star Rating
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function render_star_rating( $rating ) {
		$output = str_repeat( '<span class="dashicons dashicons-star-filled"></span>', absint( $rating ) );
		$output .= str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
		echo apply_filters( 'edd_reviews_star_rating', $output );
	}

	/**
	 * Process and insert review into the database
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function process_review() {
		if ( ! isset( $_POST['edd_reviews_nonce'] ) && ! wp_verify_nonce( $_POST['edd_reviews_nonce'], 'edd_reviews_nonce' ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		if ( ! is_user_logged_in() && $this->is_guest_reviews_enabled() && ( ! isset( $_POST['edd-reviews-reviewer-name'] ) || ! isset( $_POST['edd-reviews-reviewer-email'] ) ) || ( isset( $_POST['edd-reviews-reviewer-name'] ) && empty( $_POST['edd-reviews-reviewer-name'] ) ) || ( isset( $_POST['edd-reviews-reviewer-email'] ) && empty( $_POST['edd-reviews-reviewer-email'] ) ) ) {
			wp_die( __( 'ERROR: please fill the required fields (name, email).', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
        }

		$comment_post_ID = isset( $_POST['edd-reviews-review-post-ID'] ) ? (int) $_POST['edd-reviews-review-post-ID'] : 0;
		$post = get_post( $comment_post_ID );

		$user = wp_get_current_user();

		if ( edd_get_option( 'edd_reviews_disable_own_reviews', false ) ) {

			if ( $user->exists() ) {
				if ( $post->post_author == $user->ID ) {
					wp_die( sprintf( __( 'Posting reviews for your own %s is not allowed.', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
				}
			}
		}

		$delay = absint( edd_get_option( 'edd_reviews_delay_posting', 0 ) );

		if ( 0 !== $delay ) {
			$users_purchases = edd_get_users_purchases( $user->ID );

			$payment = null;

			if ( $users_purchases ) {
				foreach ( $users_purchases as $purchase ) {
					$payment         = new EDD_Payment( $purchase->ID );
					$purchased_files = $payment->cart_details;

					if ( is_array( $purchased_files ) ) {
						foreach ( $purchased_files as $download ) {
							if ( $download['id'] == $comment_post_ID ) {
								break 2;
							}
						}
					}
				}
			}

			if ( $payment instanceof EDD_Payment ) {
				$diff = round( abs( strtotime( current_time( 'timestamp' ) - strtotime( $payment->date ) ) ) / 86400 );

				if ( $diff <= $delay ) {
					wp_die( sprintf( __( 'You must wait at least %d days before posting a review.', 'edd-reviews' ), $delay ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
				}
			}
		}

		if ( empty( $post->comment_status ) ) {
			do_action( 'comment_id_not_found', $comment_post_ID );
			exit;
		}

		$status     = get_post_status( $post );
		$status_obj = get_post_status_object( $status );

		if ( 'trash' == $status ) {
			do_action( 'comment_on_trash', $comment_post_ID );
			exit;
		} elseif ( ! $status_obj->public && ! $status_obj->private ) {
			do_action( 'comment_on_draft', $comment_post_ID );
			exit;
		} elseif ( post_password_required( $comment_post_ID ) ) {
			do_action( 'comment_on_password_protected', $comment_post_ID );
			exit;
		} else {
			do_action( 'pre_comment_on_post', $comment_post_ID );
		}

		$review_content = ( isset( $_POST['edd-reviews-review'] ) ) ? trim( $_POST['edd-reviews-review'] ) : null;
		$rating         = ( isset( $_POST['edd-reviews-review-rating'] ) ) ?  trim( $_POST['edd-reviews-review-rating'] ) : null;
		$rating         = wp_filter_nohtml_kses( $rating );
		$review_title   = ( isset( $_POST['edd-reviews-review-title'] ) ) ?  trim( $_POST['edd-reviews-review-title'] ) : null;
		$review_title   = sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_title ) ) );

		$user = wp_get_current_user();
		if ( $user->exists() ) {
			if ( empty( $user->display_name ) ) {
				$user->display_name = $user->user_login;
			}

			$review_author       = wp_slash( $user->display_name );
			$review_author_email = wp_slash( $user->user_email );
			$review_author_url   = wp_slash( $user->user_url );

			if ( current_user_can( 'unfiltered_html' ) ) {
				if ( ! isset( $_POST['_wp_unfiltered_html_comment'] ) || ! wp_verify_nonce( $_POST['_wp_unfiltered_html_comment'], 'unfiltered-html-comment_' . $comment_post_ID ) ) {
					kses_remove_filters();
					kses_init_filters();
				}
			}
		} else {
			if ( $this->is_guest_reviews_enabled() ) {
				$review_author       = isset( $_POST['edd-reviews-reviewer-name'] )  ? trim( $_POST['edd-reviews-reviewer-name']  ) : null;
				$review_author_email = isset( $_POST['edd-reviews-reviewer-email'] ) ? trim( $_POST['edd-reviews-reviewer-email'] ) : null;
				$review_author_url   = isset( $_POST['edd-reviews-reviewer-url'] )   ? trim( $_POST['edd-reviews-reviewer-url']   ) : null;
				$review_author       = wp_slash( sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_author       ) ) ) );
				$review_author_email = wp_slash( sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_author_email ) ) ) );
				$review_author_url   = wp_slash( sanitize_text_field( wp_filter_nohtml_kses( esc_html( $review_author_url   ) ) ) );
			} else if ( get_option( 'comment_registration' ) || 'private' == $status ) {
				wp_die( __( 'Sorry, you must be logged in to post a review.', 'edd-reviews' ), 403 );
			}
		}

		$comment_type = 'edd_review';

		if ( '' == $review_content ) {
			wp_die( __( '<strong>ERROR</strong>: please type a review.', 'edd-reviews' ), 200 );
		}

		if ( '' == $rating ) {
			wp_die( __( '<strong>ERROR</strong>: please enter a rating.', 'edd-reviews' ), 200 );
		}

		if ( '' == $review_title ) {
			wp_die( __( '<strong>ERROR</strong>: please enter a review title.', 'edd-reviews' ), 200 );
		}

		$minimum_word_count = edd_get_option( 'edd_reviews_minimum_word_count', false );
		$maximum_word_count = edd_get_option( 'edd_reviews_maximum_word_count', false );

		if ( $minimum_word_count && ! empty( $minimum_word_count ) && str_word_count( $review_content ) < $minimum_word_count ) {
			wp_die( __( '<strong>ERROR</strong>: please ensure your review has the minimum word count.', 'edd-reviews' ), 200 );
		}

		if ( $maximum_word_count && ! empty( $maximum_word_count ) && str_word_count( $review_content ) > $maximum_word_count ) {
			wp_die( __( '<strong>ERROR</strong>: your review exceeds the maximum word count.', 'edd-reviews' ), 200 );
		}

		$comment_author_ip = $_SERVER['REMOTE_ADDR'];
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $comment_author_ip );

		$args = apply_filters( 'edd_reviews_insert_review_args', array(
			'comment_post_ID'      => $comment_post_ID,
			'comment_author'       => $review_author,
			'comment_author_email' => $review_author_email,
			'comment_author_url'   => $review_author_url,
			'comment_content'      => $review_content,
			'comment_type'         => $comment_type,
			'comment_parent'       => '',
			'comment_author_IP'    => $comment_author_ip,
			'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : '',
			'user_id'              => $user->ID,
			'comment_date'         => current_time( 'mysql' ),
			'comment_date_gmt'     => current_time( 'mysql', 1 ),
			'comment_approved'     => 1
		) );

		$comment_allowed = wp_allow_comment( $args );

		$args = apply_filters( 'preprocess_comment', $args );

		$review_id = wp_insert_comment( wp_filter_comment( $args ) );

		if ( $comment_allowed == 1 ) {
			$this->create_reviewer_discount( $review_id, $args );
		}

		add_comment_meta( $review_id, 'edd_rating', $rating );
		add_comment_meta( $review_id, 'edd_review_title', $review_title );
		add_comment_meta( $review_id, 'edd_review_approved', $comment_allowed );

		// Add review metadata to the $args so it can be passed to the notification email
		$args['id']              = $review_id;
		$args['rating' ]         = $rating;
		$args['review_title']    = $review_title;
		$args['review_approved'] = $comment_allowed;

		if ( ! edd_get_option( 'edd_reviews_settings_emails_disable_notifications', false ) ) {
			if ( ( ! edd_get_option( 'edd_reviews_settings_emails_toggle', false ) && $comment_allowed !== 1 ) || edd_get_option( 'edd_reviews_settings_emails_toggle', false ) ) {
				$this->email_admin_notification( $review_id, $args );
			}
		}

		update_post_meta( $comment_post_ID, 'edd_reviews_average_rating', $this->average_rating( false, $comment_post_ID ) );

		unset($_POST);
		$redirect = add_query_arg( array( 'edd_review_submitted' => true ) );
		$redirect = $redirect . '#edd-review-' . $review_id;
		wp_redirect( esc_url( $redirect ) );
		exit;
	}

	/**
	 * Email admin notification
	 *
	 * @since 2.1
	 * @access private
	 * @return void
	 */
	private function email_admin_notification( $review_id = 0, $review_data = array() ) {
		$review_id = absint( $review_id );

		if ( empty( $review_id ) ) {
			return;
		}

		$from_name  = edd_get_option( 'from_name', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$from_email = edd_get_option( 'from_email', get_bloginfo( 'admin_email' ) );

		$subject     = edd_get_option( 'edd_reviews_settings_emails_subject', sprintf( __( '[%1$s] New Review: "%2$s"', 'edd-reviews' ), get_bloginfo( 'name' ), $review_data['review_title'] ) );
		$subject     = wp_strip_all_tags( $subject );

		$headers     = "From: " . stripslashes_deep( html_entity_decode( $from_name, ENT_COMPAT, 'UTF-8' ) ) . " <$from_email>\r\n";
		$headers    .= "Reply-To: ". $from_email . "\r\n";
		$headers    .= "Content-Type: text/html; charset=utf-8\r\n";

		$message = $this->get_review_notification_email_content( $review_id, $review_data );

		$emails = EDD()->emails;
		$emails->__set( 'from_name', $from_name );
		$emails->__set( 'from_email', $from_email );
		$emails->__set( 'headers', $headers );
		$emails->__set( 'heading', __( 'New Review', 'edd-reviews' ) );

		$emails->send( edd_get_admin_notice_emails(), $subject, $message );
	}

	/**
	 * Default review notification email
	 *
	 * @since 2.1
	 * @access private
	 * @return string Review notification email
	 */
	private function get_review_notification_email() {
		$default_email_body = sprintf( __( 'A new review on the %s {download} is waiting for your approval', 'edd-reviews' ), strtolower( edd_get_label_singular() ) ) . "\n";
		$default_email_body .= '{direct_link}' . "\n\n";
		$default_email_body .= '<strong>' . __( 'Author:', 'edd-reviews' ) . '</strong>' . ' {author}' . "\n";
		$default_email_body .= '<strong>' . __( 'Email:', 'edd-reviews' ) . '</strong>' . ' {email}' . "\n";
		$default_email_body .= '<strong>' . __( 'URL:', 'edd-reviews' ) . '</strong>' . ' {url}' . "\n";
		$default_email_body .= '<strong>' . __( 'Rating:', 'edd-reviews' ) . '</strong>' . ' {rating}' . "\n";
		$default_email_body .= '<strong>' . __( 'Review Title:', 'edd-reviews' ) . '</strong>' . ' {title}' . "\n";
		$default_email_body .= '<strong>' . __( 'Review:', 'edd-reviews' ) . '</strong>' . "\n" . '{review}' . "\n\n";

		$message = edd_get_option( 'edd_reviews_settings_emails_notification', false );
		$message = $message ? stripslashes( $message ) : $default_email_body;

		return $message;
	}

	/**
	 * Review notification template body.
	 *
	 * @since 2.1
	 * @access private
	 *
	 * @param int   $review_id   Review ID.
	 * @param array $review_data Review Data.
	 * @return string $email_body Body of the email.
	 */
	private function get_review_notification_email_content( $review_id = 0, $review_data = array() ) {
		$email_body = $this->email_tags->do_review_tags( $this->get_review_notification_email(), $review_id, $review_data );
		$email_body = apply_filters( 'edd_reviews_review_notification_template_wpautop', true ) ? wpautop( $email_body ) : $email_body;

		return apply_filters( 'edd_reviews_review_notification', $email_body, $review_id, $review_data );
	}

	/**
	 * Reviewer Discount Email.
	 *
	 * @since  2.1
	 * @access private
	 * @return string $message Reviewer discount email.
	 */
	private function get_reviewer_discount_email() {
		$default_email_body = __( "Thank you for reviewing {download}. As a gesture of our appreciation, we'd like to give you a discount code to use on a future purchase.", 'edd-reviews' ) . "\n\n";
		$default_email_body .= '<strong>' . __( 'Discount code:', 'edd-reviews' ) . '</strong>' . ' {reviewer_discount_code}' . "\n";

		$message = edd_get_option( 'edd_reviews_reviewer_discount_email', false );
		$message = $message ? stripslashes( $message ) : $default_email_body;

		return $message;
	}

	/**
	 * Reviewer Discount Email Contents.
	 *
	 * @since  2.1
	 * @access private
	 *
	 * @param int   $review_id   Review ID.
	 * @param array $review_data Review Data.
	 * @return string $message Reviewer discount email.
	 */
	private function get_reviewer_discount_email_contents( $review_id = 0, $review_data = array() ) {
		$email_body = $this->email_tags->do_review_tags( $this->get_reviewer_discount_email(), $review_id, $review_data );
		$email_body = apply_filters( 'edd_reviews_reviewer_discount_template_wpautop', true ) ? wpautop( $email_body ) : $email_body;

		return apply_filters( 'edd_reviews_reviewer_discount', $email_body, $review_id, $review_data );
	}

	/**
	 * Process and insert reply into the database
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function process_reply() {
		if ( ! isset( $_POST['edd_reviews_reply_nonce'] ) && ! wp_verify_nonce( $_POST['edd_reviews_reply_nonce'], 'edd_reviews_reply_nonce' ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		$comment_post_ID = isset( $_POST['edd-reviews-review-post-ID'] ) ? (int) $_POST['edd-reviews-review-post-ID'] : 0;
		$comment_parent = isset( $_POST['comment_parent'] ) ? (int) $_POST['comment_parent'] : '';
		$post = get_post( $comment_post_ID );

		if ( empty( $post->comment_status ) ) {
			do_action( 'comment_id_not_found', $comment_post_ID );
			exit;
		}

		$status     = get_post_status( $post );
		$status_obj = get_post_status_object( $status );

		if ( 'trash' == $status ) {
			do_action( 'comment_on_trash', $comment_post_ID );
			exit;
		} elseif ( ! $status_obj->public && ! $status_obj->private ) {
			do_action( 'comment_on_draft', $comment_post_ID );
			exit;
		} elseif ( post_password_required( $comment_post_ID ) ) {
			do_action( 'comment_on_password_protected', $comment_post_ID );
			exit;
		} else {
			do_action( 'pre_comment_on_post', $comment_post_ID );
		}

		$reply_content = ( isset( $_POST['edd-reviews-reply'] ) ) ? trim( $_POST['edd-reviews-reply'] ) : null;

		$user = wp_get_current_user();
		if ( $user->exists() ) {
			if ( empty( $user->display_name ) ) {
				$user->display_name = $user->user_login;
			}

			$review_author       = wp_slash( $user->display_name );
			$review_author_email = wp_slash( $user->user_email );
			$review_author_url   = wp_slash( $user->user_url );

			if ( current_user_can( 'unfiltered_html' ) ) {
				if ( ! isset( $_POST['_wp_unfiltered_html_comment'] ) || ! wp_verify_nonce( $_POST['_wp_unfiltered_html_comment'], 'unfiltered-html-comment_' . $comment_post_ID ) ) {
					kses_remove_filters();
					kses_init_filters();
				}
			}
		} else {
			if ( get_option( 'comment_registration' ) || 'private' == $status ) {
				wp_die( __( 'Sorry, you must be logged in to post a review.', 'edd-reviews' ), 403 );
			}
		}

		$comment_type = 'edd_review';

		if ( '' == $reply_content ) {
			wp_die( __( '<strong>ERROR</strong>: please type a reply.', 'edd-reviews' ), 200 );
		}

		$comment_author_ip = $_SERVER['REMOTE_ADDR'];
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $comment_author_ip );

		$args = apply_filters( 'edd_reviews_insert_reply_args', array(
			'comment_post_ID'      => $comment_post_ID,
			'comment_author'       => $review_author,
			'comment_author_email' => $review_author_email,
			'comment_author_url'   => $review_author_url,
			'comment_content'      => $reply_content,
			'comment_type'         =>  $comment_type,
			'comment_parent'       => $comment_parent,
			'comment_author_IP'    => $comment_author_ip,
			'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : '',
			'user_id'              => $user->ID,
			'comment_date'         => current_time( 'mysql' ),
			'comment_date_gmt'     => current_time( 'mysql', 1 ),
			'comment_approved'     => 1
		) );

		$comment_allowed = wp_allow_comment( $args );

		$args = apply_filters( 'preprocess_comment', $args );

		$review_id = wp_insert_comment( wp_filter_comment( $args ) );

		add_comment_meta( $review_id, 'edd_review_reply', 1 );
		add_comment_meta( $review_id, 'edd_review_approved', $comment_allowed );
	}

	/**
	 * Adjust get_comment_link() for reviews
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function get_comment_link( $link, $comment ) {
		if ( 'edd_review' == $comment->comment_type ) {
			return preg_replace("/comment/", 'edd-review', $link);
		} else {
			return $link;
		}
	}

	/**
	 * Generate a link to the review form for a post.
	 *
	 * @since 2.1
	 * @access public
	 * @return string $link Link to review form.
	 */
	public function get_form_link( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			return;
		}

		$link = get_permalink( $post_id ) . '#edd-reviews';

		return $link;
	}

	/**
	 * Adjust review status
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function set_review_status() {
		global $wpdb;

		if ( ! isset( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'approve-review_' . $_GET['r'] ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 200 ) );
		}

		$review_id = trim( $_GET['r'] );
		$review = get_comment( $review_id, ARRAY_A );

		$actions = array(
			'approve_review',
			'unapprove_review',
			'spam_review',
			'unspam_review',
			'trash_review',
			'restore_review',
			'delete_review'
		);

		if ( in_array( trim( $_GET['edd_action'] ), $actions ) ) {
			$action = trim( $_GET['edd_action'] );

			if ( 'approve_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '1' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$this->create_reviewer_discount( $review_id, $review );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'approved' => '1' ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'unapprove_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '0' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true' ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'spam_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', 'spam' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'spammed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'unspam_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '1' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'unspammed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'trash_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', 'trash' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'trashed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'restore_review' == $action ) {
				update_comment_meta( $review_id, 'edd_review_approved', '1' );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'restored' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'delete_review' == $action ) {
				wp_delete_comment( $review_id, true );
				update_post_meta( $review['comment_post_ID'], 'edd_reviews_average_rating', $this->average_rating( false, $review['comment_post_ID'] ) );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd_status_updated' => 'true', 'deleted' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}
		}
	}

	/**
	 * Conditional to check if FES is installed
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function is_fes_installed() {
		return class_exists( 'EDD_Front_End_Submissions' );
	}

	/**
	 * Display average rating with [downloads] shortcode
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function display_average_rating() {
		$rating = $this->average_rating( false );

		if ( $rating > 0 ) {
			echo '<div class="edd-reviews-rating">';
			echo '<span class="edd-reviews-average-rating-label">' . __( 'Average rating:', 'edd-reviews' ) . '</span> ' . str_repeat( '<span class="dashicons dashicons-star-filled"></span>', absint( $rating ) );
			echo '</div>';
		}
	}

	/**
	 * Render Star Ratings HTML
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function render_star_rating_html() {
		ob_start();
		?>
		<span class="edd-reviews-star-rating-container">
			<span class="edd-reviews-stars-filled">
				<?php for ( $i = 1; $i <= 5; $i++ ) { ?>
				<span class="dashicons dashicons-star-filled edd-reviews-star-rating edd-reviews-star-rating-<?php echo $i; ?>" data-rating="<?php echo $i; ?>"></span>
				<?php } ?>
			</span>
			<span class="edd-reviews-stars-empty">
				<?php for ( $i = 1; $i <= 5; $i++ ) { ?>
				<span class="dashicons dashicons-star-empty edd-reviews-star-rating edd-reviews-star-rating-<?php echo $i; ?>" data-rating="<?php echo $i; ?>"></span>
				<?php } ?>
			</span>
			<input type="hidden" name="edd-reviews-review-rating" id="edd-reviews-star-rating" value="" />
		</span>

		<?php
		$rendered = ob_get_contents();
		ob_end_clean();

		$rendered = apply_filters( 'edd_reviews_star_rating_html', $rendered );
		echo $rendered;
	}

	/**
	 * Add "Insert Review" button to the WordPress Edit page
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function media_buttons() {
		global $pagenow, $typenow, $wp_version;

		$button = '';

		if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) {
			$icon = '<span class="wp-media-buttons-icon dashicons dashicons-star-filled"></span>';
			$button = '<a href="#TB_inline?width=640&inlineId=edd-choose-review" class="thickbox button edd-reviews-thickbox" title="' . __( 'Insert Review', 'edd-reviews' ) . '">' . $icon . __( 'Insert Review', 'edd-reviews' ) . '</a>';
		}

		echo $button;
	}

	/**
	 * Admin Footer for Thickbox
	 *
	 * @return [type] [description]
	 */
	public function admin_footer_for_thickbox() {
		global $pagenow, $typenow, $wpdb;

		$reviews = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->commentmeta} INNER JOIN {$wpdb->comments} ON {$wpdb->commentmeta}.comment_id = {$wpdb->comments}.comment_ID WHERE {$wpdb->commentmeta}.meta_key = %s", 'edd_review_title' ) );

		if ( in_array( $pagenow, array( 'post.php', 'page.php', 'post-new.php', 'post-edit.php' ) ) ) { ?>
		<script type="text/javascript">
		function insertReview() {
			var id = jQuery('#edd_reviews_shortcode_dialog').val();
			if ('' === id) {
				alert('<?php _e( "You must choose a review", "edd-reviews" ); ?>');
				return;
			}
			window.send_to_editor('[review id="' + id + '"]');
		}
		</script>

		<div id="edd-choose-review" style="display: none;">
			<div class="wrap" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
				<?php if ( $reviews ) { ?>
					<h2> <?php echo __( 'Select a Review to Embed', 'edd-reviews' ); ?> </h2>
					<p><select id="edd_reviews_shortcode_dialog" name="edd_reviews_shortcode_dialog">

					<?php
					foreach ( $reviews as $review ) {
						echo '<option value="' . $review->comment_id . '">' . esc_html( $review->meta_value ) . ' (' .  get_the_title( $review->comment_post_ID ) . ')</option>';
					}
					?>

					</select></p>
					<p class="submit">
						<input type="button" id="edd-insert-download" class="button-primary" value="<?php _e( 'Insert Review', 'edd-reviews' ); ?>" onclick="insertReview();" />
						<a id="edd-cancel-download-insert" class="button-secondary" onclick="tb_remove();" title="<?php _e( 'Cancel', 'edd-reviews' ); ?>"><?php _e( 'Cancel', 'edd-reviews' ); ?></a>
					</p>
				<?php } else { ?>
					<h2><?php _e( 'No Reviews Have Been Created Yet', 'edd-reviews' ); ?></h2>
				<?php } ?>
			</div>
		</div>
	<?php
		}
	}

	/**
	 * Create Reviewer Discount
	 *
	 * @since 2.0
	 * @access public
	 * @param  $args Review arguments
	 * @return void
	 */
	public function create_reviewer_discount( $review_id, $args ) {
		global $current_user;

		if ( edd_get_option( 'edd_reviews_reviewer_discount', false ) && edd_get_option( 'edd_reviews_reviewer_discount_amount', false ) ) {
			$users = get_post_meta( absint( $_POST['edd-reviews-review-post-ID'] ), 'edd_reviews_discount_users', true );

			if ( empty( $users ) || ! is_array( $users ) ) {
				$users = array();
			}

			if ( ! in_array( $args['comment_author_email'], (array) $users ) ) {
				// Store a discount code in the databse
				$code = md5( $args['comment_author'] . $args['comment_date'] );
				$discount_code = edd_store_discount( array(
					'name'     => 'Reviewer Discount - ' . $args['comment_author'],
					'code'     => $code,
					'use_once' => true,
					'max'      => 1,
					'type'     => 'percent',
					'amount'   => absint( edd_get_option( 'edd_reviews_reviewer_discount_amount' ) ),
					'start'    => date( 'm/d/Y H:i:s', time() )
				) );

				// Send an email out to the user letting them know about the discount
				$subject     = edd_get_option( 'edd_reviews_reviewer_discount_subject', __( 'Reviewer Discount Code', 'edd-reviews' ) );
				$subject     = wp_strip_all_tags( $subject );

				$args['discount'] = $code;

				$message = $this->get_reviewer_discount_email_contents( $review_id, $args );

				$emails = EDD()->emails;
				$emails->__set( 'heading', $subject );
				$emails->send( $args['comment_author_email'], apply_filters( 'edd_reviews_reviewer_discount_subject', $subject ), apply_filters( 'edd_reviews_reviewer_discount_message', $message ) );

				$users[] = $args['comment_author_email'];
				update_post_meta( absint( $_POST['edd-reviews-review-post-ID'] ), 'edd_reviews_discount_users', $users );
			}
		}
	}

	/**
	 * Reviews Reply JavaScript
	 *
	 * Load the WordPress comment_reply JavaScript
	 *
	 * @since 2.0
	 * @access public
	 */
	public function reviews_reply_script() {
		global $post;

		if ( is_singular() && $post->post_type == 'download' && get_option('thread_comments') ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	/**
	 * Has the user purchased this download
	 *
	 * Redefines the edd_has_user_purchased() as it causes a memory leak
	 *
	 * @since 2.0
	 * @access public
	 * @global $wpdb
	 * @param int $user_id - the ID of the user to check
	 * @param array $download_id - The download ID to check if it's in the user's purchases
	 * @return boolean - true if has purchased, false otherwise
	 */
	public function has_user_purchased( $user_id, $download_id ) {
		global $wpdb;

		$payment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT {$wpdb->posts}.ID FROM {$wpdb->posts}
				INNER JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
				WHERE post_type = 'edd_payment' AND post_status = 'publish' AND {$wpdb->postmeta}.meta_key = '_edd_payment_user_id' AND {$wpdb->postmeta}.meta_value = %d
				",
				$user_id
			)
		);

		if ( is_array( $payment_ids ) && ! empty( $payment_ids ) ) {
			array_values( $payment_ids );
			$payment_ids = esc_sql( $payment_ids );
			$payment_ids = implode(', ', $payment_ids);

			$meta = $wpdb->get_results(
				"
				SELECT {$wpdb->postmeta}.meta_value FROM {$wpdb->posts}
				INNER JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
				WHERE post_type = 'edd_payment' AND post_status = 'publish' AND {$wpdb->postmeta}.meta_key = '_edd_payment_meta' AND {$wpdb->posts}.ID IN( {$payment_ids} )
				", ARRAY_A
			);

			if ( $meta ) {
				foreach ( $meta as $item ) {

					preg_match( '/[oO]\s*:\s*\d+\s*:\s*"\s*(?!(?i)(stdClass))/', $item['meta_value'], $matches );
					if ( ! empty( $matches ) ) {
						continue;
					}

					if ( is_serialized( $item['meta_value'] ) ) {
						$meta_value = @unserialize( $item['meta_value'], array( 'allowed_classes' => false ) );
					}

					$cart_details = $meta_value['cart_details'];
					if ( is_array( $cart_details )  && ! empty( $cart_details ) ) {
						$download_ids = wp_list_pluck( $cart_details, 'id' );
						if ( in_array( $download_id, $download_ids ) ) {
							return true;
						} else {
							return false;
						}
					} else {
						return false;
					}
				}
			} else {
				return false;
			}
		}
	}

	/**
	 * Review Reply Link
	 *
	 * Generates a link for replying to reviews. Extends the WordPress Core
	 * comment_reply_link() function because it bails if comments aren't open
	 *
	 * @since 2.0
	 * @access public
	 * @global $comment
	 * @global $post
	 * @return void
	 */
	public function reviews_reply_link( $args = array() ) {
		global $comment, $post;

		$defaults = array(
			'add_below'     => 'edd-review',
			'respond_id'    => 'edd-reviews-reply',
			'login_text'    => __( 'Login to reply', 'edd-reviews' ),
			'reply_text'    => __( 'Leave a reply', 'edd-reviews' ),
			'reply_to_text' => __( 'Reply to %s', 'edd-reviews' ),
			'before'        => '<div class="reply">',
			'after'         => '</div>',
			'depth'         => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( 0 === $args['depth'] || $args['max_depth'] <= $args['depth'] ) {
			return;
		}

		if ( ! is_user_logged_in() && edd_get_option( 'edd_reviews_enable_guest_reviews', false ) ) {
			return;
		}

		$review = get_comment( $comment );

		if ( empty( $post ) ) {
			$post = $review->comment_post_ID;
		}

		$post_object = get_post( $post );

		$args = apply_filters( 'comment_reply_link_args', $args, $review, $post_object );

		$link = null;

		if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
			$link = sprintf( '<a rel="nofollow" class="comment-reply-login" href="%s">%s</a>',
				esc_url( wp_login_url( get_permalink() ) ),
				$args['login_text']
			);
		} elseif ( is_user_logged_in() ) {
			$onclick = sprintf( 'return addComment.moveForm( "%1$s-%2$s", "%2$s", "%3$s", "%4$s" )',
				$args['add_below'], $review->comment_ID, $args['respond_id'], $post_object->ID
			);

			$link = sprintf( "<a rel='nofollow' class='comment-reply-link' href='%s' onclick='%s' aria-label='%s'>%s</a>",
				esc_url( add_query_arg( 'replytocom', $review->comment_ID, get_permalink( $post_object->ID ) ) ) . "#" . $args['respond_id'],
				$onclick,
				esc_attr( sprintf( $args['reply_to_text'], $review->comment_author ) ),
				$args['reply_text']
			);
		}

		/**
		 * Filters the comment reply link.
		 *
		 * @since 2.7.0
		 *
		 * @param string  $link    The HTML markup for the comment reply link.
		 * @param array   $args    An array of arguments overriding the defaults.
		 * @param object  $comment The object of the comment being replied.
		 * @param WP_Post $post    The WP_Post object.
		 */
		echo apply_filters( 'comment_reply_link', $args['before'] . $link . $args['after'], $args, $comment, $post );
	}

	/**
	 * Filter the download title in the purchase receipt to include reviews link.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param string $title      Download title.
	 * @param string $item       Cart item.
	 * @param int    $price_id   Cart item price ID.
	 * @param int    $payment_id Payment ID.
	 * @return string $title Download title.
	 */
	public function email_receipt_download_title( $title, $item, $price_id, $payment_id ) {
		$status = get_post_meta( $item['id'], 'edd_reviews_status', true );
		if ( 'disabled' == $status || 'closed' == $status ) {
			return $title;
		}
		if ( edd_get_option( 'edd_reviews_display_review_link_in_purchase_receipt', false ) ) {
			$title .= "&nbsp;&ndash;&nbsp;" . '<a href="' . get_permalink( $item['id'] ) . '#edd-reviews">' . __( 'Leave a Review', 'edd-reviews' ) . '</a>';
		}
		return $title;
	}

	/**
	 * Filter the download title in the purchase receipt to include reviews link.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $query WP_Query arguments.
	 * @param array $atts  Shortcodes attributes.
	 */
	public function downloads_query( $query, $atts ) {
		if ( 'rating' == $atts['orderby'] ) {
			$query['meta_key'] = 'edd_reviews_average_rating';
			$query['orderby']  = 'meta_value_num';
		}

		return $query;
	}

	/**
	 * Add postmeta when saving downloads.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param int     $post_id Post ID.
	 */
	public function add_postmeta( $post_id ) {
		if ( ! get_post_meta( $post_id, 'edd_reviews_average_rating', true ) ) {
			add_post_meta( $post_id, 'edd_reviews_average_rating', 0 );
		}
	}

	/**
	 * AJAX handler to update the status of a review.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function ajax_change_status() {
		if ( ! check_ajax_referer( 'edd-reviews-admin-nonce', '_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
			wp_die();
		}

		if ( ! current_user_can( 'moderate_comments' ) ) {
			return;
		}

		$review_id = absint( $_POST['review_id'] );

		$review = get_comment( $review_id );

		$old_status = get_comment_meta( $review_id, 'edd_review_approved', true );

		$action = $_POST['status'];

		if ( 'approve' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', '1' );
			$this->create_reviewer_discount( $review_id, get_comment( $review_id, ARRAY_A ) );
		}

		if ( 'unapprove' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', '0' );
		}

		if ( 'spam' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', 'spam' );
		}

		if ( 'unspam' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', '1' );
		}

		if ( 'trash' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', 'trash' );
		}

		if ( 'restore' == $action ) {
			update_comment_meta( $review_id, 'edd_review_approved', '1' );
		}

		if ( 'delete' == $action ) {
			wp_delete_comment( $review_id, true );
		}

		$data = array();

		$data['updated'] = true;
		$data['old_status'] = $old_status;
		$data['new_status'] = get_comment_meta( $review_id, 'edd_review_approved', true );

		wp_send_json( $data );
	}

	/**
	 * AJAX handler to process a reply to a review.
	 *
	 * @since 2.1
	 * @access public
	 */
	public function ajax_reply_to_review() {
		if ( ! check_ajax_referer( 'edd-reviews-admin-nonce', '_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce.' );
			wp_die();
		}

		$review_id = absint( $_POST['review_ID'] );
		$review = get_comment( $review_id );

		$reply_content = ( isset( $_POST['content'] ) ) ? trim( $_POST['content'] ) : null;

		$user = wp_get_current_user();
		if ( $user->exists() ) {
			if ( empty( $user->display_name ) ) {
				$user->display_name = $user->user_login;
			}

			$review_author       = wp_slash( $user->display_name );
			$review_author_email = wp_slash( $user->user_email );
			$review_author_url   = wp_slash( $user->user_url );

			if ( current_user_can( 'unfiltered_html' ) ) {
				if ( ! isset( $_POST['_wp_unfiltered_html_comment'] ) || ! wp_verify_nonce( $_POST['_wp_unfiltered_html_comment'], 'unfiltered-html-comment_' . $review->comment_post_ID ) ) {
					kses_remove_filters();
					kses_init_filters();
				}
			}
		}

		$comment_type = 'edd_review';

		if ( '' == $reply_content ) {
			wp_die( __( '<strong>ERROR</strong>: please type a reply.', 'edd-reviews' ), 200 );
		}

		$comment_author_ip = $_SERVER['REMOTE_ADDR'];
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $comment_author_ip );

		$args = apply_filters( 'edd_reviews_insert_reply_args', array(
			'comment_post_ID'      => $review->comment_post_ID,
			'comment_author'       => $review_author,
			'comment_author_email' => $review_author_email,
			'comment_author_url'   => $review_author_url,
			'comment_content'      => sanitize_text_field( $reply_content ),
			'comment_type'         => $comment_type,
			'comment_parent'       => $review_id,
			'comment_author_IP'    => $comment_author_ip,
			'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 254 ) : '',
			'user_id'              => $user->ID,
			'comment_date'         => current_time( 'mysql' ),
			'comment_date_gmt'     => current_time( 'mysql', 1 ),
			'comment_approved'     => 1
		) );

		$args = apply_filters( 'preprocess_comment', $args );

		$review_id = wp_insert_comment( wp_filter_comment( $args ) );

		add_comment_meta( $review_id, 'edd_review_reply', 1 );
		add_comment_meta( $review_id, 'edd_review_approved', 1 );

		$data = array();

		$review = get_comment( $review_id );

		ob_start();
		if ( isset( $_POST['metabox'] ) ) {
			$reviews_list_table = new EDD_Reviews_Download_List_Table();
		} else {
			$reviews_list_table = new EDD_Reviews_List_Table();
		}
		$reviews_list_table->single_row( $review );
		$list_item = ob_get_clean();

		$data = array(
			'what' => 'edd_review_reply',
			'id'   => $review_id,
			'data' => $list_item
		);

		$response = new WP_Ajax_Response();
		$response->add( $data );
		$response->send();
	}
}

/**
 * Loads a single instance of EDD Reviews
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_reviews = edd_reviews(); ?>
 *
 * @since 1.0
 *
 * @see EDD_Reviews::get_instance()
 *
 * @return object Returns an instance of the EDD_Reviews class
 */
function edd_reviews() {
	if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
		return null;
	}

	return EDD_Reviews::get_instance();
}

/**
 * Loads plugin after all the others have loaded and have registered their
 * hooks and filters
 */
add_action( 'plugins_loaded', 'edd_reviews', apply_filters( 'edd_reviews_action_priority', 10 ) );

endif;
