<?php
/**
 * Course landing page template.
 *
 * @package Learn
 *
 * @var array $course_data Course data from Learn_Course_Navigation::get_course_data().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$term     = $course_data['term'];
$groups   = $course_data['groups'];
$ungrouped = $course_data['ungrouped'];
?>
<div class="1111-learn-course-landing">
	<header class="1111-learn-course-header">
		<h2><?php echo esc_html( $term->name ); ?></h2>

		<?php if ( ! empty( $course_data['narrative_description'] ) ) : ?>
			<div class="1111-learn-course-narrative">
				<p><?php echo esc_html( $course_data['narrative_description'] ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $course_data['work_product_description'] ) ) : ?>
			<div class="1111-learn-course-work-product">
				<strong><?php esc_html_e( 'Work Product:', '1111-learn' ); ?></strong>
				<p><?php echo esc_html( $course_data['work_product_description'] ); ?></p>
			</div>
		<?php endif; ?>
	</header>

	<section class="1111-learn-course-progress" aria-label="<?php esc_attr_e( 'Course progress', '1111-learn' ); ?>">
		<div class="1111-learn-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $course_data['progress'] ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: progress percentage */ __( '%d%% complete', '1111-learn' ), $course_data['progress'] ) ); ?>">
			<div class="1111-learn-progress-fill" style="width: <?php echo esc_attr( $course_data['progress'] ); ?>%;"></div>
		</div>
		<p>
			<?php
			printf(
				/* translators: 1: completed count, 2: total count */
				esc_html__( '%1$d of %2$d lessons completed', '1111-learn' ),
				absint( $course_data['completed_lessons'] ),
				absint( $course_data['total_lessons'] )
			);
			?>
		</p>
	</section>

	<section class="1111-learn-course-lessons" aria-labelledby="1111-learn-lessons-heading">
		<h3 id="1111-learn-lessons-heading"><?php esc_html_e( 'Lessons', '1111-learn' ); ?></h3>

		<?php if ( ! empty( $groups ) ) : ?>
			<?php foreach ( $groups as $group ) : ?>
				<div class="1111-learn-lesson-group">
					<h4><?php echo esc_html( $group['term']->name ); ?></h4>

					<ol class="1111-learn-lesson-list">
						<?php foreach ( $group['lessons'] as $lesson ) : ?>
							<li class="1111-learn-lesson-item <?php echo $lesson['completed'] ? '1111-learn-lesson-completed' : ''; ?>">
								<a href="<?php echo esc_url( $lesson['url'] ); ?>">
									<?php echo esc_html( $lesson['post']->post_title ); ?>
								</a>
								<?php if ( $lesson['completed'] ) : ?>
									<span class="1111-learn-status-complete" aria-label="<?php esc_attr_e( 'Completed', '1111-learn' ); ?>">&#10003;</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( ! empty( $ungrouped ) ) : ?>
			<ol class="1111-learn-lesson-list">
				<?php foreach ( $ungrouped as $lesson ) : ?>
					<li class="1111-learn-lesson-item <?php echo $lesson['completed'] ? '1111-learn-lesson-completed' : ''; ?>">
						<a href="<?php echo esc_url( $lesson['url'] ); ?>">
							<?php echo esc_html( $lesson['post']->post_title ); ?>
						</a>
						<?php if ( $lesson['completed'] ) : ?>
							<span class="1111-learn-status-complete" aria-label="<?php esc_attr_e( 'Completed', '1111-learn' ); ?>">&#10003;</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>

		<?php if ( empty( $groups ) && empty( $ungrouped ) ) : ?>
			<p><?php esc_html_e( 'No lessons available for this course yet.', '1111-learn' ); ?></p>
		<?php endif; ?>
	</section>
</div>
