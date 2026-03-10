(function ($) {
	'use strict';

	$(document).on('submit', '#learn-course-form', function (event) {
		event.preventDefault();
		var $form = $(this);
		var payload = {
			action: '1111_learn_generate_course',
			nonce: LearnAdmin.nonce,
			course_title: $form.find('[name="course_title"]').val(),
			course_description: $form.find('[name="course_description"]').val(),
			course_objectives: $form.find('[name="course_objectives"]').val()
		};

		$('#learn-generation-result').text('Generating course...');

		$.post(LearnAdmin.ajaxUrl, payload)
			.done(function (response) {
				if (!response.success) {
					$('#learn-generation-result').text(response.data.message || 'Generation failed.');
					return;
				}
				var data = response.data;
				$('#learn-generation-result').html(
					'<p><strong>Course generated.</strong></p>' +
					'<p>Course term ID: ' + data.course_term_id + '</p>' +
					'<p>Posts created: ' + data.post_ids.length + '</p>' +
					'<p>Total XP: ' + data.total_xp + '</p>'
				);
			})
			.fail(function () {
				$('#learn-generation-result').text('Request failed.');
			});
	});
})(jQuery);
