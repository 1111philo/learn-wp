<?php
/**
 * XP and Completion Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_XP_Engine
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
        add_action('1111_learn_lesson_completed', array($this, 'award_xp'), 10, 3);
    }

    /**
     * Award XP for lesson completion
     */
    public function award_xp($user_id, $course_term_id, $post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_enrollments';

        $activity_data = get_post_meta($post_id, '_1111_activity_data', true);
        $xp_to_award = isset($activity_data['xp_value']) ? intval($activity_data['xp_value']) : 10;

        // Check if already completed to prevent double XP
        if ($this->is_lesson_completed($user_id, $post_id)) {
            return;
        }

        // Update enrollment
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_name 
             SET current_xp = current_xp + %d, 
                 lessons_completed = lessons_completed + 1 
             WHERE user_id = %d AND course_term_id = %d",
            $xp_to_award,
            $user_id,
            $course_term_id
        ));

        // Mark as completed in meta for quick check
        add_user_meta($user_id, "_1111_completed_lesson_{$post_id}", current_time('mysql'), true);
    }

    /**
     * Check if a lesson is completed
     */
    public function is_lesson_completed($user_id, $post_id)
    {
        return !empty(get_user_meta($user_id, "_1111_completed_lesson_{$post_id}", true));
    }
}
