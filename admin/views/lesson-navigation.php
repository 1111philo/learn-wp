<?php
/**
 * Lesson navigation template.
 *
 * Appended to learn post content on the frontend.
 *
 * @package Learn
 *
 * @var array $navigation Navigation data from Learn_Course_Navigation::get_lesson_navigation().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$course_term = $navigation['course_term'];
$group_term  = $navigation['group_term'];
$prev        = $navigation['prev_lesson'];
$next        = $navigation['next_lesson'];
$current     = $navigation['current_number'];
$total       = $navigation['total_lessons'];
$activity    = $navigation['activity'];
?>
<div class="1111-learn-lesson-nav">

	<?php if ( $course_term ) : ?>
		<nav class="1111-learn-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', '1111-learn' ); ?>">
			<ol>
				<li>
					<a href="<?php echo esc_url( get_term_link( $course_term ) ); ?>">
						<?php echo esc_html( $course_term->name ); ?>
					</a>
				</li>
				<?php if ( $group_term ) : ?>
					<li>
						<a href="<?php echo esc_url( get_term_link( $group_term ) ); ?>">
							<?php echo esc_html( $group_term->name ); ?>
						</a>
					</li>
				<?php endif; ?>
				<li aria-current="page">
					<?php echo esc_html( get_the_title() ); ?>
				</li>
			</ol>
		</nav>
	<?php endif; ?>

	<?php if ( $total > 0 ) : ?>
		<p class="1111-learn-progress-indicator" aria-label="<?php esc_attr_e( 'Lesson progress', '1111-learn' ); ?>">
			<?php
			printf(
				/* translators: 1: current lesson number, 2: total lessons */
				esc_html__( 'Lesson %1$d of %2$d', '1111-learn' ),
				absint( $current ),
				absint( $total )
			);
			?>
		</p>
	<?php endif; ?>

	<nav class="1111-learn-prev-next" aria-label="<?php esc_attr_e( 'Lesson navigation', '1111-learn' ); ?>">
		<div class="1111-learn-prev">
			<?php if ( $prev ) : ?>
				<a href="<?php echo esc_url( get_permalink( $prev->ID ) ); ?>" rel="prev">
					&larr; <?php echo esc_html( $prev->post_title ); ?>
				</a>
			<?php endif; ?>
		</div>
		<div class="1111-learn-next">
			<?php if ( $next ) : ?>
				<a href="<?php echo esc_url( get_permalink( $next->ID ) ); ?>" rel="next">
					<?php echo esc_html( $next->post_title ); ?> &rarr;
				</a>
			<?php endif; ?>
		</div>
	</nav>

	<?php if ( ! empty( $activity['prompt'] ) ) : ?>
		<section class="1111-learn-activity" aria-labelledby="1111-learn-activity-heading">
			<h3 id="1111-learn-activity-heading"><?php esc_html_e( 'Activity', '1111-learn' ); ?></h3>

			<div class="1111-learn-activity-prompt">
				<p><?php echo esc_html( $activity['prompt'] ); ?></p>
			</div>

			<?php if ( ! empty( $activity['instructions'] ) ) : ?>
				<div class="1111-learn-activity-instructions">
					<h4><?php esc_html_e( 'Instructions', '1111-learn' ); ?></h4>
					<?php echo wp_kses_post( $activity['instructions'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $activity['hints'] ) ) : ?>
				<details class="1111-learn-activity-hints">
					<summary><?php esc_html_e( 'Show Hints', '1111-learn' ); ?></summary>
					<?php echo wp_kses_post( $activity['hints'] ); ?>
				</details>
			<?php endif; ?>

			<?php if ( ! $activity['submitted'] ) : ?>
				<form class="1111-learn-submit-assessment" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
					<?php wp_nonce_field( '1111_learn_submit_assessment', '1111_learn_assessment_nonce' ); ?>
					<input type="hidden" name="action" value="1111_submit_assessment" />
					<input type="hidden" name="post_id" value="<?php echo esc_attr( get_the_ID() ); ?>" />
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Submit for Assessment', '1111-learn' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( $activity['submitted'] && ! empty( $activity['result'] ) ) : ?>
				<div class="1111-learn-assessment-result" aria-labelledby="1111-learn-result-heading">
					<h4 id="1111-learn-result-heading"><?php esc_html_e( 'Assessment Results', '1111-learn' ); ?></h4>
					<?php echo wp_kses_post( $activity['result'] ); ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

</div>
