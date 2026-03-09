<?php
/**
 * Manages the 1111 Agent user and custom role.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Agent_User
 *
 * Creates and manages the 1111 Agent system user that authors all generated content.
 */
class Learn_Agent_User {

	/**
	 * Agent user login name.
	 *
	 * @var string
	 */
	const USERNAME = '1111-learn-agent';

	/**
	 * Agent user email (non-routable placeholder).
	 *
	 * @var string
	 */
	const EMAIL = 'agent@1111-learn.local';

	/**
	 * Agent user display name.
	 *
	 * @var string
	 */
	const DISPLAY_NAME = '1111';

	/**
	 * Custom role slug.
	 *
	 * @var string
	 */
	const ROLE = '1111_learn_agent';

	/**
	 * Create the agent user and custom role on the current site.
	 */
	public static function create() {
		self::add_role();

		$user = get_user_by( 'login', self::USERNAME );
		if ( $user ) {
			// User exists — ensure the role is assigned.
			if ( ! in_array( self::ROLE, (array) $user->roles, true ) ) {
				$user->add_role( self::ROLE );
			}
			update_option( '1111_learn_agent_user_id', $user->ID );
			return $user->ID;
		}

		$user_id = wp_insert_user( array(
			'user_login'   => self::USERNAME,
			'user_email'   => self::EMAIL,
			'display_name' => self::DISPLAY_NAME,
			'user_pass'    => wp_generate_password( 64, true, true ),
			'role'         => self::ROLE,
		) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_option( '1111_learn_agent_user_id', $user_id );
		return $user_id;
	}

	/**
	 * Add the custom agent role with learn CPT capabilities.
	 */
	public static function add_role() {
		$existing = get_role( self::ROLE );
		if ( $existing ) {
			return;
		}

		add_role(
			self::ROLE,
			__( '1111 Learn Agent', 'learn' ),
			array(
				'read'                       => true,
				'edit_learn_posts'           => true,
				'edit_published_learn_posts' => true,
				'publish_learn_posts'        => true,
				'delete_learn_posts'         => true,
			)
		);
	}

	/**
	 * Get the agent user ID for the current site.
	 *
	 * @return int|false Agent user ID or false if not found.
	 */
	public static function get_id() {
		$user_id = get_option( '1111_learn_agent_user_id' );
		if ( $user_id ) {
			return (int) $user_id;
		}

		$user = get_user_by( 'login', self::USERNAME );
		if ( $user ) {
			update_option( '1111_learn_agent_user_id', $user->ID );
			return $user->ID;
		}

		return false;
	}

	/**
	 * Check if a given user ID is the agent user.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool
	 */
	public static function is_agent( $user_id ) {
		return (int) $user_id === self::get_id();
	}

	/**
	 * Remove the agent user and custom role from the current site.
	 */
	public static function remove() {
		$user_id = self::get_id();
		if ( $user_id ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			delete_option( '1111_learn_agent_user_id' );
		}

		remove_role( self::ROLE );
	}

	/**
	 * Filter the avatar for the agent user to use the 1111 logo.
	 *
	 * @param string $avatar      HTML for the avatar.
	 * @param mixed  $id_or_email User ID, email, or WP_User object.
	 * @param int    $size        Avatar size in pixels.
	 * @param string $default     Default avatar URL.
	 * @param string $alt         Alt text.
	 * @param array  $args        Extra arguments.
	 * @return string
	 */
	public static function filter_avatar( $avatar, $id_or_email, $size, $default, $alt, $args = array() ) {
		$user_id = 0;

		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( is_string( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		}

		if ( ! $user_id || ! self::is_agent( $user_id ) ) {
			return $avatar;
		}

		$logo_url = LEARN_PLUGIN_URL . 'assets/1111-logo.svg';
		return sprintf(
			'<img alt="%s" src="%s" class="avatar avatar-%d photo learn-agent-avatar" height="%d" width="%d" />',
			esc_attr( $alt ),
			esc_url( $logo_url ),
			(int) $size,
			(int) $size,
			(int) $size
		);
	}
}

add_filter( 'get_avatar', array( 'Learn_Agent_User', 'filter_avatar' ), 10, 6 );
