<?php
/**
 * Learner self-registration and subsite provisioning.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles learner self-registration, user creation, and subsite provisioning.
 */
class Learn_Registration {

	/**
	 * Registration errors.
	 *
	 * @var WP_Error
	 */
	private $errors;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->errors = new WP_Error();
		add_shortcode( 'learn_register', array( $this, 'render_registration_form' ) );
		add_action( 'init', array( $this, 'process_registration' ) );
	}

	/**
	 * Render the registration form via shortcode.
	 *
	 * @return string HTML output.
	 */
	public function render_registration_form() {
		// If user is already logged in, redirect or show message.
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already registered and logged in.', '1111-learn' ) . '</p>';
		}

		ob_start();
		$errors = $this->errors;
		include plugin_dir_path( __DIR__ ) . 'admin/views/registration.php';
		return ob_get_clean();
	}

	/**
	 * Process the registration form submission.
	 */
	public function process_registration() {
		if ( ! isset( $_POST['1111_learn_register_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['1111_learn_register_nonce'] ) ), '1111_learn_register' ) ) {
			$this->errors->add( 'nonce', __( 'Security check failed. Please try again.', '1111-learn' ) );
			return;
		}

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Validate username.
		if ( empty( $username ) ) {
			$this->errors->add( 'username', __( 'Username is required.', '1111-learn' ) );
		} elseif ( strlen( $username ) < 3 || strlen( $username ) > 60 ) {
			$this->errors->add( 'username', __( 'Username must be between 3 and 60 characters.', '1111-learn' ) );
		} elseif ( ! validate_username( $username ) ) {
			$this->errors->add( 'username', __( 'Username contains invalid characters.', '1111-learn' ) );
		} elseif ( username_exists( $username ) ) {
			$this->errors->add( 'username', __( 'That username is already taken.', '1111-learn' ) );
		}

		// Validate email.
		if ( empty( $email ) ) {
			$this->errors->add( 'email', __( 'Email address is required.', '1111-learn' ) );
		} elseif ( ! is_email( $email ) ) {
			$this->errors->add( 'email', __( 'Please enter a valid email address.', '1111-learn' ) );
		} elseif ( email_exists( $email ) ) {
			$this->errors->add( 'email', __( 'An account with that email already exists.', '1111-learn' ) );
		}

		// Validate password.
		if ( empty( $password ) ) {
			$this->errors->add( 'password', __( 'Password is required.', '1111-learn' ) );
		} elseif ( strlen( $password ) < 8 ) {
			$this->errors->add( 'password', __( 'Password must be at least 8 characters.', '1111-learn' ) );
		}

		if ( $this->errors->has_errors() ) {
			return;
		}

		// Create WordPress user.
		$user_id = wpmu_create_user( $username, $password, $email );

		if ( ! $user_id ) {
			$this->errors->add( 'registration', __( 'Registration failed. Please try again.', '1111-learn' ) );
			return;
		}

		// Create subsite.
		$site_path = $this->get_site_path( $username );
		$site_title = sprintf(
			/* translators: %s: username */
			__( '%s\'s Learning Site', '1111-learn' ),
			$username
		);

		$main_site_id = get_main_site_id();
		$network      = get_network();
		$domain       = $network->domain;

		if ( is_subdomain_install() ) {
			$blog_domain = $site_path . '.' . $domain;
			$blog_path   = '/';
		} else {
			$blog_domain = $domain;
			$blog_path   = $network->path . $site_path . '/';
		}

		$blog_id = wpmu_create_blog( $blog_domain, $blog_path, $site_title, $user_id );

		if ( is_wp_error( $blog_id ) ) {
			// Clean up user if subsite creation fails.
			wpmu_delete_user( $user_id );
			$this->errors->add( 'subsite', $blog_id->get_error_message() );
			return;
		}

		// Set user as administrator of their subsite.
		add_user_to_blog( $blog_id, $user_id, 'administrator' );

		// Create the 1111 Agent user on the new subsite.
		switch_to_blog( $blog_id );

		$agent = Learn_Agent_User::get_instance();
		$agent->create_agent_user();

		restore_current_blog();

		/**
		 * Fires after a learner has registered and their subsite is provisioned.
		 *
		 * @param int    $user_id  The new user ID.
		 * @param int    $blog_id  The new subsite ID.
		 * @param string $username The learner's username.
		 */
		do_action( '1111_learn_learner_registered', $user_id, $blog_id, $username );

		// Log the user in.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Redirect to their learner panel.
		$redirect_url = get_admin_url( $blog_id, 'admin.php?page=1111-learn-panel' );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Generate a site path/subdomain from a username.
	 *
	 * Sanitizes the username into a valid path segment.
	 *
	 * @param string $username The username.
	 * @return string Sanitized site path.
	 */
	private function get_site_path( $username ) {
		$path = sanitize_title( $username );
		$path = preg_replace( '/[^a-z0-9-]/', '', $path );
		$path = trim( $path, '-' );

		if ( empty( $path ) ) {
			$path = 'learner-' . wp_rand( 1000, 9999 );
		}

		// Ensure uniqueness.
		$original = $path;
		$counter  = 1;
		while ( $this->site_path_exists( $path ) ) {
			$path = $original . '-' . $counter;
			$counter++;
		}

		return $path;
	}

	/**
	 * Check if a site path already exists.
	 *
	 * @param string $path The site path to check.
	 * @return bool Whether the path exists.
	 */
	private function site_path_exists( $path ) {
		$network = get_network();

		if ( is_subdomain_install() ) {
			$existing = get_blog_id_from_url( $path . '.' . $network->domain );
		} else {
			$existing = get_blog_id_from_url( $network->domain, $network->path . $path . '/' );
		}

		return $existing > 0;
	}
}
