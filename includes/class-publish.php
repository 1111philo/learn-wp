<?php
/**
 * Course publish flow and immutability enforcement.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the course publish workflow and enforces immutability for published courses.
 *
 * Provides an AJAX endpoint for super admins to publish all lessons in a course,
 * and prevents any modifications or deletions to posts belonging to published courses.
 */
class Learn_Publish {

	/**
	 * Term meta key for course generation/publish status.
	 *
	 * @var string
	 */
	const STATUS_META_KEY = '_1111_generation_status';

	/**
	 * Constructor. Registers all hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_1111_publish_course', array( $this, 'ajax_publish_course' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'enforce_immutability' ), 20, 2 );
		add_action( 'before_delete_post', array( $this, 'prevent_published_deletion' ) );
		add_action( 'wp_trash_post', array( $this, 'prevent_published_deletion' ) );
	}

	/**
	 * AJAX handler: publish all learn posts in a course.
	 *
	 * Changes all `learn` posts associated with a course term from draft to published,
	 * sets the course generation status to 'published', and fires a custom action.
	 */
	public function ajax_publish_course() {
		check_ajax_referer( '1111_learn_admin', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', '1111-learn' ) ),
				403
			);
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

		if ( ! $term_id ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid course term ID.', '1111-learn' ) ),
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

		// Prevent re-publishing an already published course.
		if ( $this->is_course_published( $term_id ) ) {
			wp_send_json_error(
				array( 'message' => __( 'This course is already published.', '1111-learn' ) ),
				400
			);
		}

		// Get all learn posts in this course.
		$posts = get_posts(
			array(
				'post_type'      => 'learn',
				'post_status'    => 'draft',
				'posts_per_page' => -1,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		if ( empty( $posts ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No draft lessons found for this course.', '1111-learn' ) ),
				400
			);
		}

		$published_count = 0;

		foreach ( $posts as $post_id ) {
			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				$published_count++;
			}
		}

		// Mark the course as published.
		update_term_meta( $term_id, self::STATUS_META_KEY, 'published' );

		/**
		 * Fires after a course has been published.
		 *
		 * @param int   $term_id         The course term ID.
		 * @param int   $published_count Number of lessons published.
		 * @param array $posts           Array of post IDs that were published.
		 */
		do_action( '1111_learn_course_published', $term_id, $published_count, $posts );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of published lessons */
					__( 'Course published successfully. %d lessons published.', '1111-learn' ),
					$published_count
				),
				'published_count' => $published_count,
			)
		);
	}

	/**
	 * Enforce immutability for posts belonging to published courses on the main site.
	 *
	 * Prevents any changes to `learn` posts that belong to a published course
	 * when the operation occurs on the main site.
	 *
	 * @param array $data    An array of slashed, sanitized post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @return array Filtered post data.
	 */
	public function enforce_immutability( $data, $postarr ) {
		if ( ! isset( $data['post_type'] ) || 'learn' !== $data['post_type'] ) {
			return $data;
		}

		if ( empty( $postarr['ID'] ) ) {
			return $data;
		}

		if ( ! is_main_site() ) {
			return $data;
		}

		$post_id = (int) $postarr['ID'];

		if ( ! $this->post_belongs_to_published_course( $post_id ) ) {
			return $data;
		}

		// Return the existing post data unchanged.
		$existing = get_post( $post_id );

		if ( ! $existing ) {
			return $data;
		}

		$data['post_title']   = $existing->post_title;
		$data['post_content'] = $existing->post_content;
		$data['post_excerpt'] = $existing->post_excerpt;
		$data['post_status']  = $existing->post_status;
		$data['post_name']    = $existing->post_name;
		$data['menu_order']   = $existing->menu_order;

		return $data;
	}

	/**
	 * Prevent deletion of posts belonging to published courses.
	 *
	 * Hooked into `before_delete_post` and `wp_trash_post` to block deletion
	 * and trashing of learn posts in published courses on the main site.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public function prevent_published_deletion( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'learn' !== $post->post_type ) {
			return;
		}

		if ( ! is_main_site() ) {
			return;
		}

		if ( $this->post_belongs_to_published_course( $post_id ) ) {
			wp_die(
				esc_html__( 'Lessons belonging to a published course cannot be deleted.', '1111-learn' ),
				esc_html__( 'Action Blocked', '1111-learn' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Check whether a course is published.
	 *
	 * @param int $term_id The course term ID.
	 * @return bool True if the course generation status is 'published'.
	 */
	public function is_course_published( $term_id ) {
		$status = get_term_meta( $term_id, self::STATUS_META_KEY, true );

		return 'published' === $status;
	}

	/**
	 * Check whether a post belongs to a published course.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the post belongs to at least one published course.
	 */
	private function post_belongs_to_published_course( $post_id ) {
		$terms = wp_get_post_terms( $post_id, 'course', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term_id ) {
			if ( $this->is_course_published( $term_id ) ) {
				return true;
			}
		}

		return false;
	}
}
