/**
 * Learn public JavaScript — learner-facing submission and registration.
 *
 * @package Learn
 */

/* global learnPublic */

( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		initSubmitButtons();
		initRegistrationForm();
		initEnrollButtons();
	} );

	// -------------------------------------------------------------------------
	// Activity Submission.
	// -------------------------------------------------------------------------

	function initSubmitButtons() {
		document.querySelectorAll( '.learn-submit-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var postId = btn.getAttribute( 'data-post-id' );
				submitActivity( postId, btn );
			} );
		} );
	}

	function submitActivity( postId, btn ) {
		btn.disabled = true;
		btn.textContent = learnPublic.i18n.submitting;

		var resultDiv = document.getElementById( 'learn-result-' + postId );

		var formData = new FormData();
		formData.append( 'action', '1111_submit_assessment' );
		formData.append( 'nonce', learnPublic.nonce );
		formData.append( 'lesson_post_id', postId );

		fetch( learnPublic.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( data ) {
				btn.disabled = false;
				btn.textContent = learnPublic.i18n.submit;

				if ( data.success ) {
					renderAssessmentResult( resultDiv, data.data );
				} else {
					resultDiv.hidden = false;
					resultDiv.innerHTML = '<p class="learn-result-error">' +
						escapeHtml( data.data && data.data.message ? data.data.message : learnPublic.i18n.error ) +
						'</p>';
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = learnPublic.i18n.submit;
				resultDiv.hidden = false;
				resultDiv.innerHTML = '<p class="learn-result-error">' + escapeHtml( learnPublic.i18n.error ) + '</p>';
			} );
	}

	function renderAssessmentResult( container, result ) {
		var html = '';

		// Score.
		var scorePercent = Math.round( result.score * 100 );
		html += '<div class="learn-result-score">' + scorePercent + '%</div>';

		// Recommendation.
		html += '<div class="learn-result-recommendation learn-result-recommendation--' + result.recommendation + '">';
		html += escapeHtml( result.recommendation.charAt( 0 ).toUpperCase() + result.recommendation.slice( 1 ) );
		html += '</div>';

		// Strengths.
		if ( result.strengths && result.strengths.length ) {
			html += '<div class="learn-result-section">';
			html += '<h4>Strengths</h4><ul>';
			result.strengths.forEach( function ( s ) {
				html += '<li>' + escapeHtml( s ) + '</li>';
			} );
			html += '</ul></div>';
		}

		// Improvements.
		if ( result.improvements && result.improvements.length ) {
			html += '<div class="learn-result-section">';
			html += '<h4>To Improve</h4><ul>';
			result.improvements.forEach( function ( s ) {
				html += '<li>' + escapeHtml( s ) + '</li>';
			} );
			html += '</ul></div>';
		}

		// Rubric results.
		if ( result.rubric_results && result.rubric_results.length ) {
			html += '<div class="learn-result-section">';
			html += '<h4>Rubric</h4><ul>';
			result.rubric_results.forEach( function ( r ) {
				var icon = r.met ? '&#10003;' : '&#10007;';
				html += '<li>' + icon + ' ' + escapeHtml( r.criterion );
				if ( r.note ) {
					html += ' — <em>' + escapeHtml( r.note ) + '</em>';
				}
				html += '</li>';
			} );
			html += '</ul></div>';
		}

		// Portfolio check.
		if ( result.portfolio_check ) {
			html += '<div class="learn-result-section">';
			html += '<h4>Portfolio</h4>';
			html += '<p>' + escapeHtml( result.portfolio_check ) + '</p>';
			html += '</div>';
		}

		container.hidden = false;
		container.innerHTML = html;
	}

	// -------------------------------------------------------------------------
	// Registration Form.
	// -------------------------------------------------------------------------

	function initRegistrationForm() {
		var form = document.getElementById( 'learn-registration' );
		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var errDiv = document.getElementById( 'learn-register-errors' );
			var successDiv = document.getElementById( 'learn-register-success' );
			errDiv.hidden = true;
			successDiv.hidden = true;

			var btn = form.querySelector( '.learn-reg-submit' );
			btn.disabled = true;
			btn.textContent = 'Creating account...';

			var formData = new FormData();
			formData.append( 'action', '1111_learner_register' );
			formData.append( 'nonce', form.querySelector( '[name="_learn_reg_nonce"]' ).value );
			formData.append( 'username', form.querySelector( '#learn-reg-username' ).value.trim() );
			formData.append( 'email', form.querySelector( '#learn-reg-email' ).value.trim() );
			formData.append( 'display_name', form.querySelector( '#learn-reg-display' ).value.trim() );
			formData.append( 'password', form.querySelector( '#learn-reg-password' ).value );

			fetch( form.getAttribute( 'action' ) || ( typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php' ), {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						successDiv.hidden = false;
						successDiv.textContent = data.data.message;
						form.hidden = true;
						if ( data.data.site_url ) {
							setTimeout( function () {
								window.location.href = data.data.site_url;
							}, 1500 );
						}
					} else {
						btn.disabled = false;
						btn.textContent = 'Create Account';
						errDiv.hidden = false;
						if ( data.data && data.data.errors ) {
							errDiv.innerHTML = data.data.errors.map( escapeHtml ).join( '<br>' );
						} else {
							errDiv.textContent = 'Registration failed. Please try again.';
						}
					}
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = 'Create Account';
					errDiv.hidden = false;
					errDiv.textContent = 'Network error. Please try again.';
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Enrollment buttons (learner panel).
	// -------------------------------------------------------------------------

	function initEnrollButtons() {
		document.querySelectorAll( '.learn-enroll-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var termId = btn.getAttribute( 'data-term-id' );
				btn.disabled = true;
				btn.textContent = 'Enrolling...';

				var nonce = document.querySelector( '[name="_learn_enroll_nonce"]' );
				var formData = new FormData();
				formData.append( 'action', '1111_enroll_course' );
				formData.append( 'nonce', nonce ? nonce.value : '' );
				formData.append( 'course_term_id', termId );

				fetch( ( typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php' ), {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				} )
					.then( function ( r ) { return r.json(); } )
					.then( function ( data ) {
						if ( data.success ) {
							btn.textContent = data.data.message;
							setTimeout( function () {
								window.location.reload();
							}, 1500 );
						} else {
							btn.disabled = false;
							btn.textContent = 'Start Course';
							alert( data.data && data.data.message ? data.data.message : 'Enrollment failed.' );
						}
					} )
					.catch( function () {
						btn.disabled = false;
						btn.textContent = 'Start Course';
						alert( 'Network error. Please try again.' );
					} );
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
