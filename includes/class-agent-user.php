<?php
/**
 * Agent User and Role Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Agent_User
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
     * Create the agent user and role
     */
    public function create_agent_user()
    {
        $this->add_agent_role();

        $username = '1111-learn-agent';
        $email = 'agent@1111-learn.local';

        if (!username_exists($username)) {
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('1111_learn_agent');
                $user->display_name = '1111';
                wp_update_user($user);
                update_option('1111_learn_agent_user_id', $user_id);
            }
        } else {
            $user = get_user_by('slug', $username);
            update_option('1111_learn_agent_user_id', $user->ID);
        }
    }

    /**
     * Add custom agent role
     */
    private function add_agent_role()
    {
        add_role(
            '1111_learn_agent',
            __('1111 Agent', '1111-learn'),
            array(
                'edit_learn_posts' => true,
                'edit_published_learn_posts' => true,
                'publish_learn_posts' => true,
                'delete_learn_posts' => true,
                'read' => true,
            )
        );

        // Grant capabilities to administrator
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('edit_learn_posts');
            $role->add_cap('edit_published_learn_posts');
            $role->add_cap('publish_learn_posts');
            $role->add_cap('delete_learn_posts');
        }
    }

    /**
     * Get agent user ID
     */
    public function get_agent_user_id()
    {
        return get_option('1111_learn_agent_user_id');
    }
}
