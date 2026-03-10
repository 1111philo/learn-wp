<?php
/**
 * Learner panel template.
 *
 * @package Learn
 *
 * @var array $catalog  Available courses from main site.
 * @var array $enrolled Enrolled courses on this subsite.
 * @var array $xp       XP summary data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Learn — My Courses', '1111-learn' ); ?></h1>

	<?php if ( $xp['total_xp'] > 0 ) : ?>
		<div class="1111-learn-xp-summary" aria-label="<?php esc_attr_e( 'Experience points summary', '1111-learn' ); ?>">
			<p>
				<strong><?php esc_html_e( 'Total XP:', '1111-learn' ); ?></strong>
				<?php echo esc_html( number_format_i18n( $xp['total_xp'] ) ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $enrolled ) ) : ?>
		<section aria-labelledby="1111-learn-enrolled-heading">
			<h2 id="1111-learn-enrolled-heading"><?php esc_html_e( 'My Courses', '1111-learn' ); ?></h2>

			<div class="1111-learn-course-grid">
				<?php foreach ( $enrolled as $course ) : ?>
					<div class="1111-learn-course-card" role="article" aria-label="<?php echo esc_attr( $course['name'] ); ?>">
						<h3><?php echo esc_html( $course['name'] ); ?></h3>

						<div class="1111-learn-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( $course['progress'] ); ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: progress percentage */ __( '%d%% complete', '1111-learn' ), $course['progress'] ) ); ?>">
							<div class="1111-learn-progress-fill" style="width: <?php echo esc_attr( $course['progress'] ); ?>%;"></div>
						</div>

						<p>
							<?php
							printf(
								/* translators: 1: completed count, 2: total count */
								esc_html__( '%1$d of %2$d lessons completed', '1111-learn' ),
								absint( $course['completed'] ),
								absint( $course['total'] )
							);
							?>
						</p>

						<?php if ( $course['xp'] > 0 ) : ?>
							<p>
								<strong><?php esc_html_e( 'XP:', '1111-learn' ); ?></strong>
								<?php echo esc_html( number_format_i18n( $course['xp'] ) ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<section aria-labelledby="1111-learn-catalog-heading">
		<h2 id="1111-learn-catalog-heading"><?php esc_html_e( 'Available Courses', '1111-learn' ); ?></h2>

		<?php if ( empty( $catalog ) ) : ?>
			<p><?php esc_html_e( 'No courses are available at this time.', '1111-learn' ); ?></p>
		<?php else : ?>
			<div class="1111-learn-course-grid">
				<?php foreach ( $catalog as $course ) : ?>
					<div class="1111-learn-course-card" role="article" aria-label="<?php echo esc_attr( $course['name'] ); ?>">
						<h3><?php echo esc_html( $course['name'] ); ?></h3>

						<?php if ( ! empty( $course['description'] ) ) : ?>
							<p><?php echo esc_html( $course['description'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $course['narrative_description'] ) ) : ?>
							<p><em><?php echo esc_html( $course['narrative_description'] ); ?></em></p>
						<?php endif; ?>

						<p>
							<?php
							printf(
								/* translators: %d: number of lessons */
								esc_html( _n( '%d lesson', '%d lessons', $course['lesson_count'], '1111-learn' ) ),
								absint( $course['lesson_count'] )
							);
							?>
						</p>

						<form class="1111-learn-select-course" method="post">
							<?php wp_nonce_field( '1111_learn_panel', '1111_learn_panel_nonce' ); ?>
							<input type="hidden" name="course_term_id" value="<?php echo esc_attr( $course['term_id'] ); ?>" />
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Select', '1111-learn' ); ?>
							</button>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>

<script>
(function() {
	var forms = document.querySelectorAll( '.1111-learn-select-course' );

	forms.forEach( function( form ) {
		form.addEventListener( 'submit', function( e ) {
			e.preventDefault();

			var button    = form.querySelector( 'button[type="submit"]' );
			var termId    = form.querySelector( 'input[name="course_term_id"]' ).value;
			var nonce     = form.querySelector( 'input[name="1111_learn_panel_nonce"]' ).value;

			button.disabled  = true;
			button.textContent = '<?php echo esc_js( __( 'Enrolling...', '1111-learn' ) ); ?>';

			var data = new FormData();
			data.append( 'action', '1111_select_course' );
			data.append( 'nonce', nonce );
			data.append( 'course_term_id', termId );

			fetch( ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( result ) {
				if ( result.success ) {
					window.location.reload();
				} else {
					alert( result.data.message || '<?php echo esc_js( __( 'Enrollment failed.', '1111-learn' ) ); ?>' );
					button.disabled    = false;
					button.textContent = '<?php echo esc_js( __( 'Select', '1111-learn' ) ); ?>';
				}
			} )
			.catch( function() {
				alert( '<?php echo esc_js( __( 'An error occurred. Please try again.', '1111-learn' ) ); ?>' );
				button.disabled    = false;
				button.textContent = '<?php echo esc_js( __( 'Select', '1111-learn' ) ); ?>';
			} );
		} );
	} );
})();
</script>
