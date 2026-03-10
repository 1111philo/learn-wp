<?php
/**
 * Course content copy from main site to learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copies published course content from the main site to learner subsites.
 */
class Learn_Content_Copy {

	/**
	 * Copy a course and all its content from the main site to a target subsite.
	 *
	 * @param int $course_term_id The course taxonomy term ID on the main site.
	 * @param int $target_blog_id The target subsite blog ID.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function copy_course( $course_term_id, $target_blog_id ) {
		$main_site_id = get_main_site_id();

		if ( $target_blog_id === $main_site_id ) {
			return new WP_Error(
				'invalid_target',
				__( 'Cannot copy course content to the main site.', '1111-learn' )
			);
		}

		// Switch to main site and gather source data.
		switch_to_blog( $main_site_id );

		$course_term = get_term( $course_term_id, 'course' );

		if ( is_wp_error( $course_term ) || ! $course_term ) {
			restore_current_blog();
			return new WP_Error(
				'invalid_course',
				__( 'Course not found on the main site.', '1111-learn' )
			);
		}

		// Get all published learn posts for this course.
		$posts = get_posts(
			array(
				'post_type'      => 'learn',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $course_term_id,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			restore_current_blog();
			return new WP_Error(
				'no_content',
				__( 'No published content found for this course.', '1111-learn' )
			);
		}

		// Gather course term metadata.
		$course_meta = get_term_meta( $course_term_id );

		// Gather lesson_group terms associated with these posts.
		$lesson_groups     = array();
		$post_lesson_groups = array();

		foreach ( $posts as $post ) {
			$groups = wp_get_object_terms( $post->ID, 'lesson_group' );
			if ( ! is_wp_error( $groups ) ) {
				foreach ( $groups as $group ) {
					$lesson_groups[ $group->term_id ] = $group;
				}
				$post_lesson_groups[ $post->ID ] = wp_list_pluck( $groups, 'term_id' );
			}
		}

		// Gather all post data and meta while on main site.
		$source_posts = array();
		foreach ( $posts as $post ) {
			$meta = get_post_meta( $post->ID );
			$source_posts[] = array(
				'post'          => $post,
				'meta'          => $meta,
				'lesson_groups' => isset( $post_lesson_groups[ $post->ID ] ) ? $post_lesson_groups[ $post->ID ] : array(),
			);
		}

		// Gather lesson_group meta.
		$lesson_group_meta = array();
		foreach ( $lesson_groups as $group_id => $group ) {
			$lesson_group_meta[ $group_id ] = get_term_meta( $group_id );
		}

		$source_blog_id = $main_site_id;

		restore_current_blog();

		// Switch to target blog and replicate.
		switch_to_blog( $target_blog_id );

		// Get the agent user ID on the target site.
		$agent    = Learn_Agent_User::get_instance();
		$agent_id = $agent->get_agent_user_id();

		if ( ! $agent_id ) {
			// Create agent user if it doesn't exist on this site.
			$agent_id = $agent->create_agent_user();
		}

		if ( is_wp_error( $agent_id ) ) {
			restore_current_blog();
			return new WP_Error(
				'agent_user_error',
				__( 'Failed to create agent user on target site.', '1111-learn' )
			);
		}

		// Replicate course taxonomy term.
		$target_course = $this->replicate_term( $course_term, 'course', $course_meta );

		if ( is_wp_error( $target_course ) ) {
			restore_current_blog();
			return $target_course;
		}

		// Replicate lesson_group taxonomy terms.
		$group_id_map = array();
		foreach ( $lesson_groups as $source_group_id => $group ) {
			$meta = isset( $lesson_group_meta[ $source_group_id ] ) ? $lesson_group_meta[ $source_group_id ] : array();
			$target_group = $this->replicate_term( $group, 'lesson_group', $meta );
			if ( ! is_wp_error( $target_group ) ) {
				$group_id_map[ $source_group_id ] = $target_group;
			}
		}

		// Duplicate each post.
		foreach ( $source_posts as $source ) {
			$post      = $source['post'];
			$meta      = $source['meta'];
			$group_ids = $source['lesson_groups'];

			$new_post_data = array(
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_status'  => 'publish',
				'post_type'    => 'learn',
				'post_author'  => $agent_id,
				'menu_order'   => $post->menu_order,
			);

			$new_post_id = wp_insert_post( $new_post_data, true );

			if ( is_wp_error( $new_post_id ) ) {
				continue;
			}

			// Copy all post meta.
			if ( ! empty( $meta ) ) {
				foreach ( $meta as $key => $values ) {
					foreach ( $values as $value ) {
						add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
					}
				}
			}

			// Set source tracking meta.
			update_post_meta( $new_post_id, '_1111_source_post_id', $post->ID );
			update_post_meta( $new_post_id, '_1111_source_blog_id', $source_blog_id );

			// Assign to course term.
			wp_set_object_terms( $new_post_id, $target_course, 'course' );

			// Assign to replicated lesson_group terms.
			$target_groups = array();
			foreach ( $group_ids as $source_group_id ) {
				if ( isset( $group_id_map[ $source_group_id ] ) ) {
					$target_groups[] = $group_id_map[ $source_group_id ];
				}
			}
			if ( ! empty( $target_groups ) ) {
				wp_set_object_terms( $new_post_id, $target_groups, 'lesson_group' );
			}
		}

		restore_current_blog();

		/**
		 * Fires after a course has been copied to a learner subsite.
		 *
		 * @param int $course_term_id The source course term ID.
		 * @param int $target_blog_id The target subsite blog ID.
		 * @param int $source_blog_id The source (main) blog ID.
		 */
		do_action( '1111_learn_course_enrolled', $course_term_id, $target_blog_id, $source_blog_id );

		return true;
	}

	/**
	 * Replicate a taxonomy term on the current blog.
	 *
	 * @param WP_Term $source_term The source term object.
	 * @param string  $taxonomy    The taxonomy slug.
	 * @param array   $meta        The term meta to copy.
	 * @return int|WP_Error The new term ID or WP_Error.
	 */
	private function replicate_term( $source_term, $taxonomy, $meta = array() ) {
		// Check if term already exists on this site (by slug).
		$existing = get_term_by( 'slug', $source_term->slug, $taxonomy );

		if ( $existing ) {
			return $existing->term_id;
		}

		$result = wp_insert_term(
			$source_term->name,
			$taxonomy,
			array(
				'slug'        => $source_term->slug,
				'description' => $source_term->description,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_term_id = $result['term_id'];

		// Copy term meta.
		if ( ! empty( $meta ) ) {
			foreach ( $meta as $key => $values ) {
				if ( is_array( $values ) ) {
					foreach ( $values as $value ) {
						add_term_meta( $new_term_id, $key, maybe_unserialize( $value ) );
					}
				}
			}
		}

		// Track source term.
		update_term_meta( $new_term_id, '_1111_source_term_id', $source_term->term_id );
		update_term_meta( $new_term_id, '_1111_source_blog_id', get_main_site_id() );

		return $new_term_id;
	}
}
