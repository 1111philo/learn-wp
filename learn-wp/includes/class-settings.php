<?php
/**
 * Network settings.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Settings {
	/**
	 * Singleton instance.
	 *
	 * @var Learn_Settings|null
	 */
	private static $instance = null;

	/**
	 * Option keys.
	 */
	const OPTION_API_KEY   = '_1111_learn_api_key_encrypted';
	const OPTION_TELEMETRY = '_1111_learn_telemetry_opt_in';
	const OPTION_KEY_SALT  = '_1111_learn_encryption_salt';

	/**
	 * Returns singleton.
	 *
	 * @return Learn_Settings
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'network_admin_menu', array( $this, 'register_menu' ) );
		add_action( 'network_admin_edit_1111_learn_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Register network settings page.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Learn Settings', 'learn-wp' ),
			__( 'Learn', 'learn-wp' ),
			'manage_network_options',
			'learn-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * Render settings form.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$telemetry = (bool) get_site_option( self::OPTION_TELEMETRY, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Learn Settings', 'learn-wp' ); ?></h1>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=1111_learn_save_settings' ) ); ?>">
				<?php wp_nonce_field( '1111_learn_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="learn_api_key"><?php esc_html_e( 'Anthropic API Key', 'learn-wp' ); ?></label></th>
						<td>
							<input name="learn_api_key" id="learn_api_key" type="password" class="regular-text" autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Stored encrypted in network options. Leave blank to keep current key.', 'learn-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Data Sharing (Telemetry)', 'learn-wp' ); ?></th>
						<td>
							<label for="learn_telemetry_opt_in">
								<input name="learn_telemetry_opt_in" id="learn_telemetry_opt_in" type="checkbox" value="1" <?php checked( $telemetry ); ?> />
								<?php esc_html_e( 'Share anonymous operational telemetry to improve prompts.', 'learn-wp' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'learn-wp' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings handler.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'learn-wp' ) );
		}

		check_admin_referer( '1111_learn_save_settings' );

		if ( isset( $_POST['learn_telemetry_opt_in'] ) ) {
			update_site_option( self::OPTION_TELEMETRY, true );
		} else {
			update_site_option( self::OPTION_TELEMETRY, false );
		}

		if ( ! empty( $_POST['learn_api_key'] ) ) {
			$api_key = sanitize_text_field( wp_unslash( $_POST['learn_api_key'] ) );
			$stored  = $this->encrypt_secret( $api_key );
			if ( $stored ) {
				update_site_option( self::OPTION_API_KEY, $stored );
			}
		}

		wp_safe_redirect( network_admin_url( 'settings.php?page=learn-settings&updated=true' ) );
		exit;
	}

	/**
	 * Get decrypted API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		$encrypted = (string) get_site_option( self::OPTION_API_KEY, '' );
		if ( '' === $encrypted ) {
			return '';
		}

		return $this->decrypt_secret( $encrypted );
	}

	/**
	 * Encrypts a secret at rest.
	 *
	 * @param string $plaintext Secret.
	 * @return string
	 */
	private function encrypt_secret( $plaintext ) {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return base64_encode( $plaintext );
		}

		$key   = $this->get_encryption_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return base64_encode( $nonce . $cipher );
	}

	/**
	 * Decrypts the stored secret.
	 *
	 * @param string $ciphertext Ciphertext.
	 * @return string
	 */
	private function decrypt_secret( $ciphertext ) {
		$raw = base64_decode( $ciphertext, true );
		if ( false === $raw ) {
			return '';
		}

		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return (string) $raw;
		}

		$key   = $this->get_encryption_key();
		$nonce = mb_substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit' );
		$cipher = mb_substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit' );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		return false === $plain ? '' : $plain;
	}

	/**
	 * Build stable encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$salt = get_site_option( self::OPTION_KEY_SALT, '' );
		if ( '' === $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_site_option( self::OPTION_KEY_SALT, $salt );
		}

		return hash( 'sha256', wp_salt( 'auth' ) . $salt, true );
	}
}
