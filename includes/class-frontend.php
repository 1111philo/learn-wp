<?php
/**
 * Frontend Learner Interactions
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Frontend
{
    /**
     * Instance of this class
     */
    private static $instance;

    /**
     * Get instance of this class
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        add_shortcode('1111_registration', array($this, 'shortcode_registration'));
        add_shortcode('1111_course_enroll', array($this, 'shortcode_enroll'));
        add_shortcode('1111_course_catalog', array($this, 'shortcode_catalog'));
        add_shortcode('1111_learner_dashboard', array($this, 'shortcode_dashboard'));

        add_action('wp_ajax_1111_complete_lesson', array($this, 'ajax_complete_lesson'));

        add_filter('the_content', array($this, 'append_completion_button'));
        add_filter('the_content', array($this, 'append_course_navigation'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Append 'Mark as Complete' button to content
     */
    public function append_completion_button($content)
    {
        if (!is_singular('learn') || !is_user_logged_in()) {
            return $content;
        }

        $post_id = get_the_ID();
        $user_id = get_current_user_id();
        $courses = wp_get_post_terms($post_id, 'course');

        if (empty($courses)) {
            return $content;
        }

        $course_id = $courses[0]->term_id;
        if (!Learn_DB::get_instance()->is_user_enrolled($user_id, $course_id)) {
            return $content;
        }

        $activity_data = get_post_meta($post_id, '_1111_activity_data', true);
        $is_activity = !empty($activity_data['activity_type']);

        if (Learn_XP_Engine::get_instance()->is_lesson_completed($user_id, $post_id)) {
            $submission = Learn_DB::get_instance()->get_latest_assessment($user_id, $post_id);
            $feedback = '';
            if ($submission && $submission->status === 'completed') {
                $assessment = json_decode($submission->assessment_json, true);
                $feedback = '<div class="learn-assessment-feedback">';
                $feedback .= '<h4>' . __('Assessment Feedback', '1111-learn') . '</h4>';
                $feedback .= '<p><strong>' . __('Score:', '1111-learn') . '</strong> ' . esc_html($submission->score) . '/10</p>';
                $feedback .= '<p>' . nl2br(esc_html($assessment['logic'])) . '</p>';
                $feedback .= '</div>';
            }
            $btn = '<div class="learn-completion-wrap">' . $feedback . '<button disabled class="learn-button completed">' . __('Lesson Completed', '1111-learn') . '</button></div>';
        } else {
            $btn = '<div class="learn-completion-wrap">';

            if ($is_activity) {
                $btn .= '<div class="learn-activity-submission">';
                $btn .= '<h4>' . __('Submit Your Activity', '1111-learn') . '</h4>';
                $btn .= '<p class="description">' . esc_html($activity_data['instructions']) . '</p>';
                $btn .= '<textarea id="activity_submission_content" rows="5" placeholder="' . __('Type your submission here...', '1111-learn') . '"></textarea>';
                $btn .= '</div>';
            }

            $btn .= sprintf(
                '<button class="learn-button complete-btn" data-post-id="%d" data-course-id="%d" data-is-activity="%d">%s</button></div>',
                $post_id,
                $course_id,
                $is_activity ? 1 : 0,
                $is_activity ? __('Submit & Complete', '1111-learn') : __('Mark as Complete', '1111-learn')
            );
        }

        return $content . $btn;
    }

    /**
     * Append course navigation (Prev/Next) to content
     */
    public function append_course_navigation($content)
    {
        if (!is_singular('learn')) {
            return $content;
        }

        $post_id = get_the_ID();
        $courses = wp_get_post_terms($post_id, 'course');

        if (empty($courses)) {
            return $content;
        }

        $course_id = $courses[0]->term_id;

        // Find adjacent lessons in the same course
        $lessons = get_posts(array(
            'post_type' => 'learn',
            'tax_query' => array(
                array(
                    'taxonomy' => 'course',
                    'field' => 'term_id',
                    'terms' => $course_id,
                ),
            ),
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'fields' => 'ids',
        ));

        $current_index = array_search($post_id, $lessons);
        $prev_id = ($current_index > 0) ? $lessons[$current_index - 1] : null;
        $next_id = ($current_index < count($lessons) - 1) ? $lessons[$current_index + 1] : null;

        $nav = '<div class="learn-course-nav">';
        if ($prev_id) {
            $nav .= sprintf('<a href="%s" class="learn-button prev-btn">&larr; %s</a>', get_permalink($prev_id), __('Previous', '1111-learn'));
        }
        if ($next_id) {
            $nav .= sprintf('<a href="%s" class="learn-button next-btn">%s &rarr;</a>', get_permalink($next_id), __('Next', '1111-learn'));
        }
        $nav .= '</div>';

        return $content . $nav;
    }

    /**
     * AJAX handler for completing a lesson
     */
    public function ajax_complete_lesson()
    {
        check_ajax_referer('1111_learn_frontend', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        $submission_content = isset($_POST['submission']) ? sanitize_textarea_field($_POST['submission']) : '';
        $user_id = get_current_user_id();

        if (!$post_id || !$course_id || !$user_id) {
            wp_send_json_error();
        }

        // Save submission if present
        if ($submission_content) {
            Learn_DB::get_instance()->add_submission(array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'lesson_post_id' => $post_id,
                'course_term_id' => $course_id,
                'content_snapshot' => $submission_content,
            ));
        }

        do_action('1111_learn_lesson_completed', $user_id, $course_id, $post_id);

        wp_send_json_success(array('message' => $submission_content ? __('Work submitted and lesson completed!', '1111-learn') : __('Great job! Lesson completed.', '1111-learn')));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('1111-learn-frontend', LEARN_URL . 'admin/css/frontend.css', array(), LEARN_VERSION);
        wp_enqueue_script('1111-learn-frontend', LEARN_URL . 'admin/js/frontend.js', array('jquery'), LEARN_VERSION, true);
        wp_localize_script('1111-learn-frontend', 'learnFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('1111_learn_frontend')
        ));
    }

    /**
     * Registration shortcode
     */
    public function shortcode_registration($atts)
    {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', '1111-learn') . '</p>';
        }

        ob_start();
        ?>
        <form id="1111-registration-form" class="learn-form">
            <p>
                <label for="learner_user">
                    <?php _e('Username', '1111-learn'); ?>
                </label>
                <input type="text" name="learner_user" id="learner_user" required>
            </p>
            <p>
                <label for="learner_email">
                    <?php _e('Email', '1111-learn'); ?>
                </label>
                <input type="email" name="learner_email" id="learner_email" required>
            </p>
            <p>
                <input type="submit" value="<?php _e('Register as Learner', '1111-learn'); ?>">
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Enrollment shortcode (Button)
     */
    public function shortcode_enroll($atts)
    {
        $atts = shortcode_atts(array(
            'course_id' => 0
        ), $atts);

        if (!$atts['course_id']) {
            return '';
        }

        if (!is_user_logged_in()) {
            return '<p>' . sprintf(__('Please <a href="%s">login</a> to enroll.', '1111-learn'), wp_login_url(get_permalink())) . '</p>';
        }

        $user_id = get_current_user_id();
        $is_enrolled = Learn_DB::get_instance()->is_user_enrolled($user_id, $atts['course_id']);

        if ($is_enrolled) {
            return '<button disabled class="learn-button enrolled">' . __('Enrolled', '1111-learn') . '</button>';
        }

        return sprintf(
            '<button class="learn-button enroll-btn" data-course-id="%d">%s</button>',
            $atts['course_id'],
            __('Enroll Now', '1111-learn')
        );
    }

    /**
     * AJAX handler for enrollment
     */
    public function ajax_enroll_learner()
    {
        check_ajax_referer('1111_learn_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in.', '1111-learn')));
        }

        $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        if (!$course_id) {
            wp_send_json_error(array('message' => __('Invalid course.', '1111-learn')));
        }

        $success = Learn_DB::get_instance()->enroll_user(get_current_user_id(), $course_id);

        if ($success && is_multisite()) {
            $user = wp_get_current_user();
            $blog_id = Learn_Multisite::get_instance()->provision_learner_site($user->ID, $user->user_login);
            if (!is_wp_error($blog_id)) {
                Learn_Multisite::get_instance()->distribute_course(get_current_blog_id(), $blog_id, $course_id);
                // Update enrollment with learner site ID
                global $wpdb;
                $wpdb->update($wpdb->prefix . '1111_learn_enrollments', array('blog_id' => $blog_id), array('user_id' => $user->ID, 'course_term_id' => $course_id));
            }
        }

        if ($success) {
            wp_send_json_success(array('message' => __('Successfully enrolled! Your course copy is being prepared.', '1111-learn')));
        } else {
            wp_send_json_error(array('message' => __('Enrollment failed.', '1111-learn')));
        }
    }

    /**
     * Course Catalog Shortcode
     */
    public function shortcode_catalog($atts)
    {
        $courses = get_terms(array(
            'taxonomy' => 'course',
            'hide_empty' => false,
        ));

        if (empty($courses)) {
            return '<p>' . __('No courses available yet.', '1111-learn') . '</p>';
        }

        ob_start();
        ?>
        <div class="learn-catalog">
            <?php foreach ($courses as $course): ?>
                <div class="learn-course-card">
                    <h3><?php echo esc_html($course->name); ?></h3>
                    <p><?php echo esc_html($course->description); ?></p>
                    <div class="learn-card-footer">
                        <?php echo $this->shortcode_enroll(array('course_id' => $course->term_id)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Learner Dashboard Shortcode
     */
    public function shortcode_dashboard($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>' . sprintf(__('Please <a href="%s">login</a> to view your dashboard.', '1111-learn'), wp_login_url(get_permalink())) . '</p>';
        }

        $user_id = get_current_user_id();
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_enrollments';
        $enrollments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND status = 'active'",
            $user_id
        ));

        if (empty($enrollments)) {
            return '<p>' . __('You are not enrolled in any courses.', '1111-learn') . ' <a href="' . get_post_type_archive_link('learn') . '">' . __('Browse Catalog', '1111-learn') . '</a></p>';
        }

        ob_start();
        ?>
        <div class="learn-dashboard">
            <h2><?php _e('My Courses', '1111-learn'); ?></h2>
            <div class="learn-enrollment-list">
                <?php foreach ($enrollments as $enrollment): ?>
                    <?php
                    $course = get_term($enrollment->course_term_id, 'course');
                    $progress = ($enrollment->total_lessons > 0) ? round(($enrollment->lessons_completed / $enrollment->total_lessons) * 100) : 0;
                    ?>
                    <div class="learn-enrollment-item">
                        <div class="learn-enrollment-info">
                            <h3><?php echo esc_html($course->name); ?></h3>
                            <p><?php printf(__('XP: %d', '1111-learn'), $enrollment->current_xp); ?></p>
                        </div>
                        <div class="learn-progress-wrap">
                            <div class="learn-progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                            <span class="learn-progress-text"><?php echo $progress; ?>%</span>
                        </div>
                        <div class="learn-enrollment-actions">
                            <?php if ($enrollment->blog_id && $enrollment->blog_id != get_current_blog_id()): ?>
                                <a href="<?php echo esc_url(get_site_url($enrollment->blog_id)); ?>"
                                    class="learn-button"><?php _e('Go to Course', '1111-learn'); ?></a>
                            <?php else: ?>
                                <a href="<?php echo esc_url(get_term_link($course)); ?>"
                                    class="learn-button"><?php _e('View Lessons', '1111-learn'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
