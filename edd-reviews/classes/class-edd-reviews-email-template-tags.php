<?php
/**
 * EDD Reviews Email Template Tags API
 *
 * Builds upon the existing API provided by Easy Digital Downloads but tailors it to be
 * specific to EDD Reviews.
 *
 * @package EDD_Reviews
 * @subpackage Emails
 * @copyright Copyright (c) 2017, Sunny Ratilal
 * @since 2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Reviews_Email_Template_Tags' ) ) :

class EDD_Reviews_Email_Template_Tags extends EDD_Email_Template_Tags {
	/**
	 * Container for storing all tags
	 *
	 * @since 2.1
	 */
	protected $tags;

	/**
	 * Review ID
	 *
	 * @access protected
	 * @var int
	 * @since 2.1
	 */
	protected $review_id;

	/**
	 * Review data
	 *
	 * @access protected
	 * @var array
	 * @since 2.1
	 */
	protected $review_data;

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 2.1
	 */
	public function __construct() {
		$this->get_tags();
	}

	/**
	 * Return a list of email template tags specific for EDD Reviews
	 *
	 * @access public
	 * @since 2.1
	 */
	public function get_tags() {
		$tags = array(
			'download' => array(
				'tag'         => strtolower( edd_get_label_singular() ),
				'description' => __( 'Name of the download the review was posted on', 'edd-reviews' ),
				'function'    => 'download_tag',
				'discount'    => true,
			),
			'direct_link' => array(
				'tag'         => 'direct_link',
				'description' => __( 'Direct link to the review', 'edd-reviews' ),
				'function'    => 'direct_link_tag'
			),
			'author' => array(
				'tag'         => 'author',
				'description' => __( 'The review author', 'edd-reviews' ),
				'function'    => 'author_tag',
				'discount'    => true,
			),
			'email' => array(
				'tag'         => 'email',
				'description' => __( "The reviewer's email", 'edd-reviews' ),
				'function'    => 'email_tag',
				'discount'    => true,
			),
			'url' => array(
				'tag'         => 'url',
				'description' => __( "The reviewer's URL", 'edd-reviews' ),
				'function'    => 'url_tag',
				'discount'    => true,
			),
			'rating' => array(
				'tag'         => 'rating',
				'description' => __( 'The rating given by the reviewer', 'edd-reviews' ),
				'function'    => 'rating_tag',
				'discount'    => true,
			),
			'title' => array(
				'tag'         => 'title',
				'description' => __( 'The title of the review', 'edd-reviews' ),
				'function'    => 'title_tag',
				'discount'    => true,
			),
			'review' => array(
				'tag'         => 'review',
				'description' => __( 'The content of the review', 'edd-reviews' ),
				'function'    => 'review_tag',
				'discount'    => true,
			),
			'item_as_described' => array(
				'tag'         => 'item_as_described',
				'description' => __( 'If the item was as described or not', 'edd-reviews' ),
				'function'    => 'item_as_described_tag',
				'fes'         => true,
			),
			'feedback' => array(
				'tag'         => 'feedback',
				'description' => __( 'The content of the feedback', 'edd-reviews' ),
				'function'    => 'review_tag',
				'fes'         => true,
			),
			'reviewer_discount_code' => array(
				'tag'         => 'reviewer_discount_code',
				'description' => __( 'Discount code for the reviewer', 'edd-reviews' ),
				'function'    => 'discount_tag',
				'discount'    => true,
			)
		);

		$this->tags = $tags;

		return apply_filters( 'edd_reviews_email_tags', $tags );
	}

	/**
	 * Search content for email tags and filter email tags through their hooks.
	 *
	 * @access public
	 * @since 2.1
	 *
	 * @param string $content     Content to search for email tags.
	 * @param int    $review_id   Review ID.
	 * @param array  $review_data Review data.
	 * @return string Content with email tags filtered out.
	 */
	public function do_review_tags( $content, $review_id, $review_data ) {

		$tags = $this->get_tags();

		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return $content;
		}

		$this->review_id = $review_id;
		$this->review_data = $review_data;

		$new_content = preg_replace_callback( "/{([A-z0-9\-\_]+)}/s", array( $this, 'do_tag' ), $content );

		$this->review_id = null;
		$this->review_data = null;

		return $new_content;
	}

	/**
	 * Do a specific tag, this function should not be used. Please use edd_do_email_tags instead.
	 *
	 * @access public
	 * @since 2.1
	 * @param string $m message
	 * @return mixed
	 */
	public function do_tag( $m ) {
		$tag = $m[1];

		$tags = $this->get_tags();

		if ( ! array_key_exists( $tag, $tags ) ) {
			return $m[0];
		}

		return call_user_func( array( $this, $tags[ $tag ]['function'] ), $tag );
	}

	/**
	 * Retrieve a formatted list of all the email tags.
	 *
	 * @access public
	 * @since 2.1
	 *
	 * @param string $include Only fetch tags with this param.
	 * @return string List of email template tags.
	 */
	public function get_tags_list( $include = '' ) {
		$list = '';

		if ( count( $this->get_tags() ) > 0 ) {
			foreach ( $this->get_tags() as $email_tag ) {
				if ( isset( $email_tag['fes'] ) ) {
					continue;
				}

				if ( ! empty( $include ) && isset( $email_tag[ $include ] ) ) {
					$list .= '{' . $email_tag['tag'] . '} - ' . $email_tag['description'] . '<br/>';
				}

				if ( empty( $include ) ) {
					$list .= '{' . $email_tag['tag'] . '} - ' . $email_tag['description'] . '<br/>';
				}
			}
		}

		return $list;
	}

	/**
	 * Download tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Download name with permalink.
	 */
	private function download_tag() {
		$download_id = $this->review_data['comment_post_ID'];
		return '<a href="' . get_permalink( $download_id ) . '">' . get_the_title( $download_id ) . '</a>';
	}

	/**
	 * Direct link tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Direct link to download.
	 */
	private function direct_link_tag() {
		$download_id = $this->review_data['comment_post_ID'];
		return '<a href="' . get_permalink( $download_id ) . '#edd-review-' . $this->review_data['id'] . '">' . get_permalink( $download_id ) . '</a>';
	}

	/**
	 * Author tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Author name and IP address.
	 */
	private function author_tag() {
		return $this->review_data['comment_author'] . ' (' . __( 'IP', 'edd-reviews' ) . ': ' . $this->review_data['comment_author_IP'] . ')';
	}

	/**
	 * Email tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Email address.
	 */
	private function email_tag() {
		return '<a href="mailto:' . $this->review_data['comment_author_email'] . '">' . $this->review_data['comment_author_email'] . '</a>';
	}

	/**
	 * URL tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Author's URL.
	 */
	private function url_tag() {
		return '<a href="' . $this->review_data['comment_author_url'] . '">' . $this->review_data['comment_author_url'] . '</a>';
	}

	/**
	 * Rating tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Rating.
	 */
	private function rating_tag() {
		return $this->review_data['rating'];
	}

	/**
	 * Review title tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Review title.
	 */
	private function title_tag() {
		return $this->review_data['review_title'];
	}

	/**
	 * Review tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Review content.
	 */
	private function review_tag() {
		return $this->review_data['comment_content'];
	}

	/**
	 * Item as Described tag (used with FES integration).
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Item as described (Yes/No).
	 */
	private function item_as_described_tag() {
		return $this->review_data['item_as_described'];
	}

	/**
	 * Discount tag.
	 *
	 * @access private
	 * @since  2.1
	 *
	 * @return string Discount code.
	 */
	private function discount_tag() {
		return isset( $this->review_data['discount'] ) ? $this->review_data['discount'] : '';
	}
}

endif;