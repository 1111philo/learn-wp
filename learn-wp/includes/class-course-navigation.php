<?php
/**
 * Course and lesson navigation shortcode.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Course_Navigation {
	/**
	 * Singleton.
	 *
	 * @var Learn_Course_Navigation|null
	 */
	private static $instance = null;

	/**
	 * Singleton getter.
	 *
	 * @return Learn_Course_Navigation
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'learn_course', array( $this, 'render_course' ) );
	}

	/**
	 * Render course overview.
	 *
	 * @param array $atts Shortcode attrs.
	 * @return string
	 */
	public function render_course( $atts ) {
		$atts = shortcode_atts(
			array(
				'course_id' => 0,
			),
			$atts,
			'learn_course'
		);

		$course_id = absint( $atts['course_id'] );
		if ( ! $course_id ) {
			return '<p>' . esc_html__( 'Missing course_id for [learn_course].', 'learn-wp' ) . '</p>';
		}

		$term = get_term( $course_id, 'course' );
		if ( ! $term || is_wp_error( $term ) ) {
			return '<p>' . esc_html__( 'Course not found.', 'learn-wp' ) . '</p>';
		}

		$posts = get_posts(
			array(
				'post_type'      => 'learn',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $course_id,
					),
				),
			)
		);

		ob_start();
		?>
		<section class="learn-course-landing">
			<h2><?php echo esc_html( $term->name ); ?></h2>
			<div class="learn-course-narrative"><?php echo wp_kses_post( get_term_meta( $course_id, '_1111_narrative_description', true ) ); ?></div>
			<ol class="learn-lesson-list">
				<?php foreach ( $posts as $post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post->ID ) ); ?></a>
						<?php if ( get_post_meta( $post->ID, '_1111_is_assessment', true ) ) : ?>
							<span class="learn-badge"><?php esc_html_e( 'Final', 'learn-wp' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
