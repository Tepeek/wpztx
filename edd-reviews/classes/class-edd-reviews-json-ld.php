<?php
/**
 * JSON-LD Class for Google Rich Cards.
 *
 * @package    EDD_Reviews
 * @subpackage Frontend
 * @copyright  Copyright (c) 2017, Sunny Ratilal
 * @since      2.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class EDD_Reviews_JSON_LD {
	/**
	 * Constructor.
	 *
	 * @access public
	 * @since  2.1
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
	 * @access private
	 * @since  2.1
	 */
	private function hooks() {
		add_action( 'wp_head', array( $this, 'json_ld_markup' ) );
	}

	/**
	 * Generate the structured data from the post object.
	 *
	 * @access private
	 * @since  2.1
	 * @return array $data Structured data for the JSON-LD markup.
	 */
	private function generate_structured_data() {
		global $post;

		$data = array(
			'@context' => 'http://schema.org',
			'@type' => 'Product',
			'name' => strip_tags( get_the_title( $post ) ),
			'aggregateRating' => array(
				'@type' => 'AggregateRating',
				'ratingValue' => edd_reviews()->average_rating( false ),
				'bestRating' => '5',
				'worstRating' => '1',
				'ratingCount' => edd_reviews()->count_reviews()
			)
		);

		/**
		 * Filter the JSON-LD markup that will be output.
		 *
		 * @since 2.1
		 *
		 * @param array               $data JSON-LD data.
		 * @param EDD_Reviews_JSON_LD $this Instance of EDD_Reviews_JSON_LD class.
		 */
		return apply_filters( 'edd_reviews_json_ld_data', $data, $this );
	}

	/**
	 * Generate the JSON-LD Markup.
	 *
	 * @access public
	 * @since  2.1
	 */
	public function json_ld_markup() {
		if ( ! is_singular( 'download' ) ) {
			return;
		}

		$data = $this->generate_structured_data();

		if ( 0 == $data['aggregateRating']['ratingCount'] ) {
			return;
		}

		ob_start();
		?>
		<script type="application/ld+json">
		<?php echo json_encode( $data ); ?>
		</script>
		<?php
		echo ob_get_clean();
	}
}