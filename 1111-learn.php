<?php
/**
 * Plugin Name: 1111 Learn
 * Plugin URI:  https://github.com/1111philo/learn-wp
 * Description: AI-powered course creation and learning platform for WordPress Multisite.
 * Version:     0.1.0
 * Author:      11:11 Philosopher's Group
 * Author URI:  https://github.com/1111philo
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 1111-learn
 * Network:     true
 *
 * @package Learn
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'LEARN_VERSION', '0.1.0' );
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
 * Multisite network activation check.
 *
 * If WordPress is not running as a multisite installation, display an admin
 * notice and bail out early.
 */
if ( ! is_multisite() ) {
	/**
	 * Show admin notice when plugin is activated on a non-multisite install.
	 */
	function _1111_learn_multisite_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				_e(
					'1111 Learn requires a WordPress Multisite installation. Please enable Multisite to use this plugin.',
					'1111-learn'
				);
				?>
			</p>
		</div>
		<?php
	}
	add_action( 'admin_notices', '_1111_learn_multisite_notice' );
	return;
}

/*
 * -------------------------------------------------------------------------
 * Include files.
 * -------------------------------------------------------------------------
 */

// Core includes.
require_once LEARN_PLUGIN_DIR . 'includes/class-post-type.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-agent-user.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-api-client.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-prompt-loader.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-orchestrator.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-validator.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-content-lock.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-feedback.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-learner-assessment.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-learner-panel.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-registration.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-content-copy.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-course-navigation.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-publish.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-settings.php';
require_once LEARN_PLUGIN_DIR . 'includes/class-telemetry.php';

// Agent includes.
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-course-describer.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-lesson-planner.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-lesson-writer.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-activity-creator.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-activity-reviewer.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-assessment-creator.php';
require_once LEARN_PLUGIN_DIR . 'includes/agents/class-activity-assessment.php';

/*
 * -------------------------------------------------------------------------
 * Bootstrap.
 * -------------------------------------------------------------------------
 */

/**
 * Initialize core plugin classes on `plugins_loaded`.
 */
function _1111_learn_init() {
	$post_type    = new Learn_Post_Type();
	$agent_user   = Learn_Agent_User::get_instance();
	$settings     = new Learn_Settings();
	$api_client   = new Learn_API_Client();
	$prompt       = new Learn_Prompt_Loader();
	$validator    = new Learn_Validator();
	$orchestrator = new Learn_Orchestrator( $api_client, $prompt, $validator );
	$content_lock = new Learn_Content_Lock();
	$feedback     = new Learn_Feedback();
	$assessment   = new Learn_Learner_Assessment();
	$admin_page   = new Learn_Admin_Page();
	$learner      = new Learn_Learner_Panel();
	$registration = new Learn_Registration();
	$content_copy = new Learn_Content_Copy();
	$navigation   = new Learn_Course_Navigation();
	$publish      = new Learn_Publish();
	$telemetry    = new Learn_Telemetry();

	/**
	 * Fires after all 1111 Learn core classes have been instantiated.
	 *
	 * @param Learn_Orchestrator $orchestrator The orchestrator instance.
	 */
	do_action( '1111_learn_loaded', $orchestrator );
}
add_action( 'plugins_loaded', '_1111_learn_init' );

/*
 * -------------------------------------------------------------------------
 * Activation / Deactivation.
 * -------------------------------------------------------------------------
 */

/**
 * Run setup tasks on plugin activation.
 *
 * Creates the dedicated agent user and any custom database tables.
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 */
function _1111_learn_activate( $network_wide ) {
	if ( ! is_multisite() ) {
		return;
	}

	// Create the agent user used for programmatic content authoring.
	Learn_Agent_User::get_instance()->create_agent_user();

	// Create custom database tables.
	_1111_learn_create_tables();

	/**
	 * Fires after 1111 Learn activation tasks complete.
	 *
	 * @param bool $network_wide Whether the activation is network-wide.
	 */
	do_action( '1111_learn_activated', $network_wide );
}
register_activation_hook( __FILE__, '_1111_learn_activate' );

