<?php
/**
 * Course review panel template.
 *
 * Loaded from dashboard.php when ?review=<term_id> is present.
 * $review_term_id is set by the parent template.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_data = Learn_Admin_Page::get_course_review_data( $review_term_id );
if ( is_wp_error( $course_data ) ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $course_data->get_error_message() ) . '</p></div></div>';
	return;
}

$is_publishable = ( 'complete' === $course_data['status'] );
$is_published   = ( 'published' === $course_data['status'] );
?>
<div class="wrap learn-dashboard learn-review">
	<div class="learn-page-header">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=learn-dashboard' ) ); ?>" class="learn-back-link">
			&larr; <?php esc_html_e( 'Back to Dashboard', 'learn' ); ?>
		</a>
	</div>

	<div class="learn-review-header">
		<h1><?php echo esc_html( $course_data['title'] ); ?></h1>
		<span class="learn-status learn-status--<?php echo esc_attr( $course_data['status'] ); ?>">
			<?php echo esc_html( learn_status_label( $course_data['status'] ) ); ?>
		</span>
	</div>

	<?php if ( $is_publishable ) : ?>
	<div class="learn-card learn-publish-card">
		<p><?php esc_html_e( 'This course is ready to publish. Review the content below, then publish when satisfied.', 'learn' ); ?></p>
		<button type="button" id="learn-publish-btn" class="button button-primary button-hero" data-term-id="<?php echo esc_attr( $review_term_id ); ?>">
			<?php esc_html_e( 'Publish Course', 'learn' ); ?>
		</button>
		<?php wp_nonce_field( '1111_learn_dashboard', '_learn_nonce' ); ?>
	</div>
	<?php endif; ?>

	<!-- Course Overview -->
	<div class="learn-card">
		<h2><?php esc_html_e( 'Course Overview', 'learn' ); ?></h2>

		<?php if ( $course_data['narrative'] ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Narrative Description', 'learn' ); ?></h3>
			<p><?php echo esc_html( $course_data['narrative'] ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( $course_data['work_product'] ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Portfolio Work Product', 'learn' ); ?></h3>
			<p>
				<strong><?php echo esc_html( $course_data['work_product'] ); ?></strong>
				(<?php echo esc_html( $course_data['work_product_type'] ); ?>)
			</p>
			<?php if ( $course_data['work_product_description'] ) : ?>
			<p><?php echo esc_html( $course_data['work_product_description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $course_data['objectives'] ) ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Learning Objectives', 'learn' ); ?></h3>
			<ol>
				<?php foreach ( $course_data['objectives'] as $obj ) : ?>
				<li><?php echo esc_html( $obj ); ?></li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php endif; ?>

		<div class="learn-review-meta">
			<span><?php printf( esc_html__( 'Total XP: %s', 'learn' ), '<strong>' . esc_html( number_format_i18n( $course_data['total_xp'] ) ) . '</strong>' ); ?></span>
		</div>
	</div>

	<!-- Lesson Groups -->
	<?php foreach ( $course_data['lesson_groups'] as $group_index => $group ) : ?>
	<div class="learn-card learn-lesson-group">
		<h2>
			<?php
			printf(
				/* translators: 1: group number, 2: group name */
				esc_html__( 'Lesson Group %1$d: %2$s', 'learn' ),
				$group_index + 1,
				esc_html( $group['group_name'] )
			);
			?>
		</h2>

		<?php if ( $group['objective'] ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Learning Objective', 'learn' ); ?></h3>
			<p><?php echo esc_html( $group['objective'] ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $group['mastery_criteria'] ) ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Mastery Criteria', 'learn' ); ?></h3>
			<ul>
				<?php foreach ( $group['mastery_criteria'] as $criterion ) : ?>
				<li><?php echo esc_html( $criterion ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $group['key_concepts'] ) ) : ?>
		<div class="learn-review-section">
			<h3><?php esc_html_e( 'Key Concepts', 'learn' ); ?></h3>
			<ul class="learn-inline-list">
				<?php foreach ( $group['key_concepts'] as $concept ) : ?>
				<li><?php echo esc_html( $concept ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<!-- Lessons in this group -->
		<?php foreach ( $group['lessons'] as $lesson_index => $lesson ) : ?>
		<details class="learn-lesson-details" <?php echo ( 0 === $lesson_index ) ? 'open' : ''; ?>>
			<summary>
				<span class="learn-lesson-title"><?php echo esc_html( $lesson['title'] ); ?></span>
				<?php if ( $lesson['activity_type'] ) : ?>
				<span class="learn-badge learn-badge--<?php echo esc_attr( $lesson['activity_type'] ); ?>">
					<?php echo esc_html( ucfirst( $lesson['activity_type'] ) ); ?>
				</span>
				<?php endif; ?>
				<?php if ( $lesson['xp_value'] ) : ?>
				<span class="learn-xp"><?php echo esc_html( $lesson['xp_value'] ); ?> XP</span>
				<?php endif; ?>
			</summary>

			<div class="learn-lesson-content">
				<!-- Key Takeaways -->
				<?php if ( ! empty( $lesson['key_takeaways'] ) ) : ?>
				<div class="learn-review-section">
					<h4><?php esc_html_e( 'Key Takeaways', 'learn' ); ?></h4>
					<ul>
						<?php foreach ( $lesson['key_takeaways'] as $takeaway ) : ?>
						<li><?php echo esc_html( $takeaway ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>

				<!-- Content Preview -->
				<div class="learn-review-section">
					<h4><?php esc_html_e( 'Lesson Content Preview', 'learn' ); ?></h4>
					<div class="learn-content-preview">
						<?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $lesson['content'] ), 80 ) ); ?>
					</div>
					<?php if ( $lesson['edit_url'] ) : ?>
					<a href="<?php echo esc_url( $lesson['edit_url'] ); ?>" class="learn-view-full">
						<?php esc_html_e( 'View full lesson in editor', 'learn' ); ?> &rarr;
					</a>
					<?php endif; ?>
				</div>

				<!-- Activity -->
				<?php if ( $lesson['activity'] ) : ?>
				<div class="learn-review-section learn-activity-section">
					<h4>
						<?php esc_html_e( 'Activity', 'learn' ); ?>
						<?php if ( $lesson['review_verdict'] ) : ?>
						<span class="learn-verdict learn-verdict--<?php echo esc_attr( $lesson['review_verdict'] ); ?>">
							<?php echo esc_html( 'approved' === $lesson['review_verdict'] ? __( 'Approved', 'learn' ) : __( 'Revised', 'learn' ) ); ?>
						</span>
						<?php endif; ?>
					</h4>

					<?php if ( isset( $lesson['activity']['prompt'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Prompt:', 'learn' ); ?></strong> <?php echo esc_html( $lesson['activity']['prompt'] ); ?></p>
					<?php endif; ?>

					<?php if ( isset( $lesson['activity']['instructions'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Instructions:', 'learn' ); ?></strong> <?php echo esc_html( $lesson['activity']['instructions'] ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $lesson['activity']['scoring_rubric'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Rubric:', 'learn' ); ?></strong></p>
					<ul>
						<?php foreach ( $lesson['activity']['scoring_rubric'] as $rubric_item ) : ?>
						<li><?php echo esc_html( $rubric_item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>

					<?php if ( ! empty( $lesson['activity']['hints'] ) ) : ?>
					<p><strong><?php esc_html_e( 'Hints:', 'learn' ); ?></strong></p>
					<ul>
						<?php foreach ( $lesson['activity']['hints'] as $hint ) : ?>
						<li><?php echo esc_html( $hint ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php endif; ?>

					<?php if ( $lesson['portfolio'] ) : ?>
					<p><strong><?php esc_html_e( 'Portfolio Contribution:', 'learn' ); ?></strong> <?php echo esc_html( $lesson['portfolio'] ); ?></p>
					<?php endif; ?>

					<?php if ( $lesson['milestone'] ) : ?>
					<p><strong><?php esc_html_e( 'Milestone:', 'learn' ); ?></strong> <?php echo esc_html( $lesson['milestone'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</details>
		<?php endforeach; ?>
	</div>
	<?php endforeach; ?>

	<!-- Final Assessment -->
	<?php if ( $course_data['assessment'] ) :
		$assessment = $course_data['assessment'];
	?>
	<div class="learn-card learn-assessment-card">
		<h2><?php esc_html_e( 'Final Assessment', 'learn' ); ?></h2>

		<?php if ( isset( $assessment['assessment_title'] ) ) : ?>
		<h3><?php echo esc_html( $assessment['assessment_title'] ); ?></h3>
		<?php endif; ?>

		<?php if ( isset( $assessment['prompt'] ) ) : ?>
		<div class="learn-review-section">
			<h4><?php esc_html_e( 'Prompt', 'learn' ); ?></h4>
			<p><?php echo esc_html( $assessment['prompt'] ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( isset( $assessment['instructions'] ) ) : ?>
		<div class="learn-review-section">
			<h4><?php esc_html_e( 'Instructions', 'learn' ); ?></h4>
			<p><?php echo esc_html( $assessment['instructions'] ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $assessment['portfolio_rubric'] ) ) : ?>
		<div class="learn-review-section">
			<h4><?php esc_html_e( 'Portfolio Rubric', 'learn' ); ?></h4>
			<?php foreach ( $assessment['portfolio_rubric'] as $rubric ) : ?>
			<div class="learn-rubric-item">
				<p><strong><?php echo esc_html( $rubric['objective'] ); ?></strong></p>
				<ul>
					<?php foreach ( $rubric['criteria'] as $criterion ) : ?>
					<li><?php echo esc_html( $criterion ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $assessment['scoring_guide'] ) ) : ?>
		<div class="learn-review-section">
			<h4><?php esc_html_e( 'Scoring Guide', 'learn' ); ?></h4>
			<dl class="learn-scoring-guide">
				<dt><?php esc_html_e( 'Mastery (0.85-1.0)', 'learn' ); ?></dt>
				<dd><?php echo esc_html( $assessment['scoring_guide']['mastery'] ); ?></dd>
				<dt><?php esc_html_e( 'Proficient (0.70-0.84)', 'learn' ); ?></dt>
				<dd><?php echo esc_html( $assessment['scoring_guide']['proficient'] ); ?></dd>
				<dt><?php esc_html_e( 'Developing (0.50-0.69)', 'learn' ); ?></dt>
				<dd><?php echo esc_html( $assessment['scoring_guide']['developing'] ); ?></dd>
				<dt><?php esc_html_e( 'Beginning (0.0-0.49)', 'learn' ); ?></dt>
				<dd><?php echo esc_html( $assessment['scoring_guide']['beginning'] ); ?></dd>
			</dl>
		</div>
		<?php endif; ?>

		<div class="learn-review-meta">
			<span><?php esc_html_e( 'XP: 500', 'learn' ); ?></span>
			<span><?php esc_html_e( 'Milestone: Portfolio Delivered', 'learn' ); ?></span>
		</div>

		<?php if ( isset( $assessment['completion_message'] ) ) : ?>
		<div class="learn-review-section">
			<h4><?php esc_html_e( 'Completion Message', 'learn' ); ?></h4>
			<p><?php echo esc_html( $assessment['completion_message'] ); ?></p>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<!-- Publish Confirmation Dialog -->
	<?php if ( $is_publishable ) : ?>
	<div id="learn-publish-dialog" class="learn-dialog" hidden>
		<div class="learn-dialog-content" role="alertdialog" aria-modal="true" aria-labelledby="learn-dialog-title" aria-describedby="learn-dialog-desc">
			<h2 id="learn-dialog-title"><?php esc_html_e( 'Publish Course?', 'learn' ); ?></h2>
			<p id="learn-dialog-desc">
				<?php esc_html_e( 'Publishing this course makes it available to learners. Published course content cannot be updated — you\'ll need to create a new course to make changes.', 'learn' ); ?>
			</p>
			<div class="learn-dialog-actions">
				<button type="button" id="learn-dialog-confirm" class="button button-primary">
					<?php esc_html_e( 'Publish', 'learn' ); ?>
				</button>
				<button type="button" id="learn-dialog-cancel" class="button">
					<?php esc_html_e( 'Cancel', 'learn' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
