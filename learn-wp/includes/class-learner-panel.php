<?php
/**
 * Learner panel shortcode and enrollment actions.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Learner_Panel {
	/**
	 * Singleton.
	 *
	 * @var Learn_Learner_Panel|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Learn_Learner_Panel
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
		add_shortcode( 'learn_learner_panel', array( $this, 'render_panel' ) );
		add_action( 'wp_ajax_1111_learn_enroll_course', array( $this, 'ajax_enroll' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue public scripts.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( '1111-learn-public', LEARN_WP_PLUGIN_URL . 'public/css/learn-public.css', array(), LEARN_WP_VERSION );
		wp_enqueue_script( '1111-learn-public', LEARN_WP_PLUGIN_URL . 'public/js/learn-public.js', array( 'jquery' ), LEARN_WP_VERSION, true );
		wp_localize_script(
			'1111-learn-public',
			'LearnPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( '1111_learn_enroll' ),
			)
		);
	}

	/**
	 * Render learner catalog.
	 *
	 * @return string
	 */
	public function render_panel() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please sign in to access your learner panel.', 'learn-wp' ) . '</p>';
		}

		$main_blog_id = get_main_site_id();
		switch_to_blog( $main_blog_id );
		$courses = get_terms(
			array(
				'taxonomy'   => 'course',
				'hide_empty' => false,
			)
		);
		restore_current_blog();

		ob_start();
		?>
		<div class="learn-learner-panel">
			<h2><?php esc_html_e( 'Course Catalog', 'learn-wp' ); ?></h2>
			<ul class="learn-course-list">
				<?php foreach ( $courses as $course ) : ?>
					<li>
						<strong><?php echo esc_html( $course->name ); ?></strong>
						<button class="learn-enroll-course" data-course-id="<?php echo esc_attr( $course->term_id ); ?>"><?php esc_html_e( 'Enroll', 'learn-wp' ); ?></button>
					</li>
				<?php endforeach; ?>
			</ul>
			<div id="learn-enroll-message" aria-live="polite"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * AJAX enrollment and content copy.
	 *
	 * @return void
	 */
	public function ajax_enroll() {
		check_ajax_referer( '1111_learn_enroll', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Must be logged in.', 'learn-wp' ) ), 403 );
		}

		$course_term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;
		if ( ! $course_term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course ID.', 'learn-wp' ) ), 400 );
		}

		$copy   = new Learn_Content_Copy();
		$result = $copy->copy_course( $course_term_id, get_main_site_id(), get_current_blog_id() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		$this->record_enrollment( get_current_user_id(), get_current_blog_id(), get_main_site_id(), $course_term_id );
		do_action( '1111_learn_course_enrolled', get_current_user_id(), $course_term_id, get_current_blog_id() );
		wp_send_json_success( $result );
	}

	/**
	 * Records an enrollment row.
	 *
	 * @param int $user_id User id.
	 * @param int $blog_id Blog id.
	 * @param int $source_blog_id Source blog id.
	 * @param int $course_term_id Course id.
	 * @return void
	 */
	private function record_enrollment( $user_id, $blog_id, $source_blog_id, $course_term_id ) {
		global $wpdb;
		$table = $wpdb->base_prefix . '1111_learn_enrollments';

		$wpdb->replace(
			$table,
			array(
				'blog_id'               => absint( $blog_id ),
				'user_id'               => absint( $user_id ),
				'source_course_term_id' => absint( $course_term_id ),
				'source_blog_id'        => absint( $source_blog_id ),
				'lessons_completed'     => 0,
				'current_xp'            => 0,
				'status'                => 'active',
			)
		);
	}
}
