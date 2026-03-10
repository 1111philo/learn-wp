<?php
/**
 * Learner submission and Activity Assessment Agent orchestration.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles learner activity submissions and orchestrates the Activity Assessment Agent.
 *
 * Provides an AJAX endpoint for learners to submit their work from subsites.
 * Extracts content from the learner's subsite, retrieves rubric and mastery criteria,
 * calls the assessment agent, stores results, and updates enrollment progress.
 */
class Learn_Learner_Assessment {

	/**
	 * Submissions table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_SUFFIX = '_1111_learn_submissions';

	/**
	 * Constructor. Registers AJAX handlers.
	 */
	public function __construct() {
		add_action( 'wp_ajax_1111_submit_assessment', array( $this, 'ajax_submit_assessment' ) );
	}

	/**
	 * AJAX handler: submit learner work for assessment.
	 *
	 * Validates the request, extracts content from the learner's subsite,
	 * retrieves rubric data, calls the Activity Assessment Agent, stores
	 * the submission, and updates enrollment progress.
	 */
	public function ajax_submit_assessment() {
		check_ajax_referer( '1111_learn_public', 'nonce' );

		$lesson_post_id = isset( $_POST['lesson_post_id'] ) ? absint( $_POST['lesson_post_id'] ) : 0;
		$blog_id        = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : 0;
		$post_id        = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $lesson_post_id || ! $blog_id || ! $post_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Missing required parameters.', '1111-learn' ) ),
				400
			);
		}

		// Verify user is a member of the submitting subsite.
		$user_id = get_current_user_id();

		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not a member of this site.', '1111-learn' ) ),
				403
			);
		}

		// Extract learner content from the subsite.
		$learner_content = $this->extract_learner_content( $blog_id, $post_id );

		if ( is_wp_error( $learner_content ) ) {
			wp_send_json_error(
				array( 'message' => $learner_content->get_error_message() ),
				400
			);
		}

		// Get rubric and mastery criteria from the lesson post on the main site.
		$rubric_data = $this->get_rubric_data( $lesson_post_id );

		if ( is_wp_error( $rubric_data ) ) {
			wp_send_json_error(
				array( 'message' => $rubric_data->get_error_message() ),
				400
			);
		}

		// Check for existing submission to determine attempt number.
		$attempt_number = $this->get_next_attempt_number( $user_id, $lesson_post_id, $blog_id );

		// Call Activity Assessment Agent via orchestrator.
		$assessment_result = $this->run_assessment_agent( $learner_content, $rubric_data );

		if ( is_wp_error( $assessment_result ) ) {
			wp_send_json_error(
				array( 'message' => $assessment_result->get_error_message() ),
				500
			);
		}

		// Store submission in the database.
		$submission_id = $this->store_submission(
			array(
				'user_id'          => $user_id,
				'lesson_post_id'   => $lesson_post_id,
				'blog_id'          => $blog_id,
				'post_id'          => $post_id,
				'attempt_number'   => $attempt_number,
				'content_snapshot' => $learner_content['content'],
				'assessment_data'  => $assessment_result,
			)
		);

		if ( is_wp_error( $submission_id ) ) {
			wp_send_json_error(
				array( 'message' => $submission_id->get_error_message() ),
				500
			);
		}

		// Update enrollment progress.
		$this->update_enrollment_progress( $user_id, $lesson_post_id, $assessment_result );

		wp_send_json_success(
			array(
				'message'        => __( 'Assessment complete.', '1111-learn' ),
				'submission_id'  => $submission_id,
				'attempt_number' => $attempt_number,
				'result'         => $assessment_result,
			)
		);
	}

	/**
	 * Extract content from a learner's subsite post.
	 *
	 * Switches to the target blog, reads the post title and content,
	 * then restores the current blog.
	 *
	 * @param int $blog_id The subsite blog ID.
	 * @param int $post_id The post ID on the subsite.
	 * @return array|WP_Error Array with 'title' and 'content' keys, or WP_Error.
	 */
	private function extract_learner_content( $blog_id, $post_id ) {
		switch_to_blog( $blog_id );

		$post = get_post( $post_id );

		if ( ! $post ) {
			restore_current_blog();

			return new WP_Error(
				'post_not_found',
				__( 'The submitted content was not found on the learner site.', '1111-learn' )
			);
		}

		$title   = $post->post_title;
		$content = wp_strip_all_tags( $post->post_content );

		restore_current_blog();

		if ( empty( $content ) ) {
			return new WP_Error(
				'empty_content',
				__( 'The submitted content is empty.', '1111-learn' )
			);
		}

		return array(
			'title'   => $title,
			'content' => $title . "\n\n" . $content,
		);
	}

	/**
	 * Get rubric and mastery criteria from a lesson post's meta.
	 *
	 * @param int $lesson_post_id The lesson post ID on the main site.
	 * @return array|WP_Error Array with 'scoring_rubric' and 'mastery_criteria' keys, or WP_Error.
	 */
	private function get_rubric_data( $lesson_post_id ) {
		$post = get_post( $lesson_post_id );

		if ( ! $post || 'learn' !== $post->post_type ) {
			return new WP_Error(
				'lesson_not_found',
				__( 'Lesson not found.', '1111-learn' )
			);
		}

		$scoring_rubric   = get_post_meta( $lesson_post_id, '_1111_scoring_rubric', true );
		$mastery_criteria = get_post_meta( $lesson_post_id, '_1111_mastery_criteria', true );

		if ( empty( $scoring_rubric ) ) {
			return new WP_Error(
				'missing_rubric',
				__( 'No scoring rubric found for this lesson.', '1111-learn' )
			);
		}

		return array(
			'scoring_rubric'   => $scoring_rubric,
			'mastery_criteria' => $mastery_criteria,
		);
	}

	/**
	 * Determine the next attempt number for a given user and lesson.
	 *
	 * @param int $user_id         The user ID.
	 * @param int $lesson_post_id  The lesson post ID.
	 * @param int $blog_id         The subsite blog ID.
	 * @return int The next attempt number (1-based).
	 */
	private function get_next_attempt_number( $user_id, $lesson_post_id, $blog_id ) {
		global $wpdb;

		$table_name = $wpdb->base_prefix . ltrim( self::TABLE_SUFFIX, '_' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$max_attempt = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(attempt_number) FROM {$table_name} WHERE user_id = %d AND lesson_post_id = %d AND blog_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$lesson_post_id,
				$blog_id
			)
		);

		return $max_attempt ? ( (int) $max_attempt + 1 ) : 1;
	}

	/**
	 * Call the Activity Assessment Agent via the orchestrator.
	 *
	 * @param array $learner_content Array with 'title' and 'content' keys.
	 * @param array $rubric_data     Array with 'scoring_rubric' and 'mastery_criteria' keys.
	 * @return array|WP_Error Assessment result array, or WP_Error on failure.
	 */
	private function run_assessment_agent( $learner_content, $rubric_data ) {
		/**
		 * Filters the assessment agent input before calling the orchestrator.
		 *
		 * @param array $input Assessment input data.
		 */
		$input = apply_filters(
			'1111_learn_assessment_input',
			array(
				'learner_content'  => $learner_content['content'],
				'scoring_rubric'   => $rubric_data['scoring_rubric'],
				'mastery_criteria' => $rubric_data['mastery_criteria'],
			)
		);

		/**
		 * Fires to trigger the Activity Assessment Agent.
		 *
		 * The orchestrator hooks into this action to run the assessment.
		 * The result is passed back via the filter below.
		 *
		 * @param array $input Assessment input data.
		 */
		do_action( '1111_learn_run_assessment', $input );

		/**
		 * Filters the assessment agent result.
		 *
		 * The orchestrator populates this with the agent output.
		 *
		 * @param array|null $result The assessment result, null if not yet populated.
		 * @param array      $input  The assessment input data.
		 */
		$result = apply_filters( '1111_learn_assessment_result', null, $input );

		if ( null === $result ) {
			return new WP_Error(
				'assessment_failed',
				__( 'The assessment agent did not return a result.', '1111-learn' )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Store a submission in the custom submissions table.
	 *
	 * @param array $data Submission data.
	 * @return int|WP_Error The submission ID, or WP_Error on failure.
	 */
	private function store_submission( $data ) {
		global $wpdb;

		$table_name = $wpdb->base_prefix . ltrim( self::TABLE_SUFFIX, '_' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'          => $data['user_id'],
				'lesson_post_id'   => $data['lesson_post_id'],
				'blog_id'          => $data['blog_id'],
				'post_id'          => $data['post_id'],
				'attempt_number'   => $data['attempt_number'],
				'content_snapshot' => $data['content_snapshot'],
				'assessment_data'  => wp_json_encode( $data['assessment_data'] ),
				'submitted_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'submission_save_failed',
				__( 'Failed to save the submission.', '1111-learn' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update enrollment progress after a successful assessment.
	 *
	 * Increments lessons_completed and adds earned XP to the learner's
	 * enrollment record when the assessment recommends advancement.
	 *
	 * @param int   $user_id          The user ID.
	 * @param int   $lesson_post_id   The lesson post ID.
	 * @param array $assessment_result The assessment result from the agent.
	 */
	private function update_enrollment_progress( $user_id, $lesson_post_id, $assessment_result ) {
		// Only update progress if the recommendation is to advance.
		$recommendation = isset( $assessment_result['recommendation'] ) ? $assessment_result['recommendation'] : '';

		if ( 'advance' !== $recommendation ) {
			return;
		}

		// Get the course term(s) for this lesson.
		$terms = wp_get_post_terms( $lesson_post_id, 'course', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$course_term_id = $terms[0];

		// Build a user meta key scoped to the course.
		$progress_key = '_1111_course_progress_' . $course_term_id;
		$progress     = get_user_meta( $user_id, $progress_key, true );

		if ( ! is_array( $progress ) ) {
			$progress = array(
				'lessons_completed' => array(),
				'current_xp'       => 0,
			);
		}

		// Mark lesson as completed (avoid duplicates).
		if ( ! in_array( $lesson_post_id, $progress['lessons_completed'], true ) ) {
			$progress['lessons_completed'][] = $lesson_post_id;
		}

		// Add XP from the lesson's activity.
		$xp_value = get_post_meta( $lesson_post_id, '_1111_xp_value', true );

		if ( $xp_value ) {
			$progress['current_xp'] += (int) $xp_value;
		}

		update_user_meta( $user_id, $progress_key, $progress );

		/**
		 * Fires after enrollment progress is updated.
		 *
		 * @param int   $user_id          The user ID.
		 * @param int   $course_term_id   The course term ID.
		 * @param int   $lesson_post_id   The completed lesson post ID.
		 * @param array $progress         The updated progress data.
		 * @param array $assessment_result The assessment result.
		 */
		do_action( '1111_learn_progress_updated', $user_id, $course_term_id, $lesson_post_id, $progress, $assessment_result );
	}
}
