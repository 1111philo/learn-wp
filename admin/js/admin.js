(function($) {
	'use strict';

	var LearnAdmin = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Add objective repeater
			$('#add-objective').on('click', function() {
				var row = $('.objective-row:first').clone();
				row.find('input').val('');
				$('#objectives-container').append(row);
			});

			// Form submission
			$('#learn-generate-form').on('submit', function(e) {
				e.preventDefault();
				self.startGeneration($(this));
			});
		},

		startGeneration: function($form) {
			var self = this;
			var data = $form.serializeArray();
			data.push({ name: 'action', value: '1111_generate_course' });
			data.push({ name: 'nonce', value: learnAdmin.nonce });

			$form.find('input, textarea, button').prop('disabled', true);
			$('#learn-creation-card').fadeOut(function() {
				$('#learn-progress-stepper').fadeIn();
				self.setActiveStep('phase-0');
			});

			$.post(learnAdmin.ajaxUrl, data)
				.done(function(response) {
					if (response.success) {
						self.pollStatus(response.data.term_id);
					} else {
						self.showError(response.data.message);
					}
				})
				.fail(function() {
					self.showError(learnAdmin.labels.error);
				});
		},

		pollStatus: function(termId) {
			var self = this;
			var pollInterval = setInterval(function() {
				$.get(learnAdmin.ajaxUrl, {
					action: '1111_generation_status',
					term_id: termId
				}).done(function(response) {
					if (response.success) {
						self.updateProgress(response.data.progress);
						if (response.data.status === 'complete' || response.data.status === 'published') {
							clearInterval(pollInterval);
							self.showSuccess(termId);
						} else if (response.data.status === 'failed') {
							clearInterval(pollInterval);
							self.showError(response.data.progress.error || learnAdmin.labels.error);
						}
					}
				});
			}, 2000);
		},

		setActiveStep: function(stepId) {
			$('.stepper-item').removeClass('active');
			$('#step-' + stepId).addClass('active');
		},

		setCompleteStep: function(stepId) {
			$('#step-' + stepId).removeClass('active').addClass('complete');
		},

		updateProgress: function(progress) {
			if (!progress || !progress.phase) return;

			// Mock logic to update steps
			if (progress.phase === 'lesson') {
				this.setCompleteStep('phase-0');
				// Logic to update dynamic objective steps
			} else if (progress.phase === 'final') {
				this.setCompleteStep('phase-0');
				this.setActiveStep('phase-final');
			}
		},

		showError: function(message) {
			$('#stepper-status-message').addClass('error').text(message);
		},

		showSuccess: function(termId) {
			$('#step-phase-final').addClass('complete');
			var reviewUrl = 'admin.php?page=learn-course-review&term_id=' + termId;
			$('#stepper-status-message').addClass('success').html(
				'Course generated successfully! <a href="' + reviewUrl + '" class="button button-primary">Review Course</a>'
			);
		}
	};

	$(document).ready(function() {
		LearnAdmin.init();
	});

})(jQuery);
