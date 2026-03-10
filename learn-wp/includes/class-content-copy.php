<?php
/**
 * Copy published course content from main site to learner site.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Content_Copy {
	/**
	 * Copy a course to current learner site.
	 *
	 * @param int $source_course_term_id Course term id on source site.
	 * @param int $source_blog_id Source blog id.
	 * @param int $target_blog_id Target blog id.
	 * @return array|WP_Error
	 */
	public function copy_course( $source_course_term_id, $source_blog_id, $target_blog_id ) {
		$source_course_term_id = absint( $source_course_term_id );
		$source_blog_id        = absint( $source_blog_id );
		$target_blog_id        = absint( $target_blog_id );

		if ( ! $source_course_term_id || ! $source_blog_id || ! $target_blog_id ) {
			return new WP_Error( 'learn_invalid_copy_request', 'Invalid course copy request.' );
		}

		switch_to_blog( $source_blog_id );
		$source_posts = get_posts(
			array(
				'post_type'      => 'learn',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $source_course_term_id,
					),
				),
			)
		);

		$course_term = get_term( $source_course_term_id, 'course' );
		if ( ! $course_term || is_wp_error( $course_term ) ) {
			restore_current_blog();
			return new WP_Error( 'learn_source_course_missing', 'Source course term not found.' );
		}

		$course_meta = get_term_meta( $source_course_term_id );
		$source_data = array();
		foreach ( $source_posts as $post ) {
			$source_data[] = array(
				'post'      => $post,
				'post_meta' => get_post_meta( $post->ID ),
				'groups'    => wp_get_post_terms( $post->ID, 'lesson_group', array( 'fields' => 'names' ) ),
			);
		}
		restore_current_blog();

		switch_to_blog( $target_blog_id );
		$target_course = term_exists( $course_term->name, 'course' );
		if ( ! $target_course ) {
			$target_course = wp_insert_term( $course_term->name, 'course' );
		}
		if ( is_wp_error( $target_course ) ) {
			restore_current_blog();
			return $target_course;
		}

		$target_course_id = absint( $target_course['term_id'] );
		foreach ( $course_meta as $meta_key => $meta_values ) {
			if ( isset( $meta_values[0] ) ) {
				update_term_meta( $target_course_id, $meta_key, maybe_unserialize( $meta_values[0] ) );
			}
		}

		$post_ids = array();
		foreach ( $source_data as $row ) {
			$source_post = $row['post'];
			$new_post_id = wp_insert_post(
				array(
					'post_type'    => 'learn',
					'post_status'  => 'publish',
					'post_title'   => $source_post->post_title,
					'post_content' => $source_post->post_content,
					'post_excerpt' => $source_post->post_excerpt,
					'post_author'  => get_current_user_id(),
				)
			);

			if ( is_wp_error( $new_post_id ) ) {
				continue;
			}

			wp_set_post_terms( $new_post_id, array( $target_course_id ), 'course', false );

			if ( ! empty( $row['groups'] ) ) {
				$target_group_ids = array();
				foreach ( $row['groups'] as $group_name ) {
					$target_group = term_exists( $group_name, 'lesson_group' );
					if ( ! $target_group ) {
						$target_group = wp_insert_term( $group_name, 'lesson_group' );
					}
					if ( ! is_wp_error( $target_group ) ) {
						$target_group_ids[] = absint( $target_group['term_id'] );
					}
				}
				if ( ! empty( $target_group_ids ) ) {
					wp_set_post_terms( $new_post_id, $target_group_ids, 'lesson_group', false );
				}
			}

			foreach ( $row['post_meta'] as $meta_key => $meta_values ) {
				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
				}
			}
			update_post_meta( $new_post_id, '_1111_source_post_id', (int) $source_post->ID );
			update_post_meta( $new_post_id, '_1111_source_blog_id', $source_blog_id );
			$post_ids[] = $new_post_id;
		}

		restore_current_blog();

		return array(
			'course_term_id' => $target_course_id,
			'post_ids'       => $post_ids,
		);
	}
}
