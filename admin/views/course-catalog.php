<?php
/**
 * Course catalog template.
 *
 * Lists published courses from the main site.
 *
 * @package Learn
 *
 * @var array $courses Array of course data from the main site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="1111-learn-course-catalog">
	<h2><?php esc_html_e( 'Course Catalog', '1111-learn' ); ?></h2>

	<?php if ( empty( $courses ) ) : ?>
		<p><?php esc_html_e( 'No published courses are available.', '1111-learn' ); ?></p>
	<?php else : ?>
		<div class="1111-learn-course-list" role="list">
			<?php foreach ( $courses as $course ) : ?>
				<div class="1111-learn-catalog-card" role="listitem" aria-label="<?php echo esc_attr( $course['name'] ); ?>">
					<h3><?php echo esc_html( $course['name'] ); ?></h3>

					<?php if ( ! empty( $course['narrative_description'] ) ) : ?>
						<div class="1111-learn-catalog-narrative">
							<p><?php echo esc_html( $course['narrative_description'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $course['work_product_description'] ) ) : ?>
						<div class="1111-learn-catalog-work-product">
							<strong><?php esc_html_e( 'Work Product:', '1111-learn' ); ?></strong>
							<p><?php echo esc_html( $course['work_product_description'] ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $course['description'] ) ) : ?>
						<div class="1111-learn-catalog-description">
							<p><?php echo esc_html( $course['description'] ); ?></p>
						</div>
					<?php endif; ?>

					<form class="1111-learn-start-course" method="post">
						<?php wp_nonce_field( '1111_learn_panel', '1111_learn_panel_nonce' ); ?>
						<input type="hidden" name="course_term_id" value="<?php echo esc_attr( $course['term_id'] ); ?>" />
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Start This Course', '1111-learn' ); ?>
						</button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
