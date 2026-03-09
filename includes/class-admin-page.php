<?php
/**
 * Admin page AJAX handlers and helpers.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Admin_Page
 *
 * Handles AJAX endpoints for course generation, status polling, publishing, and retry.
 */
class Learn_Admin_Page {

	/**
	 * Register AJAX hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_1111_generate_course', array( __CLASS__, 'ajax_generate_course' ) );
		add_action( 'wp_ajax_1111_generation_status', array( __CLASS__, 'ajax_generation_status' ) );
		add_action( 'wp_ajax_1111_publish_course', array( __CLASS__, 'ajax_publish_course' ) );
		add_action( 'wp_ajax_1111_retry_generation', array( __CLASS__, 'ajax_retry_generation' ) );
	}

	/**
	 * AJAX: Generate a course.
	 */
	public static function ajax_generate_course() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );

		if ( ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learn' ) ), 403 );
		}

		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$objectives  = isset( $_POST['objectives'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['objectives'] ) ) : array();

		// Remove empty objectives.
		$objectives = array_values( array_filter( $objectives, function ( $obj ) {
			return '' !== trim( $obj );
		} ) );

		// Validate.
		$errors = array();
		if ( '' === $title ) {
			$errors['title'] = __( 'Course title is required.', 'learn' );
		} elseif ( strlen( $title ) < 3 ) {
			$errors['title'] = __( 'Course title must be at least 3 characters.', 'learn' );
		}

		if ( '' === $description ) {
			$errors['description'] = __( 'Course description is required.', 'learn' );
		} elseif ( strlen( $description ) < 20 ) {
			$errors['description'] = __( 'Course description must be at least 20 characters.', 'learn' );
		}

		if ( count( $objectives ) < 1 ) {
			$errors['objectives'] = __( 'At least one learning objective is required.', 'learn' );
		} else {
			foreach ( $objectives as $i => $obj ) {
				if ( strlen( $obj ) < 10 ) {
					/* translators: %d: objective number */
					$errors[ 'objective_' . $i ] = sprintf( __( 'Objective %d must be at least 10 characters.', 'learn' ), $i + 1 );
				}
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ), 422 );
		}

		// Check API key.
		if ( ! Learn_Settings::get_api_key() ) {
			wp_send_json_error( array(
				'message' => __( 'Anthropic API key is not configured. Go to Learn > Settings to add your API key.', 'learn' ),
			), 400 );
		}

		$term_id = Learn_Orchestrator::start_generation( $title, $description, $objectives );
		if ( is_wp_error( $term_id ) ) {
			wp_send_json_error( array( 'message' => $term_id->get_error_message() ), 500 );
		}

		wp_send_json_success( array(
			'term_id' => $term_id,
			'message' => __( 'Course generation started.', 'learn' ),
		) );
	}

	/**
	 * AJAX: Get generation status.
	 */
	public static function ajax_generation_status() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );

		if ( ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learn' ) ), 403 );
		}

		$term_id = isset( $_GET['term_id'] ) ? absint( $_GET['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learn' ) ), 400 );
		}

		$progress = Learn_Orchestrator::get_progress( $term_id );
		if ( ! $progress ) {
			$status = get_term_meta( $term_id, '_1111_generation_status', true );
			wp_send_json_success( array(
				'status' => $status ? $status : 'unknown',
			) );
			return;
		}

		wp_send_json_success( $progress );
	}

	/**
	 * AJAX: Publish a course.
	 */
	public static function ajax_publish_course() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );

		if ( ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learn' ) ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learn' ) ), 400 );
		}

		$status = get_term_meta( $term_id, '_1111_generation_status', true );
		if ( 'complete' !== $status ) {
			wp_send_json_error( array( 'message' => __( 'Only completed courses can be published.', 'learn' ) ), 400 );
		}

		// Publish all learn posts for this course.
		$posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'draft',
			'numberposts' => -1,
			'tax_query'   => array( array(
				'taxonomy' => 'course',
				'terms'    => $term_id,
			) ),
		) );

		$published_count = 0;
		foreach ( $posts as $post ) {
			wp_update_post( array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			) );
			$published_count++;
		}

		update_term_meta( $term_id, '_1111_generation_status', 'published' );
		update_term_meta( $term_id, '_1111_published_date', gmdate( 'c' ) );

		wp_send_json_success( array(
			'message'         => sprintf(
				/* translators: %d: number of published posts */
				__( 'Course published with %d lessons.', 'learn' ),
				$published_count
			),
			'published_count' => $published_count,
		) );
	}

	/**
	 * AJAX: Retry failed generation.
	 */
	public static function ajax_retry_generation() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );

		if ( ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learn' ) ), 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learn' ) ), 400 );
		}

		$result = Learn_Orchestrator::retry_generation( $term_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'message' => __( 'Generation retry started.', 'learn' ) ) );
	}

	/**
	 * Get all courses with their status for the dashboard.
	 *
	 * @return array List of course data.
	 */
	public static function get_courses_list() {
		$terms = get_terms( array(
			'taxonomy'   => 'course',
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
		) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$courses = array();
		foreach ( $terms as $term ) {
			$status = get_term_meta( $term->term_id, '_1111_generation_status', true );
			if ( ! $status ) {
				continue;
			}

			$post_count = 0;
			$posts      = get_posts( array(
				'post_type'   => 'learn',
				'post_status' => array( 'draft', 'publish' ),
				'numberposts' => -1,
				'fields'      => 'ids',
				'tax_query'   => array( array(
					'taxonomy' => 'course',
					'terms'    => $term->term_id,
				) ),
			) );
			$post_count = count( $posts );

			$courses[] = array(
				'term_id'     => $term->term_id,
				'title'       => $term->name,
				'status'      => $status,
				'post_count'  => $post_count,
				'created'     => get_term_meta( $term->term_id, '_1111_generation_date', true ),
				'published'   => get_term_meta( $term->term_id, '_1111_published_date', true ),
				'total_xp'    => (int) get_term_meta( $term->term_id, '_1111_total_xp', true ),
				'description' => get_term_meta( $term->term_id, '_1111_narrative_description', true ),
			);
		}

		return $courses;
	}

	/**
	 * Get full course data for the review panel.
	 *
	 * @param int $term_id Course term ID.
	 * @return array|WP_Error Course data or error.
	 */
	public static function get_course_review_data( $term_id ) {
		$term = get_term( $term_id, 'course' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'invalid_term', __( 'Course not found.', 'learn' ) );
		}

		$status      = get_term_meta( $term_id, '_1111_generation_status', true );
		$narrative   = get_term_meta( $term_id, '_1111_narrative_description', true );
		$objectives  = get_term_meta( $term_id, '_1111_learning_objectives', true );
		$work_product = get_term_meta( $term_id, '_1111_work_product', true );
		$wp_type     = get_term_meta( $term_id, '_1111_work_product_type', true );
		$wp_desc     = get_term_meta( $term_id, '_1111_work_product_description', true );
		$total_xp    = (int) get_term_meta( $term_id, '_1111_total_xp', true );
		$assessment  = get_term_meta( $term_id, '_1111_assessment', true );

		// Get lesson groups.
		$groups = get_terms( array(
			'taxonomy'   => 'lesson_group',
			'hide_empty' => false,
			'meta_query' => array(
				array( 'key' => '_1111_course_term_id', 'value' => $term_id ),
			),
			'meta_key'   => '_1111_objective_index',
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		) );

		$lesson_groups = array();
		if ( ! is_wp_error( $groups ) ) {
			foreach ( $groups as $group ) {
				$plan = get_term_meta( $group->term_id, '_1111_lesson_plan_raw', true );
				$obj  = get_term_meta( $group->term_id, '_1111_learning_objective', true );

				// Get posts in this group.
				$group_posts = get_posts( array(
					'post_type'   => 'learn',
					'post_status' => array( 'draft', 'publish' ),
					'numberposts' => -1,
					'orderby'     => 'menu_order',
					'order'       => 'ASC',
					'tax_query'   => array(
						'relation' => 'AND',
						array( 'taxonomy' => 'course', 'terms' => $term_id ),
						array( 'taxonomy' => 'lesson_group', 'terms' => $group->term_id ),
					),
				) );

				$lessons = array();
				foreach ( $group_posts as $post ) {
					$activity = get_post_meta( $post->ID, '_1111_activity', true );
					$review   = get_post_meta( $post->ID, '_1111_activity_review', true );
					$lessons[] = array(
						'post_id'        => $post->ID,
						'title'          => $post->post_title,
						'content'        => $post->post_content,
						'excerpt'        => $post->post_excerpt,
						'key_takeaways'  => get_post_meta( $post->ID, '_1111_key_takeaways', true ),
						'activity'       => $activity,
						'activity_type'  => get_post_meta( $post->ID, '_1111_activity_type', true ),
						'xp_value'       => (int) get_post_meta( $post->ID, '_1111_xp_value', true ),
						'milestone'      => get_post_meta( $post->ID, '_1111_milestone', true ),
						'portfolio'      => isset( $activity['portfolio_contribution'] ) ? $activity['portfolio_contribution'] : '',
						'review_verdict' => $review ? $review['verdict'] : '',
						'edit_url'       => get_edit_post_link( $post->ID, 'raw' ),
					);
				}

				$lesson_groups[] = array(
					'group_term_id'    => $group->term_id,
					'group_name'       => $group->name,
					'objective'        => $obj,
					'mastery_criteria' => isset( $plan['mastery_criteria'] ) ? $plan['mastery_criteria'] : array(),
					'key_concepts'     => isset( $plan['key_concepts'] ) ? $plan['key_concepts'] : array(),
					'lessons'          => $lessons,
				);
			}
		}

		return array(
			'term_id'                  => $term_id,
			'title'                    => $term->name,
			'status'                   => $status,
			'narrative'                => $narrative,
			'objectives'               => $objectives,
			'work_product'             => $work_product,
			'work_product_type'        => $wp_type,
			'work_product_description' => $wp_desc,
			'total_xp'                 => $total_xp,
			'lesson_groups'            => $lesson_groups,
			'assessment'               => $assessment,
		);
	}

	/**
	 * Get learner progress data.
	 *
	 * @return array Learner progress data.
	 */
	public static function get_learner_progress() {
		global $wpdb;

		$table = $wpdb->prefix . '1111_learn_enrollments';

		// Check if table exists.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			return array();
		}

		$enrollments = $wpdb->get_results(
			"SELECT e.*, u.user_login, u.display_name
			FROM $table e
			INNER JOIN {$wpdb->users} u ON e.user_id = u.ID
			ORDER BY e.enrolled_at DESC",
			ARRAY_A
		);

		if ( ! $enrollments ) {
			return array();
		}

		$progress = array();
		foreach ( $enrollments as $enrollment ) {
			$course_term = get_term( $enrollment['course_term_id'], 'course' );
			$course_name = $course_term && ! is_wp_error( $course_term ) ? $course_term->name : __( 'Unknown Course', 'learn' );

			$progress[] = array(
				'user_id'           => $enrollment['user_id'],
				'display_name'      => $enrollment['display_name'],
				'user_login'        => $enrollment['user_login'],
				'course_name'       => $course_name,
				'course_term_id'    => $enrollment['course_term_id'],
				'blog_id'           => $enrollment['blog_id'],
				'enrolled_at'       => $enrollment['enrolled_at'],
				'lessons_completed' => (int) $enrollment['lessons_completed'],
				'total_lessons'     => (int) $enrollment['total_lessons'],
				'current_xp'       => (int) $enrollment['current_xp'],
				'total_xp'         => (int) $enrollment['total_xp'],
				'status'            => $enrollment['status'],
			);
		}

		return $progress;
	}
}
