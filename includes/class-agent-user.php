<?php
/**
 * 1111 Agent user and role management.
 *
 * @package Jesuspended\Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and manages the 1111 Agent user and custom role.
 */
class Learn_Agent_User {

	/**
	 * Singleton instance.
	 *
	 * @var Learn_Agent_User|null
	 */
	private static $instance = null;

	/**
	 * The agent username.
	 *
	 * @var string
	 */
	const USERNAME = '1111-learn-agent';

	/**
	 * The agent role slug.
	 *
	 * @var string
	 */
	const ROLE = '1111_learn_agent';

	/**
	 * The option key for storing the agent user ID.
	 *
	 * @var string
	 */
	const OPTION_KEY = '1111_learn_agent_user_id';

	/**
	 * Get singleton instance.
	 *
	 * @return Learn_Agent_User
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'get_avatar_url', array( $this, 'filter_agent_avatar_url' ), 10, 2 );
	}

	/**
	 * Create the agent role and user.
	 *
	 * Safe to call multiple times — will ensure the role and user exist
	 * with correct settings.
	 */
	public function create_agent_user() {
		// Create or update the custom role.
		$capabilities = array(
			'edit_learn_posts'           => true,
			'edit_published_learn_posts' => true,
			'publish_learn_posts'        => true,
			'delete_learn_posts'         => true,
			'read'                       => true,
		);

		// Remove existing role first to ensure capabilities are current.
		remove_role( self::ROLE );
		add_role(
			self::ROLE,
			__( '1111 Learn Agent', '1111-learn' ),
			$capabilities
		);

		// Check if user already exists.
		$existing_user = get_user_by( 'login', self::USERNAME );

		if ( $existing_user ) {
			// Ensure the role is correct.
			$existing_user->set_role( self::ROLE );
			update_option( self::OPTION_KEY, $existing_user->ID );

			return $existing_user->ID;
		}

		// Create the agent user.
		$user_id = wp_insert_user(
			array(
				'user_login'   => self::USERNAME,
				'user_pass'    => wp_generate_password( 64, true, true ),
				'user_email'   => 'agent@1111-learn.local',
				'display_name' => '1111',
				'role'         => self::ROLE,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_option( self::OPTION_KEY, $user_id );

		return $user_id;
	}

	/**
	 * Get the agent user ID.
	 *
	 * @return int|false The user ID or false if not set.
	 */
	public function get_agent_user_id() {
		return get_option( self::OPTION_KEY, false );
	}

	/**
	 * Remove the agent user and role.
	 *
	 * Intended for use during plugin uninstall.
	 */
	public function remove_agent_user() {
		$user_id = $this->get_agent_user_id();

		if ( $user_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
		}

		delete_option( self::OPTION_KEY );
		remove_role( self::ROLE );
	}

	/**
	 * Filter the avatar URL for the agent user to use the 1111 logo.
	 *
	 * @param string $url         The avatar URL.
	 * @param mixed  $id_or_email The user ID, email, or comment object.
	 * @return string Filtered avatar URL.
	 */
	public function filter_agent_avatar_url( $url, $id_or_email ) {
		$user_id = 0;

		if ( is_numeric( $id_or_email ) ) {
			$user_id = absint( $id_or_email );
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = absint( $id_or_email->user_id );
		}

		$agent_user_id = $this->get_agent_user_id();

		if ( $user_id && $agent_user_id && $user_id === (int) $agent_user_id ) {
			$url = plugin_dir_url( dirname( __FILE__ ) ) . 'assets/1111-logo.svg';
		}

		return $url;
	}
}
