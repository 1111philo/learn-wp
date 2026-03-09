<?php
/**
 * Registers the learn custom post type and associated taxonomies.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Post_Type
 *
 * Handles registration of the learn CPT, course taxonomy, and lesson_group taxonomy.
 */
class Learn_Post_Type {

	/**
	 * Register the custom post type and taxonomies.
	 */
	public static function register() {
		self::register_post_type();
		self::register_course_taxonomy();
		self::register_lesson_group_taxonomy();
	}

	/**
	 * Register the learn custom post type.
	 */
	private static function register_post_type() {
		$labels = array(
			'name'                  => __( 'Lessons', 'learn' ),
			'singular_name'         => __( 'Lesson', 'learn' ),
			'add_new'               => __( 'Add New', 'learn' ),
			'add_new_item'          => __( 'Add New Lesson', 'learn' ),
			'edit_item'             => __( 'Edit Lesson', 'learn' ),
			'new_item'              => __( 'New Lesson', 'learn' ),
			'view_item'             => __( 'View Lesson', 'learn' ),
			'view_items'            => __( 'View Lessons', 'learn' ),
			'search_items'          => __( 'Search Lessons', 'learn' ),
			'not_found'             => __( 'No lessons found.', 'learn' ),
			'not_found_in_trash'    => __( 'No lessons found in Trash.', 'learn' ),
			'all_items'             => __( 'All Lessons', 'learn' ),
			'archives'              => __( 'Lesson Archives', 'learn' ),
			'attributes'            => __( 'Lesson Attributes', 'learn' ),
			'insert_into_item'      => __( 'Insert into lesson', 'learn' ),
			'uploaded_to_this_item' => __( 'Uploaded to this lesson', 'learn' ),
			'filter_items_list'     => __( 'Filter lessons list', 'learn' ),
			'items_list_navigation' => __( 'Lessons list navigation', 'learn' ),
			'items_list'            => __( 'Lessons list', 'learn' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'has_archive'         => true,
			'show_in_rest'        => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'menu_icon'           => learn_get_menu_icon(),
			'menu_position'       => 25,
			'rewrite'             => array( 'slug' => 'learn' ),
			'capability_type'     => array( 'learn_post', 'learn_posts' ),
			'map_meta_cap'        => true,
			'show_in_menu'        => false,
		);

		register_post_type( 'learn', $args );

		// Grant super admins full capabilities for the learn CPT.
		self::grant_super_admin_caps();
	}

	/**
	 * Grant super admins capabilities for the learn CPT.
	 */
	private static function grant_super_admin_caps() {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		$caps = array(
			'edit_learn_post',
			'edit_learn_posts',
			'edit_others_learn_posts',
			'edit_published_learn_posts',
			'publish_learn_posts',
			'read_learn_post',
			'read_private_learn_posts',
			'delete_learn_post',
			'delete_learn_posts',
			'delete_others_learn_posts',
			'delete_published_learn_posts',
		);

		foreach ( $caps as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Register the course taxonomy (flat, like tags).
	 */
	private static function register_course_taxonomy() {
		$labels = array(
			'name'                       => __( 'Courses', 'learn' ),
			'singular_name'              => __( 'Course', 'learn' ),
			'search_items'               => __( 'Search Courses', 'learn' ),
			'all_items'                  => __( 'All Courses', 'learn' ),
			'edit_item'                  => __( 'Edit Course', 'learn' ),
			'update_item'                => __( 'Update Course', 'learn' ),
			'add_new_item'               => __( 'Add New Course', 'learn' ),
			'new_item_name'              => __( 'New Course Name', 'learn' ),
			'not_found'                  => __( 'No courses found.', 'learn' ),
			'no_terms'                   => __( 'No courses', 'learn' ),
			'items_list_navigation'      => __( 'Courses list navigation', 'learn' ),
			'items_list'                 => __( 'Courses list', 'learn' ),
			'back_to_items'              => __( '&larr; Go to Courses', 'learn' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'course' ),
		);

		register_taxonomy( 'course', 'learn', $args );
	}

	/**
	 * Register the lesson_group taxonomy (tag-style).
	 */
	private static function register_lesson_group_taxonomy() {
		$labels = array(
			'name'                       => __( 'Lesson Groups', 'learn' ),
			'singular_name'              => __( 'Lesson Group', 'learn' ),
			'search_items'               => __( 'Search Lesson Groups', 'learn' ),
			'all_items'                  => __( 'All Lesson Groups', 'learn' ),
			'edit_item'                  => __( 'Edit Lesson Group', 'learn' ),
			'update_item'                => __( 'Update Lesson Group', 'learn' ),
			'add_new_item'               => __( 'Add New Lesson Group', 'learn' ),
			'new_item_name'              => __( 'New Lesson Group Name', 'learn' ),
			'not_found'                  => __( 'No lesson groups found.', 'learn' ),
			'no_terms'                   => __( 'No lesson groups', 'learn' ),
			'items_list_navigation'      => __( 'Lesson Groups list navigation', 'learn' ),
			'items_list'                 => __( 'Lesson Groups list', 'learn' ),
			'back_to_items'              => __( '&larr; Go to Lesson Groups', 'learn' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'lesson-group' ),
		);

		register_taxonomy( 'lesson_group', 'learn', $args );
	}
}
