<?php
/**
 * EDD Reviews & FES Integration
 *
 * @package EDD_Reviews
 * @subpackage Integrations
 * @copyright Copyright (c) 2016, Sunny Ratilal
 * @since 2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * EDD_Reviews_FES_Integration Class
 *
 * @package EDD_Reviews
 * @since 2.0
 * @version 1.0
 * @author Sunny Ratilal
 */
class EDD_Reviews_FES_Integration {
	/**
	 * Constructor.
	 *
	 * @since 2.0
	 * @access public
	 * @uses EDD_Reviews_FES_Integration::hooks() Setup hooks and actions
	 */
	public function __construct() {
		add_shortcode( 'edd_reviews_vendor_feedback', array( 'EDD_Reviews_Vendor_Feedback', 'render' ) );
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
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function hooks() {
		add_action( 'edd_purchase_history_row_end',      array( $this, 'vendor_feedback' ), 10, 2 );
		add_action( 'edd_purchase_history_header_after', array( $this, 'vendor_feedback_header' ) );
		add_action( 'fes_custom_task_vendor-feedback',   array( $this, 'render_vendor_feedback_page' ) );
		add_action( 'edd_process_vendor_review',         array( $this, 'process_vendor_review' ) );
		add_action( 'pre_get_comments',                  array( $this, 'hide_feedback' ) );
		add_action( 'admin_menu',                        array( $this, 'admin_menu' ) );
		add_action( 'edd_update_vendor_feedback',        array( $this, 'update_vendor_feedback' ) );
		add_action( 'edd_unapprove_vendor_feedback',     array( $this, 'set_vendor_feedback_status' ) );
		add_action( 'edd_approve_vendor_feedback',       array( $this, 'set_vendor_feedback_status' ) );
		add_action( 'edd_spam_vendor_feedback',          array( $this, 'set_vendor_feedback_status' ) );
		add_action( 'edd_trash_vendor_feedback',         array( $this, 'set_vendor_feedback_status' ) );
		add_action( 'edd_delete_vendor_feedback',        array( $this, 'set_vendor_feedback_status' ) );
		add_action( 'admin_notices',                     array( $this, 'admin_notices' ) );

		add_filter( 'fes_signal_custom_task',            array( $this, 'fes_signal_custom_task' ) );
		add_filter( 'fes_vendor_dashboard_menu',         array( $this, 'vendor_dashboard_menu' ) );
		add_filter( 'comment_feed_where',                array( $this, 'hide_feedback_from_comment_feeds' ), 10, 2 );
		add_filter( 'comments_clauses',                  array( $this, 'hide_feedback_from_comment_feeds_compat' ), 10, 2 );
		add_filter( 'wp_count_comments',                 array( $this, 'wp_count_comments' ), 10, 2 );
	}

	/**
	 * Display links to Seller Feedback on Purchase Confirmation
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function vendor_feedback( $payment, $edd_receipt_args ) {

		$feedback_page = edd_get_option( 'edd_reviews_vendor_feedback_page' );

		if( empty( $feedback_page ) ) {
			return;
		}

		if ( ! edd_is_payment_complete( $payment ) ) {
			echo '<td class="edd-reviews-vendor-feedback">-</td>';
			return;
		}

		$payment_obj = new EDD_Payment( $payment );
		$is_vendor = false;
		foreach ( (array) $payment_obj->downloads as $download ) {
			$is_vendor = (bool) EDD_FES()->vendors->user_is_vendor( get_post_field( 'post_author', $download['id'] ) );
		}

		$text = edd_get_option( 'edd_reviews_vendor_feedback_table_label', __( 'Give Feedback', 'edd-reviews' ) );
		ob_start();
		?>
		<td class="edd-reviews-vendor-feedback">
			<?php if ( $is_vendor ) { ?>
			<a href="<?php echo esc_url( add_query_arg( 'payment_key', $edd_receipt_args['key'], get_permalink( $feedback_page ) ) ); ?>"><?php echo $text; ?></a>
			<?php } else { ?>
			<span>-</span>
			<?php } ?>
		</td>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;
	}

	/**
	 * Display Seller Feedback table header
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function vendor_feedback_header() {

		$feedback_page = edd_get_option( 'edd_reviews_vendor_feedback_page' );

		if( empty( $feedback_page ) ) {
			return;
		}

		$header = edd_get_option( 'edd_reviews_vendor_feedback_table_heading', sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ) );
		ob_start();
		?>
		<th class="edd-reviews-vendor-feedback">
			<?php echo $header; ?>
		</th>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;
	}

	/**
	 * Register the custom task for the FES Vendor Dashboard
	 *
	 * @since 2.0
	 * @access public
	 * @return string Query var
	 */
	public function fes_signal_custom_task( $task ) {
		if ( 'vendor-feedback' == $task ) {
			return true;
		}
	}

	/**
	 * Add a link to the Vendor Feedback page to the Vendor Dashboard menu
	 *
	 * @since 2.0
	 * @access public
	 * @param  array $menu_items
	 * @return array $menu_items
	 */
	public function vendor_dashboard_menu( $menu_items ) {
		$menu_items['edd_reviews_vendor_feedback'] = array(
			'task' => 'vendor-feedback',
			'name' => sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ),
			'icon' => apply_filters( 'edd_reviews_vendor_feedback_icon', '' ),
		);

		return $menu_items;
	}

