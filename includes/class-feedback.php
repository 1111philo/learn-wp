<?php
/**
 * Feedback submission and regeneration triggers.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles feedback submission at all five levels and triggers appropriate regeneration.
 *
 * Provides AJAX endpoints for course, plan, lesson, activity, and assessment feedback.
 * Each handler validates input, stores feedback in the appropriate meta, fires a common
 * action, and kicks off regeneration via the orchestrator.
 */
class Learn_Feedback {

	/**
	 * Feedback levels and their corresponding meta keys.
	 *
	 * @var array
	 */
	const FEEDBACK_META_KEYS = array(
		'course'     => '_1111_course_feedback',
		'plan'       => '_1111_plan_feedback',
		'lesson'     => '_1111_lesson_feedback',
		'activity'   => '_1111_activity_feedback',
		'assessment' => '_1111_assessment_feedback',
	);

	/**
	 * Constructor. Registers AJAX handlers.
	 */
	public function __construct() {
		add_action( 'wp_ajax_1111_feedback_course', array( $this, 'ajax_feedback_course' ) );
		add_action( 'wp_ajax_1111_feedback_plan', array( $this, 'ajax_feedback_plan' ) );
		add_action( 'wp_ajax_1111_feedback_lesson', array( $this, 'ajax_feedback_lesson' ) );
		add_action( 'wp_ajax_1111_feedback_activity', array( $this, 'ajax_feedback_activity' ) );
		add_action( 'wp_ajax_1111_feedback_assessment', array( $this, 'ajax_feedback_assessment' ) );
	}

