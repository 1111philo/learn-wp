<?php
/**
 * Admin page registration and AJAX handlers.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin menus, renders dashboard pages, and handles AJAX requests.
 */
class Learn_Admin_Page {

	/**
	 * Settings instance.
	 *
	 * @var Learn_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Learn_Settings $settings Settings instance for submenu registration.
	 */
	public function __construct( Learn_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'wp_ajax_1111_generate_course', array( $this, 'ajax_generate_course' ) );
		add_action( 'wp_ajax_1111_generation_status', array( $this, 'ajax_generation_status' ) );
	}

	/**
	 * Register the top-level Learn menu and all submenus.
	 *
	 * Only visible on the main site for super admins.
	 */
	public function register_menus() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			return;
		}

		// Top-level menu.
		add_menu_page(
			__( 'Learn', '1111-learn' ),
			__( 'Learn', '1111-learn' ),
			'manage_network',
			'1111-learn',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-learn-more',
			30
		);

		// Dashboard submenu (replaces default top-level duplicate).
		add_submenu_page(
			'1111-learn',
			__( 'Dashboard', '1111-learn' ),
			__( 'Dashboard', '1111-learn' ),
			'manage_network',
			'1111-learn',
			array( $this, 'render_dashboard' )
		);

		// All Lessons — standard CPT list table.
		add_submenu_page(
			'1111-learn',
			__( 'All Lessons', '1111-learn' ),
			__( 'All Lessons', '1111-learn' ),
			'manage_network',
			'edit.php?post_type=learn'
		);

		// Courses taxonomy management.
		add_submenu_page(
			'1111-learn',
			__( 'Courses', '1111-learn' ),
			__( 'Courses', '1111-learn' ),
			'manage_network',
			'edit-tags.php?taxonomy=course&post_type=learn'
		);

		// Lesson Groups taxonomy management.
		add_submenu_page(
			'1111-learn',
			__( 'Lesson Groups', '1111-learn' ),
			__( 'Lesson Groups', '1111-learn' ),
			'manage_network',
			'edit-tags.php?taxonomy=lesson_group&post_type=learn'
		);

		// Learner Progress custom page.
		add_submenu_page(
			'1111-learn',
			__( 'Learner Progress', '1111-learn' ),
			__( 'Learner Progress', '1111-learn' ),
			'manage_network',
			'1111-learn-progress',
			array( $this, 'render_learner_progress' )
		);

		// Settings page (delegated to Learn_Settings).
		$this->settings->add_settings_page();
	}

	/**
	 * Render the course creation dashboard.
	 */
	public function render_dashboard() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '1111-learn' ) );
		}

		include plugin_dir_path( __DIR__ ) . 'admin/views/dashboard.php';
	}

	/**
	 * Render the course review page (placeholder).
	 */
	public function render_course_review() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '1111-learn' ) );
		}

		include plugin_dir_path( __DIR__ ) . 'admin/views/course-review.php';
	}

	/**
	 * Render the learner progress page (placeholder).
	 */
	public function render_learner_progress() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '1111-learn' ) );
		}

		include plugin_dir_path( __DIR__ ) . 'admin/views/learner-progress.php';
	}

	/**
	 * AJAX handler: generate a course from form input.
	 *
	 * Validates nonce, capability, and input before kicking off the pipeline.
	 */
	public function ajax_generate_course() {
		check_ajax_referer( '1111_generate_course', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', '1111-learn' ) ),
				403
			);
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( empty( $title ) || strlen( $title ) > 200 ) {
			wp_send_json_error(
				array( 'message' => __( 'Course title is required and must be 200 characters or fewer.', '1111-learn' ) ),
				400
			);
		}

		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		if ( empty( $description ) || strlen( $description ) < 10 || strlen( $description ) > 1000 ) {
			wp_send_json_error(
				array( 'message' => __( 'Course description is required and must be between 10 and 1000 characters.', '1111-learn' ) ),
				400
			);
		}

		$objectives = array();
		if ( isset( $_POST['objectives'] ) && is_array( $_POST['objectives'] ) ) {
			foreach ( $_POST['objectives'] as $objective ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$clean = sanitize_text_field( wp_unslash( $objective ) );
				if ( ! empty( $clean ) && strlen( $clean ) <= 300 ) {
					$objectives[] = $clean;
				}
			}
		}

		if ( empty( $objectives ) || count( $objectives ) > 8 ) {
			wp_send_json_error(
				array( 'message' => __( 'At least 1 and at most 8 learning objectives are required.', '1111-learn' ) ),
				400
			);
		}

		/**
		 * Fires when a course generation request is validated and ready.
		 *
		 * @param string $title       Course title.
		 * @param string $description Course description.
		 * @param array  $objectives  Learning objectives.
		 */
		do_action( '1111_learn_generate_course', $title, $description, $objectives );

		wp_send_json_success(
			array(
				'message' => __( 'Course generation started.', '1111-learn' ),
				'status'  => 'processing',
			)
		);
	}

	/**
	 * AJAX handler: return current generation progress.
	 */
	public function ajax_generation_status() {
		check_ajax_referer( '1111_generate_course', 'nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', '1111-learn' ) ),
				403
			);
		}

		/**
		 * Filters the current generation status.
		 *
		 * @param array $status Default status array.
		 */
		$status = apply_filters(
			'1111_learn_generation_status',
			array(
				'step'    => 0,
				'total'   => 7,
				'label'   => __( 'Waiting...', '1111-learn' ),
				'status'  => 'idle',
			)
		);

		wp_send_json_success( $status );
	}
}
