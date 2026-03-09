<?php
/**
 * Learner progress dashboard template.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$progress_data = Learn_Admin_Page::get_learner_progress();
?>
<div class="wrap learn-dashboard">
	<div class="learn-page-header">
		<img src="<?php echo esc_url( LEARN_PLUGIN_URL . 'assets/learn-logo.svg' ); ?>" alt="" class="learn-logo">
		<h1><?php esc_html_e( 'Learner Progress', 'learn' ); ?></h1>
	</div>

	<?php if ( empty( $progress_data ) ) : ?>
	<div class="learn-card">
		<p><?php esc_html_e( 'No learners have enrolled in courses yet.', 'learn' ); ?></p>
	</div>
	<?php else : ?>
	<div class="learn-card">
		<table class="widefat striped learn-progress-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Learner', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Course', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Progress', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'XP', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'learn' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Enrolled', 'learn' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $progress_data as $entry ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $entry['display_name'] ); ?></strong>
						<br><span class="learn-course-desc"><?php echo esc_html( $entry['user_login'] ); ?></span>
					</td>
					<td><?php echo esc_html( $entry['course_name'] ); ?></td>
					<td>
						<?php if ( $entry['total_lessons'] > 0 ) : ?>
						<div class="learn-progress-bar" role="progressbar"
							aria-valuenow="<?php echo esc_attr( $entry['lessons_completed'] ); ?>"
							aria-valuemin="0"
							aria-valuemax="<?php echo esc_attr( $entry['total_lessons'] ); ?>"
							aria-label="<?php echo esc_attr( sprintf( __( '%1$d of %2$d lessons completed', 'learn' ), $entry['lessons_completed'], $entry['total_lessons'] ) ); ?>">
							<div class="learn-progress-fill" style="width: <?php echo esc_attr( round( ( $entry['lessons_completed'] / $entry['total_lessons'] ) * 100 ) ); ?>%"></div>
						</div>
						<span class="learn-progress-text">
							<?php
							printf(
								/* translators: 1: completed, 2: total */
								esc_html__( '%1$d / %2$d lessons', 'learn' ),
								$entry['lessons_completed'],
								$entry['total_lessons']
							);
							?>
						</span>
						<?php else : ?>
						<span class="learn-progress-text"><?php esc_html_e( 'Not started', 'learn' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						printf(
							/* translators: 1: current XP, 2: total XP */
							esc_html__( '%1$s / %2$s', 'learn' ),
							number_format_i18n( $entry['current_xp'] ),
							number_format_i18n( $entry['total_xp'] )
						);
						?>
					</td>
					<td>
						<span class="learn-status learn-status--<?php echo esc_attr( $entry['status'] ); ?>">
							<?php echo esc_html( ucfirst( $entry['status'] ) ); ?>
						</span>
					</td>
					<td>
						<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $entry['enrolled_at'] ) ) ); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
