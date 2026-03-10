<?php
/**
 * Main plugin bootstrap.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Learn_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton.
	 *
	 * @return Learn_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Init plugin.
	 *
	 * @return void
	 */
	public function init() {
		$this->load_dependencies();

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_environment_notice' ) );
	}

	/**
	 * Plugin activation callback.
	 *
	 * @param bool $network_wide Network activation state.
	 * @return void
	 */
	public static function activate( $network_wide ) {
		require_once LEARN_WP_PLUGIN_DIR . 'includes/class-installer.php';
		Learn_Installer::activate( (bool) $network_wide );
	}

	/**
	 * Loads required classes.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$files = array(
			'includes/class-installer.php',
			'includes/class-cpt.php',
			'includes/class-settings.php',
			'includes/class-json-parser.php',
			'includes/class-prompt-loader.php',
			'includes/class-validator.php',
			'includes/class-api-client.php',
			'includes/class-telemetry.php',
			'includes/class-agent-base.php',
			'includes/agents/class-course-describer.php',
			'includes/agents/class-lesson-planner.php',
			'includes/agents/class-lesson-writer.php',
			'includes/agents/class-activity-creator.php',
			'includes/agents/class-activity-reviewer.php',
			'includes/agents/class-assessment-creator.php',
			'includes/agents/class-activity-assessment.php',
			'includes/class-orchestrator.php',
			'includes/class-admin-page.php',
			'includes/class-content-lock.php',
			'includes/class-learner-registration.php',
			'includes/class-content-copy.php',
			'includes/class-learner-panel.php',
			'includes/class-course-navigation.php',
			'includes/class-assessment.php',
		);

		foreach ( $files as $file ) {
			require_once LEARN_WP_PLUGIN_DIR . $file;
		}
	}

	/**
	 * Runtime bootstrap.
	 *
	 * @return void
	 */
	public function bootstrap() {
		if ( ! is_multisite() ) {
			return;
		}

		Learn_CPT::instance()->init();
		Learn_Settings::instance()->init();
		Learn_Telemetry::instance()->init();
		Learn_Admin_Page::instance()->init();
		Learn_Content_Lock::instance()->init();
		Learn_Learner_Registration::instance()->init();
		Learn_Learner_Panel::instance()->init();
		Learn_Course_Navigation::instance()->init();
		Learn_Assessment::instance()->init();
	}

	/**
	 * Admin environment notice.
	 *
	 * @return void
	 */
	public function maybe_show_environment_notice() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		if ( ! is_multisite() ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Learn requires WordPress Multisite and network activation.', 'learn-wp' )
			);
			return;
		}

		if ( ! is_plugin_active_for_network( plugin_basename( LEARN_WP_PLUGIN_FILE ) ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'Learn should be network activated for consistent Multisite behavior.', 'learn-wp' )
			);
		}
	}
}
