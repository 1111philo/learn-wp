<?php
/**
 * Uninstall handler for the Learn plugin.
 *
 * Removes all plugin data: options, term meta, post meta, custom tables, and the agent user.
 *
 * @package Learn
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up a single site's Learn data.
 */
function learn_uninstall_site() {
	global $wpdb;

	// Remove the agent user.
	$agent_user_id = get_option( '1111_learn_agent_user_id' );
	if ( $agent_user_id ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $agent_user_id );
	}

	// Remove the custom role.
	remove_role( '1111_learn_agent' );

	// Remove learn CPT capabilities from administrator role.
	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		$caps = array(
			'edit_learn_post',
			'edit_learn_posts',
			'edit_others_learn_posts',
			'edit_published_learn_posts',
			'publish_learn_posts',
			'read_learn_post',
			'read_private_learn_posts',
			'delete_learn_post',
			'delete_learn_posts',
			'delete_others_learn_posts',
			'delete_published_learn_posts',
		);
		foreach ( $caps as $cap ) {
			$admin_role->remove_cap( $cap );
		}
	}

	// Delete all learn posts.
	$posts = get_posts( array(
		'post_type'      => 'learn',
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
	) );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	// Delete course taxonomy terms and their meta.
	$course_terms = get_terms( array(
		'taxonomy'   => 'course',
		'hide_empty' => false,
		'fields'     => 'ids',
	) );
	if ( ! is_wp_error( $course_terms ) ) {
		foreach ( $course_terms as $term_id ) {
			$meta_keys = array(
				'_1111_learning_objectives',
				'_1111_course_description',
				'_1111_narrative_description',
				'_1111_lesson_titles',
				'_1111_work_product',
				'_1111_work_product_type',
				'_1111_work_product_description',
				'_1111_assessment',
				'_1111_assessment_post_id',
				'_1111_assessment_feedback',
				'_1111_total_xp',
				'_1111_generation_date',
				'_1111_generation_status',
				'_1111_course_feedback',
				'_1111_course_version',
			);
			foreach ( $meta_keys as $key ) {
				delete_term_meta( $term_id, $key );
			}
			wp_delete_term( $term_id, 'course' );
		}
	}

	// Delete lesson_group taxonomy terms and their meta.
	$group_terms = get_terms( array(
		'taxonomy'   => 'lesson_group',
		'hide_empty' => false,
		'fields'     => 'ids',
	) );
	if ( ! is_wp_error( $group_terms ) ) {
		foreach ( $group_terms as $term_id ) {
			$meta_keys = array(
				'_1111_lesson_plan_raw',
				'_1111_learning_objective',
				'_1111_lesson_count',
				'_1111_plan_feedback',
				'_1111_plan_version',
			);
			foreach ( $meta_keys as $key ) {
				delete_term_meta( $term_id, $key );
			}
			wp_delete_term( $term_id, 'lesson_group' );
		}
	}

	// Delete plugin options.
	$options = array(
		'1111_learn_api_key',
		'1111_learn_agent_user_id',
		'1111_learn_telemetry_enabled',
		'1111_learn_telemetry_consent_at',
		'1111_learn_service_credential',
		'1111_learn_anonymous_id',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Drop custom tables from the main site.
 */
function learn_uninstall_tables() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}1111_learn_submissions" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}1111_learn_enrollments" );
}

// Run cleanup across all sites on Multisite.
if ( is_multisite() ) {
	$sites = get_sites( array( 'number' => 0 ) );
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		learn_uninstall_site();
		restore_current_blog();
	}

	// Drop custom tables on main site.
	switch_to_blog( get_main_site_id() );
	learn_uninstall_tables();
	restore_current_blog();
} else {
	learn_uninstall_site();
	learn_uninstall_tables();
}
