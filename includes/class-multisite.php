<?php
/**
 * Multisite Support and Content Distribution
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Multisite
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
        add_action('wp_insert_site', array($this, 'on_site_provisioned'), 10, 1);
    }

    /**
     * Provision a site for a learner
     */
    public function provision_learner_site($user_id, $username)
    {
        if (!is_multisite()) {
            return get_current_blog_id();
        }

        $domain = get_network()->domain;
        $path = '/' . $username . '/';

        $blog_id = wp_insert_site(array(
            'domain' => $domain,
            'path' => $path,
            'title' => sprintf(__("%s's Learning Journey", '1111-learn'), $username),
            'user_id' => $user_id,
        ));

        if (is_wp_error($blog_id)) {
            return $blog_id;
        }

        return $blog_id;
    }

    /**
     * Copy course content to a subsite
     */
    public function distribute_course($source_blog_id, $target_blog_id, $course_term_id)
    {
        switch_to_blog($source_blog_id);

        $course = get_term($course_term_id, 'course');
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
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        restore_current_blog();

        switch_to_blog($target_blog_id);

        // Ensure taxonomy term exists
        $new_term = wp_insert_term($course->name, 'course', array('description' => $course->description));
        $new_term_id = is_wp_error($new_term) ? $new_term->get_error_data('term_exists') : $new_term['term_id'];

        foreach ($lessons as $lesson) {
            $new_post_data = array(
                'post_title' => $lesson->post_title,
                'post_content' => $lesson->post_content,
                'post_status' => 'publish',
                'post_type' => 'learn',
                'menu_order' => $lesson->menu_order,
            );
            $new_post_id = wp_insert_post($new_post_data);

            if (!is_wp_error($new_post_id)) {
                wp_set_object_terms($new_post_id, $new_term_id, 'course');

                // Copy meta
                $meta = get_post_meta($lesson->ID, '_1111_activity_data', true);
                if ($meta) {
                    update_post_meta($new_post_id, '_1111_activity_data', $meta);
                }
            }
        }

        restore_current_blog();

        return true;
    }

    /**
     * Hook after site provisioned if needed
     */
    public function on_site_provisioned($site)
    {
        // Add any default settings or plugin activation for the new site
    }
}
