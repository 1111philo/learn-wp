<?php
/**
 * Uninstall handler for the 1111 Learn plugin.
 *
 * Cleans up all plugin data when uninstalled via the WordPress admin.
 *
 * @package Learn
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------------
 * 1. Remove the 1111 Agent user.
 * -------------------------------------------------------------------------
 */
$agent_user_file = __DIR__ . '/includes/class-agent-user.php';

if ( file_exists( $agent_user_file ) ) {
	require_once $agent_user_file;

	if ( class_exists( 'Learn_Agent_User' ) ) {
		$agent = Learn_Agent_User::get_instance();
		$agent->remove_agent_user();
	}
} else {
	// Direct removal if class is unavailable.
	$agent_user = get_user_by( 'login', '1111-learn-agent' );

	if ( $agent_user ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $agent_user->ID );
	}
}

/*
 * -------------------------------------------------------------------------
 * 2. Remove custom role.
 * -------------------------------------------------------------------------
 */
remove_role( '1111_learn_agent' );

/*
 * -------------------------------------------------------------------------
 * 3. Delete all options with 1111_learn_ prefix.
 * -------------------------------------------------------------------------
 */
global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		'1111_learn_%'
	)
);

if ( $option_names ) {
	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

/*
 * -------------------------------------------------------------------------
 * 4. Delete all post meta with _1111_ prefix on learn posts.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE pm FROM {$wpdb->postmeta} pm
		INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE p.post_type = %s AND pm.meta_key LIKE %s",
		'learn',
		'\_1111\_%'
	)
);

/*
 * -------------------------------------------------------------------------
 * 5. Delete all term meta with _1111_ prefix.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		'\_1111\_%'
	)
);

/*
 * -------------------------------------------------------------------------
 * 6. Drop custom tables.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}_1111_learn_submissions" );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}_1111_learn_enrollments" );

/*
 * -------------------------------------------------------------------------
 * 7. Delete transients with _1111_ prefix.
 * -------------------------------------------------------------------------
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'\_transient\_\_1111\_%',
		'\_transient\_timeout\_\_1111\_%'
	)
);

/*
 * Note: This uninstaller does NOT delete learner subsites.
 * Deleting entire subsites is too destructive for an uninstall operation.
 * Administrators should manually remove learner subsites if desired.
 */
