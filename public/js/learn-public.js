/**
 * 1111 Learn — Public Frontend JavaScript
 *
 * Handles activity submissions, hints toggle, assessment results display,
 * and course catalog interactions.
 *
 * @package Learn
 */

/* global learnPublic */

( function () {
	'use strict';

	/**
	 * Activity submission handling via AJAX.
	 */
	var ActivitySubmission = {
		init: function () {
			var forms = document.querySelectorAll( '.learn-activity-form' );

			forms.forEach( function ( form ) {
				form.addEventListener( 'submit', ActivitySubmission.onSubmit );
			} );
		},

		onSubmit: function ( e ) {
			e.preventDefault();

			var form = e.target;
			var submitBtn = form.querySelector( 'button[type="submit"]' );
			var resultContainer = form.closest( '.learn-activity-section' )
				.querySelector( '.learn-assessment-results' );

			if ( ! submitBtn ) {
				return;
			}

			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting...';

			var formData = new FormData( form );
			formData.append( 'action', '1111_submit_activity' );
			formData.append( 'nonce', learnPublic.nonce );

			fetch( learnPublic.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit';

					if ( data.success && data.data ) {
						ActivitySubmission.showResults( resultContainer, data.data );
					} else {
						ActivitySubmission.showError(
							resultContainer,
							data.data && data.data.message
								? data.data.message
								: 'Submission failed. Please try again.'
						);
					}
				} )
				.catch( function () {
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit';
					ActivitySubmission.showError(
						resultContainer,
						'Network error. Please try again.'
					);
				} );
		},

		showResults: function ( container, result ) {
			if ( ! container ) {
				return;
			}

			var passed = result.passed;
			container.className = 'learn-assessment-results ' + ( passed ? 'is-pass' : 'is-fail' );

			container.innerHTML =
				'<h4>' + ( passed ? 'Passed' : 'Not Yet' ) + '</h4>' +
				( result.score
					? '<div class="learn-result-score">Score: ' +
					  ActivitySubmission.escapeHtml( String( result.score ) ) +
					  '</div>'
					: '' ) +
				( result.feedback
					? '<div class="learn-result-details">' +
					  ActivitySubmission.escapeHtml( result.feedback ) +
					  '</div>'
					: '' );

			container.style.display = 'block';
			container.setAttribute( 'tabindex', '-1' );
			container.focus();
		},

		showError: function ( container, message ) {
			if ( ! container ) {
				return;
			}

			container.className = 'learn-assessment-results is-fail';
			container.innerHTML =
				'<h4>Error</h4>' +
				'<div class="learn-result-details">' +
				ActivitySubmission.escapeHtml( message ) +
				'</div>';
			container.style.display = 'block';
			container.setAttribute( 'tabindex', '-1' );
			container.focus();
		},

		escapeHtml: function ( str ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( str ) );
			return div.innerHTML;
		},
	};

	/**
	 * Hints toggle — show/hide hint content.
	 */
	var Hints = {
		init: function () {
			var toggles = document.querySelectorAll( '.learn-hints-toggle' );

			toggles.forEach( function ( toggle ) {
				toggle.addEventListener( 'click', Hints.onToggle );

				// Keyboard: Enter and Space.
				toggle.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						Hints.onToggle.call( toggle, e );
					}
				} );
			} );
		},

		onToggle: function () {
			var content = this.nextElementSibling;

			if ( ! content || ! content.classList.contains( 'learn-hints-content' ) ) {
				return;
			}

			var isVisible = content.classList.contains( 'is-visible' );
			content.classList.toggle( 'is-visible' );

			this.setAttribute( 'aria-expanded', isVisible ? 'false' : 'true' );
			this.textContent = isVisible ? 'Show Hints' : 'Hide Hints';
		},
	};

	/**
	 * Course catalog — enroll via AJAX.
	 */
	var CourseCatalog = {
		init: function () {
			var buttons = document.querySelectorAll( '.learn-enroll-btn' );

			buttons.forEach( function ( btn ) {
				btn.addEventListener( 'click', CourseCatalog.onEnroll );
			} );
		},

		onEnroll: function ( e ) {
			e.preventDefault();

			var btn = e.currentTarget;
			var courseId = btn.getAttribute( 'data-course-id' );

			if ( ! courseId ) {
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Enrolling...';

			var formData = new FormData();
			formData.append( 'action', '1111_enroll_course' );
			formData.append( 'nonce', learnPublic.nonce );
			formData.append( 'course_id', courseId );

			fetch( learnPublic.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					if ( data.success && data.data && data.data.redirect_url ) {
						window.location.href = data.data.redirect_url;
					} else {
						btn.disabled = false;
						btn.textContent = 'Enroll';

						var message = data.data && data.data.message
							? data.data.message
							: 'Enrollment failed. Please try again.';

						// Show inline error.
						var card = btn.closest( '.learn-catalog-card' );
						var existing = card.querySelector( '.learn-enroll-error' );
						if ( existing ) {
							existing.remove();
						}

						var error = document.createElement( 'p' );
						error.className = 'learn-enroll-error';
						error.setAttribute( 'role', 'alert' );
						error.style.color = '#d63638';
						error.style.fontSize = '0.85em';
						error.style.marginTop = '0.5em';
						error.textContent = message;
						btn.parentNode.appendChild( error );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = 'Enroll';
				} );
		},
	};

	/**
	 * Keyboard accessibility.
	 */
	function initKeyboard() {
		// Escape clears visible error messages.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				var errors = document.querySelectorAll( '.learn-enroll-error' );
				errors.forEach( function ( el ) {
					el.remove();
				} );
			}
		} );
	}

	/**
	 * Initialize on DOM ready.
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
		ActivitySubmission.init();
		Hints.init();
		CourseCatalog.init();
		initKeyboard();
	}
} )();
