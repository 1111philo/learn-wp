<?php
/**
 * Post types and taxonomies.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_CPT {
	/**
	 * Singleton instance.
	 *
	 * @var Learn_CPT|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton.
	 *
	 * @return Learn_CPT
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
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Registers Learn post type and taxonomies.
	 *
	 * @return void
	 */
	public function register() {
		register_post_type(
			'learn',
			array(
				'labels'       => array(
					'name'          => __( 'Lessons', 'learn-wp' ),
					'singular_name' => __( 'Lesson', 'learn-wp' ),
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'menu_icon'    => 'dashicons-welcome-learn-more',
				'menu_position'=> 25,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
				'capability_type' => array( 'learn_post', 'learn_posts' ),
				'map_meta_cap' => true,
			)
		);

		register_taxonomy(
			'course',
			'learn',
			array(
				'labels'       => array(
					'name'          => __( 'Courses', 'learn-wp' ),
					'singular_name' => __( 'Course', 'learn-wp' ),
				),
				'hierarchical' => false,
				'public'       => true,
				'show_in_rest' => true,
			)
		);

		register_taxonomy(
			'lesson_group',
			'learn',
			array(
				'labels'       => array(
					'name'          => __( 'Lesson Groups', 'learn-wp' ),
					'singular_name' => __( 'Lesson Group', 'learn-wp' ),
				),
				'hierarchical' => false,
				'public'       => true,
				'show_in_rest' => true,
			)
		);
	}
}
