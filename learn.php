<?php
/**
 * Plugin Name: Learn
 * Plugin URI: https://github.com/1111philo/learn-wp
 * Description: AI-powered course creation plugin for WordPress Multisite by 11:11 Philosopher's Group.
 * Version: 0.1.0
 * Author: 11:11 Philosopher's Group
 * Author URI: https://1111philo.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learn
 * Network: true
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'LEARN_VERSION', '0.1.0' );
define( 'LEARN_PLUGIN_FILE', __FILE__ );
define( 'LEARN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEARN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'LEARN_FAST_MODEL' ) ) {
	define( 'LEARN_FAST_MODEL', 'claude-haiku-4-5-20251001' );
}
if ( ! defined( 'LEARN_DEFAULT_MODEL' ) ) {
	define( 'LEARN_DEFAULT_MODEL', 'claude-sonnet-4-6' );
}
if ( ! defined( 'LEARN_PLAN_MAX_TOKENS' ) ) {
	define( 'LEARN_PLAN_MAX_TOKENS', 2048 );
}
if ( ! defined( 'LEARN_CONTENT_MAX_TOKENS' ) ) {
	define( 'LEARN_CONTENT_MAX_TOKENS', 8192 );
}
if ( ! defined( 'LEARN_LESSONS_PER_OBJECTIVE' ) ) {
	define( 'LEARN_LESSONS_PER_OBJECTIVE', 1 );
}

/**
 * Check for Multisite. Bail with admin notice if not network activated on Multisite.
 */
function learn_multisite_check() {
	if ( ! is_multisite() ) {
		add_action( 'admin_notices', 'learn_multisite_notice' );
		add_action( 'network_admin_notices', 'learn_multisite_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice when not on Multisite.
 */
function learn_multisite_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Learn requires WordPress Multisite.', 'learn' ); ?></strong>
			<?php esc_html_e( 'This plugin must be network activated on a WordPress Multisite installation.', 'learn' ); ?>
		</p>
	</div>
	<?php
}

if ( ! learn_multisite_check() ) {
	return;
}

/**
 * Include plugin classes.
 */
require_once LEARN_PLUGIN_DIR . 'includes/class-post-type.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-agent-user.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-settings.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-api-client.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-prompt-loader.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-validator.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-orchestrator.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-admin-page.php';

/**
 * Plugin activation — network-wide.
 */
function learn_activate( $network_wide ) {
	if ( ! is_multisite() ) {
		return;
	}

	if ( $network_wide ) {
		$sites = get_sites( array( 'number' => 0 ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			learn_activate_single_site();
			restore_current_blog();
		}
	} else {
		learn_activate_single_site();
	}

	// Create custom tables on the main site.
	switch_to_blog( get_main_site_id() );
	learn_create_tables();
	restore_current_blog();
}

/**
 * Activate on a single site — register CPT, create agent user.
 */
function learn_activate_single_site() {
	Learn_Post_Type::register();
	flush_rewrite_rules();
	Learn_Agent_User::create();
}

/**
 * Create custom database tables.
 */
function learn_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$submissions_table = $wpdb->prefix . '1111_learn_submissions';
	$enrollments_table = $wpdb->prefix . '1111_learn_enrollments';

	$sql_submissions = "CREATE TABLE $submissions_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		blog_id bigint(20) unsigned NOT NULL,
		post_id bigint(20) unsigned NOT NULL,
		lesson_post_id bigint(20) unsigned NOT NULL,
		course_term_id bigint(20) unsigned NOT NULL,
		activity_type varchar(20) NOT NULL,
		submitted_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		score decimal(3,2) DEFAULT NULL,
		recommendation varchar(20) DEFAULT NULL,
		assessment_json longtext DEFAULT NULL,
		assessed_at datetime DEFAULT NULL,
		attempt_number int(11) NOT NULL DEFAULT 1,
		content_snapshot longtext DEFAULT NULL,
		PRIMARY KEY (id),
		KEY user_course (user_id, course_term_id),
		KEY lesson_user (lesson_post_id, user_id),
		KEY blog_post (blog_id, post_id)
	) $charset_collate;";

	$sql_enrollments = "CREATE TABLE $enrollments_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		blog_id bigint(20) unsigned NOT NULL,
		course_term_id bigint(20) unsigned NOT NULL,
		enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		content_copied_at datetime DEFAULT NULL,
		lessons_completed int(11) NOT NULL DEFAULT 0,
		total_lessons int(11) NOT NULL DEFAULT 0,
		current_xp int(11) NOT NULL DEFAULT 0,
		total_xp int(11) NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		PRIMARY KEY (id),
		KEY user_course (user_id, course_term_id),
		KEY blog (blog_id),
		KEY course_status (course_term_id, status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_submissions );
	dbDelta( $sql_enrollments );
}

register_activation_hook( __FILE__, 'learn_activate' );

/**
 * Create agent user on new sites added to the network.
 */
function learn_new_site( $new_site ) {
	switch_to_blog( $new_site->blog_id );
	learn_activate_single_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'learn_new_site', 200 );

/**
 * Initialize plugin.
 */
function learn_init() {
	Learn_Post_Type::register();
}
add_action( 'init', 'learn_init' );

/**
 * Register WP-Cron hook for generation pipeline steps.
 */
add_action( '1111_learn_generation_step', array( 'Learn_Orchestrator', 'execute_step' ), 10, 2 );

/**
 * Initialize admin features.
 */
function learn_admin_init() {
	Learn_Settings::init();
	Learn_Admin_Page::init();
}
add_action( 'admin_init', 'learn_admin_init' );

/**
 * Register admin menus.
 */
function learn_admin_menu() {
	if ( ! is_main_site() || ! is_super_admin() ) {
		return;
	}

	$icon_svg = learn_get_menu_icon();

	add_menu_page(
		__( 'Learn', 'learn' ),
		__( 'Learn', 'learn' ),
		'manage_network',
		'learn-dashboard',
		'learn_render_dashboard_page',
		$icon_svg,
		25
	);

	add_submenu_page(
		'learn-dashboard',
		__( 'Dashboard', 'learn' ),
		__( 'Dashboard', 'learn' ),
		'manage_network',
		'learn-dashboard',
		'learn_render_dashboard_page'
	);

	add_submenu_page(
		'learn-dashboard',
		__( 'Learner Progress', 'learn' ),
		__( 'Learner Progress', 'learn' ),
		'manage_network',
		'learn-progress',
		'learn_render_progress_page'
	);

	add_submenu_page(
		'learn-dashboard',
		__( 'Settings', 'learn' ),
		__( 'Settings', 'learn' ),
		'manage_network',
		'learn-settings',
		'learn_render_settings_page'
	);
}
add_action( 'admin_menu', 'learn_admin_menu' );

/**
 * Get the base64-encoded SVG menu icon.
 *
 * @return string Data URI for the menu icon.
 */
function learn_get_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><rect width="20" height="20" rx="2" fill="black"/><rect x="0.5" y="0.5" width="19" height="19" rx="1.5" stroke="white" stroke-opacity="0.5"/><text x="10" y="14" text-anchor="middle" font-family="system-ui,-apple-system,sans-serif" font-weight="700" font-size="6" fill="white">LEARN</text></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Render the dashboard page.
 */
function learn_render_dashboard_page() {
	if ( ! is_super_admin() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'learn' ) );
	}
	include LEARN_PLUGIN_DIR . 'admin/views/dashboard.php';
}

