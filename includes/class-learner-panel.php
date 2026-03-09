<?php
/**
 * Learner panel for learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Learner_Panel
 *
 * Provides the learner dashboard on learner subsites: course catalog,
 * enrollment, and progress tracking.
 */
class Learn_Learner_Panel {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'wp_ajax_1111_enroll_course', array( __CLASS__, 'ajax_enroll' ) );
	}

	/**
	 * Register the learner menu on non-main subsites.
	 */
	public static function register_menu() {
		if ( is_main_site() ) {
			return;
		}

		$user_id = get_current_user_id();
		$blog_id = get_user_meta( $user_id, '_1111_learner_blog_id', true );
		if ( (int) $blog_id !== get_current_blog_id() ) {
			return;
		}

		add_menu_page(
			__( 'My Learning', 'learn' ),
			__( 'My Learning', 'learn' ),
			'read',
			'learn-learner',
			array( __CLASS__, 'render_panel' ),
			'dashicons-welcome-learn-more',
			3
		);
	}

	/**
	 * Render the learner panel.
	 */
	public static function render_panel() {
		$user_id = get_current_user_id();
		$blog_id = get_current_blog_id();

		// Get enrolled courses on this subsite.
		$enrolled = self::get_enrollments( $user_id, $blog_id );

		// Get available courses from main site.
		$available = self::get_available_courses( $user_id );

		?>
		<div class="wrap learn-dashboard">
			<h1><?php esc_html_e( 'My Learning', 'learn' ); ?></h1>

			<?php if ( ! empty( $enrolled ) ) : ?>
			<div class="learn-card">
				<h2><?php esc_html_e( 'My Courses', 'learn' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Course', 'learn' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Progress', 'learn' ); ?></th>
							<th scope="col"><?php esc_html_e( 'XP', 'learn' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'learn' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $enrolled as $enrollment ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $enrollment['course_name'] ); ?></strong>
							</td>
							<td>
								<?php if ( $enrollment['total_lessons'] > 0 ) : ?>
								<?php
								printf(
									esc_html__( '%1$d / %2$d lessons', 'learn' ),
									$enrollment['lessons_completed'],
									$enrollment['total_lessons']
								);
								?>
								<?php else : ?>
								<?php esc_html_e( 'Not started', 'learn' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php
								printf(
									esc_html__( '%1$s / %2$s XP', 'learn' ),
									number_format_i18n( $enrollment['current_xp'] ),
									number_format_i18n( $enrollment['total_xp'] )
								);
								?>
							</td>
							<td>
								<span class="learn-status learn-status--<?php echo esc_attr( $enrollment['status'] ); ?>">
									<?php echo esc_html( ucfirst( $enrollment['status'] ) ); ?>
								</span>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $available ) ) : ?>
			<div class="learn-card">
				<h2><?php esc_html_e( 'Available Courses', 'learn' ); ?></h2>
				<?php wp_nonce_field( '1111_learn_enroll', '_learn_enroll_nonce' ); ?>
				<div class="learn-course-catalog">
					<?php foreach ( $available as $course ) : ?>
					<div class="learn-catalog-item">
						<h3><?php echo esc_html( $course['title'] ); ?></h3>
						<?php if ( $course['narrative'] ) : ?>
						<p><?php echo esc_html( wp_trim_words( $course['narrative'], 30 ) ); ?></p>
						<?php endif; ?>
						<p class="learn-catalog-meta">
							<?php
							printf(
								esc_html__( '%d lessons | %s XP', 'learn' ),
								$course['post_count'],
								number_format_i18n( $course['total_xp'] )
							);
							?>
						</p>
						<button type="button" class="button button-primary learn-enroll-btn"
							data-term-id="<?php echo esc_attr( $course['term_id'] ); ?>">
							<?php esc_html_e( 'Start Course', 'learn' ); ?>
						</button>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( empty( $enrolled ) && empty( $available ) ) : ?>
			<div class="learn-card">
				<p><?php esc_html_e( 'No courses available yet. Check back soon.', 'learn' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get enrollments for a user on a specific blog.
	 *
	 * @param int $user_id User ID.
	 * @param int $blog_id Blog ID.
	 * @return array
	 */
	private static function get_enrollments( $user_id, $blog_id ) {
		global $wpdb;

		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$table = $wpdb->prefix . '1111_learn_enrollments';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! $table_exists ) {
			restore_current_blog();
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE user_id = %d AND blog_id = %d ORDER BY enrolled_at DESC",
				$user_id,
				$blog_id
			),
			ARRAY_A
		);

		$enrollments = array();
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$course_term = get_term( $row['course_term_id'], 'course' );
				$enrollments[] = array(
					'course_name'       => $course_term && ! is_wp_error( $course_term ) ? $course_term->name : __( 'Unknown', 'learn' ),
					'course_term_id'    => $row['course_term_id'],
					'lessons_completed' => (int) $row['lessons_completed'],
					'total_lessons'     => (int) $row['total_lessons'],
					'current_xp'       => (int) $row['current_xp'],
					'total_xp'         => (int) $row['total_xp'],
					'status'            => $row['status'],
				);
			}
		}

		restore_current_blog();
		return $enrollments;
	}

	/**
	 * Get published courses available for enrollment.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function get_available_courses( $user_id ) {
		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$terms = get_terms( array(
			'taxonomy'   => 'course',
			'hide_empty' => false,
			'meta_query' => array(
				array( 'key' => '_1111_generation_status', 'value' => 'published' ),
			),
		) );

		$courses = array();
		if ( ! is_wp_error( $terms ) ) {
			// Get already enrolled course IDs.
			global $wpdb;
			$blog_id = get_user_meta( $user_id, '_1111_learner_blog_id', true );
			$table   = $wpdb->prefix . '1111_learn_enrollments';
			$enrolled_ids = array();

			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists && $blog_id ) {
				$enrolled_rows = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT course_term_id FROM $table WHERE user_id = %d AND blog_id = %d",
						$user_id,
						$blog_id
					)
				);
				$enrolled_ids = array_map( 'intval', $enrolled_rows );
			}

			foreach ( $terms as $term ) {
				if ( in_array( $term->term_id, $enrolled_ids, true ) ) {
					continue;
				}

				$post_count = count( get_posts( array(
					'post_type'   => 'learn',
					'post_status' => 'publish',
					'numberposts' => -1,
					'fields'      => 'ids',
					'tax_query'   => array( array(
						'taxonomy' => 'course',
						'terms'    => $term->term_id,
					) ),
				) ) );

				$courses[] = array(
					'term_id'    => $term->term_id,
					'title'      => $term->name,
					'narrative'  => get_term_meta( $term->term_id, '_1111_narrative_description', true ),
					'total_xp'   => (int) get_term_meta( $term->term_id, '_1111_total_xp', true ),
					'post_count' => $post_count,
				);
			}
		}

		restore_current_blog();
		return $courses;
	}

	/**
	 * AJAX: Enroll in a course.
	 */
	public static function ajax_enroll() {
		check_ajax_referer( '1111_learn_enroll', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Not logged in.', 'learn' ) ), 403 );
		}

		$course_term_id = isset( $_POST['course_term_id'] ) ? absint( $_POST['course_term_id'] ) : 0;
		if ( ! $course_term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid course.', 'learn' ) ), 400 );
		}

		$blog_id = get_user_meta( $user_id, '_1111_learner_blog_id', true );
		if ( ! $blog_id ) {
			wp_send_json_error( array( 'message' => __( 'No learner site found.', 'learn' ) ), 400 );
		}

		// Copy course content.
		$copied = Learn_Content_Copy::copy_course( $course_term_id, $blog_id, $user_id );
		if ( is_wp_error( $copied ) ) {
			wp_send_json_error( array( 'message' => $copied->get_error_message() ), 500 );
		}

		// Create enrollment record.
		global $wpdb;
		$main_site_id = get_main_site_id();
		switch_to_blog( $main_site_id );

		$total_xp = (int) get_term_meta( $course_term_id, '_1111_total_xp', true );
		$table    = $wpdb->prefix . '1111_learn_enrollments';

		$wpdb->insert( $table, array(
			'user_id'        => $user_id,
			'blog_id'        => $blog_id,
			'course_term_id' => $course_term_id,
			'enrolled_at'    => gmdate( 'Y-m-d H:i:s' ),
			'content_copied_at' => gmdate( 'Y-m-d H:i:s' ),
			'total_lessons'  => $copied,
			'total_xp'       => $total_xp,
		), array( '%d', '%d', '%d', '%s', '%s', '%d', '%d' ) );

		restore_current_blog();

		wp_send_json_success( array(
			'message'      => sprintf(
				/* translators: %d: number of lessons */
				__( 'Enrolled! %d lessons copied to your site.', 'learn' ),
				$copied
			),
			'copied_count' => $copied,
		) );
	}
}
