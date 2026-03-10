<?php
/**
 * Uninstall Learn plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	exit;
}

global $wpdb;

$agent_user_id = (int) get_site_option( '_1111_learn_agent_user_id', 0 );
if ( $agent_user_id > 0 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $agent_user_id );
}

remove_role( '1111_learn_agent' );

delete_site_option( '_1111_learn_agent_user_id' );
delete_site_option( '_1111_learn_api_key_encrypted' );
delete_site_option( '_1111_learn_telemetry_opt_in' );
delete_site_option( '_1111_learn_encryption_salt' );
delete_site_option( '_1111_learn_installed_version' );

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->base_prefix . '1111_learn_submissions' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->base_prefix . '1111_learn_enrollments' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
