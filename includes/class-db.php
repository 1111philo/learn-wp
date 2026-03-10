<?php
/**
 * Database Migrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_DB
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
    }

    /**
     * Create custom tables
     */
    public function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Submissions table
        $table_name = $wpdb->prefix . '1111_learn_submissions';
        $sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			blog_id bigint(20) NOT NULL,
			post_id bigint(20) NOT NULL,
			lesson_post_id bigint(20) NOT NULL,
			course_term_id bigint(20) NOT NULL,
			activity_type varchar(20) NOT NULL,
			submitted_at datetime NOT NULL,
			score decimal(3,2) DEFAULT NULL,
			recommendation varchar(20) DEFAULT NULL,
			assessment_json longtext DEFAULT NULL,
			assessed_at datetime DEFAULT NULL,
			attempt_number int(11) NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'pending',
			content_snapshot longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_course (user_id, course_term_id),
			KEY lesson_user (lesson_post_id, user_id),
			KEY blog_post (blog_id, post_id)
		) $charset_collate;";
        dbDelta($sql);

        // Enrollments table
        $table_name = $wpdb->prefix . '1111_learn_enrollments';
        $sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			blog_id bigint(20) NOT NULL,
			course_term_id bigint(20) NOT NULL,
			enrolled_at datetime NOT NULL,
			content_copied_at datetime DEFAULT NULL,
			lessons_completed int(11) NOT NULL DEFAULT 0,
			total_lessons int(11) NOT NULL DEFAULT 0,
			current_xp int(11) NOT NULL DEFAULT 0,
			total_xp int(11) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY user_course (user_id, course_term_id),
			KEY blog_id (blog_id),
			KEY course_status (course_term_id, status)
		) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Check if user is enrolled in a course
     */
    public function is_user_enrolled($user_id, $course_term_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_enrollments';
        $enrolled = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND course_term_id = %d AND status = 'active'",
            $user_id,
            $course_term_id
        ));
        return !empty($enrolled);
    }

    /**
     * Enroll a user in a course
     */
    public function enroll_user($user_id, $course_term_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_enrollments';

        if ($this->is_user_enrolled($user_id, $course_term_id)) {
            return true;
        }

        // Calculate total lessons in course
        $lessons = get_posts(array(
            'post_type' => 'learn',
            'tax_query' => array(
                array(
                    'taxonomy' => 'course',
                    'field' => 'term_id',
                    'terms' => $course_term_id,
                ),
            ),
            'posts_per_page' => -1,
            'fields' => 'ids',
        ));
        $total_lessons = count($lessons);

        // Calculate total possible XP
        $total_xp = 0;
        foreach ($lessons as $lesson_id) {
            $activity_data = get_post_meta($lesson_id, '_1111_activity_data', true);
            $total_xp += isset($activity_data['xp_value']) ? intval($activity_data['xp_value']) : 10;
        }

        return $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'blog_id' => get_current_blog_id(),
                'course_term_id' => $course_term_id,
                'enrolled_at' => current_time('mysql'),
                'total_lessons' => $total_lessons,
                'total_xp' => $total_xp,
                'status' => 'active'
            ),
            array('%d', '%d', '%d', '%s', '%d', '%d', '%s')
        );
    }

    /**
     * Get user's enrollment data
     */
    public function get_user_enrollment($user_id, $course_term_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_enrollments';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND course_term_id = %d",
            $user_id,
            $course_term_id
        ));
    }

    /**
     * Add a lesson submission
     */
    public function add_submission($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_submissions';

        $defaults = array(
            'submitted_at' => current_time('mysql'),
            'blog_id' => get_current_blog_id(),
        );

        $data = array_merge($defaults, $data);

        return $wpdb->insert($table_name, $data);
    }

    /**
     * Get latest assessment for a user and lesson
     */
    public function get_latest_assessment($user_id, $lesson_post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_submissions';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND lesson_post_id = %d ORDER BY submitted_at DESC LIMIT 1",
            $user_id,
            $lesson_post_id
        ));
    }
}