	/**
	 * AJAX handler: course-level feedback.
	 *
	 * Stores feedback in course term meta and triggers regeneration.
	 */
	public function ajax_feedback_course() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! $this->verify_feedback_capability() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to submit feedback.', '1111-learn' ) ),
				403
			);
		}

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$feedback = $this->sanitize_feedback_input();

		if ( is_wp_error( $feedback ) ) {
			wp_send_json_error(
				array( 'message' => $feedback->get_error_message() ),
				400
			);
		}

		$term = get_term( $term_id, 'course' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Course not found.', '1111-learn' ) ),
				404
			);
		}

		// Store feedback.
		update_term_meta( $term_id, self::FEEDBACK_META_KEYS['course'], $feedback );

		/**
		 * Fires after feedback is submitted at any level.
		 *
		 * @param string $level    The feedback level (course, plan, lesson, activity, assessment).
		 * @param array  $context  Context data for the feedback.
		 */
		do_action(
			'1111_learn_feedback_submitted',
			'course',
			array(
				'term_id'  => $term_id,
				'feedback' => $feedback,
			)
		);

		// Trigger regeneration via orchestrator.
		$result = $this->trigger_regeneration( 'course', array( 'term_id' => $term_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		// Clear feedback after successful regeneration.
		delete_term_meta( $term_id, self::FEEDBACK_META_KEYS['course'] );

		wp_send_json_success(
			array( 'message' => __( 'Course feedback submitted and regeneration started.', '1111-learn' ) )
		);
	}

	/**
	 * AJAX handler: lesson plan feedback.
	 *
	 * Stores feedback in lesson_group term meta and triggers regeneration.
	 */
	public function ajax_feedback_plan() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! $this->verify_feedback_capability() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to submit feedback.', '1111-learn' ) ),
				403
			);
		}

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$feedback = $this->sanitize_feedback_input();

		if ( is_wp_error( $feedback ) ) {
			wp_send_json_error(
				array( 'message' => $feedback->get_error_message() ),
				400
			);
		}

		$term = get_term( $term_id, 'lesson_group' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Lesson group not found.', '1111-learn' ) ),
				404
			);
		}

		// Store feedback.
		update_term_meta( $term_id, self::FEEDBACK_META_KEYS['plan'], $feedback );

		do_action(
			'1111_learn_feedback_submitted',
			'plan',
			array(
				'term_id'  => $term_id,
				'feedback' => $feedback,
			)
		);

		$result = $this->trigger_regeneration( 'plan', array( 'term_id' => $term_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		delete_term_meta( $term_id, self::FEEDBACK_META_KEYS['plan'] );

		wp_send_json_success(
			array( 'message' => __( 'Plan feedback submitted and regeneration started.', '1111-learn' ) )
		);
	}

	/**
	 * AJAX handler: lesson feedback.
	 *
	 * Stores feedback in post meta and triggers regeneration.
	 */
	public function ajax_feedback_lesson() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! $this->verify_feedback_capability() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to submit feedback.', '1111-learn' ) ),
				403
			);
		}

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$feedback = $this->sanitize_feedback_input();

		if ( is_wp_error( $feedback ) ) {
			wp_send_json_error(
				array( 'message' => $feedback->get_error_message() ),
				400
			);
		}

		$post = get_post( $post_id );

		if ( ! $post || 'learn' !== $post->post_type ) {
			wp_send_json_error(
				array( 'message' => __( 'Lesson not found.', '1111-learn' ) ),
				404
			);
		}

		// Store feedback.
		update_post_meta( $post_id, self::FEEDBACK_META_KEYS['lesson'], $feedback );

		do_action(
			'1111_learn_feedback_submitted',
			'lesson',
			array(
				'post_id'  => $post_id,
				'feedback' => $feedback,
			)
		);

		$result = $this->trigger_regeneration( 'lesson', array( 'post_id' => $post_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		delete_post_meta( $post_id, self::FEEDBACK_META_KEYS['lesson'] );

		wp_send_json_success(
			array( 'message' => __( 'Lesson feedback submitted and regeneration started.', '1111-learn' ) )
		);
	}

	/**
	 * AJAX handler: activity feedback.
	 *
	 * Stores feedback in post meta and triggers regeneration.
	 */
	public function ajax_feedback_activity() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! $this->verify_feedback_capability() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to submit feedback.', '1111-learn' ) ),
				403
			);
		}

		$post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$feedback = $this->sanitize_feedback_input();

		if ( is_wp_error( $feedback ) ) {
			wp_send_json_error(
				array( 'message' => $feedback->get_error_message() ),
				400
			);
		}

		$post = get_post( $post_id );

		if ( ! $post || 'learn' !== $post->post_type ) {
			wp_send_json_error(
				array( 'message' => __( 'Activity not found.', '1111-learn' ) ),
				404
			);
		}

		// Store feedback.
		update_post_meta( $post_id, self::FEEDBACK_META_KEYS['activity'], $feedback );

		do_action(
			'1111_learn_feedback_submitted',
			'activity',
			array(
				'post_id'  => $post_id,
				'feedback' => $feedback,
			)
		);

		$result = $this->trigger_regeneration( 'activity', array( 'post_id' => $post_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		delete_post_meta( $post_id, self::FEEDBACK_META_KEYS['activity'] );

		wp_send_json_success(
			array( 'message' => __( 'Activity feedback submitted and regeneration started.', '1111-learn' ) )
		);
	}

	/**
	 * AJAX handler: assessment feedback.
	 *
	 * Stores feedback in course term meta and triggers regeneration.
	 */
	public function ajax_feedback_assessment() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! $this->verify_feedback_capability() ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to submit feedback.', '1111-learn' ) ),
				403
			);
		}

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$feedback = $this->sanitize_feedback_input();

		if ( is_wp_error( $feedback ) ) {
			wp_send_json_error(
				array( 'message' => $feedback->get_error_message() ),
				400
			);
		}

		$term = get_term( $term_id, 'course' );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Course not found.', '1111-learn' ) ),
				404
			);
		}

		// Store feedback.
		update_term_meta( $term_id, self::FEEDBACK_META_KEYS['assessment'], $feedback );

		do_action(
			'1111_learn_feedback_submitted',
			'assessment',
			array(
				'term_id'  => $term_id,
				'feedback' => $feedback,
			)
		);

		$result = $this->trigger_regeneration( 'assessment', array( 'term_id' => $term_id ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				500
			);
		}

		delete_term_meta( $term_id, self::FEEDBACK_META_KEYS['assessment'] );

		wp_send_json_success(
			array( 'message' => __( 'Assessment feedback submitted and regeneration started.', '1111-learn' ) )
		);
	}

	/**
	 * Get the regeneration scope for a given feedback level.
	 *
	 * Returns which agents need to re-run when feedback is submitted at each level.
	 *
	 * @param string $level The feedback level.
	 * @return array List of agent names that need to re-run.
	 */
	public function get_regeneration_scope( $level ) {
		$scopes = array(
			'course'     => array( 'course_describer', 'lesson_planner', 'lesson_writer', 'activity_creator', 'activity_reviewer', 'assessment_creator' ),
			'plan'       => array( 'lesson_planner', 'lesson_writer', 'activity_creator', 'activity_reviewer' ),
			'lesson'     => array( 'lesson_writer', 'activity_creator', 'activity_reviewer' ),
			'activity'   => array( 'activity_creator', 'activity_reviewer' ),
			'assessment' => array( 'assessment_creator' ),
		);

		return isset( $scopes[ $level ] ) ? $scopes[ $level ] : array();
	}

	/**
	 * Verify that the current user has permission to submit feedback.
	 *
	 * Super admins on the main site can always submit feedback. On learner subsites,
	 * any member of that subsite can submit feedback.
	 *
	 * @return bool True if the user has the appropriate capability.
	 */
	private function verify_feedback_capability() {
		// Super admins on the main site can always submit.
		if ( current_user_can( 'manage_network' ) ) {
			return true;
		}

		// On subsites, check if the user is a member.
		if ( ! is_main_site() && is_user_member_of_blog() ) {
			return true;
		}

		return false;
	}

	/**
	 * Sanitize and validate the feedback text from the request.
	 *
	 * @return string|WP_Error The sanitized feedback text, or WP_Error on invalid input.
	 */
	private function sanitize_feedback_input() {
		if ( ! isset( $_POST['feedback'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return new WP_Error(
				'missing_feedback',
				__( 'Feedback text is required.', '1111-learn' )
			);
		}

		$feedback = sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $feedback ) ) {
			return new WP_Error(
				'empty_feedback',
				__( 'Feedback text cannot be empty.', '1111-learn' )
			);
		}

		return $feedback;
	}

	/**
	 * Trigger regeneration via the orchestrator for the given feedback level.
	 *
	 * Fires an action that the orchestrator listens to for kicking off the
	 * appropriate agents based on the feedback level.
	 *
	 * @param string $level   The feedback level.
	 * @param array  $context Context data (term_id or post_id).
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function trigger_regeneration( $level, $context ) {
		$agents = $this->get_regeneration_scope( $level );

		if ( empty( $agents ) ) {
			return new WP_Error(
				'invalid_level',
				__( 'Unknown feedback level.', '1111-learn' )
			);
		}

		/**
		 * Fires to trigger regeneration for a specific feedback level.
		 *
		 * The orchestrator hooks into this action to re-run the appropriate agents.
		 *
		 * @param string $level   The feedback level.
		 * @param array  $agents  Agent names that need to re-run.
		 * @param array  $context Context data (term_id or post_id).
		 */
		do_action( '1111_learn_regenerate', $level, $agents, $context );

		return true;
	}
}