/**
 * Render the learner progress page.
 */
function learn_render_progress_page() {
	if ( ! is_super_admin() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'learn' ) );
	}
	include LEARN_PLUGIN_DIR . 'admin/views/learner-progress.php';
}

/**
 * Render the settings page.
 */
function learn_render_settings_page() {
	if ( ! is_super_admin() ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'learn' ) );
	}
	include LEARN_PLUGIN_DIR . 'admin/views/settings.php';
}

/**
 * Enqueue admin styles and scripts.
 */
function learn_admin_enqueue( $hook ) {
	$learn_pages = array(
		'toplevel_page_learn-dashboard',
		'learn_page_learn-settings',
		'learn_page_learn-progress',
	);

	if ( in_array( $hook, $learn_pages, true ) ) {
		wp_enqueue_style(
			'learn-admin',
			LEARN_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			LEARN_VERSION
		);

		wp_enqueue_script(
			'learn-admin',
			LEARN_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			LEARN_VERSION,
			true
		);

		wp_localize_script( 'learn-admin', 'learnAdmin', array(
			'nonce'        => wp_create_nonce( '1111_learn_dashboard' ),
			'dashboardUrl' => admin_url( 'admin.php?page=learn-dashboard' ),
			'i18n'         => array(
				'titleRequired'     => __( 'Course title is required.', 'learn' ),
				'titleMinLength'    => __( 'Course title must be at least 3 characters.', 'learn' ),
				'descRequired'      => __( 'Course description is required.', 'learn' ),
				'descMinLength'     => __( 'Course description must be at least 20 characters.', 'learn' ),
				'objRequired'       => __( 'At least one learning objective is required.', 'learn' ),
				'objMinLength'      => __( 'Objective %d must be at least 10 characters.', 'learn' ),
				'objectiveN'        => __( 'Objective %d', 'learn' ),
				'remove'            => __( 'Remove', 'learn' ),
				'removeObjective'   => __( 'Remove objective %d', 'learn' ),
				'generating'        => __( 'Generating...', 'learn' ),
				'generateCourse'    => __( 'Generate Course', 'learn' ),
				'generatingCourse'  => __( 'Generating Course', 'learn' ),
				'generationComplete' => __( 'Generation Complete', 'learn' ),
				'generationFailed'  => __( 'Generation Failed', 'learn' ),
				'describingCourse'  => __( 'Establishing course narrative...', 'learn' ),
				'creatingAssessment' => __( 'Creating final assessment...', 'learn' ),
				'stepComplete'      => __( 'Complete', 'learn' ),
				'stepInProgress'    => __( 'In progress', 'learn' ),
				'stepPending'       => __( 'Pending', 'learn' ),
				'publishCourse'     => __( 'Publish Course', 'learn' ),
				'publishing'        => __( 'Publishing...', 'learn' ),
				'publishError'      => __( 'Failed to publish course.', 'learn' ),
				'retryError'        => __( 'Failed to retry generation.', 'learn' ),
				'networkError'      => __( 'Network error. Please try again.', 'learn' ),
			),
		) );
	}
}
add_action( 'admin_enqueue_scripts', 'learn_admin_enqueue' );
