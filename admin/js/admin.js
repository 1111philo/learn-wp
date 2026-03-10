/**
 * 1111 Learn — Admin Dashboard JavaScript
 *
 * Handles course creation form, objectives repeater, generation progress
 * polling, and keyboard interactions.
 *
 * @package Learn
 */

/* global jQuery, learnAdmin */

( function ( $ ) {
	'use strict';

	/**
	 * Objectives repeater — add/remove objective inputs.
	 */
	var Objectives = {
		MIN: 1,
		MAX: 8,

		init: function () {
			this.$list = $( '.learn-objectives-repeater' );
			this.$addBtn = $( '.learn-add-objective' );

			if ( ! this.$list.length ) {
				return;
			}

			this.$addBtn.on( 'click', this.add.bind( this ) );
			this.$list.on( 'click', '.button-link-delete', this.remove.bind( this ) );
			this.updateButtons();
		},

		add: function ( e ) {
			e.preventDefault();

			var count = this.$list.children( 'li' ).length;

			if ( count >= this.MAX ) {
				return;
			}

			var index = count + 1;
			var $li = $(
				'<li>' +
					'<input type="text" name="objectives[]" ' +
						'id="objective-' + index + '" ' +
						'aria-label="' + 'Objective ' + index + '" ' +
						'placeholder="Objective ' + index + '" />' +
					'<button type="button" class="button-link-delete" ' +
						'aria-label="Remove objective ' + index + '">' +
						'Remove' +
					'</button>' +
				'</li>'
			);

			this.$list.append( $li );
			$li.find( 'input' ).trigger( 'focus' );
			this.updateButtons();
		},

		remove: function ( e ) {
			e.preventDefault();

			var count = this.$list.children( 'li' ).length;

			if ( count <= this.MIN ) {
				return;
			}

			$( e.currentTarget ).closest( 'li' ).remove();
			this.renumber();
			this.updateButtons();
		},

		renumber: function () {
			this.$list.children( 'li' ).each( function ( i ) {
				var num = i + 1;
				var $input = $( this ).find( 'input' );
				var $btn = $( this ).find( '.button-link-delete' );

				$input
					.attr( 'id', 'objective-' + num )
					.attr( 'aria-label', 'Objective ' + num )
					.attr( 'placeholder', 'Objective ' + num );
				$btn.attr( 'aria-label', 'Remove objective ' + num );
			} );
		},

		updateButtons: function () {
			var count = this.$list.children( 'li' ).length;

			this.$addBtn.prop( 'disabled', count >= this.MAX );
			this.$list
				.find( '.button-link-delete' )
				.toggle( count > this.MIN );
		},
	};

	/**
	 * Client-side form validation.
	 */
	var Validation = {
		clearErrors: function () {
			$( '.learn-field-error' ).remove();
			$( '.learn-dashboard [aria-invalid]' ).removeAttr( 'aria-invalid' );
		},

		showError: function ( $field, message ) {
			$field.attr( 'aria-invalid', 'true' );

			var errorId = $field.attr( 'id' ) + '-error';
			var $error = $(
				'<span class="learn-field-error" role="alert" id="' +
					errorId +
					'">' +
					message +
					'</span>'
			);

			$field.attr( 'aria-describedby', errorId );
			$field.after( $error );
		},

		validate: function () {
			this.clearErrors();

			var valid = true;
			var $firstInvalid = null;

			// Title.
			var $title = $( '#course-title' );
			if ( $title.length && ! $.trim( $title.val() ) ) {
				this.showError( $title, 'Course title is required.' );
				valid = false;
				$firstInvalid = $firstInvalid || $title;
			}

			// Description.
			var $desc = $( '#course-description' );
			if ( $desc.length && ! $.trim( $desc.val() ) ) {
				this.showError( $desc, 'Course description is required.' );
				valid = false;
				$firstInvalid = $firstInvalid || $desc;
			}

			// Objectives — at least one non-empty.
			var hasObjective = false;
			$( '.learn-objectives-repeater input' ).each( function () {
				if ( $.trim( $( this ).val() ) ) {
					hasObjective = true;
					return false; // Break.
				}
			} );

			if ( ! hasObjective ) {
				var $firstObj = $( '.learn-objectives-repeater input' ).first();
				this.showError( $firstObj, 'At least one objective is required.' );
				valid = false;
				$firstInvalid = $firstInvalid || $firstObj;
			}

			// Focus first invalid field.
			if ( $firstInvalid ) {
				$firstInvalid.trigger( 'focus' );
			}

			return valid;
		},
	};

	/**
	 * Course generation — form submission, progress polling, stepper UI.
	 */
	var Generation = {
		pollInterval: null,
		$form: null,
		$stepper: null,
		$submitBtn: null,

		init: function () {
			this.$form = $( '#learn-course-form' );
			this.$stepper = $( '.learn-progress-stepper' );
			this.$submitBtn = this.$form.find( 'button[type="submit"]' );

			if ( ! this.$form.length ) {
				return;
			}

			this.$form.on( 'submit', this.onSubmit.bind( this ) );
		},

		onSubmit: function ( e ) {
			e.preventDefault();

			if ( ! Validation.validate() ) {
				return;
			}

			this.$submitBtn.prop( 'disabled', true ).text( 'Generating...' );

			var data = {
				action: '1111_generate_course',
				nonce: learnAdmin.nonce,
				title: $( '#course-title' ).val(),
				description: $( '#course-description' ).val(),
				objectives: [],
			};

			$( '.learn-objectives-repeater input' ).each( function () {
				var val = $.trim( $( this ).val() );
				if ( val ) {
					data.objectives.push( val );
				}
			} );

			var self = this;

			wp.ajax.post( data.action, data ).done( function ( response ) {
				if ( response && response.generation_id ) {
					self.showStepper();
					self.startPolling( response.generation_id );
				}
			} ).fail( function ( error ) {
				self.onError( error && error.message ? error.message : 'Generation failed. Please try again.' );
			} );
		},

		showStepper: function () {
			this.$stepper.show();
			this.$stepper.attr( 'tabindex', '-1' ).trigger( 'focus' );
		},

		startPolling: function ( generationId ) {
			var self = this;

			this.pollInterval = setInterval( function () {
				wp.ajax.post( '1111_generation_status', {
					nonce: learnAdmin.nonce,
					generation_id: generationId,
				} ).done( function ( response ) {
					self.updateStepper( response );

					if ( 'complete' === response.status ) {
						self.onComplete( response );
					} else if ( 'error' === response.status ) {
						self.onError( response.message || 'Generation encountered an error.' );
					}
				} ).fail( function () {
					self.onError( 'Could not check generation status.' );
				} );
			}, 2000 );
		},

		updateStepper: function ( response ) {
			if ( ! response.steps ) {
				return;
			}

			this.$stepper.find( '.learn-stepper-step' ).each( function ( i ) {
				var step = response.steps[ i ];
				if ( ! step ) {
					return;
				}

				$( this )
					.removeClass( 'is-pending is-active is-complete is-error' )
					.addClass( 'is-' + step.status );
			} );
		},

		onComplete: function ( response ) {
			this.stopPolling();
			this.$submitBtn.prop( 'disabled', false ).text( 'Generate Course' );

			if ( response.review_url ) {
				window.location.href = response.review_url;
			}
		},

		onError: function ( message ) {
			this.stopPolling();
			this.$submitBtn.prop( 'disabled', false ).text( 'Generate Course' );

			// Show error notice.
			var $notice = $(
				'<div class="notice notice-error is-dismissible" role="alert">' +
					'<p>' + $( '<span>' ).text( message ).html() + '</p>' +
				'</div>'
			);

			$( '.learn-dashboard' ).prepend( $notice );
			$notice.trigger( 'focus' );
		},

		stopPolling: function () {
			if ( this.pollInterval ) {
				clearInterval( this.pollInterval );
				this.pollInterval = null;
			}
		},
	};

	/**
	 * Keyboard support.
	 */
	function initKeyboard() {
		// Enter submits inputs (not textareas).
		$( '.learn-dashboard' ).on( 'keydown', 'input[type="text"]', function ( e ) {
			if ( 13 === e.which ) {
				e.preventDefault();
				$( '#learn-course-form' ).trigger( 'submit' );
			}
		} );

		// Escape dismisses notices.
		$( document ).on( 'keydown', function ( e ) {
			if ( 27 === e.which ) {
				$( '.learn-dashboard .notice.is-dismissible' ).remove();
			}
		} );
	}

	/**
	 * Initialize on DOM ready.
	 */
	$( function () {
		Objectives.init();
		Generation.init();
		initKeyboard();
	} );
} )( jQuery );
