<?php
/**
 * Learner assessment pipeline.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Assessment
 *
 * Handles learner activity submission, content extraction from learner subsites,
 * Activity Assessment Agent invocation, and results storage.
 */
class Learn_Assessment {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_1111_submit_assessment', array( __CLASS__, 'ajax_submit' ) );
	}

	/**
	 * AJAX: Submit learner work for assessment.
	 */
	public static function ajax_submit() {
		check_ajax_referer( '1111_learn_submit', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'learn' ) ), 403 );
		}

		$lesson_post_id = isset( $_POST['lesson_post_id'] ) ? absint( $_POST['lesson_post_id'] ) : 0;
		if ( ! $lesson_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Lesson post ID required.', 'learn' ) ), 400 );
		}

		$learner_blog_id = get_current_blog_id();
		if ( is_main_site() ) {
			wp_send_json_error( array( 'message' => __( 'Submissions are made from your learner site.', 'learn' ) ), 400 );
		}

		// Get the lesson post on the learner's subsite.
		$lesson_post = get_post( $lesson_post_id );
		if ( ! $lesson_post || 'learn' !== $lesson_post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid lesson post.', 'learn' ) ), 400 );
		}

		$activity       = get_post_meta( $lesson_post_id, '_1111_activity', true );
		$activity_type  = get_post_meta( $lesson_post_id, '_1111_activity_type', true );
		$mastery        = get_post_meta( $lesson_post_id, '_1111_mastery_criteria', true );
		$work_product   = '';
		$portfolio_contribution = isset( $activity['portfolio_contribution'] ) ? $activity['portfolio_contribution'] : '';

		if ( ! $activity ) {
			wp_send_json_error( array( 'message' => __( 'No activity found for this lesson.', 'learn' ) ), 400 );
		}

		// Get course term ID for this lesson.
		$course_terms = wp_get_object_terms( $lesson_post_id, 'course', array( 'fields' => 'ids' ) );
		$course_term_id = ! empty( $course_terms ) ? $course_terms[0] : 0;

		if ( $course_term_id ) {
			$work_product = get_term_meta( $course_term_id, '_1111_work_product', true );
		}

		// Extract learner's content.
		$learner_content = self::extract_learner_content( $user_id, $learner_blog_id );
		if ( is_wp_error( $learner_content ) ) {
			wp_send_json_error( array( 'message' => $learner_content->get_error_message() ), 400 );
		}

		// Build rubric criteria for the prompt.
		$rubric = isset( $activity['scoring_rubric'] ) ? $activity['scoring_rubric'] : array();

		// Call Activity Assessment Agent.
		$prompt = Learn_Prompt_Loader::load( 'activity-assessment' );
		if ( is_wp_error( $prompt ) ) {
			wp_send_json_error( array( 'message' => $prompt->get_error_message() ), 500 );
		}

		$user_message = self::build_assessment_input(
			$activity, $mastery, $work_product, $learner_content, $rubric
		);

		$result = Learn_API_Client::call_agent( $prompt, $user_message, LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$valid = Learn_Validator::validate_activity_assessment( $result );
		if ( is_wp_error( $valid ) ) {
			wp_send_json_error( array( 'message' => $valid->get_error_message() ), 500 );
		}

		// Get attempt number.
		$attempt = self::get_next_attempt( $user_id, $learner_blog_id, $lesson_post_id, $course_term_id );

		// Store submission on the main site.
		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		global $wpdb;
		$table = $wpdb->prefix . '1111_learn_submissions';

		$wpdb->insert( $table, array(
			'user_id'          => $user_id,
			'blog_id'          => $learner_blog_id,
			'post_id'          => $lesson_post_id,
			'lesson_post_id'   => $lesson_post_id,
			'course_term_id'   => $course_term_id,
			'activity_type'    => $activity_type,
			'submitted_at'     => gmdate( 'Y-m-d H:i:s' ),
			'score'            => $result['score'],
			'recommendation'   => $result['recommendation'],
			'assessment_json'  => wp_json_encode( $result ),
			'assessed_at'      => gmdate( 'Y-m-d H:i:s' ),
			'attempt_number'   => $attempt,
			'content_snapshot' => wp_json_encode( $learner_content ),
		), array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%d', '%s' ) );

		// Update enrollment progress.
		if ( 'advance' === $result['recommendation'] || 'continue' === $result['recommendation'] ) {
			$xp_value = (int) get_post_meta( $lesson_post_id, '_1111_xp_value', true );
			self::update_enrollment_progress( $user_id, $learner_blog_id, $course_term_id, $xp_value );
		}

		restore_current_blog();

		// Telemetry.
		Learn_Telemetry::submission_assessed( $activity_type, $result['score'], $result['recommendation'], $attempt );

		wp_send_json_success( array(
			'score'          => $result['score'],
			'recommendation' => $result['recommendation'],
			'strengths'      => $result['strengths'],
			'improvements'   => $result['improvements'],
			'rubric_results' => $result['rubric_results'],
			'portfolio_check' => $result['portfolio_check'],
		) );
	}

	/**
	 * Extract learner's WordPress content from their subsite.
	 *
	 * @param int $user_id User ID.
	 * @param int $blog_id Blog ID.
	 * @return array|WP_Error Content data.
	 */
	private static function extract_learner_content( $user_id, $blog_id ) {
		// Get recent posts and pages by this user.
		$posts = get_posts( array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => array( 'publish', 'draft' ),
			'author'      => $user_id,
			'numberposts' => 20,
			'orderby'     => 'modified',
			'order'       => 'DESC',
		) );

		if ( empty( $posts ) ) {
			return new WP_Error(
				'no_content',
				__( 'No content found on your site. Create posts or pages as directed by the activity before submitting.', 'learn' )
			);
		}

		$content = array();
		foreach ( $posts as $post ) {
			$content[] = array(
				'type'    => $post->post_type,
				'title'   => $post->post_title,
				'content' => wp_strip_all_tags( $post->post_content ),
				'status'  => $post->post_status,
			);
		}

		return $content;
	}

	/**
	 * Build user message for Activity Assessment Agent.
	 *
	 * @param array  $activity        Activity data.
	 * @param array  $mastery         Mastery criteria.
	 * @param string $work_product    Work product name.
	 * @param array  $learner_content Extracted learner content.
	 * @param array  $rubric          Scoring rubric.
	 * @return string
	 */
	private static function build_assessment_input( $activity, $mastery, $work_product, $learner_content, $rubric ) {
		$msg  = "Activity type: " . $activity['activity_type'] . "\n\n";
		$msg .= "Activity prompt: " . $activity['prompt'] . "\n\n";

		if ( $work_product ) {
			$msg .= "Work product: $work_product\n\n";
		}

		if ( ! empty( $mastery ) ) {
			$msg .= "Mastery criteria:\n";
			foreach ( $mastery as $criterion ) {
				$msg .= "- $criterion\n";
			}
			$msg .= "\n";
		}

		if ( ! empty( $rubric ) ) {
			$msg .= "Scoring rubric:\n";
			foreach ( $rubric as $r ) {
				$msg .= "- $r\n";
			}
			$msg .= "\n";
		}

		$msg .= "Learner's submitted content:\n\n";
		foreach ( $learner_content as $item ) {
			$msg .= "--- " . ucfirst( $item['type'] ) . ": " . $item['title'] . " ---\n";
			$msg .= $item['content'] . "\n\n";
		}

		return $msg;
	}

	/**
	 * Get next attempt number for a submission.
	 *
	 * @param int $user_id         User ID.
	 * @param int $blog_id         Blog ID.
	 * @param int $lesson_post_id  Lesson post ID.
	 * @param int $course_term_id  Course term ID.
	 * @return int
	 */
	private static function get_next_attempt( $user_id, $blog_id, $lesson_post_id, $course_term_id ) {
		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		global $wpdb;
		$table = $wpdb->prefix . '1111_learn_submissions';

		$max = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(attempt_number) FROM $table WHERE user_id = %d AND blog_id = %d AND lesson_post_id = %d AND course_term_id = %d",
				$user_id, $blog_id, $lesson_post_id, $course_term_id
			)
		);

		restore_current_blog();

		return $max ? (int) $max + 1 : 1;
	}

	/**
	 * Update enrollment progress after a successful submission.
	 *
	 * @param int $user_id        User ID.
	 * @param int $blog_id        Blog ID.
	 * @param int $course_term_id Course term ID.
	 * @param int $xp_earned      XP earned.
	 */
	private static function update_enrollment_progress( $user_id, $blog_id, $course_term_id, $xp_earned ) {
		global $wpdb;
		$table = $wpdb->prefix . '1111_learn_enrollments';

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $table SET lessons_completed = lessons_completed + 1, current_xp = current_xp + %d WHERE user_id = %d AND blog_id = %d AND course_term_id = %d",
				$xp_earned, $user_id, $blog_id, $course_term_id
			)
		);
	}
}
