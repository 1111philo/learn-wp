jQuery(document).ready(function ($) {
    $('.enroll-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var courseId = $btn.data('course-id');

        $btn.prop('disabled', true).text('Enrolling...');

        $.post(learnFrontend.ajax_url, {
            action: '1111_enroll_learner',
            course_id: courseId,
            nonce: learnFrontend.nonce
        }).done(function (response) {
            if (response.success) {
                $btn.text('Enrolled').addClass('enrolled');
                alert(response.data.message);
            } else {
                alert(response.data.message || 'Enrollment failed');
                $btn.prop('disabled', false).text('Enroll Now');
            }
        }).fail(function () {
            alert('Server error');
            $btn.prop('disabled', false).text('Enroll Now');
        });
    });

    $('.complete-btn').on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var courseId = $btn.data('course-id');
        var isActivity = $btn.data('is-activity');
        var submission = isActivity ? $('#activity_submission_content').val() : '';

        if (isActivity && !submission) {
            alert('Please enter your submission before completing.');
            return;
        }

        $btn.prop('disabled', true).text('Saving...');

        $.post(learnFrontend.ajax_url, {
            action: '1111_complete_lesson',
            post_id: postId,
            course_id: courseId,
            submission: submission,
            nonce: learnFrontend.nonce
        }).done(function (response) {
            if (response.success) {
                $btn.text('Completed').addClass('completed');
                alert(response.data.message);
            } else {
                alert('Failed to save progress');
                $btn.prop('disabled', false).text('Mark as Complete');
            }
        }).fail(function () {
            alert('Server error');
            $btn.prop('disabled', false).text('Mark as Complete');
        });
    });
});
