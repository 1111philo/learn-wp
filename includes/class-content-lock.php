<?php
/**
 * Block editor content locking for agent-authored posts.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Content_Lock
 *
 * Prevents human users from editing post_content of agent-authored learn posts.
 * Implements both server-side guard (wp_insert_post_data) and client-side
 * block locking via enqueued editor script.
 */
class Learn_Content_Lock {

	/**
	 * Initialize content locking hooks.
	 */
	public static function init() {
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'guard_post_content' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'lock_title_placeholder' ), 10, 2 );
	}

	/**
	 * Server-side guard: reject content changes from non-agent users.
	 *
	 * @param array $data    Slashed post data.
	 * @param array $postarr Raw post data including ID.
	 * @return array Filtered post data.
	 */
	public static function guard_post_content( $data, $postarr ) {
		if ( 'learn' !== $data['post_type'] ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! $post_id ) {
			return $data;
		}

		$existing = get_post( $post_id );
		if ( ! $existing ) {
			return $data;
		}

		// Only protect agent-authored posts.
		$agent_id = Learn_Agent_User::get_id();
		if ( (int) $existing->post_author !== $agent_id ) {
			return $data;
		}

		// Allow the agent user to make changes.
		if ( get_current_user_id() === $agent_id ) {
			return $data;
		}

		// Allow status changes (draft → publish) but not content changes.
		$data['post_content'] = $existing->post_content;
		$data['post_title']   = $existing->post_title;
		$data['post_excerpt'] = $existing->post_excerpt;

		return $data;
	}

	/**
	 * Enqueue block editor assets for learn posts.
	 */
	public static function enqueue_editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'learn' !== $screen->post_type ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$agent_id     = Learn_Agent_User::get_id();
		$is_agent_post = ( (int) $post->post_author === $agent_id );

		if ( ! $is_agent_post ) {
			return;
		}

		$is_generated = (bool) get_post_meta( $post_id, '_1111_generated', true );
		$activity     = get_post_meta( $post_id, '_1111_activity', true );
		$activity_type = get_post_meta( $post_id, '_1111_activity_type', true );
		$review       = get_post_meta( $post_id, '_1111_activity_review', true );
		$takeaways    = get_post_meta( $post_id, '_1111_key_takeaways', true );
		$xp_value     = (int) get_post_meta( $post_id, '_1111_xp_value', true );
		$milestone    = get_post_meta( $post_id, '_1111_milestone', true );
		$portfolio    = isset( $activity['portfolio_contribution'] ) ? $activity['portfolio_contribution'] : '';
		$lesson_ver   = (int) get_post_meta( $post_id, '_1111_lesson_version', true );
		$activity_ver = (int) get_post_meta( $post_id, '_1111_activity_version', true );

		// Check if published (immutable).
		$course_terms = wp_get_object_terms( $post_id, 'course', array( 'fields' => 'ids' ) );
		$is_published = false;
		if ( ! empty( $course_terms ) ) {
			$status = get_term_meta( $course_terms[0], '_1111_generation_status', true );
			$is_published = ( 'published' === $status );
		}

		wp_enqueue_script(
			'learn-editor-sidebar',
			LEARN_PLUGIN_URL . 'admin/js/editor-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-compose', 'wp-i18n' ),
			LEARN_VERSION,
			true
		);

		wp_localize_script( 'learn-editor-sidebar', 'learnEditor', array(
			'nonce'         => wp_create_nonce( '1111_learn_feedback' ),
			'isGenerated'   => $is_generated,
			'isPublished'   => $is_published,
			'isFinalAssessment' => ( 'final' === $activity_type ),
			'postId'        => $post_id,
			'courseTermId'   => ! empty( $course_terms ) ? $course_terms[0] : 0,
			'activity'      => $activity ? $activity : null,
			'activityType'  => $activity_type,
			'review'        => $review ? $review : null,
			'takeaways'     => $takeaways ? $takeaways : array(),
			'xpValue'       => $xp_value,
			'milestone'     => $milestone,
			'portfolio'     => $portfolio,
			'lessonVersion' => $lesson_ver,
			'activityVersion' => $activity_ver,
			'i18n'          => array(
				'pluginTitle'       => __( 'Learn', 'learn' ),
				'feedbackPanel'     => __( 'Lesson Feedback', 'learn' ),
				'assessmentPanel'   => __( 'Assessment Feedback', 'learn' ),
				'feedbackLabel'     => __( 'What should change about this lesson?', 'learn' ),
				'assessmentFeedbackLabel' => __( 'What should change about this assessment?', 'learn' ),
				'regenerate'        => __( 'Regenerate Lesson', 'learn' ),
				'regenerateAssessment' => __( 'Regenerate Assessment', 'learn' ),
				'regenerating'      => __( 'Regenerating...', 'learn' ),
				'regenerateSuccess' => __( 'Regeneration complete. Reload to see changes.', 'learn' ),
				'regenerateError'   => __( 'Regeneration failed. Please try again.', 'learn' ),
				'version'           => __( 'Version', 'learn' ),
				'lockedNotice'      => __( 'This lesson was generated by Learn. Use the feedback panel in the sidebar to request changes.', 'learn' ),
				'publishedNotice'   => __( 'This content is published and cannot be modified.', 'learn' ),
			),
		) );

		wp_enqueue_style(
			'learn-editor',
			LEARN_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			LEARN_VERSION
		);
	}

	/**
	 * Lock the title placeholder for agent-authored posts.
	 *
	 * @param string  $placeholder Default placeholder.
	 * @param WP_Post $post        Post object.
	 * @return string
	 */
	public static function lock_title_placeholder( $placeholder, $post ) {
		if ( 'learn' !== $post->post_type ) {
			return $placeholder;
		}

		$agent_id = Learn_Agent_User::get_id();
		if ( (int) $post->post_author === $agent_id && $post->post_title ) {
			return $post->post_title;
		}

		return $placeholder;
	}
}
