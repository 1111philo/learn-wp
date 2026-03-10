<?php
/**
 * Plugin Name: Learn
 * Plugin URI: https://1111philo.com
 * Description: AI-powered course creation, learner portfolio building, and assessment for WordPress Multisite.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: 11:11 Philosopher's Group
 * Author URI: https://1111philo.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: learn-wp
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LEARN_WP_VERSION', '0.1.0' );
define( 'LEARN_WP_PLUGIN_FILE', __FILE__ );
define( 'LEARN_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEARN_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once LEARN_WP_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( LEARN_WP_PLUGIN_FILE, array( 'Learn_Plugin', 'activate' ) );

Learn_Plugin::instance()->init();
