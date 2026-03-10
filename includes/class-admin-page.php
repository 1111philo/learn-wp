<?php
/**
 * Admin Page Controller
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Admin_Page
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
        add_action('admin_menu', array($this, 'register_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Register Admin Pages
     */
    public function register_admin_pages()
    {
        if (!is_main_site() || !is_super_admin()) {
            return;
        }

        // Dashboard (Main)
        add_menu_page(
            __('Learn', '1111-learn'),
            __('Learn', '1111-learn'),
            'manage_network',
            'learn-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-welcome-learn-more',
            25
        );

        // Course Review (Hidden from menu but accessible)
        add_submenu_page(
            null,
            __('Course Review', '1111-learn'),
            __('Course Review', '1111-learn'),
            'manage_network',
            'learn-course-review',
            array($this, 'render_course_review')
        );

        // Learner Progress
        add_submenu_page(
            'learn-dashboard',
            __('Learner Progress', '1111-learn'),
            __('Learner Progress', '1111-learn'),
            'manage_network',
            'learn-progress',
            array($this, 'render_learner_progress')
        );
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets($hook)
    {
        if (false === strpos($hook, 'learn-') && 'edit-learn' !== $hook) {
            return;
        }

        wp_enqueue_style('1111-learn-admin', LEARN_URL . 'admin/css/admin.css', array(), LEARN_VERSION);
        wp_enqueue_script('1111-learn-admin', LEARN_URL . 'admin/js/admin.js', array('jquery', 'wp-util'), LEARN_VERSION, true);

        wp_localize_script('1111-learn-admin', 'learnAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('1111_learn_generate'),
            'labels' => array(
                'generating' => __('Generating...', '1111-learn'),
                'complete' => __('Complete!', '1111-learn'),
                'error' => __('Error!', '1111-learn'),
            )
        ));
    }

    /**
     * Render Dashboard
     */
    public function render_dashboard()
    {
        include LEARN_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Render Course Review
     */
    public function render_course_review()
    {
        include LEARN_PATH . 'admin/views/course-review.php';
    }

    /**
     * Render Learner Progress
     */
    public function render_learner_progress()
    {
        include LEARN_PATH . 'admin/views/learner-progress.php';
    }
}