/**
 * Run cleanup tasks on plugin deactivation.
 *
 * @param bool $network_wide Whether the plugin is being deactivated network-wide.
 */
function _1111_learn_deactivate( $network_wide ) {
	/**
	 * Fires during 1111 Learn deactivation.
	 *
	 * @param bool $network_wide Whether the deactivation is network-wide.
	 */
	do_action( '1111_learn_deactivated', $network_wide );
}
register_deactivation_hook( __FILE__, '_1111_learn_deactivate' );

/**
 * Create custom database tables on activation.
 */
function _1111_learn_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$submissions_table = $wpdb->base_prefix . '1111_learn_submissions';
	$enrollments_table = $wpdb->base_prefix . '1111_learn_enrollments';

	$sql_submissions = "CREATE TABLE IF NOT EXISTS {$submissions_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		blog_id bigint(20) unsigned NOT NULL,
		post_id bigint(20) unsigned NOT NULL,
		lesson_post_id bigint(20) unsigned NOT NULL,
		course_term_id bigint(20) unsigned NOT NULL,
		activity_type varchar(20) NOT NULL,
		submitted_at datetime NOT NULL,
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
	) {$charset_collate};";

	$sql_enrollments = "CREATE TABLE IF NOT EXISTS {$enrollments_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		blog_id bigint(20) unsigned NOT NULL,
		course_term_id bigint(20) unsigned NOT NULL,
		enrolled_at datetime NOT NULL,
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
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_submissions );
	dbDelta( $sql_enrollments );
}

/*
 * -------------------------------------------------------------------------
 * Asset enqueueing.
 * -------------------------------------------------------------------------
 */

/**
 * Enqueue admin scripts and styles.
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function _1111_learn_admin_enqueue( $hook_suffix ) {
	$screen = get_current_screen();

	// Only load on our own admin pages and relevant post types.
	if ( null === $screen ) {
		return;
	}

	$allowed = array(
		'toplevel_page_1111-learn',
		'learn_page_1111-learn-settings',
	);

	$is_learn_screen = in_array( $hook_suffix, $allowed, true )
		|| ( isset( $screen->post_type ) && 'learn' === $screen->post_type );

	if ( ! $is_learn_screen ) {
		return;
	}

	wp_enqueue_style(
		'1111-learn-admin',
		LEARN_PLUGIN_URL . 'admin/css/admin.css',
		array(),
		LEARN_VERSION
	);

	wp_enqueue_script(
		'1111-learn-admin',
		LEARN_PLUGIN_URL . 'admin/js/admin.js',
		array( 'jquery' ),
		LEARN_VERSION,
		true
	);

	wp_localize_script(
		'1111-learn-admin',
		'learnAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( '1111_learn_admin' ),
		)
	);

	/**
	 * Fires after 1111 Learn admin assets are enqueued.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	do_action( '1111_learn_admin_enqueue', $hook_suffix );
}
add_action( 'admin_enqueue_scripts', '_1111_learn_admin_enqueue' );

/**
 * Enqueue public-facing scripts and styles.
 */
function _1111_learn_public_enqueue() {
	if ( ! is_singular( 'learn' ) && ! is_post_type_archive( 'learn' ) ) {
		return;
	}

	wp_enqueue_style(
		'1111-learn-public',
		LEARN_PLUGIN_URL . 'public/css/learn-public.css',
		array(),
		LEARN_VERSION
	);

	wp_enqueue_script(
		'1111-learn-public',
		LEARN_PLUGIN_URL . 'public/js/learn-public.js',
		array(),
		LEARN_VERSION,
		true
	);

	wp_localize_script(
		'1111-learn-public',
		'learnPublic',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( '1111_learn_public' ),
		)
	);

	/**
	 * Fires after 1111 Learn public assets are enqueued.
	 */
	do_action( '1111_learn_public_enqueue' );
}
add_action( 'wp_enqueue_scripts', '_1111_learn_public_enqueue' );
