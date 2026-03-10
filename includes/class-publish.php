<?php
/**
 * Course Publish Logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Publish
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
        add_action('wp_ajax_1111_publish_course', array($this, 'ajax_publish_course'));
        add_filter('wp_insert_post_data', array($this, 'guard_published_content'), 10, 2);
    }

    /**
     * AJAX handler to publish a course
     */
    public function ajax_publish_course()
    {
        check_ajax_referer('1111_learn_generate', 'nonce');

        if (!is_super_admin() || !is_main_site()) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', '1111-learn')));
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        if (!$term_id) {
            wp_send_json_error(array('message' => __('Invalid course.', '1111-learn')));
        }

        // Get all lessons for this course
        $lessons = get_posts(array(
            'post_type' => 'learn',
            'tax_query' => array(
                array(
                    'taxonomy' => 'course',
                    'field' => 'term_id',
                    'terms' => $term_id,
                ),
            ),
            'post_status' => 'draft',
            'numberposts' => -1,
        ));

        foreach ($lessons as $lesson) {
            wp_update_post(array(
                'ID' => $lesson->ID,
                'post_status' => 'publish',
            ));
        }

        update_term_meta($term_id, '_1111_generation_status', 'published');

        wp_send_json_success(array('message' => __('Course published and locked.', '1111-learn')));
    }

    /**
     * Prevent human users from editing published learn posts
     */
    public function guard_published_content($data, $postarr)
    {
        if ('learn' !== $data['post_type']) {
            return $data;
        }

        $post_id = isset($postarr['ID']) ? $postarr['ID'] : 0;
        if (!$post_id) {
            return $data;
        }

        $status = get_post_status($post_id);
        if ('publish' === $status) {
            // Only allow the agent user to edit (simplified check)
            $agent_id = get_option('1111_learn_agent_user_id');
            if (get_current_user_id() != $agent_id) {
                // Revert changes if not agent
                $original = get_post($post_id);
                $data['post_content'] = $original->post_content;
                $data['post_title'] = $original->post_title;
            }
        }

        return $data;
    }
}
