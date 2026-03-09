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
		add_action( 'wp_ajax_1111_submit_feedback', array( __CLASS__, 'ajax_submit_feedback' ) );
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

	/**
	 * AJAX: Submit feedback at any level and trigger regeneration.
	 */
	public static function ajax_submit_feedback() {
		check_ajax_referer( '1111_learn_feedback', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'learn' ) ), 403 );
		}

		$level    = isset( $_POST['level'] ) ? sanitize_text_field( wp_unslash( $_POST['level'] ) ) : '';
		$feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';

		if ( ! $feedback ) {
			wp_send_json_error( array( 'message' => __( 'Feedback text is required.', 'learn' ) ), 400 );
		}

		$valid_levels = array( 'course', 'plan', 'lesson', 'activity', 'assessment' );
		if ( ! in_array( $level, $valid_levels, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid feedback level.', 'learn' ) ), 400 );
		}

		switch ( $level ) {
			case 'course':
				$result = self::handle_course_feedback( $feedback );
				break;
			case 'plan':
				$result = self::handle_plan_feedback( $feedback );
				break;
			case 'lesson':
				$result = self::handle_lesson_feedback( $feedback );
				break;
			case 'activity':
				$result = self::handle_activity_feedback( $feedback );
				break;
			case 'assessment':
				$result = self::handle_assessment_feedback( $feedback );
				break;
			default:
				$result = new WP_Error( 'invalid_level', __( 'Invalid feedback level.', 'learn' ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handle course-level feedback — re-run entire pipeline.
	 *
	 * @param string $feedback Feedback text.
	 * @return array|WP_Error
	 */
	private static function handle_course_feedback( $feedback ) {
		$term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;
		if ( ! $term_id ) {
			return new WP_Error( 'missing_term', __( 'Course term ID required.', 'learn' ) );
		}

		// Only super admins can regenerate on the main site.
		if ( is_main_site() && ! is_super_admin() ) {
			return new WP_Error( 'permission', __( 'Permission denied.', 'learn' ) );
		}

		update_term_meta( $term_id, '_1111_course_feedback', $feedback );
		update_term_meta( $term_id, '_1111_generation_status', 'generating' );

		Learn_Orchestrator::schedule_next( $term_id, 'phase_0' );

		return array( 'message' => __( 'Course regeneration started.', 'learn' ) );
	}

	/**
	 * Handle plan-level feedback — re-run planner + downstream for one objective.
	 *
	 * @param string $feedback Feedback text.
	 * @return array|WP_Error
	 */
	private static function handle_plan_feedback( $feedback ) {
		$group_term_id = isset( $_POST['group_term_id'] ) ? absint( $_POST['group_term_id'] ) : 0;
		if ( ! $group_term_id ) {
			return new WP_Error( 'missing_group', __( 'Lesson group term ID required.', 'learn' ) );
		}

		$course_term_id  = get_term_meta( $group_term_id, '_1111_course_term_id', true );
		$objective_index = get_term_meta( $group_term_id, '_1111_objective_index', true );

		if ( ! $course_term_id ) {
			return new WP_Error( 'invalid_group', __( 'Invalid lesson group.', 'learn' ) );
		}

		update_term_meta( $group_term_id, '_1111_plan_feedback', $feedback );
		update_term_meta( $course_term_id, '_1111_generation_status', 'generating' );

		// Delete existing posts for this group so they get regenerated.
		$existing_posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array(
				array( 'taxonomy' => 'lesson_group', 'terms' => $group_term_id ),
			),
		) );

		foreach ( $existing_posts as $pid ) {
			wp_delete_post( $pid, true );
		}

		Learn_Orchestrator::schedule_next( $course_term_id, 'plan_' . $objective_index );

		return array( 'message' => __( 'Plan regeneration started.', 'learn' ) );
	}

	/**
	 * Handle lesson-level feedback — re-run Lesson Writer only.
	 *
	 * @param string $feedback Feedback text.
	 * @return array|WP_Error
	 */
	private static function handle_lesson_feedback( $feedback ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post', __( 'Post ID required.', 'learn' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'learn' !== $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post.', 'learn' ) );
		}

		// Get lesson context.
		$group_terms = wp_get_object_terms( $post_id, 'lesson_group', array( 'fields' => 'ids' ) );
		$course_terms = wp_get_object_terms( $post_id, 'course', array( 'fields' => 'ids' ) );

		if ( empty( $group_terms ) || empty( $course_terms ) ) {
			return new WP_Error( 'missing_terms', __( 'Post missing taxonomy terms.', 'learn' ) );
		}

		$group_term_id = $group_terms[0];
		$course_term_id = $course_terms[0];
		$plan = get_term_meta( $group_term_id, '_1111_lesson_plan_raw', true );
		$narrative = get_term_meta( $course_term_id, '_1111_narrative_description', true );

		if ( ! $plan ) {
			return new WP_Error( 'no_plan', __( 'No lesson plan found.', 'learn' ) );
		}

		// Find which lesson in the plan this post is.
		$lesson_index = 0;
		foreach ( $plan['lessons'] as $i => $lesson ) {
			if ( $lesson['lesson_title'] === $post->post_title ) {
				$lesson_index = $i;
				break;
			}
		}

		$lesson_outline = $plan['lessons'][ $lesson_index ];

		$prompt = Learn_Prompt_Loader::load( 'lesson-writer' );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}

		$user_message = Learn_Orchestrator::build_lesson_writer_input_static(
			$narrative,
			$lesson_outline['lesson_title'],
			$lesson_outline['lesson_outline'],
			$plan['mastery_criteria'],
			$plan['key_concepts'],
			$feedback
		);

		$result = Learn_API_Client::call_agent( $prompt, $user_message, LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$valid = Learn_Validator::validate_lesson_writer( $result );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$block_content = Learn_Orchestrator::markdown_to_blocks( $result['lesson_body'] );
		$agent_user_id = Learn_Agent_User::get_id();

		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => $result['lesson_title'],
			'post_content' => wp_kses_post( $block_content ),
			'post_author'  => $agent_user_id,
		) );

		$version = (int) get_post_meta( $post_id, '_1111_lesson_version', true );
		update_post_meta( $post_id, '_1111_lesson_version', $version + 1 );
		update_post_meta( $post_id, '_1111_key_takeaways', $result['key_takeaways'] );

		return array( 'message' => __( 'Lesson regenerated.', 'learn' ) );
	}

	/**
	 * Handle activity-level feedback — re-run Activity Creator + Reviewer.
	 *
	 * @param string $feedback Feedback text.
	 * @return array|WP_Error
	 */
	private static function handle_activity_feedback( $feedback ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new WP_Error( 'missing_post', __( 'Post ID required.', 'learn' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'learn' !== $post->post_type ) {
			return new WP_Error( 'invalid_post', __( 'Invalid post.', 'learn' ) );
		}

		$group_terms  = wp_get_object_terms( $post_id, 'lesson_group', array( 'fields' => 'ids' ) );
		$course_terms = wp_get_object_terms( $post_id, 'course', array( 'fields' => 'ids' ) );

		if ( empty( $group_terms ) || empty( $course_terms ) ) {
			return new WP_Error( 'missing_terms', __( 'Post missing taxonomy terms.', 'learn' ) );
		}

		$group_term_id  = $group_terms[0];
		$course_term_id = $course_terms[0];
		$plan = get_term_meta( $group_term_id, '_1111_lesson_plan_raw', true );
		$work_product = get_term_meta( $course_term_id, '_1111_work_product', true );
		$work_product_type = get_term_meta( $course_term_id, '_1111_work_product_type', true );

		if ( ! $plan ) {
			return new WP_Error( 'no_plan', __( 'No lesson plan found.', 'learn' ) );
		}

		// Re-create activity with feedback.
		$activity_type = get_post_meta( $post_id, '_1111_activity_type', true );

		$prompt = Learn_Prompt_Loader::load( 'activity-creator' );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}

		$user_message  = "Learning objective: " . $plan['learning_objective'] . "\n\n";
		$user_message .= "Activity type: $activity_type\n\n";
		$user_message .= "Work product: $work_product\n";
		$user_message .= "Work product type: $work_product_type\n\n";
		$user_message .= "Mastery criteria:\n";
		foreach ( $plan['mastery_criteria'] as $item ) {
			$user_message .= "- $item\n";
		}
		$user_message .= "\nActivity seed:\n" . wp_json_encode( $plan['suggested_activity'], JSON_PRETTY_PRINT ) . "\n";
		$user_message .= "\nFeedback on previous activity: $feedback";

		$result = Learn_API_Client::call_agent( $prompt, $user_message, LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$valid = Learn_Validator::validate_activity_creator( $result );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		update_post_meta( $post_id, '_1111_activity', $result );
		update_post_meta( $post_id, '_1111_activity_type', $result['activity_type'] );
		update_post_meta( $post_id, '_1111_xp_value', $result['xp_value'] );
		update_post_meta( $post_id, '_1111_milestone', isset( $result['milestone'] ) ? $result['milestone'] : null );
		update_post_meta( $post_id, '_1111_portfolio_contribution', $result['portfolio_contribution'] );

		$version = (int) get_post_meta( $post_id, '_1111_activity_version', true );
		update_post_meta( $post_id, '_1111_activity_version', $version + 1 );

		return array( 'message' => __( 'Activity regenerated.', 'learn' ) );
	}

	/**
	 * Handle assessment-level feedback — re-run Assessment Creator.
	 *
	 * @param string $feedback Feedback text.
	 * @return array|WP_Error
	 */
	private static function handle_assessment_feedback( $feedback ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$course_term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;

		if ( ! $post_id || ! $course_term_id ) {
			return new WP_Error( 'missing_ids', __( 'Post ID and course term ID required.', 'learn' ) );
		}

		update_term_meta( $course_term_id, '_1111_assessment_feedback', $feedback );

		// Re-run assessment creator as a single inline call.
		$term = get_term( $course_term_id, 'course' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'invalid_term', __( 'Course not found.', 'learn' ) );
		}

		$narrative    = get_term_meta( $course_term_id, '_1111_narrative_description', true );
		$objectives   = get_term_meta( $course_term_id, '_1111_learning_objectives', true );
		$work_product = get_term_meta( $course_term_id, '_1111_work_product', true );
		$wp_type      = get_term_meta( $course_term_id, '_1111_work_product_type', true );

		$lesson_posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => array( 'draft', 'publish' ),
			'numberposts' => -1,
			'tax_query'   => array( array( 'taxonomy' => 'course', 'terms' => $course_term_id ) ),
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );

		$all_mastery    = array();
		$all_activities = array();
		foreach ( $lesson_posts as $lp ) {
			if ( 'final' === get_post_meta( $lp->ID, '_1111_activity_type', true ) ) {
				continue;
			}
			$obj_idx  = (int) get_post_meta( $lp->ID, '_1111_objective_index', true );
			$mastery  = get_post_meta( $lp->ID, '_1111_mastery_criteria', true );
			$activity = get_post_meta( $lp->ID, '_1111_activity', true );
			if ( $mastery && ! isset( $all_mastery[ $obj_idx ] ) ) {
				$all_mastery[ $obj_idx ] = array( 'objective' => $objectives[ $obj_idx ], 'criteria' => $mastery );
			}
			if ( $activity ) {
				$all_activities[] = array(
					'lesson_title'           => $lp->post_title,
					'activity_type'          => $activity['activity_type'],
					'prompt'                 => $activity['prompt'],
					'portfolio_contribution' => $activity['portfolio_contribution'],
				);
			}
		}

		$prompt = Learn_Prompt_Loader::load( 'assessment-creator' );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}

		$user_message  = "Course title: " . $term->name . "\n\n";
		$user_message .= "Course description: $narrative\n\n";
		$user_message .= "Work product: $work_product\nWork product type: $wp_type\n\n";
		$user_message .= "Learning objectives:\n";
		foreach ( $objectives as $i => $obj ) {
			$user_message .= ( $i + 1 ) . ". $obj\n";
		}
		$user_message .= "\nMastery criteria:\n";
		foreach ( $all_mastery as $m ) {
			$user_message .= 'Objective: ' . $m['objective'] . "\n";
			foreach ( $m['criteria'] as $c ) {
				$user_message .= "  - $c\n";
			}
		}
		$user_message .= "\nActivities:\n";
		foreach ( $all_activities as $a ) {
			$user_message .= "- [{$a['activity_type']}] {$a['lesson_title']}: {$a['prompt']}\n";
		}
		$user_message .= "\nFeedback on previous assessment: $feedback";

		$result = Learn_API_Client::call_agent( $prompt, $user_message, LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$valid = Learn_Validator::validate_assessment_creator( $result, count( $objectives ) );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$agent_user_id      = Learn_Agent_User::get_id();
		$assessment_content = Learn_Orchestrator::build_assessment_block_content_static( $result );

		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => $result['assessment_title'],
			'post_content' => wp_kses_post( $assessment_content ),
			'post_author'  => $agent_user_id,
		) );

		update_post_meta( $post_id, '_1111_activity', $result );
		update_term_meta( $course_term_id, '_1111_assessment', $result );
		delete_term_meta( $course_term_id, '_1111_assessment_feedback' );

		$version = (int) get_post_meta( $post_id, '_1111_activity_version', true );
		update_post_meta( $post_id, '_1111_activity_version', $version + 1 );

		return array( 'message' => __( 'Assessment regenerated.', 'learn' ) );
	}
}
