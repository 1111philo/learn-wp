<?php
/**
 * Learner self-registration and provisioning.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Learner_Registration {
	/**
	 * Singleton.
	 *
	 * @var Learn_Learner_Registration|null
	 */
	private static $instance = null;

	/**
	 * Returns singleton.
	 *
	 * @return Learn_Learner_Registration
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'learn_register', array( $this, 'render_shortcode' ) );
		add_action( 'admin_post_nopriv_1111_learn_register', array( $this, 'handle_registration' ) );
	}

	/**
	 * Renders registration form.
	 *
	 * @return string
	 */
	public function render_shortcode() {
		ob_start();
		?>
		<form class="learn-register-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="1111_learn_register" />
			<?php wp_nonce_field( '1111_learn_register' ); ?>
			<p>
				<label for="learn_reg_username"><?php esc_html_e( 'Username', 'learn-wp' ); ?></label>
				<input id="learn_reg_username" name="username" type="text" required />
			</p>
			<p>
				<label for="learn_reg_email"><?php esc_html_e( 'Email', 'learn-wp' ); ?></label>
				<input id="learn_reg_email" name="email" type="email" required />
			</p>
			<p>
				<label for="learn_reg_password"><?php esc_html_e( 'Password', 'learn-wp' ); ?></label>
				<input id="learn_reg_password" name="password" type="password" required />
			</p>
			<p>
				<button type="submit"><?php esc_html_e( 'Create Learning Site', 'learn-wp' ); ?></button>
			</p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handles learner registration flow.
	 *
	 * @return void
	 */
	public function handle_registration() {
		check_admin_referer( '1111_learn_register' );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' === $username || '' === $email || '' === $password ) {
			wp_die( esc_html__( 'All registration fields are required.', 'learn-wp' ) );
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html( $user_id->get_error_message() ) );
		}

		$network      = get_network();
		$subdomain    = sanitize_title( $username ) . '-' . wp_generate_password( 6, false, false );
		$domain       = is_subdomain_install() ? $subdomain . '.' . preg_replace( '#^https?://#', '', untrailingslashit( network_home_url() ) ) : parse_url( network_home_url(), PHP_URL_HOST );
		$path         = is_subdomain_install() ? '/' : '/' . $subdomain . '/';
		$site_title   = sprintf( __( "%s's Learning Site", 'learn-wp' ), $username );
		$new_blog_id  = wpmu_create_blog( $domain, $path, $site_title, $user_id, array(), $network->id );

		if ( is_wp_error( $new_blog_id ) ) {
			wp_delete_user( $user_id );
			wp_die( esc_html( $new_blog_id->get_error_message() ) );
		}

		add_user_to_blog( $new_blog_id, $user_id, 'administrator' );
		do_action( '1111_learn_learner_registered', $user_id, $new_blog_id );

		wp_safe_redirect( get_admin_url( $new_blog_id ) );
		exit;
	}
}
