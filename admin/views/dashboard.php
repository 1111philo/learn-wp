<?php
/**
 * Dashboard page template — course creation form, progress stepper, existing courses.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'learn_status_label' ) ) {
	/**
	 * Get human-readable status label.
	 *
	 * @param string $status Status key.
	 * @return string Label.
	 */
	function learn_status_label( $status ) {
		$labels = array(
			'generating' => __( 'Generating', 'learn' ),
			'complete'   => __( 'Draft', 'learn' ),
			'published'  => __( 'Published', 'learn' ),
			'failed'     => __( 'Failed', 'learn' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}
}

$courses = Learn_Admin_Page::get_courses_list();
$review_term_id = isset( $_GET['review'] ) ? absint( $_GET['review'] ) : 0;

if ( $review_term_id ) {
	include LEARN_PLUGIN_DIR . 'admin/views/course-review.php';
	return;
}
?>
<div class="wrap learn-dashboard">
	<div class="learn-page-header">
		<img src="<?php echo esc_url( LEARN_PLUGIN_URL . 'assets/learn-logo.svg' ); ?>" alt="" class="learn-logo">
		<h1><?php esc_html_e( 'Learn Dashboard', 'learn' ); ?></h1>
	</div>

	<?php if ( ! Learn_Settings::get_api_key() ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: settings page URL */
					esc_html__( 'Anthropic API key is not configured. %s to add your API key before creating courses.', 'learn' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=learn-settings' ) ) . '">' . esc_html__( 'Go to Settings', 'learn' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Course Creation Form -->
	<div class="learn-card" id="learn-create-course">
		<h2><?php esc_html_e( 'Create a New Course', 'learn' ); ?></h2>

		<div id="learn-form-errors" class="learn-form-errors" role="alert" hidden></div>

		<form id="learn-course-form" novalidate>
			<?php wp_nonce_field( '1111_learn_dashboard', '_learn_nonce' ); ?>

			<div class="learn-field">
				<label for="learn-title"><?php esc_html_e( 'Course Title', 'learn' ); ?></label>
				<input type="text" id="learn-title" name="title" maxlength="200" required
					aria-describedby="learn-title-desc learn-title-error">
				<p class="description" id="learn-title-desc">
					<?php esc_html_e( 'A clear, descriptive title for the course.', 'learn' ); ?>
				</p>
				<p class="learn-field-error" id="learn-title-error" hidden></p>
			</div>

			<div class="learn-field">
				<label for="learn-description"><?php esc_html_e( 'Course Description', 'learn' ); ?></label>
				<textarea id="learn-description" name="description" rows="4" required
					aria-describedby="learn-desc-desc learn-desc-error"></textarea>
				<p class="description" id="learn-desc-desc">
					<?php esc_html_e( 'What should learners be able to do after completing this course? What topics does it cover?', 'learn' ); ?>
				</p>
				<p class="learn-field-error" id="learn-desc-error" hidden></p>
			</div>

			<fieldset class="learn-field">
				<legend><?php esc_html_e( 'Learning Objectives', 'learn' ); ?></legend>
				<p class="description" id="learn-obj-desc">
					<?php esc_html_e( 'Each objective becomes a lesson group. Add at least one.', 'learn' ); ?>
				</p>
				<div id="learn-objectives-list" role="list" aria-describedby="learn-obj-desc">
					<div class="learn-objective-row" role="listitem">
						<label for="learn-obj-0" class="screen-reader-text">
							<?php esc_html_e( 'Objective 1', 'learn' ); ?>
						</label>
						<input type="text" id="learn-obj-0" name="objectives[]" class="learn-objective-input" required
							aria-describedby="learn-obj-0-error"
							placeholder="<?php esc_attr_e( 'e.g. Identify common accessibility barriers on the web', 'learn' ); ?>">
						<p class="learn-field-error" id="learn-obj-0-error" hidden></p>
					</div>
				</div>
				<button type="button" id="learn-add-objective" class="button">
					<?php esc_html_e( '+ Add Objective', 'learn' ); ?>
				</button>
			</fieldset>

			<div class="learn-form-actions">
				<button type="submit" id="learn-generate-btn" class="button button-primary button-hero">
					<?php esc_html_e( 'Generate Course', 'learn' ); ?>
				</button>
			</div>
		</form>
	</div>

	<!-- Progress Stepper (hidden until generation starts) -->
	<div class="learn-card" id="learn-progress-panel" hidden>
		<h2 id="learn-progress-heading"><?php esc_html_e( 'Generating Course', 'learn' ); ?></h2>
		<div id="learn-progress-stepper" class="learn-stepper" aria-live="polite" aria-label="<?php esc_attr_e( 'Generation progress', 'learn' ); ?>">
		</div>
		<div id="learn-progress-actions" hidden>
			<a id="learn-review-link" href="#" class="button button-primary">
				<?php esc_html_e( 'Review Course', 'learn' ); ?>
			</a>
			<button type="button" id="learn-retry-btn" class="button" hidden>
				<?php esc_html_e( 'Retry', 'learn' ); ?>
			</button>
		</div>
	</div>

	<!-- Existing Courses -->
	<?php if ( ! empty( $courses ) ) : ?>
	<div class="learn-card">
		<h2><?php esc_html_e( 'Existing Courses', 'learn' ); ?></h2>
		<table class="widefat striped learn-courses-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Course', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Lessons', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Total XP', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'learn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $courses as $course ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $course['title'] ); ?></strong>
						<?php if ( $course['description'] ) : ?>
							<br><span class="learn-course-desc"><?php echo esc_html( wp_trim_words( $course['description'], 15 ) ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<span class="learn-status learn-status--<?php echo esc_attr( $course['status'] ); ?>">
							<?php echo esc_html( learn_status_label( $course['status'] ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $course['post_count'] ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $course['total_xp'] ) ); ?></td>
					<td>
						<?php
						if ( $course['created'] ) {
							echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $course['created'] ) ) );
						}
						?>
					</td>
					<td>
						<?php if ( in_array( $course['status'], array( 'complete', 'published' ), true ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=learn-dashboard&review=' . $course['term_id'] ) ); ?>" class="button button-small">
								<?php esc_html_e( 'Review', 'learn' ); ?>
							</a>
						<?php elseif ( 'failed' === $course['status'] ) : ?>
							<button type="button" class="button button-small learn-retry-course" data-term-id="<?php echo esc_attr( $course['term_id'] ); ?>">
								<?php esc_html_e( 'Retry', 'learn' ); ?>
							</button>
						<?php elseif ( 'generating' === $course['status'] ) : ?>
							<button type="button" class="button button-small learn-poll-course" data-term-id="<?php echo esc_attr( $course['term_id'] ); ?>">
								<?php esc_html_e( 'View Progress', 'learn' ); ?>
							</button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