	/**
	 * Renders the Vendor Feedback page on the FES Vendor Dashboard
	 *
	 * @since 2.0
	 * @access public
	 * @return $content Rendered page
	 */
	public function render_vendor_feedback_page() {
		ob_start();
		edd_get_template_part( 'reviews-fes-vendor-feedback' );
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;
	}

	/**
	 * Process the Vendor Review and add it to the database
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function process_vendor_review() {
		if ( ! isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'edd-review-vendor-feedback_' . $_POST['download_id'] ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 200 ) );
		}

		if ( isset( $_POST['edd-reviews-vendor-feedback-comments'] ) ) {
			$review_content = sanitize_text_field( wp_filter_nohtml_kses( esc_html( $_POST['edd-reviews-vendor-feedback-comments'] ) ) );
		} else {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please leave the vendor some comments.', 'edd-reviews' ) ), 200 );
		}

		if ( isset( $_POST['edd-reviews-item-as-described'] ) && ! is_numeric( $_POST['edd-reviews-item-as-described'] ) ) {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please tell us if the item was as described.', 'edd-reviews' ) ), 200 );
		}

		if ( isset( $_POST['edd-reviews-review-rating'] ) && ! is_numeric( $_POST['edd-reviews-review-rating'] ) ) {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please leave a star rating on your feedback.', 'edd-reviews' ) ), 200 );
		}

		$rating = wp_filter_nohtml_kses( $_POST['edd-reviews-review-rating'] );
		$item_as_described = wp_filter_nohtml_kses( $_POST['edd-reviews-item-as-described'] );

		$comment_post_ID = isset( $_POST['download_id'] ) ? (int) $_POST['download_id'] : 0;

		$comment_type = 'edd_vendor_feedback';

		$user = wp_get_current_user();

		if ( isset( $user ) && empty( $user->display_name ) ) {
			$user->display_name = $user->user_login;
		}

		$review_author       = wp_slash( $user->display_name );
		$review_author_email = wp_slash( $user->user_email );
		$review_author_url   = wp_slash( $user->user_url );

		if ( '' == $review_content ) {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please leave the vendor some comments.', 'edd-reviews' ) ), 200 );
		}

		if ( '' == $rating ) {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please leave a star rating on your feedback.', 'edd-reviews' ) ), 200 );
		}

		if ( '' == $item_as_described ) {
			wp_die( sprintf( '<strong>%s</strong> %s', __( 'ERROR:', 'edd-reviews' ), __( 'Please tell us if the item was as described.', 'edd-reviews' ) ), 200 );
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
			'comment_approved'     => 1,
		) );

		$item_as_described_text = ( $item_as_described == 1 ) ? 'Yes' : 'No';
		$comments = wp_specialchars_decode( $review_content );
		$vendor_email = get_the_author_meta( 'user_email', get_post_field( 'post_author', $comment_post_ID ) );

		$feedback_id = wp_new_comment( wp_filter_comment( $args ) );
		add_comment_meta( $feedback_id, 'edd_rating', $rating );
		add_comment_meta( $feedback_id, 'edd_item_as_described', $item_as_described );

		$args['id']                = $feedback_id;
		$args['rating' ]           = $rating;
		$args['item_as_described'] = $item_as_described_text;

		$message = $this->get_default_email();
		$message = edd_reviews()->email_tags->do_review_tags( $message, $feedback_id, $args );

		$emails = EDD()->emails;
		$emails->__set( 'heading', __( 'New Feedback', 'edd-reviews' ) );

		$emails->send( $vendor_email, wp_specialchars_decode( sprintf( __( '[%1$s] New Feedback', 'edd-reviews' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ) ), $message );
	}

	/**
	 * Exclude vendor feedback from showing in comment feeds (backwards compabitility)
	 *
	 * @since  2.0
	 * @access public
	 * @return void
	 */
	public function hide_feedback_from_comment_feeds_compat( $clauses, $wp_comment_query ) {
		global $wpdb, $wp_version;

		if ( version_compare( floatval( $wp_version ), '4.1', '<' ) ) {
			$clauses['where'] .= ' AND comment_type != "edd_vendor_feedback"';
		}

		return $clauses;
	}

