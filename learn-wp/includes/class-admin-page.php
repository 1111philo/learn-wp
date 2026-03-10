<?php
/**
 * Learn admin dashboard page and generation endpoint.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Admin_Page {
	/**
	 * Singleton.
	 *
	 * @var Learn_Admin_Page|null
	 */
	private static $instance = null;

	/**
	 * Singleton getter.
	 *
	 * @return Learn_Admin_Page
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
		add_action( 'network_admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_1111_learn_generate_course', array( $this, 'ajax_generate_course' ) );
		add_action( 'wp_ajax_1111_learn_publish_course', array( $this, 'ajax_publish_course' ) );
	}

	/**
	 * Adds menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		if ( ! is_main_site() ) {
			return;
		}

		add_menu_page(
			__( 'Learn', 'learn-wp' ),
			__( 'Learn', 'learn-wp' ),
			'manage_network',
			'learn-dashboard',
			array( $this, 'render' ),
			'dashicons-welcome-learn-more',
			25
		);
	}

	/**
	 * Enqueue dashboard assets.
	 *
	 * @param string $hook_suffix Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'learn-dashboard' ) ) {
			return;
		}

		wp_enqueue_style( '1111-learn-admin', LEARN_WP_PLUGIN_URL . 'admin/css/admin.css', array(), LEARN_WP_VERSION );
		wp_enqueue_script( '1111-learn-admin', LEARN_WP_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), LEARN_WP_VERSION, true );
		wp_localize_script(
			'1111-learn-admin',
			'LearnAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( '1111_learn_dashboard' ),
			)
		);
	}

	/**
	 * Renders dashboard.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_die( esc_html__( 'Learn dashboard is available only to super admins on the main site.', 'learn-wp' ) );
		}
		?>
		<div class="wrap learn-admin-wrap">
			<h1><?php esc_html_e( 'Learn Course Builder', 'learn-wp' ); ?></h1>
			<p><?php esc_html_e( 'Create a complete AI-generated course from title, description, and objectives.', 'learn-wp' ); ?></p>
			<form id="learn-course-form" class="learn-card" method="post">
				<?php wp_nonce_field( '1111_learn_dashboard', 'learn_dashboard_nonce' ); ?>
				<label for="learn_course_title"><?php esc_html_e( 'Course Title', 'learn-wp' ); ?></label>
				<input type="text" id="learn_course_title" name="course_title" required />

				<label for="learn_course_description"><?php esc_html_e( 'Course Description', 'learn-wp' ); ?></label>
				<textarea id="learn_course_description" name="course_description" rows="4" required></textarea>

				<label for="learn_course_objectives"><?php esc_html_e( 'Learning Objectives (one per line)', 'learn-wp' ); ?></label>
				<textarea id="learn_course_objectives" name="course_objectives" rows="8" required></textarea>

				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate Course', 'learn-wp' ); ?></button>
			</form>

			<div id="learn-generation-result" class="learn-card" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * AJAX generation.
	 *
	 * @return void
	 */
	public function ajax_generate_course() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );

		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'learn-wp' ) ), 403 );
		}

		$title       = isset( $_POST['course_title'] ) ? sanitize_text_field( wp_unslash( $_POST['course_title'] ) ) : '';
		$description = isset( $_POST['course_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['course_description'] ) ) : '';
		$objectives  = isset( $_POST['course_objectives'] ) ? sanitize_textarea_field( wp_unslash( $_POST['course_objectives'] ) ) : '';

		$objective_list = array_filter( array_map( 'trim', preg_split( '/\R+/', $objectives ) ) );
		if ( '' === $title || '' === $description || empty( $objective_list ) ) {
			wp_send_json_error( array( 'message' => __( 'All course fields are required.', 'learn-wp' ) ), 400 );
		}

		$orchestrator = new Learn_Orchestrator();
		$result       = $orchestrator->generate_course(
			array(
				'title'       => $title,
				'description' => $description,
				'objectives'  => array_values( $objective_list ),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				),
				500
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Publish course term and related posts.
	 *
	 * @return void
	 */
	public function ajax_publish_course() {
		check_ajax_referer( '1111_learn_dashboard', 'nonce' );
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'learn-wp' ) ), 403 );
		}

		$course_term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;
		if ( ! $course_term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'learn-wp' ) ), 400 );
		}

		update_term_meta( $course_term_id, '_1111_learn_status', 'published' );

		$posts = get_posts(
			array(
				'post_type'      => 'learn',
				'post_status'    => array( 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'course',
						'field'    => 'term_id',
						'terms'    => $course_term_id,
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		do_action( '1111_learn_course_published', $course_term_id, $posts );
		wp_send_json_success( array( 'published_count' => count( $posts ) ) );
	}
}
