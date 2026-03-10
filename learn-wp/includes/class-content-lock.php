<?php
/**
 * Content locking and immutability guards.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Content_Lock {
	/**
	 * Singleton instance.
	 *
	 * @var Learn_Content_Lock|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton.
	 *
	 * @return Learn_Content_Lock
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_insert_post_data', array( $this, 'block_manual_content_edits' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Prevent edits on agent-authored content by humans.
	 *
	 * @param array $data Sanitized post data.
	 * @param array $postarr Raw post data.
	 * @return array
	 */
	public function block_manual_content_edits( $data, $postarr ) {
		if ( ! isset( $postarr['ID'] ) || 'learn' !== $data['post_type'] ) {
			return $data;
		}

		$post = get_post( absint( $postarr['ID'] ) );
		if ( ! $post ) {
			return $data;
		}

		$agent_user_id = (int) get_site_option( '_1111_learn_agent_user_id', 0 );
		if ( (int) $post->post_author !== $agent_user_id ) {
			return $data;
		}

		if ( get_current_user_id() === $agent_user_id ) {
			return $data;
		}

		$course_terms = wp_get_post_terms( $post->ID, 'course', array( 'fields' => 'ids' ) );
		$course_term  = ! empty( $course_terms ) ? (int) $course_terms[0] : 0;
		$status       = $course_term ? get_term_meta( $course_term, '_1111_learn_status', true ) : 'draft';

		if ( 'published' === $status ) {
			$data['post_content'] = $post->post_content;
			$data['post_title']   = $post->post_title;
			$data['post_excerpt'] = $post->post_excerpt;
			return $data;
		}

		$content_changed = isset( $postarr['post_content'] ) && $postarr['post_content'] !== $post->post_content;
		$title_changed   = isset( $postarr['post_title'] ) && $postarr['post_title'] !== $post->post_title;
		$excerpt_changed = isset( $postarr['post_excerpt'] ) && $postarr['post_excerpt'] !== $post->post_excerpt;

		if ( $content_changed || $title_changed || $excerpt_changed ) {
			$data['post_content'] = $post->post_content;
			$data['post_title']   = $post->post_title;
			$data['post_excerpt'] = $post->post_excerpt;
		}

		return $data;
	}

	/**
	 * Enqueue editor sidebar and lock script.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$screen = get_current_screen();
		if ( ! $screen || 'learn' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'1111-learn-editor-sidebar',
			LEARN_WP_PLUGIN_URL . 'admin/js/editor-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-components', 'wp-element', 'wp-data', 'wp-api-fetch' ),
			LEARN_WP_VERSION,
			true
		);

		wp_localize_script(
			'1111-learn-editor-sidebar',
			'LearnEditorSidebar',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
