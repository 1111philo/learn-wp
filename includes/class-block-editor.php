<?php
/**
 * Block Editor (Gutenberg) Extensions and Locking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Block_Editor
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
        add_filter('register_post_type_args', array($this, 'lock_learn_post_type_template'), 10, 2);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }

    /**
     * Lock the template for learn posts to prevent block movement/deletion
     */
    public function lock_learn_post_type_template($args, $post_type)
    {
        if ('learn' === $post_type) {
            // All generated posts are locked. 
            // In Phase 6 we might relax this for the agent user specifically.
            $args['template_lock'] = 'all';
        }
        return $args;
    }

    /**
     * Enqueue Editor Assets
     */
    public function enqueue_editor_assets()
    {
        $screen = get_current_screen();
        if (!$screen || 'learn' !== $screen->post_type) {
            return;
        }

        wp_enqueue_script(
            '1111-learn-editor',
            LEARN_URL . 'admin/js/editor.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'),
            LEARN_VERSION,
            true
        );

        wp_enqueue_style(
            '1111-learn-editor',
            LEARN_URL . 'admin/css/editor.css',
            array(),
            LEARN_VERSION
        );

        // Localize data for the sidebar
        $post_id = get_the_ID();
        $activity_data = get_post_meta($post_id, '_1111_activity_data', true);

        wp_localize_script('1111-learn-editor', 'learnEditor', array(
            'post_id' => $post_id,
            'activity_data' => $activity_data,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('1111_learn_editor'),
            'labels' => array(
                'sidebar_title' => __('Learn Settings', '1111-learn'),
                'feedback_label' => __('Provide Feedback for AI', '1111-learn'),
                'feedback_help' => __('Specify what should be changed. The agent will regenerate the content based on this feedback.', '1111-learn'),
                'submit_feedback' => __('Apply Feedback', '1111-learn'),
            )
        ));
    }
}
