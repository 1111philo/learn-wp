<?php
/**
 * Learner registration and subsite provisioning.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Learner_Registration
 *
 * Handles learner self-registration via shortcode, subsite provisioning,
 * and login redirection.
 */
class Learn_Learner_Registration {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_shortcode( 'learn_register', array( __CLASS__, 'render_registration_form' ) );
		add_action( 'wp_ajax_nopriv_1111_learner_register', array( __CLASS__, 'ajax_register' ) );
		add_action( 'wp_ajax_1111_learner_register', array( __CLASS__, 'ajax_register' ) );
	}

	/**
	 * Render the registration form shortcode.
	 *
	 * @return string HTML.
	 */
	public static function render_registration_form() {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$blog_id = get_user_meta( $user->ID, '_1111_learner_blog_id', true );
			if ( $blog_id ) {
				$site_url = get_home_url( $blog_id );
				return '<p>' . sprintf(
					/* translators: %s: link to learner site */
					esc_html__( 'You are already registered. %s', 'learn' ),
					'<a href="' . esc_url( $site_url . '/wp-admin/' ) . '">' . esc_html__( 'Go to your learning site', 'learn' ) . '</a>'
				) . '</p>';
			}
		}

		ob_start();
		?>
		<div class="learn-register-form" id="learn-register-form">
			<h2><?php esc_html_e( 'Sign Up to Learn', 'learn' ); ?></h2>
			<p><?php esc_html_e( 'Create your account to get your own WordPress learning site.', 'learn' ); ?></p>

			<div id="learn-register-errors" role="alert" hidden></div>
			<div id="learn-register-success" hidden></div>

			<form id="learn-registration" novalidate>
				<?php wp_nonce_field( '1111_learner_register', '_learn_reg_nonce' ); ?>

				<div class="learn-reg-field">
					<label for="learn-reg-username"><?php esc_html_e( 'Username', 'learn' ); ?></label>
					<input type="text" id="learn-reg-username" name="username" required
						autocomplete="username">
				</div>

				<div class="learn-reg-field">
					<label for="learn-reg-email"><?php esc_html_e( 'Email', 'learn' ); ?></label>
					<input type="email" id="learn-reg-email" name="email" required
						autocomplete="email">
				</div>

				<div class="learn-reg-field">
					<label for="learn-reg-display"><?php esc_html_e( 'Display Name', 'learn' ); ?></label>
					<input type="text" id="learn-reg-display" name="display_name"
						autocomplete="name">
				</div>

				<div class="learn-reg-field">
					<label for="learn-reg-password"><?php esc_html_e( 'Password', 'learn' ); ?></label>
					<input type="password" id="learn-reg-password" name="password" required
						autocomplete="new-password" minlength="8">
				</div>

				<button type="submit" class="learn-reg-submit">
					<?php esc_html_e( 'Create Account', 'learn' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Register a learner.
	 */
	public static function ajax_register() {
		check_ajax_referer( '1111_learner_register', 'nonce' );

		$username     = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
		$password     = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '';

		$errors = array();
		if ( ! $username ) {
			$errors[] = __( 'Username is required.', 'learn' );
		}
		if ( ! is_email( $email ) ) {
			$errors[] = __( 'Valid email is required.', 'learn' );
		}
		if ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Password must be at least 8 characters.', 'learn' );
		}
		if ( username_exists( $username ) ) {
			$errors[] = __( 'Username already taken.', 'learn' );
		}
		if ( email_exists( $email ) ) {
			$errors[] = __( 'Email already registered.', 'learn' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'errors' => $errors ), 422 );
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'errors' => array( $user_id->get_error_message() ) ), 500 );
		}

		if ( $display_name ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
		}

		// Create subsite.
		$site_name = sanitize_title( $username );
		$site_title = $display_name ? $display_name . "'s Learning Site" : $username . "'s Learning Site";
		$main_site_id = get_main_site_id();
		$network = get_network();
		$domain = $network->domain;
		$path   = $network->path . $site_name . '/';

		$blog_id = wpmu_create_blog( $domain, $path, $site_title, $user_id );
		if ( is_wp_error( $blog_id ) ) {
			wp_send_json_error( array( 'errors' => array( $blog_id->get_error_message() ) ), 500 );
		}

		// Store learner blog ID on user meta.
		update_user_meta( $user_id, '_1111_learner_blog_id', $blog_id );
		update_user_meta( $user_id, '_1111_learner_registered', gmdate( 'c' ) );

		// Ensure learn CPT and agent user are set up on the new site.
		switch_to_blog( $blog_id );
		Learn_Post_Type::register();
		flush_rewrite_rules();
		Learn_Agent_User::create();
		restore_current_blog();

		// Auto-login.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );

		$site_url = get_home_url( $blog_id );

		wp_send_json_success( array(
			'message'  => __( 'Account created! Redirecting to your learning site...', 'learn' ),
			'site_url' => $site_url . '/wp-admin/',
			'blog_id'  => $blog_id,
		) );
	}
}
