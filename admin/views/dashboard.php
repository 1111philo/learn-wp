<?php
/**
 * Course creation dashboard template.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Learn — Create Course', '1111-learn' ); ?></h1>

	<div id="1111-learn-error-summary" role="alert" aria-live="assertive" style="display:none;">
		<div class="notice notice-error">
			<p id="1111-learn-error-message"></p>
		</div>
	</div>

	<form id="1111-learn-course-form" method="post">
		<?php wp_nonce_field( '1111_generate_course', '1111_generate_course_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="1111-learn-course-title">
							<?php esc_html_e( 'Course Title', '1111-learn' ); ?>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="1111-learn-course-title"
							name="title"
							class="regular-text"
							required
							maxlength="200"
							aria-describedby="1111-learn-title-desc 1111-learn-title-error"
						/>
						<p class="description" id="1111-learn-title-desc">
							<?php esc_html_e( 'Required. Maximum 200 characters.', '1111-learn' ); ?>
						</p>
						<p class="1111-learn-field-error" id="1111-learn-title-error" role="alert" aria-live="polite" style="display:none;color:#d63638;"></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="1111-learn-course-description">
							<?php esc_html_e( 'Course Description', '1111-learn' ); ?>
						</label>
					</th>
					<td>
						<textarea
							id="1111-learn-course-description"
							name="description"
							class="large-text"
							rows="5"
							required
							minlength="10"
							maxlength="1000"
							aria-describedby="1111-learn-desc-desc 1111-learn-desc-error"
						></textarea>
						<p class="description" id="1111-learn-desc-desc">
							<?php esc_html_e( 'Required. Between 10 and 1000 characters.', '1111-learn' ); ?>
						</p>
						<p class="1111-learn-field-error" id="1111-learn-desc-error" role="alert" aria-live="polite" style="display:none;color:#d63638;"></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<span id="1111-learn-objectives-label">
							<?php esc_html_e( 'Learning Objectives', '1111-learn' ); ?>
						</span>
					</th>
					<td>
						<fieldset aria-labelledby="1111-learn-objectives-label" aria-describedby="1111-learn-objectives-desc 1111-learn-objectives-error">
							<div id="1111-learn-objectives-list">
								<div class="1111-learn-objective-row" style="margin-bottom:8px;">
									<label for="1111-learn-objective-0" class="screen-reader-text">
										<?php esc_html_e( 'Objective 1', '1111-learn' ); ?>
									</label>
									<input
										type="text"
										id="1111-learn-objective-0"
										name="objectives[]"
										class="regular-text"
										maxlength="300"
										required
									/>
									<button
										type="button"
										class="button 1111-learn-remove-objective"
										aria-label="<?php esc_attr_e( 'Remove this objective', '1111-learn' ); ?>"
										style="display:none;"
									>
										<?php esc_html_e( 'Remove', '1111-learn' ); ?>
									</button>
								</div>
							</div>

							<p>
								<button
									type="button"
									id="1111-learn-add-objective"
									class="button button-secondary"
								>
									<?php esc_html_e( 'Add Objective', '1111-learn' ); ?>
								</button>
							</p>

							<p class="description" id="1111-learn-objectives-desc">
								<?php esc_html_e( 'At least 1, up to 8 objectives. Each must be 300 characters or fewer.', '1111-learn' ); ?>
							</p>
							<p class="1111-learn-field-error" id="1111-learn-objectives-error" role="alert" aria-live="polite" style="display:none;color:#d63638;"></p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" id="1111-learn-generate" class="button button-primary button-hero">
				<?php esc_html_e( 'Generate Course', '1111-learn' ); ?>
			</button>
		</p>
	</form>

	<div id="1111-learn-progress" style="display:none;" aria-live="polite" aria-atomic="true">
		<h2><?php esc_html_e( 'Generating Course...', '1111-learn' ); ?></h2>
		<div class="1111-learn-progress-stepper">
			<ol id="1111-learn-progress-steps">
				<li data-step="1"><?php esc_html_e( 'Describing course', '1111-learn' ); ?></li>
				<li data-step="2"><?php esc_html_e( 'Planning lessons', '1111-learn' ); ?></li>
				<li data-step="3"><?php esc_html_e( 'Writing lessons', '1111-learn' ); ?></li>
				<li data-step="4"><?php esc_html_e( 'Creating activities', '1111-learn' ); ?></li>
				<li data-step="5"><?php esc_html_e( 'Reviewing activities', '1111-learn' ); ?></li>
				<li data-step="6"><?php esc_html_e( 'Creating assessments', '1111-learn' ); ?></li>
				<li data-step="7"><?php esc_html_e( 'Finalizing', '1111-learn' ); ?></li>
			</ol>
		</div>
		<p id="1111-learn-progress-label"></p>
	</div>
</div>

<script>
(function() {
	var objectivesList = document.getElementById( '1111-learn-objectives-list' );
	var addButton      = document.getElementById( '1111-learn-add-objective' );
	var maxObjectives  = 8;

	function getObjectiveCount() {
		return objectivesList.querySelectorAll( '.1111-learn-objective-row' ).length;
	}

	function updateRemoveButtons() {
		var rows    = objectivesList.querySelectorAll( '.1111-learn-objective-row' );
		var buttons = objectivesList.querySelectorAll( '.1111-learn-remove-objective' );
		var count   = rows.length;

		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].style.display = count > 1 ? 'inline-block' : 'none';
		}

		addButton.disabled = count >= maxObjectives;
	}

	addButton.addEventListener( 'click', function() {
		var count = getObjectiveCount();
		if ( count >= maxObjectives ) {
			return;
		}

		var row   = document.createElement( 'div' );
		row.className = '1111-learn-objective-row';
		row.style.marginBottom = '8px';

		var label       = document.createElement( 'label' );
		label.setAttribute( 'for', '1111-learn-objective-' + count );
		label.className = 'screen-reader-text';
		label.textContent = '<?php echo esc_js( __( 'Objective', '1111-learn' ) ); ?> ' + ( count + 1 );

		var input   = document.createElement( 'input' );
		input.type  = 'text';
		input.id    = '1111-learn-objective-' + count;
		input.name  = 'objectives[]';
		input.className = 'regular-text';
		input.maxLength = 300;
		input.required  = true;

		var remove       = document.createElement( 'button' );
		remove.type      = 'button';
		remove.className = 'button 1111-learn-remove-objective';
		remove.setAttribute( 'aria-label', '<?php echo esc_js( __( 'Remove this objective', '1111-learn' ) ); ?>' );
		remove.textContent = '<?php echo esc_js( __( 'Remove', '1111-learn' ) ); ?>';

		row.appendChild( label );
		row.appendChild( input );
		row.appendChild( remove );
		objectivesList.appendChild( row );

		input.focus();
		updateRemoveButtons();
	});

	objectivesList.addEventListener( 'click', function( e ) {
		if ( e.target.classList.contains( '1111-learn-remove-objective' ) ) {
			var row = e.target.closest( '.1111-learn-objective-row' );
			if ( row && getObjectiveCount() > 1 ) {
				row.remove();
				updateRemoveButtons();
			}
		}
	});

	updateRemoveButtons();
})();
</script>
