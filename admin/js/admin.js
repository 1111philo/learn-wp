/**
 * Learn admin dashboard JavaScript.
 *
 * Handles course creation form, AJAX submission, progress polling,
 * publish flow, and retry.
 *
 * @package Learn
 */

/* global ajaxurl, learnAdmin */

( function () {
	'use strict';

	var pollTimer = null;
	var currentTermId = null;

	/**
	 * Initialize when DOM is ready.
	 */
	document.addEventListener( 'DOMContentLoaded', function () {
		initCourseForm();
		initObjectiveRepeater();
		initPublishFlow();
		initRetryButtons();
		initPollButtons();
	} );

	// -------------------------------------------------------------------------
	// Course Creation Form.
	// -------------------------------------------------------------------------

	function initCourseForm() {
		var form = document.getElementById( 'learn-course-form' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			clearErrors();

			if ( ! validateForm( form ) ) {
				return;
			}

			submitCourse( form );
		} );
	}

	function validateForm( form ) {
		var errors = {};
		var title = form.querySelector( '#learn-title' );
		var desc = form.querySelector( '#learn-description' );
		var objInputs = form.querySelectorAll( '.learn-objective-input' );
		var valid = true;

		if ( ! title.value.trim() ) {
			errors.title = learnAdmin.i18n.titleRequired;
			valid = false;
		} else if ( title.value.trim().length < 3 ) {
			errors.title = learnAdmin.i18n.titleMinLength;
			valid = false;
		}

		if ( ! desc.value.trim() ) {
			errors.description = learnAdmin.i18n.descRequired;
			valid = false;
		} else if ( desc.value.trim().length < 20 ) {
			errors.description = learnAdmin.i18n.descMinLength;
			valid = false;
		}

		var hasObjective = false;
		objInputs.forEach( function ( input, i ) {
			if ( input.value.trim() ) {
				hasObjective = true;
				if ( input.value.trim().length < 10 ) {
					errors[ 'objective_' + i ] = learnAdmin.i18n.objMinLength.replace( '%d', i + 1 );
					valid = false;
				}
			}
		} );

		if ( ! hasObjective ) {
			errors.objectives = learnAdmin.i18n.objRequired;
			valid = false;
		}

		if ( ! valid ) {
			showErrors( errors );
		}

		return valid;
	}

	function submitCourse( form ) {
		var btn = document.getElementById( 'learn-generate-btn' );
		btn.disabled = true;
		btn.textContent = learnAdmin.i18n.generating;

		var formData = new FormData();
		formData.append( 'action', '1111_generate_course' );
		formData.append( 'nonce', learnAdmin.nonce );
		formData.append( 'title', form.querySelector( '#learn-title' ).value.trim() );
		formData.append( 'description', form.querySelector( '#learn-description' ).value.trim() );

		var objInputs = form.querySelectorAll( '.learn-objective-input' );
		objInputs.forEach( function ( input ) {
			if ( input.value.trim() ) {
				formData.append( 'objectives[]', input.value.trim() );
			}
		} );

		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( data.success ) {
					currentTermId = data.data.term_id;
					showProgressPanel( currentTermId );
					startPolling( currentTermId );
				} else {
					btn.disabled = false;
					btn.textContent = learnAdmin.i18n.generateCourse;
					if ( data.data && data.data.errors ) {
						showErrors( data.data.errors );
					} else if ( data.data && data.data.message ) {
						showErrors( { general: data.data.message } );
					}
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = learnAdmin.i18n.generateCourse;
				showErrors( { general: learnAdmin.i18n.networkError } );
			} );
	}

	// -------------------------------------------------------------------------
	// Objective Repeater.
	// -------------------------------------------------------------------------

	function initObjectiveRepeater() {
		var addBtn = document.getElementById( 'learn-add-objective' );
		if ( ! addBtn ) {
			return;
		}

		addBtn.addEventListener( 'click', function () {
			var list = document.getElementById( 'learn-objectives-list' );
			var count = list.querySelectorAll( '.learn-objective-row' ).length;

			var row = document.createElement( 'div' );
			row.className = 'learn-objective-row';
			row.setAttribute( 'role', 'listitem' );

			var label = document.createElement( 'label' );
			label.setAttribute( 'for', 'learn-obj-' + count );
			label.className = 'screen-reader-text';
			label.textContent = learnAdmin.i18n.objectiveN.replace( '%d', count + 1 );

			var input = document.createElement( 'input' );
			input.type = 'text';
			input.id = 'learn-obj-' + count;
			input.name = 'objectives[]';
			input.className = 'learn-objective-input';
			input.setAttribute( 'aria-describedby', 'learn-obj-' + count + '-error' );

			var errorP = document.createElement( 'p' );
			errorP.className = 'learn-field-error';
			errorP.id = 'learn-obj-' + count + '-error';
			errorP.hidden = true;

			var removeBtn = document.createElement( 'button' );
			removeBtn.type = 'button';
			removeBtn.className = 'button learn-remove-objective';
			removeBtn.textContent = learnAdmin.i18n.remove;
			removeBtn.setAttribute( 'aria-label', learnAdmin.i18n.removeObjective.replace( '%d', count + 1 ) );
			removeBtn.addEventListener( 'click', function () {
				row.remove();
				renumberObjectives();
			} );

			row.appendChild( label );
			row.appendChild( input );
			row.appendChild( removeBtn );
			row.appendChild( errorP );
			list.appendChild( row );

			input.focus();
		} );
	}

	function renumberObjectives() {
		var rows = document.querySelectorAll( '#learn-objectives-list .learn-objective-row' );
		rows.forEach( function ( row, i ) {
			var label = row.querySelector( 'label' );
			var input = row.querySelector( 'input' );
			var errorP = row.querySelector( '.learn-field-error' );

			if ( label ) {
				label.setAttribute( 'for', 'learn-obj-' + i );
				label.textContent = learnAdmin.i18n.objectiveN.replace( '%d', i + 1 );
			}
			if ( input ) {
				input.id = 'learn-obj-' + i;
				input.setAttribute( 'aria-describedby', 'learn-obj-' + i + '-error' );
			}
			if ( errorP ) {
				errorP.id = 'learn-obj-' + i + '-error';
			}

			var removeBtn = row.querySelector( '.learn-remove-objective' );
			if ( removeBtn ) {
				removeBtn.setAttribute( 'aria-label', learnAdmin.i18n.removeObjective.replace( '%d', i + 1 ) );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Error Handling.
	// -------------------------------------------------------------------------

	function clearErrors() {
		var container = document.getElementById( 'learn-form-errors' );
		if ( container ) {
			container.hidden = true;
			container.innerHTML = '';
		}

		document.querySelectorAll( '.learn-field-error' ).forEach( function ( el ) {
			el.hidden = true;
			el.textContent = '';
		} );

		document.querySelectorAll( '.learn-field-invalid' ).forEach( function ( el ) {
			el.classList.remove( 'learn-field-invalid' );
		} );
	}

	function showErrors( errors ) {
		var container = document.getElementById( 'learn-form-errors' );
		var messages = [];
		var firstErrorField = null;

		Object.keys( errors ).forEach( function ( key ) {
			var msg = errors[ key ];
			messages.push( msg );

			// Show inline error.
			if ( key === 'title' ) {
				setFieldError( 'learn-title', 'learn-title-error', msg );
				if ( ! firstErrorField ) {
					firstErrorField = document.getElementById( 'learn-title' );
				}
			} else if ( key === 'description' ) {
				setFieldError( 'learn-description', 'learn-desc-error', msg );
				if ( ! firstErrorField ) {
					firstErrorField = document.getElementById( 'learn-description' );
				}
			} else if ( key.indexOf( 'objective_' ) === 0 ) {
				var idx = key.replace( 'objective_', '' );
				setFieldError( 'learn-obj-' + idx, 'learn-obj-' + idx + '-error', msg );
				if ( ! firstErrorField ) {
					firstErrorField = document.getElementById( 'learn-obj-' + idx );
				}
			}
		} );

		if ( container && messages.length ) {
			container.innerHTML = '<p>' + messages.map( escapeHtml ).join( '</p><p>' ) + '</p>';
			container.hidden = false;
		}

		if ( firstErrorField ) {
			firstErrorField.focus();
		}
	}

	function setFieldError( inputId, errorId, message ) {
		var input = document.getElementById( inputId );
		var errorEl = document.getElementById( errorId );
		if ( input ) {
			input.classList.add( 'learn-field-invalid' );
		}
		if ( errorEl ) {
			errorEl.textContent = message;
			errorEl.hidden = false;
		}
	}

	// -------------------------------------------------------------------------
	// Progress Polling.
	// -------------------------------------------------------------------------

	function showProgressPanel( termId ) {
		var formCard = document.getElementById( 'learn-create-course' );
		var progressPanel = document.getElementById( 'learn-progress-panel' );
		if ( formCard ) {
			formCard.hidden = true;
		}
		if ( progressPanel ) {
			progressPanel.hidden = false;
		}
	}

	function startPolling( termId ) {
		if ( pollTimer ) {
			clearInterval( pollTimer );
		}

		pollStatus( termId );
		pollTimer = setInterval( function () {
			pollStatus( termId );
		}, 2000 );
	}

	function pollStatus( termId ) {
		var url = ajaxurl + '?action=1111_generation_status&nonce=' + encodeURIComponent( learnAdmin.nonce ) + '&term_id=' + termId;

		fetch( url, {
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( ! data.success ) {
					return;
				}

				renderProgress( data.data, termId );

				if ( data.data.status === 'complete' || data.data.status === 'failed' ) {
					clearInterval( pollTimer );
					pollTimer = null;
				}
			} )
			.catch( function () {
				// Silently retry on next poll.
			} );
	}

	function renderProgress( progress, termId ) {
		var stepper = document.getElementById( 'learn-progress-stepper' );
		var heading = document.getElementById( 'learn-progress-heading' );
		var actions = document.getElementById( 'learn-progress-actions' );
		var reviewLink = document.getElementById( 'learn-review-link' );
		var retryBtn = document.getElementById( 'learn-retry-btn' );

		if ( ! stepper ) {
			return;
		}

		var html = '';

		// Build step items.
		html += buildStepItem(
			learnAdmin.i18n.describingCourse,
			getStepStatus( progress, 'course_description' )
		);

		if ( progress.lesson_titles && progress.lesson_titles.length ) {
			progress.lesson_titles.forEach( function ( title, i ) {
				var objSteps = progress.steps_completed ? progress.steps_completed[ i ] : null;
				var objStatus = getObjectiveStatus( progress, i, objSteps );
				html += buildStepItem( title, objStatus );
			} );
		} else if ( progress.total_objectives ) {
			for ( var i = 0; i < progress.total_objectives; i++ ) {
				var objSteps = progress.steps_completed ? progress.steps_completed[ i ] : null;
				var objStatus = getObjectiveStatus( progress, i, objSteps );
				html += buildStepItem(
					learnAdmin.i18n.objectiveN.replace( '%d', i + 1 ),
					objStatus
				);
			}
		}

		html += buildStepItem(
			learnAdmin.i18n.creatingAssessment,
			getStepStatus( progress, 'assessment' )
		);

		stepper.innerHTML = html;

		// Update heading and actions.
		if ( progress.status === 'complete' ) {
			heading.textContent = learnAdmin.i18n.generationComplete;
			actions.hidden = false;
			reviewLink.href = learnAdmin.dashboardUrl + '&review=' + termId;
			reviewLink.hidden = false;
			retryBtn.hidden = true;
		} else if ( progress.status === 'failed' ) {
			heading.textContent = learnAdmin.i18n.generationFailed;
			actions.hidden = false;
			reviewLink.hidden = true;
			retryBtn.hidden = false;
			retryBtn.setAttribute( 'data-term-id', termId );

			if ( progress.error ) {
				var errorStep = progress.error.step || '';
				var errorMsg = progress.error.message || '';
				stepper.innerHTML += '<div class="learn-step-error" role="alert">' +
					'<strong>' + escapeHtml( errorStep ) + ':</strong> ' +
					escapeHtml( errorMsg ) +
					'</div>';
			}
		} else {
			heading.textContent = learnAdmin.i18n.generatingCourse;
		}
	}

	function getStepStatus( progress, phase ) {
		if ( progress.status === 'complete' ) {
			return 'complete';
		}
		if ( phase === 'course_description' ) {
			if ( progress.phase === 'course_description' ) {
				return 'active';
			}
			return progress.lesson_titles && progress.lesson_titles.length ? 'complete' : 'pending';
		}
		if ( phase === 'assessment' ) {
			if ( progress.phase === 'complete' ) {
				return 'complete';
			}
			if ( progress.current_step === 'complete' ) {
				return 'complete';
			}
			// Check if all objectives done but assessment pending.
			if ( progress.steps_completed && progress.total_objectives ) {
				var allDone = true;
				for ( var i = 0; i < progress.total_objectives; i++ ) {
					if ( ! progress.steps_completed[ i ] || ! hasReviewedAll( progress.steps_completed[ i ] ) ) {
						allDone = false;
						break;
					}
				}
				if ( allDone ) {
					return 'active';
				}
			}
			return 'pending';
		}
		return 'pending';
	}

	function getObjectiveStatus( progress, index, objSteps ) {
		if ( progress.status === 'complete' ) {
			return 'complete';
		}
		if ( ! objSteps ) {
			if ( progress.current_objective === index && progress.phase !== 'course_description' ) {
				return 'active';
			}
			return progress.current_objective > index ? 'complete' : 'pending';
		}

		if ( hasReviewedAll( objSteps ) ) {
			return 'complete';
		}

		if ( progress.current_objective === index ) {
			return 'active';
		}

		return objSteps.steps && objSteps.steps.length ? 'complete' : 'pending';
	}

	function hasReviewedAll( objSteps ) {
		if ( ! objSteps || ! objSteps.steps ) {
			return false;
		}
		return objSteps.steps.some( function ( s ) {
			return s.indexOf( 'reviewed' ) === 0;
		} );
	}

	function buildStepItem( label, status ) {
		var icon = '';
		var statusText = '';

		if ( status === 'complete' ) {
			icon = '<span class="learn-step-icon learn-step-icon--complete" aria-hidden="true">&#10003;</span>';
			statusText = '<span class="screen-reader-text"> — ' + learnAdmin.i18n.stepComplete + '</span>';
		} else if ( status === 'active' ) {
			icon = '<span class="learn-step-icon learn-step-icon--active" aria-hidden="true"></span>';
			statusText = '<span class="screen-reader-text"> — ' + learnAdmin.i18n.stepInProgress + '</span>';
		} else {
			icon = '<span class="learn-step-icon learn-step-icon--pending" aria-hidden="true">&#9675;</span>';
			statusText = '<span class="screen-reader-text"> — ' + learnAdmin.i18n.stepPending + '</span>';
		}

		return '<div class="learn-step learn-step--' + status + '">' +
			icon +
			'<span class="learn-step-label">' + escapeHtml( label ) + '</span>' +
			statusText +
			'</div>';
	}

	// -------------------------------------------------------------------------
	// Publish Flow.
	// -------------------------------------------------------------------------

	function initPublishFlow() {
		var publishBtn = document.getElementById( 'learn-publish-btn' );
		var dialog = document.getElementById( 'learn-publish-dialog' );
		var confirmBtn = document.getElementById( 'learn-dialog-confirm' );
		var cancelBtn = document.getElementById( 'learn-dialog-cancel' );

		if ( ! publishBtn || ! dialog ) {
			return;
		}

		publishBtn.addEventListener( 'click', function () {
			dialog.hidden = false;
			confirmBtn.focus();
		} );

		cancelBtn.addEventListener( 'click', function () {
			dialog.hidden = true;
			publishBtn.focus();
		} );

		confirmBtn.addEventListener( 'click', function () {
			dialog.hidden = true;
			publishCourse( publishBtn.getAttribute( 'data-term-id' ) );
		} );

		// Close on Escape.
		dialog.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				dialog.hidden = true;
				publishBtn.focus();
			}
		} );
	}

	function publishCourse( termId ) {
		var publishBtn = document.getElementById( 'learn-publish-btn' );
		publishBtn.disabled = true;
		publishBtn.textContent = learnAdmin.i18n.publishing;

		var nonce = learnAdmin.nonce;
		var nonceField = document.querySelector( '#learn-publish-btn' ).closest( '.learn-card' ).querySelector( '[name="_learn_nonce"]' );
		if ( nonceField ) {
			nonce = nonceField.value;
		}

		var formData = new FormData();
		formData.append( 'action', '1111_publish_course' );
		formData.append( 'nonce', nonce );
		formData.append( 'term_id', termId );

		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( data.success ) {
					// Reload page to reflect published state.
					window.location.reload();
				} else {
					publishBtn.disabled = false;
					publishBtn.textContent = learnAdmin.i18n.publishCourse;
					alert( data.data && data.data.message ? data.data.message : learnAdmin.i18n.publishError );
				}
			} )
			.catch( function () {
				publishBtn.disabled = false;
				publishBtn.textContent = learnAdmin.i18n.publishCourse;
				alert( learnAdmin.i18n.networkError );
			} );
	}

	// -------------------------------------------------------------------------
	// Retry.
	// -------------------------------------------------------------------------

	function initRetryButtons() {
		// Dashboard retry buttons for existing courses.
		document.querySelectorAll( '.learn-retry-course' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				retryCourse( btn.getAttribute( 'data-term-id' ), btn );
			} );
		} );

		// Progress panel retry button.
		var retryBtn = document.getElementById( 'learn-retry-btn' );
		if ( retryBtn ) {
			retryBtn.addEventListener( 'click', function () {
				var termId = retryBtn.getAttribute( 'data-term-id' );
				if ( termId ) {
					retryCourse( termId, retryBtn );
				}
			} );
		}
	}

	function retryCourse( termId, btn ) {
		btn.disabled = true;

		var formData = new FormData();
		formData.append( 'action', '1111_retry_generation' );
		formData.append( 'nonce', learnAdmin.nonce );
		formData.append( 'term_id', termId );

		fetch( ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				if ( data.success ) {
					currentTermId = parseInt( termId, 10 );
					showProgressPanel( currentTermId );
					startPolling( currentTermId );
				} else {
					btn.disabled = false;
					alert( data.data && data.data.message ? data.data.message : learnAdmin.i18n.retryError );
				}
			} )
			.catch( function () {
				btn.disabled = false;
				alert( learnAdmin.i18n.networkError );
			} );
	}

	// -------------------------------------------------------------------------
	// View Progress buttons for in-progress courses.
	// -------------------------------------------------------------------------

	function initPollButtons() {
		document.querySelectorAll( '.learn-poll-course' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var termId = parseInt( btn.getAttribute( 'data-term-id' ), 10 );
				currentTermId = termId;
				showProgressPanel( termId );
				startPolling( termId );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Utilities.
	// -------------------------------------------------------------------------

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}
} )();
