<?php
/**
 * Custom post type and taxonomy registration.
 *
 * @package Jesuspended\Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the learn CPT and associated taxonomies.
 */
class Learn_Post_Type {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register the learn custom post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Lessons', 'Post type general name', '1111-learn' ),
			'singular_name'         => _x( 'Lesson', 'Post type singular name', '1111-learn' ),
			'menu_name'             => _x( 'Lessons', 'Admin menu text', '1111-learn' ),
			'name_admin_bar'        => _x( 'Lesson', 'Add New on toolbar', '1111-learn' ),
			'add_new'               => __( 'Add New', '1111-learn' ),
			'add_new_item'          => __( 'Add New Lesson', '1111-learn' ),
			'new_item'              => __( 'New Lesson', '1111-learn' ),
			'edit_item'             => __( 'Edit Lesson', '1111-learn' ),
			'view_item'             => __( 'View Lesson', '1111-learn' ),
			'all_items'             => __( 'All Lessons', '1111-learn' ),
			'search_items'          => __( 'Search Lessons', '1111-learn' ),
			'parent_item_colon'     => __( 'Parent Lessons:', '1111-learn' ),
			'not_found'             => __( 'No lessons found.', '1111-learn' ),
			'not_found_in_trash'    => __( 'No lessons found in Trash.', '1111-learn' ),
			'featured_image'        => _x( 'Lesson Cover Image', 'Overrides the "Featured Image" phrase', '1111-learn' ),
			'set_featured_image'    => _x( 'Set cover image', 'Overrides the "Set featured image" phrase', '1111-learn' ),
			'remove_featured_image' => _x( 'Remove cover image', 'Overrides the "Remove featured image" phrase', '1111-learn' ),
			'use_featured_image'    => _x( 'Use as cover image', 'Overrides the "Use as featured image" phrase', '1111-learn' ),
			'archives'              => _x( 'Lesson Archives', 'The post type archive label', '1111-learn' ),
			'insert_into_item'      => _x( 'Insert into lesson', 'Overrides the "Insert into post" phrase', '1111-learn' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this lesson', 'Overrides the "Uploaded to this post" phrase', '1111-learn' ),
			'filter_items_list'     => _x( 'Filter lessons list', 'Screen reader text', '1111-learn' ),
			'items_list_navigation' => _x( 'Lessons list navigation', 'Screen reader text', '1111-learn' ),
			'items_list'            => _x( 'Lessons list', 'Screen reader text', '1111-learn' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'show_in_rest'       => true,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
			'menu_icon'          => 'dashicons-welcome-learn-more',
			'menu_position'      => 25,
			'rewrite'            => array( 'slug' => 'learn' ),
		);

		register_post_type( 'learn', $args );
	}

	/**
	 * Register taxonomies associated with the learn post type.
	 */
	public function register_taxonomies() {
		$this->register_course_taxonomy();
		$this->register_lesson_group_taxonomy();
	}

	/**
	 * Register the course taxonomy.
	 */
	private function register_course_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Courses', 'Taxonomy general name', '1111-learn' ),
			'singular_name'              => _x( 'Course', 'Taxonomy singular name', '1111-learn' ),
			'search_items'               => __( 'Search Courses', '1111-learn' ),
			'popular_items'              => __( 'Popular Courses', '1111-learn' ),
			'all_items'                  => __( 'All Courses', '1111-learn' ),
			'edit_item'                  => __( 'Edit Course', '1111-learn' ),
			'update_item'                => __( 'Update Course', '1111-learn' ),
			'add_new_item'               => __( 'Add New Course', '1111-learn' ),
			'new_item_name'              => __( 'New Course Name', '1111-learn' ),
			'separate_items_with_commas' => __( 'Separate courses with commas', '1111-learn' ),
			'add_or_remove_items'        => __( 'Add or remove courses', '1111-learn' ),
			'choose_from_most_used'      => __( 'Choose from the most used courses', '1111-learn' ),
			'not_found'                  => __( 'No courses found.', '1111-learn' ),
			'menu_name'                  => __( 'Courses', '1111-learn' ),
			'back_to_items'              => __( '&larr; Back to Courses', '1111-learn' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'course' ),
		);

		register_taxonomy( 'course', 'learn', $args );
	}

	/**
	 * Register the lesson group taxonomy.
	 */
	private function register_lesson_group_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Lesson Groups', 'Taxonomy general name', '1111-learn' ),
			'singular_name'              => _x( 'Lesson Group', 'Taxonomy singular name', '1111-learn' ),
			'search_items'               => __( 'Search Lesson Groups', '1111-learn' ),
			'popular_items'              => __( 'Popular Lesson Groups', '1111-learn' ),
			'all_items'                  => __( 'All Lesson Groups', '1111-learn' ),
			'edit_item'                  => __( 'Edit Lesson Group', '1111-learn' ),
			'update_item'                => __( 'Update Lesson Group', '1111-learn' ),
			'add_new_item'               => __( 'Add New Lesson Group', '1111-learn' ),
			'new_item_name'              => __( 'New Lesson Group Name', '1111-learn' ),
			'separate_items_with_commas' => __( 'Separate lesson groups with commas', '1111-learn' ),
			'add_or_remove_items'        => __( 'Add or remove lesson groups', '1111-learn' ),
			'choose_from_most_used'      => __( 'Choose from the most used lesson groups', '1111-learn' ),
			'not_found'                  => __( 'No lesson groups found.', '1111-learn' ),
			'menu_name'                  => __( 'Lesson Groups', '1111-learn' ),
			'back_to_items'              => __( '&larr; Back to Lesson Groups', '1111-learn' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'public'            => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'lesson-group' ),
		);

		register_taxonomy( 'lesson_group', 'learn', $args );
	}
}
