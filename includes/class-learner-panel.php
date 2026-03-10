<?php
/**
 * Learner panel for learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the learner panel on learner subsites.
 *
 * Displays course catalog, enrolled courses with progress, and XP summary.
 */
class Learn_Learner_Panel {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'wp_ajax_1111_select_course', array( $this, 'ajax_select_course' ) );
	}

	/**
	 * Register the Learn menu page on non-main-site subsites.
	 */
	public function register_menu() {
		if ( is_main_site() ) {
			return;
		}

		add_menu_page(
			__( 'Learn', '1111-learn' ),
			__( 'Learn', '1111-learn' ),
			'read',
			'1111-learn-panel',
			array( $this, 'render_panel' ),
			'dashicons-welcome-learn-more',
			3
		);
	}

	/**
	 * Render the learner panel.
	 */
	public function render_panel() {
		$catalog  = $this->get_course_catalog();
		$enrolled = $this->get_enrolled_courses();
		$xp       = $this->get_xp_summary();

		include plugin_dir_path( __DIR__ ) . 'admin/views/learner-panel.php';
	}

	/**
	 * Get published courses from the main site.
	 *
	 * @return array Array of course data arrays.
	 */
	public function get_course_catalog() {
		$main_site_id = get_main_site_id();
		$courses      = array();

		switch_to_blog( $main_site_id );

		$terms = get_terms(
			array(
				'taxonomy'   => 'course',
				'hide_empty' => true,
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				// Check if the course has published posts.
				$post_count = new WP_Query(
					array(
						'post_type'      => 'learn',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'course',
								'field'    => 'term_id',
								'terms'    => $term->term_id,
							),
						),
						'fields'         => 'ids',
					)
				);

				if ( $post_count->found_posts < 1 ) {
					continue;
				}

				$narrative   = get_term_meta( $term->term_id, '_1111_narrative_description', true );
				$work_product = get_term_meta( $term->term_id, '_1111_work_product_description', true );

				$courses[] = array(
					'term_id'               => $term->term_id,
					'name'                  => $term->name,
					'description'           => $term->description,
					'narrative_description'  => $narrative ? $narrative : '',
					'work_product_description' => $work_product ? $work_product : '',
					'lesson_count'          => $post_count->found_posts,
				);
			}
		}

		restore_current_blog();

		return $courses;
	}

	/**
	 * Get courses the learner is enrolled in on their subsite.
	 *
	 * @return array Array of enrolled course data.
	 */
	public function get_enrolled_courses() {
		$courses  = array();
		$terms    = get_terms(
			array(
				'taxonomy'   => 'course',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $courses;
		}

		foreach ( $terms as $term ) {
			$total_lessons = new WP_Query(
				array(
					'post_type'      => 'learn',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'course',
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						),
					),
					'fields'         => 'ids',
				)
			);

			$completed_lessons = new WP_Query(
				array(
					'post_type'      => 'learn',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'course',
							'field'    => 'term_id',
							'terms'    => $term->term_id,
						),
					),
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_1111_lesson_completed',
							'value' => '1',
						),
					),
					'fields'         => 'ids',
				)
			);

			$total     = $total_lessons->found_posts;
			$completed = $completed_lessons->found_posts;
			$progress  = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;

			$xp = absint( get_term_meta( $term->term_id, '_1111_learner_xp', true ) );

			$courses[] = array(
				'term_id'   => $term->term_id,
				'name'      => $term->name,
				'total'     => $total,
				'completed' => $completed,
				'progress'  => $progress,
				'xp'        => $xp,
			);
		}

		return $courses;
	}

	/**
	 * Get XP summary for the current learner.
	 *
	 * @return array XP summary data.
	 */
	public function get_xp_summary() {
		$total_xp = absint( get_user_meta( get_current_user_id(), '_1111_total_xp', true ) );

		return array(
			'total_xp' => $total_xp,
		);
	}

	/**
	 * AJAX handler: select a course and copy content to learner subsite.
	 */
	public function ajax_select_course() {
		check_ajax_referer( '1111_learn_panel', 'nonce' );

		if ( is_main_site() ) {
			wp_send_json_error(
				array( 'message' => __( 'Course selection is only available on learner subsites.', '1111-learn' ) ),
				403
			);
		}

		$course_term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;

		if ( empty( $course_term_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid course selection.', '1111-learn' ) ),
				400
			);
		}

		$target_blog_id = get_current_blog_id();

		// Delegate content copy to Learn_Content_Copy.
		$content_copy = new Learn_Content_Copy();
		$result       = $content_copy->copy_course( $course_term_id, $target_blog_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		// Create enrollment record.
		update_option(
			'_1111_enrollment_' . $course_term_id,
			array(
				'enrolled_at' => current_time( 'mysql' ),
				'user_id'     => get_current_user_id(),
				'status'      => 'active',
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Course enrolled successfully.', '1111-learn' ),
			)
		);
	}
}
