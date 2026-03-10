<?php
/**
 * Plugin Name: Learn
 * Plugin URI:  https://github.com/1111philo/learn-wp
 * Description: AI-powered course creation, portfolio building, and assessment for WordPress Multisite.
 * Version:     0.5.0-draft
 * Author:      11:11 Philosopher's Group
 * License:     GPL v2 or later
 * Text Domain: 1111-learn
 * Network:     true
 */

if (!defined('ABSPATH')) {
	exit;
}

// Plugin constants
define('LEARN_VERSION', '0.5.0-draft');
define('LEARN_PATH', plugin_dir_path(__FILE__));
define('LEARN_URL', plugin_dir_url(__FILE__));
define('LEARN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Learn Class
 */
final class Learn
{

	/**
	 * Instance of this class
	 * @var Learn
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
		$this->check_multisite();
		$this->init_hooks();
		$this->includes();
	}

	/**
	 * Check if multisite is enabled
	 */
	private function check_multisite()
	{
		if (!is_multisite()) {
			add_action('admin_notices', array($this, 'multisite_required_notice'));
			return;
		}
	}

	/**
	 * Display multisite required notice
	 */
	public function multisite_required_notice()
	{
		?>
		<div class="notice notice-error">
			<p><?php _e('Learn requires WordPress Multisite to be enabled. Please enable Multisite or deactivate this plugin.', '1111-learn'); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks()
	{
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
	}

	/**
	 * Include required files
	 */
	private function includes()
	{
		require_once LEARN_PATH . 'includes/class-post-type.php';
		require_once LEARN_PATH . 'includes/class-agent-user.php';
		require_once LEARN_PATH . 'includes/class-db.php';
		require_once LEARN_PATH . 'includes/class-settings.php';
		require_once LEARN_PATH . 'includes/class-block-editor.php';
		require_once LEARN_PATH . 'includes/class-frontend.php';
		require_once LEARN_PATH . 'includes/class-xp-engine.php';
		require_once LEARN_PATH . 'includes/class-multisite.php';
		require_once LEARN_PATH . 'includes/class-telemetry.php';
		require_once LEARN_PATH . 'includes/class-assessment-engine.php';

		// Initialize classes
		Learn_Post_Type::get_instance();
		Learn_Block_Editor::get_instance();
		Learn_Frontend::get_instance();
		Learn_XP_Engine::get_instance();
		Learn_Multisite::get_instance();
		Learn_Telemetry::get_instance();
		Learn_Assessment_Engine::get_instance();
		Learn_DB::get_instance();
		Learn_Settings::get_instance();
		Learn_Block_Editor::get_instance();
	}

	/**
	 * Activation hook
	 */
	public function activate()
	{
		// Run migrations
		require_once LEARN_PATH . 'includes/class-db.php';
		Learn_DB::get_instance()->create_tables();

		// Create agent user
		require_once LEARN_PATH . 'includes/class-agent-user.php';
		Learn_Agent_User::get_instance()->create_agent_user();

		// Flush rewrite rules
		require_once LEARN_PATH . 'includes/class-post-type.php';
		Learn_Post_Type::get_instance()->register_post_types();
		Learn_Post_Type::get_instance()->register_taxonomies();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook
	 */
	public function deactivate()
	{
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin
 */
function learn_init()
{
	return Learn::get_instance();
}

learn_init();
