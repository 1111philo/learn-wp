<?php
/**
 * Copy published course content from main site to learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Content_Copy
 *
 * Duplicates published course content to a learner's subsite on enrollment.
 */
class Learn_Content_Copy {

	/**
	 * Copy a course's content to a learner's subsite.
	 *
	 * @param int $course_term_id Course term ID (on the main site).
	 * @param int $blog_id        Learner's subsite blog ID.
	 * @param int $user_id        Learner's user ID.
	 * @return int|WP_Error Number of posts copied or WP_Error.
	 */
	public static function copy_course( $course_term_id, $blog_id, $user_id ) {
		$main_site_id = get_main_site_id();

		// Read course data from the main site.
		switch_to_blog( $main_site_id );

		$term = get_term( $course_term_id, 'course' );
		if ( ! $term || is_wp_error( $term ) ) {
			restore_current_blog();
			return new WP_Error( 'invalid_course', __( 'Course not found.', 'learn' ) );
		}

		$status = get_term_meta( $course_term_id, '_1111_generation_status', true );
		if ( 'published' !== $status ) {
			restore_current_blog();
			return new WP_Error( 'not_published', __( 'Only published courses can be enrolled in.', 'learn' ) );
		}

		// Collect all term meta.
		$term_meta_keys = array(
			'_1111_course_description', '_1111_learning_objectives', '_1111_narrative_description',
			'_1111_work_product', '_1111_work_product_type', '_1111_work_product_description',
			'_1111_lesson_titles', '_1111_assessment', '_1111_total_xp', '_1111_generation_status',
		);
		$term_meta = array();
		foreach ( $term_meta_keys as $key ) {
			$term_meta[ $key ] = get_term_meta( $course_term_id, $key, true );
		}

		// Get lesson groups.
		$groups = get_terms( array(
			'taxonomy'   => 'lesson_group',
			'hide_empty' => false,
			'meta_query' => array(
				array( 'key' => '_1111_course_term_id', 'value' => $course_term_id ),
			),
		) );

		$group_data = array();
		if ( ! is_wp_error( $groups ) ) {
			foreach ( $groups as $group ) {
				$group_data[ $group->term_id ] = array(
					'name' => $group->name,
					'meta' => array(
						'_1111_lesson_plan_raw'    => get_term_meta( $group->term_id, '_1111_lesson_plan_raw', true ),
						'_1111_learning_objective' => get_term_meta( $group->term_id, '_1111_learning_objective', true ),
						'_1111_lesson_count'       => get_term_meta( $group->term_id, '_1111_lesson_count', true ),
						'_1111_plan_version'       => get_term_meta( $group->term_id, '_1111_plan_version', true ),
						'_1111_objective_index'    => get_term_meta( $group->term_id, '_1111_objective_index', true ),
					),
				);
			}
		}

		// Get all posts.
		$posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'tax_query'   => array( array(
				'taxonomy' => 'course',
				'terms'    => $course_term_id,
			) ),
		) );

		$posts_data = array();
		foreach ( $posts as $post ) {
			$post_meta_keys = array(
				'_1111_objective_index', '_1111_lesson_order', '_1111_learning_objective',
				'_1111_key_concepts', '_1111_mastery_criteria', '_1111_key_takeaways',
				'_1111_generated', '_1111_lesson_version', '_1111_activity', '_1111_activity_type',
				'_1111_xp_value', '_1111_milestone', '_1111_portfolio_contribution',
				'_1111_activity_version', '_1111_activity_review',
			);

			$meta = array();
			foreach ( $post_meta_keys as $key ) {
				$meta[ $key ] = get_post_meta( $post->ID, $key, true );
			}

			$group_term_ids = wp_get_object_terms( $post->ID, 'lesson_group', array( 'fields' => 'ids' ) );

			$posts_data[] = array(
				'source_id'     => $post->ID,
				'title'         => $post->post_title,
				'content'       => $post->post_content,
				'excerpt'       => $post->post_excerpt,
				'menu_order'    => $post->menu_order,
				'meta'          => $meta,
				'group_term_id' => ! empty( $group_term_ids ) ? $group_term_ids[0] : 0,
			);
		}

		restore_current_blog();

		// Now write to the learner's subsite.
		switch_to_blog( $blog_id );

		// Create course taxonomy term.
		$new_course = wp_insert_term( $term->name, 'course' );
		if ( is_wp_error( $new_course ) ) {
			$existing = get_term_by( 'name', $term->name, 'course' );
			$new_course_id = $existing ? $existing->term_id : 0;
		} else {
			$new_course_id = $new_course['term_id'];
		}

		if ( ! $new_course_id ) {
			restore_current_blog();
			return new WP_Error( 'term_failed', __( 'Failed to create course term on learner site.', 'learn' ) );
		}

		foreach ( $term_meta as $key => $value ) {
			update_term_meta( $new_course_id, $key, $value );
		}
		update_term_meta( $new_course_id, '_1111_source_term_id', $course_term_id );
		update_term_meta( $new_course_id, '_1111_source_blog_id', $main_site_id );

		// Create lesson group terms.
		$group_id_map = array();
		foreach ( $group_data as $old_group_id => $gd ) {
			$new_group = wp_insert_term( $gd['name'], 'lesson_group' );
			if ( is_wp_error( $new_group ) ) {
				$existing = get_term_by( 'name', $gd['name'], 'lesson_group' );
				$new_group_id = $existing ? $existing->term_id : 0;
			} else {
				$new_group_id = $new_group['term_id'];
			}
			if ( $new_group_id ) {
				$group_id_map[ $old_group_id ] = $new_group_id;
				foreach ( $gd['meta'] as $key => $value ) {
					update_term_meta( $new_group_id, $key, $value );
				}
				update_term_meta( $new_group_id, '_1111_course_term_id', $new_course_id );
			}
		}

		// Copy posts.
		$agent_user_id = Learn_Agent_User::get_id();
		$copied_count  = 0;

		foreach ( $posts_data as $pd ) {
			$new_post_id = wp_insert_post( array(
				'post_type'    => 'learn',
				'post_author'  => $agent_user_id,
				'post_title'   => $pd['title'],
				'post_content' => $pd['content'],
				'post_excerpt' => $pd['excerpt'],
				'post_status'  => 'publish',
				'menu_order'   => $pd['menu_order'],
			), true );

			if ( is_wp_error( $new_post_id ) ) {
				continue;
			}

			wp_set_object_terms( $new_post_id, $new_course_id, 'course' );

			if ( $pd['group_term_id'] && isset( $group_id_map[ $pd['group_term_id'] ] ) ) {
				wp_set_object_terms( $new_post_id, $group_id_map[ $pd['group_term_id'] ], 'lesson_group' );
			}

			foreach ( $pd['meta'] as $key => $value ) {
				if ( $value ) {
					update_post_meta( $new_post_id, $key, $value );
				}
			}

			update_post_meta( $new_post_id, '_1111_source_post_id', $pd['source_id'] );
			update_post_meta( $new_post_id, '_1111_source_blog_id', $main_site_id );

			$copied_count++;
		}

		restore_current_blog();

		return $copied_count;
	}
}
