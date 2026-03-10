<?php
/**
 * Plugin Uninstall Script
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Remove custom tables
$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "1111_learn_submissions");
$wpdb->query("DROP TABLE IF EXISTS " . $wpdb->prefix . "1111_learn_enrollments");

// Remove options
delete_option('1111_learn_api_key');
delete_option('1111_learn_agent_user_id');
delete_option('1111_learn_telemetry_enabled');
delete_option('1111_learn_telemetry_consent_at');
delete_option('1111_learn_service_credential');
delete_option('1111_learn_anonymous_id');

// Remove agent user and role
$agent_user_id = get_option('1111_learn_agent_user_id');
if ($agent_user_id) {
    wp_delete_user($agent_user_id);
}
remove_role('1111_learn_agent');

// Note: Subsites created for learners are NOT deleted by default to avoid data loss.