	/**
	 * Exclude vendor feedback from showing in comment feeds
	 *
	 * @since  2.0
	 * @access public
	 * @return void
	 */
	public function hide_feedback_from_comment_feeds( $where, $wp_comment_query ) {
		global $wpdb;

		$where .= $wpdb->prepare( ' AND comment_type != %s', 'edd_vendor_feedback' );
		return $where;
	}

	/**
	 * Exclude vendor feedback from WP_Query and Recent Comments widget in the WordPress dashboard
	 *
	 * @since  2.0
	 * @access public
	 * @return void
	 */
	public function hide_feedback( $query ) {
		global $wp_version;

		if ( version_compare( floatval( $wp_version ), '4.1', '>=' ) ) {
			$types = isset( $query->query_vars['type__not_in'] ) ? $query->query_vars['type__not_in'] : array();

			if ( ! is_array( $types ) ) {
				$types = array( $types );
			}

			$types[] = 'edd_vendor_feedback';
			$query->query_vars['type__not_in'] = $types;
		}
	}

	/**
	 * Add admin menu item for Vendor Feedback
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		if ( method_exists( EDD_FES()->helper, 'get_vendor_constant_name' ) ) {

			if( version_compare(fes_plugin_version, '2.6', '>=' ) ) {
	
				add_submenu_page( 'fes-vendors', sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ), sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ), 'manage_shop_settings', 'fes-vendor-feedback', array( $this, 'render_vendor_feedback_admin_page' ) );

			} else {

				add_submenu_page( 'fes-about', sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ), sprintf( __( '%s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ), 'manage_shop_settings', 'fes-vendor-feedback', array( $this, 'render_vendor_feedback_admin_page' ) );
	
			}
		}
	}

	/**
	 * Render the Vendor Feedback page so that administrators can see all vendor feedback given
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function render_vendor_feedback_admin_page() {
		if ( isset( $_GET['r'] ) && isset( $_GET['edit'] ) && is_numeric( $_GET['r'] ) && 'true' == $_GET['edit'] ) {
			$feedback_id = absint( $_GET['r'] );
			$feedback = get_comment( $feedback_id, OBJECT );
			$post = get_post( $feedback->comment_post_ID );
			ob_start();
			?>
			<form name="edd-reviews-edit" method="post" id="edd-reviews-edit-form">
			<?php wp_nonce_field( 'edd-update-vendor-feedback_' . $feedback->comment_ID ); ?>
			<div class="wrap">
				<h1><?php printf( __( 'Edit %s Feedback', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ); ?></h1>

				<?php if ( isset( $_REQUEST['edd_status_updated'] ) && 'true' == $_REQUEST['edd_status_updated'] ) { ?>
					<div id="moderated" class="updated notice is-dismissible">
						<p><?php printf( __( '%s feedback updated successfully', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() ); ?></p>
					</div>
				<?php } ?>

				<div id="poststuff">
					<input type="hidden" name="edd_action" value="update_vendor_feedback" />
					<input type="hidden" name="feedback_ID" value="<?php echo $feedback->comment_ID; ?>" />
					<input type="hidden" name="feedback_post_ID" value="<?php echo $feedback->comment_post_ID; ?>" />
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
													<td><input type="text" name="review_author" size="30" value="<?php echo esc_attr( $feedback->comment_author ); ?>" id="name" /></td>
												</tr>
												<tr>
													<td class="first"><label for="email"><?php _e( 'E-mail:', 'edd-reviews' ); ?></label></td>
													<td>
														<input type="email" name="review_author_email" size="30" value="<?php echo $feedback->comment_author_email; ?>" id="email" />
													</td>
												</tr>
												<tr>
													<td class="first"><label for="review_author_url"><?php _e( 'URL:', 'edd-reviews' ); ?></label></td>
													<td>
														<input type="url" id="review_author_url" name="review_author_url" size="30" class="code" value="<?php echo esc_attr( $feedback->comment_author_url ); ?>" />
													</td>
												</tr>
												<tr>
													<td class="first"><label for="review_edd_rating"><?php _e( 'Rating:', 'edd-reviews' ); ?></label></td>
													<td>
														<?php
														$rating = get_comment_meta( $feedback->comment_ID, 'edd_rating', true );
														echo str_repeat( '<span class="dashicons dashicons-star-filled"></span>', $rating );
														echo str_repeat( '<span class="dashicons dashicons-star-empty"></span>', 5 - absint( $rating ) );
														?>
														</td>
													</tr>
												</tbody>
											</table>
										</fieldset>
									</div><!-- /.inside -->
								</div><!-- /#namediv -->

								<div id="postdiv" class="postarea">
									<label for="content" class="screen-reader-text"><?php _e( 'Comments', 'edd-reviews' ); ?></label>
									<?php
									$quicktags_settings = array( 'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close' );
									wp_editor( $feedback->comment_content, 'content', array( 'media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings ) );
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
											<?php $status = wp_get_comment_status( $feedback->comment_ID ); ?>

											<div id="misc-publishing-actions">
												<fieldset class="misc-pub-section misc-pub-comment-status" id="comment-status-radio">
													<legend class="screen-reader-text"><?php _e( 'Review status', 'edd-reviews' ); ?></legend>
													<label class="approved"><input type="radio" <?php checked( $status, 'approved' ); ?> name="feedback_status" value="1"><?php _e( 'Approved', 'edd-reviews' ); ?></label><br />
													<label class="waiting"><input type="radio" <?php checked( $status, 'unapproved' ); ?> name="feedback_status" value="0"><?php _e( 'Pending', 'edd-reviews' ); ?></label><br />
													<label class="spam"><input type="radio" <?php checked( $status, 'spam' ); ?> name="feedback_status" value="spam"><?php _e( 'Spam', 'edd-reviews' ); ?></label><br />
												</fieldset>
												<div class="misc-pub-section curtime misc-pub-curtime">
													<?php
													$datef = __( 'M j, Y @ H:i' );
													$stamp = __( 'Submitted on: <b>%1$s</b>' );
													$date = date_i18n( $datef, strtotime( $feedback->comment_date ) );
													?>
													<span id="timestamp"><?php printf( $stamp, $date ); ?></span>
													</div>
													<div class="misc-pub-section misc-pub-response-to">
													<?php
													$post_id = $feedback->comment_post_ID;
													if ( current_user_can( 'edit_post', $post_id ) ) {
														$post_link = "<a href='" . esc_url( get_edit_post_link( $post_id ) ) . "'>";
														$post_link .= esc_html( get_the_title( $post_id ) ) . '</a>';
													} else {
														$post_link = esc_html( get_the_title( $post_id ) );
													}
														?>
														<?php echo ucwords( edd_get_label_singular() ); ?>: <b><?php echo $post_link; ?></b>
													<p><?php printf( '%s: <b>%s</b>', EDD_FES()->helper->get_vendor_constant_name(), get_the_author_meta( 'display_name', $post->post_author ) ); ?></p>
													</div>
												</div><!-- /#misc-publishing-actions -->
											<div class="clear"></div>
											</div><!-- /#minor-publishing -->

											<div id="major-publishing-actions">
												<div id="delete-action">
													<a href="<?php echo esc_url( add_query_arg( array( 'edd_action' => 'update_vendor_feedback', 'review_status' => 'trash', '_wpnonce' => wp_create_nonce( 'edd-update-vendor-feedback_' . $feedback->comment_ID ) ) ) ); ?>" class="submitdelete deletion"><?php _e( 'Move to Trash', 'edd-reviews' ); ?></a>
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

							<input type="hidden" name="r" value="<?php echo esc_attr( $feedback->comment_ID ); ?>" />
							<input type="hidden" name="p" value="<?php echo esc_attr( $feedback->comment_post_ID ); ?>" />
							<?php wp_original_referer_field( true, 'previous' ); ?>
							<input name="referredby" type="hidden" id="referredby" value="<?php echo $referer ? esc_url( $referer ) : ''; ?>" />
						</div><!-- /#post-body -->
					</div><!-- /#poststuff -->
				</div><!-- /.wrap -->
			</form>
			<?php
			$output = ob_get_contents();
			ob_end_clean();
			echo $output;
			return;
		}

		$feedback_table = new EDD_Reviews_Vendor_Feedback_List_Table();
		$feedback_table->prepare_items();
		ob_start();
		?>
		<div class="wrap">
		<h1>
			<?php
			_e( 'Vendor Feedback', 'edd-reviews' );

			if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] ) {
				?>
				<span class="subtitle"><?php printf( __( 'Search results for &#8220;%s&#8221;' ), wp_html_excerpt( esc_html( wp_unslash( $_REQUEST['s'] ) ), 50, '&hellip;' ) ); ?></span>
				<?php
			}
			?>
			</h1>
			<form id="edd-reviews-vendor-feedback-form" method="get" action="<?php echo admin_url( 'admin.php?page=fes-vendor-feedback' ); ?>">
			<?php $feedback_table->search_box( __( 'Search Vendor Feedback', 'edd-reviews' ), 'edd-review' ); ?>
			<input type="hidden" name="page" value="fes-vendor-feedback" />
			<?php
			$feedback_table->views();
			$feedback_table->display();
			?>
			</form>
		</div>
		<?php
		$output = ob_get_contents();
		ob_end_clean();
		echo $output;
	}

	/**
	 * Update Vendor Feedback
	 *
	 * @since  2.0
	 * @access public
	 * @return void
	 */
	public function update_vendor_feedback() {
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['edd_action'] ) && 'update_vendor_feedback' == $_POST['edd_action'] ) {
			$feedback_id = absint( $_POST['feedback_ID'] );

			$actions = array( '1', '0', 'spam' );

			$action = trim( $_POST['feedback_status'] );

			if ( in_array( $action, $actions ) ) {
				check_admin_referer( 'edd-update-vendor-feedback_' . $feedback_id );
			}

			if ( ! $feedback = get_comment( $feedback_id ) ) {
				wp_die( __( 'Invalid Vendor Feedback ID', 'edd-reviews' ) . ' ' . sprintf( '<a href="%s">' . __( 'Go Back', 'edd-reviews' ) . '</a>', admin_url( 'edit.php?post_type=download&page=edd-reviews' ) ) );
			}

			if ( ! current_user_can( 'edit_comment', $feedback->comment_ID ) ) {
				wp_die( __( 'You are not allowed to edit vendor feedback.', 'edd-reviews' ) );
			}

			$feedback_id = $feedback->comment_ID;

			$args = array();

			if ( '1' == $action ) {
				$args['comment_approved'] = '1';
			}

			if ( '0' == $action ) {
				$args['comment_approved'] = '0';
			}

			if ( 'spam' == $action ) {
				$args['comment_approved'] = 'spam';
			}

			$args['comment_ID'] = $feedback_id;

			if ( isset( $_POST['review_author'] ) ) {
				$args['comment_author'] = sanitize_text_field( $_POST['review_author'] );
			}

			if ( isset( $_POST['review_author_email'] ) ) {
				$args['comment_author_email'] = sanitize_text_field( $_POST['review_author_email'] );
			}

			if ( isset( $_POST['review_author_url'] ) ) {
				$args['comment_author_url'] = esc_url( $_POST['review_author_url'] );
			}

			if ( isset( $_POST['content'] ) ) {
				$args['comment_content'] = sanitize_text_field( wp_filter_nohtml_kses( esc_textarea( $_POST['content'] ) ) );
			}

			wp_update_comment( $args );

			wp_redirect( add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-updated', 'edit' => 'true', 'r' => $feedback_id ), 'admin.php?page=fes-vendor-feedback' ) );
			exit;
		}
	}

	/**
	 * Get Vendor Feedback
	 *
	 * @since  2.0
	 * @access public
	 * @return array $data Vendor feedback
	 */
	public function get_vendor_feedback( $page = 0 ) {
		remove_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

		$offset = ($page - 1) * 10;

		$id = get_current_user_id();

		$data = get_comments(  array(
			'type'        => 'edd_vendor_feedback',
			'post_author' => $id,
			'number'      => 20,
			'offset'      => $offset,
		) );

		add_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

		return $data;
	}

	/**
	 * Print Vendor Feedback Pagination
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function pagination() {
		$limit = 10;

		$id = get_current_user_id();

		remove_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

		$data = get_comments(  array(
			'type'        => 'edd_vendor_feedback',
			'post_author' => $id,
			'count'       => true,
		) );

		add_action( 'pre_get_comments', array( edd_reviews()->fes, 'hide_feedback' ) );

		$pages = ceil( $data / $limit );

		if ( $pages > 1 ) {
			echo '<div class="edd-reviews-vendor-feedback-pagination">';
			$big = 999999999; // need an unlikely integer
			$format = '?paged=%#%';
			$base = $format == '?paged=%#%' ? $base = str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ) : $base = @add_query_arg( 'paged','%#%' );
			echo paginate_links( array(
				'current' => ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1,
				'format' => $format,
				'total' => $pages,
				'base' => $base,
				'current' => max( 1, get_query_var( 'paged' ) ),
			) );

			echo '</div>';
		}
	}

	/**
	 * Adjust vendor feedback status
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function set_vendor_feedback_status() {
		global $wpdb;

		if ( ! isset( $_REQUEST['_wpnonce'] ) && ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'approve-feedback_' . $_GET['r'] ) ) {
			wp_die( __( 'Nonce verification has failed', 'edd-reviews' ), __( 'Error', 'edd-reviews' ), array( 'response' => 403 ) );
		}

		$feedback_id = trim( $_GET['r'] );

		$actions = array(
			'approve_vendor_feedback',
			'unapprove_vendor_feedback',
			'spam_vendor_feedback',
			'unspam_vendor_feedback',
			'trash_vendor_feedback',
			'restore_vendor_feedback',
			'delete_vendor_feedback',
		);

		if ( in_array( trim( $_GET['edd_action'] ), $actions ) ) {
			$action = trim( $_GET['edd_action'] );

			if ( 'approve_vendor_feedback' == $action ) {
				$args = array( 'comment_ID' => $feedback_id, 'comment_approved' => 1 );
				wp_update_comment( $args );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-approved', 'approved' => '1' ), $uri );
				wp_redirect( $uri );
			}

			if ( 'unapprove_vendor_feedback' == $action ) {
				wp_set_comment_status( $feedback_id, 'hold' );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-unapproved' ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'spam_vendor_feedback' == $action ) {
				wp_set_comment_status( $feedback_id, 'spam' );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-spammed', 'spammed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'unspam_vendor_feedback' == $action ) {
				$args = array( 'comment_ID' => $feedback_id, 'comment_approved' => 1 );
				wp_update_comment( $args );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-unspammed', 'unspammed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'trash_vendor_feedback' == $action ) {
				wp_set_comment_status( $feedback_id, 'trash' );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-trashed', 'trashed' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'restore_vendor_feedback' == $action ) {
				$args = array( 'comment_ID' => $feedback_id, 'comment_approved' => 1 );
				wp_update_comment( $args );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-restored', 'restored' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}

			if ( 'delete_vendor_feedback' == $action ) {
				wp_delete_comment( $feedback_id );
				$uri = $_SERVER['REQUEST_URI'];
				$uri = remove_query_arg( array( 'edd_action', '_wpnonce', 'r' ), $uri );
				$uri = add_query_arg( array( 'edd-reviews-message' => 'vendor-feedback-deleted', 'deleted' => 1 ), $uri );
				wp_redirect( esc_url_raw( $uri ) );
			}
		}
	}

	/**
	 * Override wp_count_comments to ensure that pending feedback isn't shown
	 * in the WordPress admin
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function wp_count_comments( $stats, $post_id ) {
		global $wpdb;

		if ( 0 == $post_id ) {
			$count = wp_cache_get( 'comments-0', 'counts' );
			if ( false !== $count ) {
				return $count;
			}

			$where = $wpdb->prepare( 'WHERE comment_type != %s', 'edd_vendor_feedback' );

			$totals = (array) $wpdb->get_results("
			SELECT comment_approved, COUNT( * ) AS total
			FROM {$wpdb->comments}
			{$where}
			GROUP BY comment_approved", ARRAY_A);

			$comment_count = array(
			'approved'            => 0,
			'awaiting_moderation' => 0,
			'spam'                => 0,
			'trash'               => 0,
			'post-trashed'        => 0,
			'total_comments'      => 0,
			'all'                 => 0,
			);

			foreach ( $totals as $row ) {
				switch ( $row['comment_approved'] ) {
					case 'trash':
						$comment_count['trash'] = $row['total'];
						break;
					case 'post-trashed':
						$comment_count['post-trashed'] = $row['total'];
						break;
					case 'spam':
						$comment_count['spam'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						break;
					case '1':
						$comment_count['approved'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						$comment_count['all'] += $row['total'];
						break;
					case '0':
						$comment_count['awaiting_moderation'] = $row['total'];
						$comment_count['total_comments'] += $row['total'];
						$comment_count['all'] += $row['total'];
						break;
					default:
						break;
				}
			}

			$stats = new stdClass;
			$stats->total_comments = $comment_count['total_comments'];
			$stats->moderated = $comment_count['awaiting_moderation'];
			$stats->spam = $comment_count['spam'];
			$stats->trash = $comment_count['trash'];
		}

		return $stats;
	}

	/**
	 * Show relevant admin notices
	 *
	 * @since 2.0
	 * @access public
	 * @return void
	 */
	public function admin_notices() {
		$notices = array(
		'updated' => array(),
		'error'   => array(),
		);

		if ( isset( $_GET['edd-reviews-message'] ) ) {
			switch ( $_GET['edd-reviews-message'] ) {
				case 'vendor-feedback-updated' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback successfully updated.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-approved' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked as approved.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-unapproved' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked as unapproved.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-spammed' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked as spam.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-unspammed' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked as not spam.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-trashed' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked has been moved to the trash.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-restored' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked has been restored.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;

				case 'vendor-feedback-deleted' :
					$notices['updated']['vendor-feedback-updated'] = sprintf( __( '%s feedback marked has been deleted permanently.', 'edd-reviews' ), EDD_FES()->helper->get_vendor_constant_name() );
				break;
			}
		}

		if ( count( $notices['updated'] ) > 0 ) {
			foreach ( $notices['updated'] as $notice => $message ) {
				add_settings_error( 'edd-reviews-notices', $notice, $message, 'updated' );
			}
		}

		if ( count( $notices['error'] ) > 0 ) {
			foreach ( $notices['error'] as $notice => $message ) {
				add_settings_error( 'edd-reviews-notices', $notice, $message, 'error' );
			}
		}

		settings_errors( 'edd-reviews-notices' );
	}

	/**
	 * Default Vendor Feedback Email.
	 *
	 * @since  2.1
	 * @access private
	 *
	 * @return string $message Email content.
	 */
	public function get_default_email() {
		$default_email_body = sprintf( __( 'New feedback has been left on your %s ({download}). Please check the %s Feedback tab of your %s Dashboard to view this feedback.', 'edd-reviews' ), strtolower( edd_get_label_singular() ), EDD_FES()->helper->get_vendor_constant_name(), EDD_FES()->helper->get_vendor_constant_name() ) . "\n";
		$default_email_body .= '<strong>' . __( 'Author:', 'edd-reviews' ) . '</strong>' . ' {author}' . "\n";
		$default_email_body .= '<strong>' . __( 'Email:', 'edd-reviews' ) . '</strong>' . ' {email}' . "\n";
		$default_email_body .= '<strong>' . __( 'URL:', 'edd-reviews' ) . '</strong>' . ' {url}' . "\n";
		$default_email_body .= '<strong>' . __( 'Rating:', 'edd-reviews' ) . '</strong>' . ' {rating}' . "\n";
		$default_email_body .= '<strong>' . __( 'Item as Described:', 'edd-reviews' ) . '</strong>' . ' {item_as_described}' . "\n";
		$default_email_body .= '<strong>' . __( 'Feedback:', 'edd-reviews' ) . '</strong>' . "\n" . '{feedback}';

		$message = edd_get_option( 'edd_reviews_vendor_feedback_email', false );
		$message = $message ? stripslashes( $message ) : $default_email_body;

		return $message;
	}

	/**
	 * Vendor Feedback Email Tags.
	 *
	 * @since  2.1
	 * @access public
	 *
	 * @param array $tags Email tags.
	 * @return array $tags Updated email tags.
	 */
	public function email_tags( $tags ) {
		$tags = array(
			'download' => array(
				'tag'         => strtolower( edd_get_label_singular() ),
				'description' => __( 'Name of the download the review was posted on', 'edd-reviews' ),
				'function'    => 'download_tag'
			),
			'author' => array(
				'tag'         => 'author',
				'description' => __( 'The review author', 'edd-reviews' ),
				'function'    => 'author_tag'
			),
			'email' => array(
				'tag'         => 'email',
				'description' => __( "The reviewer's email", 'edd-reviews' ),
				'function'    => 'email_tag'
			),
			'url' => array(
				'tag'         => 'url',
				'description' => __( "The reviewer's URL", 'edd-reviews' ),
				'function'    => 'url_tag'
			),
			'rating' => array(
				'tag'         => 'rating',
				'description' => __( 'The rating given by the reviewer', 'edd-reviews' ),
				'function'    => 'rating_tag'
			),
			'item_as_described' => array(
				'tag'         => 'item_as_described',
				'description' => __( 'If the item was as described or not', 'edd-reviews' ),
				'function'    => 'item_as_described_tag'
			),
			'feedback' => array(
				'tag'         => 'feedback',
				'description' => __( 'The content of the feedback', 'edd-reviews' ),
				'function'    => 'review_tag'
			)
		);

		return $tags;
	}
}