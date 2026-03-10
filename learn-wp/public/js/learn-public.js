(function ($) {
	'use strict';

	$(document).on('click', '.learn-enroll-course', function () {
		var courseId = $(this).data('course-id');
		$('#learn-enroll-message').text('Enrolling...');

		$.post(LearnPublic.ajaxUrl, {
			action: '1111_learn_enroll_course',
			nonce: LearnPublic.nonce,
			course_term_id: courseId
		}).done(function (response) {
			if (!response.success) {
				$('#learn-enroll-message').text(response.data.message || 'Enrollment failed.');
				return;
			}
			$('#learn-enroll-message').text('Enrolled. Course content copied to your site.');
		}).fail(function () {
			$('#learn-enroll-message').text('Enrollment request failed.');
		});
	});
})(jQuery);
