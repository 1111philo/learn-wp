<?php
/**
 * Frontend course navigation for learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides frontend course navigation, breadcrumbs, and activity display on learner subsites.
 */
class Learn_Course_Navigation {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'learn_course', array( $this, 'render_course_landing' ) );
		add_filter( 'the_content', array( $this, 'append_lesson_navigation' ) );
	}

	/**
	 * Render the course landing page via shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_course_landing( $atts ) {
		$atts = shortcode_atts(
			array(
				'course' => '',
			),
			$atts,
			'learn_course'
		);

		$course_slug = sanitize_title( $atts['course'] );
		$course_term = null;

		if ( ! empty( $course_slug ) ) {
			$course_term = get_term_by( 'slug', $course_slug, 'course' );
		}

		// If no slug provided, get the first course.
		if ( ! $course_term ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'course',
					'hide_empty' => true,
					'number'     => 1,
				)
			);

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$course_term = $terms[0];
			}
		}

		if ( ! $course_term ) {
			return '<p>' . esc_html__( 'No course found.', '1111-learn' ) . '</p>';
		}

		$course_data = $this->get_course_data( $course_term );

		ob_start();
		include plugin_dir_path( __DIR__ ) . 'admin/views/course-landing.php';
		return ob_get_clean();
	}

	/**
	 * Append lesson navigation to learn post content on the frontend.
	 *
	 * @param string $content The post content.
	 * @return string Modified content with navigation appended.
	 */
	public function append_lesson_navigation( $content ) {
		if ( ! is_singular( 'learn' ) || is_admin() ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post || 'learn' !== $post->post_type ) {
			return $content;
		}

		$navigation = $this->get_lesson_navigation( $post );

		ob_start();
		include plugin_dir_path( __DIR__ ) . 'admin/views/lesson-navigation.php';
		$nav_html = ob_get_clean();

		return $content . $nav_html;
	}

	/**
	 * Get structured course data for the landing page.
	 *
	 * @param WP_Term $course_term The course term.
	 * @return array Course data with grouped lessons.
	 */
	public function get_course_data( $course_term ) {
		$narrative    = get_term_meta( $course_term->term_id, '_1111_narrative_description', true );
		$work_product = get_term_meta( $course_term->term_id, '_1111_work_product_description', true );

		// Get all lessons for this course.
		$lessons = get_posts(
			array(
				'post_type'      => 'learn',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $course_term->term_id,
					),
				),
			)
		);

		// Group lessons by lesson_group.
		$groups         = array();
		$ungrouped      = array();
		$total_lessons  = count( $lessons );
		$completed      = 0;

		foreach ( $lessons as $index => $lesson ) {
			$is_completed = get_post_meta( $lesson->ID, '_1111_lesson_completed', true );
			if ( $is_completed ) {
				$completed++;
			}

			$lesson_data = array(
				'post'      => $lesson,
				'number'    => $index + 1,
				'completed' => (bool) $is_completed,
				'url'       => get_permalink( $lesson->ID ),
			);

			$lesson_groups = wp_get_object_terms( $lesson->ID, 'lesson_group' );

			if ( ! is_wp_error( $lesson_groups ) && ! empty( $lesson_groups ) ) {
				$group = $lesson_groups[0];
				if ( ! isset( $groups[ $group->term_id ] ) ) {
					$groups[ $group->term_id ] = array(
						'term'    => $group,
						'lessons' => array(),
					);
				}
				$groups[ $group->term_id ]['lessons'][] = $lesson_data;
			} else {
				$ungrouped[] = $lesson_data;
			}
		}

		$progress = $total_lessons > 0 ? round( ( $completed / $total_lessons ) * 100 ) : 0;

		return array(
			'term'                     => $course_term,
			'narrative_description'    => $narrative ? $narrative : '',
			'work_product_description' => $work_product ? $work_product : '',
			'groups'                   => $groups,
			'ungrouped'                => $ungrouped,
			'total_lessons'            => $total_lessons,
			'completed_lessons'        => $completed,
			'progress'                 => $progress,
		);
	}

	/**
	 * Get navigation data for a single lesson.
	 *
	 * @param WP_Post $post The current lesson post.
	 * @return array Navigation data.
	 */
	public function get_lesson_navigation( $post ) {
		// Get course term.
		$course_terms = wp_get_object_terms( $post->ID, 'course' );
		$course_term  = ! is_wp_error( $course_terms ) && ! empty( $course_terms ) ? $course_terms[0] : null;

		// Get lesson group.
		$group_terms = wp_get_object_terms( $post->ID, 'lesson_group' );
		$group_term  = ! is_wp_error( $group_terms ) && ! empty( $group_terms ) ? $group_terms[0] : null;

		// Get all lessons in this course, ordered.
		$all_lessons = array();
		if ( $course_term ) {
			$all_lessons = get_posts(
				array(
					'post_type'      => 'learn',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'menu_order date',
					'order'          => 'ASC',
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'course',
							'field'    => 'term_id',
							'terms'    => $course_term->term_id,
						),
					),
				)
			);
		}

		// Find current position and neighbors.
		$current_index = 0;
		$prev_lesson   = null;
		$next_lesson   = null;
		$total         = count( $all_lessons );

		foreach ( $all_lessons as $index => $lesson ) {
			if ( $lesson->ID === $post->ID ) {
				$current_index = $index;
				if ( $index > 0 ) {
					$prev_lesson = $all_lessons[ $index - 1 ];
				}
				if ( $index < $total - 1 ) {
					$next_lesson = $all_lessons[ $index + 1 ];
				}
				break;
			}
		}

		// Get activity data.
		$activity_prompt       = get_post_meta( $post->ID, '_1111_activity_prompt', true );
		$activity_instructions = get_post_meta( $post->ID, '_1111_activity_instructions', true );
		$activity_hints        = get_post_meta( $post->ID, '_1111_activity_hints', true );
		$assessment_result     = get_post_meta( $post->ID, '_1111_assessment_result', true );
		$is_submitted          = get_post_meta( $post->ID, '_1111_activity_submitted', true );

		return array(
			'course_term'    => $course_term,
			'group_term'     => $group_term,
			'prev_lesson'    => $prev_lesson,
			'next_lesson'    => $next_lesson,
			'current_number' => $current_index + 1,
			'total_lessons'  => $total,
			'activity'       => array(
				'prompt'       => $activity_prompt ? $activity_prompt : '',
				'instructions' => $activity_instructions ? $activity_instructions : '',
				'hints'        => $activity_hints ? $activity_hints : '',
				'submitted'    => (bool) $is_submitted,
				'result'       => $assessment_result ? $assessment_result : '',
			),
		);
	}
}
