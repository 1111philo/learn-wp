<?php
/**
 * Learner assessment submission pipeline.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Assessment {
	/**
	 * Singleton.
	 *
	 * @var Learn_Assessment|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton.
	 *
	 * @return Learn_Assessment
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
		add_action( 'wp_ajax_1111_learn_submit_assessment', array( $this, 'ajax_submit' ) );
	}

	/**
	 * Handles assessment submission.
	 *
	 * @return void
	 */
	public function ajax_submit() {
		check_ajax_referer( '1111_learn_enroll', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'learn-wp' ) ), 403 );
		}

		$lesson_post_id     = isset( $_POST['lesson_post_id'] ) ? absint( $_POST['lesson_post_id'] ) : 0;
		$submission_blog_id = isset( $_POST['blog_id'] ) ? absint( $_POST['blog_id'] ) : get_current_blog_id();
		$submission_post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $lesson_post_id || ! $submission_post_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing lesson or submission content.', 'learn-wp' ) ), 400 );
		}

		switch_to_blog( $submission_blog_id );
		$submission_post = get_post( $submission_post_id );
		restore_current_blog();

		if ( ! $submission_post ) {
			wp_send_json_error( array( 'message' => __( 'Submission post not found.', 'learn-wp' ) ), 404 );
		}

		$activity_meta = get_post_meta( $lesson_post_id, '_1111_activity', true );
		$mastery_meta  = get_post_meta( $lesson_post_id, '_1111_mastery_criteria', true );
		$activity      = json_decode( (string) $activity_meta, true );
		$mastery       = json_decode( (string) $mastery_meta, true );

		$payload = apply_filters(
			'1111_learn_before_assess',
			array(
				'lesson_title'        => get_the_title( $lesson_post_id ),
				'activity'            => is_array( $activity ) ? $activity : array(),
				'mastery_criteria'    => is_array( $mastery ) ? $mastery : array(),
				'learner_submission'  => wp_strip_all_tags( $submission_post->post_content ),
			)
		);

		$agent  = new Learn_Activity_Assessment_Agent();
		$result = $agent->run( $payload );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$submission_id = $this->store_submission(
			get_current_user_id(),
			$submission_blog_id,
			$lesson_post_id,
			$submission_post_id,
			$result,
			$submission_post->post_content
		);

		do_action( '1111_learn_submission_assessed', $submission_id, (float) $result['score'], (string) $result['recommendation'] );
		wp_send_json_success( array( 'submission_id' => $submission_id, 'assessment' => $result ) );
	}

	/**
	 * Persist submission attempt.
	 *
	 * @param int    $user_id User id.
	 * @param int    $blog_id Blog id.
	 * @param int    $lesson_post_id Lesson id.
	 * @param int    $submission_post_id Submission post id.
	 * @param array  $result Assessment result.
	 * @param string $content_snapshot Snapshot.
	 * @return int
	 */
	private function store_submission( $user_id, $blog_id, $lesson_post_id, $submission_post_id, $result, $content_snapshot ) {
		global $wpdb;
		$table = $wpdb->base_prefix . '1111_learn_submissions';

		$attempt = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(attempt_number) FROM {$table} WHERE blog_id = %d AND user_id = %d AND lesson_post_id = %d",
				$blog_id,
				$user_id,
				$lesson_post_id
			)
		);
		$attempt = $attempt + 1;

		$wpdb->insert(
			$table,
			array(
				'blog_id'            => $blog_id,
				'user_id'            => $user_id,
				'lesson_post_id'     => $lesson_post_id,
				'submission_post_id' => $submission_post_id,
				'attempt_number'     => $attempt,
				'score'              => round( (float) $result['score'], 4 ),
				'recommendation'     => sanitize_key( $result['recommendation'] ),
				'assessment_json'    => wp_json_encode( $result ),
				'content_snapshot'   => wp_kses_post( $content_snapshot ),
			)
		);

		$submission_id = (int) $wpdb->insert_id;
		$this->update_enrollment_progress( $user_id, $blog_id, $lesson_post_id );
		return $submission_id;
	}

	/**
	 * Update enrollment completion and XP.
	 *
	 * @param int $user_id User id.
	 * @param int $blog_id Blog id.
	 * @param int $lesson_post_id Lesson id.
	 * @return void
	 */
	private function update_enrollment_progress( $user_id, $blog_id, $lesson_post_id ) {
		global $wpdb;
		$table = $wpdb->base_prefix . '1111_learn_enrollments';
		$xp    = (int) get_post_meta( $lesson_post_id, '_1111_xp_value', true );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET lessons_completed = lessons_completed + 1, current_xp = current_xp + %d WHERE blog_id = %d AND user_id = %d",
				$xp,
				$blog_id,
				$user_id
			)
		);
	}
}
