<?php
/**
 * Settings page for the Learn plugin.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Settings
 *
 * Handles the settings page with API key storage and data sharing opt-in.
 */
class Learn_Settings {

	/**
	 * Option name for the encrypted API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = '1111_learn_api_key';

	/**
	 * Option name for telemetry opt-in.
	 *
	 * @var string
	 */
	const TELEMETRY_OPTION = '1111_learn_telemetry_enabled';

	/**
	 * Option name for telemetry consent timestamp.
	 *
	 * @var string
	 */
	const TELEMETRY_CONSENT_OPTION = '1111_learn_telemetry_consent_at';

	/**
	 * Initialize settings — register settings fields.
	 */
	public static function init() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			return;
		}

		register_setting(
			'learn_settings',
			self::API_KEY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
			)
		);

		register_setting(
			'learn_settings',
			self::TELEMETRY_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( __CLASS__, 'sanitize_telemetry' ),
				'default'           => false,
			)
		);

		add_settings_section(
			'learn_api_section',
			__( 'API Configuration', 'learn' ),
			array( __CLASS__, 'render_api_section' ),
			'learn-settings'
		);

		add_settings_field(
			'learn_api_key',
			__( 'Anthropic API Key', 'learn' ),
			array( __CLASS__, 'render_api_key_field' ),
			'learn-settings',
			'learn_api_section'
		);

		add_settings_section(
			'learn_data_section',
			__( 'Data Sharing', 'learn' ),
			array( __CLASS__, 'render_data_section' ),
			'learn-settings'
		);

		add_settings_field(
			'learn_telemetry',
			__( 'Share Data with 11:11 Philosopher\'s Group', 'learn' ),
			array( __CLASS__, 'render_telemetry_field' ),
			'learn-settings',
			'learn_data_section'
		);
	}

	/**
	 * Render the API configuration section description.
	 */
	public static function render_api_section() {
		echo '<p>' . esc_html__( 'Enter your Anthropic API key to enable AI-powered course generation.', 'learn' ) . '</p>';
	}

	/**
	 * Render the API key input field.
	 */
	public static function render_api_key_field() {
		$has_key = ! empty( get_option( self::API_KEY_OPTION ) );
		$placeholder = $has_key ? '••••••••••••••••' : '';
		?>
		<input
			type="password"
			id="learn_api_key"
			name="<?php echo esc_attr( self::API_KEY_OPTION ); ?>"
			value=""
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			class="regular-text"
			autocomplete="off"
		/>
		<?php if ( $has_key ) : ?>
			<p class="description">
				<?php esc_html_e( 'An API key is saved. Enter a new key to replace it, or leave blank to keep the current key.', 'learn' ); ?>
			</p>
		<?php else : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: URL to Anthropic console */
					esc_html__( 'Get your API key from the %s.', 'learn' ),
					'<a href="https://console.anthropic.com/" target="_blank" rel="noopener">' . esc_html__( 'Anthropic Console', 'learn' ) . '</a>'
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the data sharing section description.
	 */
	public static function render_data_section() {
		echo '<p>' . esc_html__( 'Help improve Learn by sharing anonymous usage data with 11:11 Philosopher\'s Group.', 'learn' ) . '</p>';
	}

	/**
	 * Render the telemetry checkbox field.
	 */
	public static function render_telemetry_field() {
		$enabled = get_option( self::TELEMETRY_OPTION, false );
		?>
		<label for="learn_telemetry">
			<input
				type="checkbox"
				id="learn_telemetry"
				name="<?php echo esc_attr( self::TELEMETRY_OPTION ); ?>"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Share anonymous usage data to help improve course generation quality.', 'learn' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'Data includes generation events and error rates. API keys, course content, and personal information are never shared.', 'learn' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize the API key input — encrypt before storing.
	 *
	 * @param string $value The submitted API key.
	 * @return string Encrypted API key or existing value if blank.
	 */
	public static function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		// If blank, keep the existing key.
		if ( empty( $value ) ) {
			return get_option( self::API_KEY_OPTION, '' );
		}

		// Validate format: Anthropic keys start with "sk-ant-".
		if ( strpos( $value, 'sk-ant-' ) !== 0 ) {
			add_settings_error(
				self::API_KEY_OPTION,
				'invalid_api_key',
				__( 'Invalid API key format. Anthropic API keys start with "sk-ant-".', 'learn' ),
				'error'
			);
			return get_option( self::API_KEY_OPTION, '' );
		}

		return self::encrypt( $value );
	}

	/**
	 * Sanitize the telemetry opt-in. Record consent timestamp on enable.
	 *
	 * @param mixed $value The submitted value.
	 * @return bool
	 */
	public static function sanitize_telemetry( $value ) {
		$enabled = (bool) $value;

		if ( $enabled && ! get_option( self::TELEMETRY_OPTION, false ) ) {
			update_option( self::TELEMETRY_CONSENT_OPTION, gmdate( 'c' ) );
		}

		return $enabled;
	}

	/**
	 * Get the decrypted API key.
	 *
	 * @return string|false Decrypted API key or false if not set.
	 */
	public static function get_api_key() {
		$encrypted = get_option( self::API_KEY_OPTION, '' );
		if ( empty( $encrypted ) ) {
			return false;
		}
		return self::decrypt( $encrypted );
	}

	/**
	 * Encrypt a value using WordPress salts.
	 *
	 * @param string $value Value to encrypt.
	 * @return string Base64-encoded encrypted value.
	 */
	private static function encrypt( $value ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback: base64 encode with a marker (not truly secure, but functional).
			return 'b64:' . base64_encode( $value );
		}

		$key    = self::get_encryption_key();
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return 'b64:' . base64_encode( $value );
		}

		return 'enc:' . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $value Encrypted value.
	 * @return string|false Decrypted value or false on failure.
	 */
	private static function decrypt( $value ) {
		if ( strpos( $value, 'b64:' ) === 0 ) {
			return base64_decode( substr( $value, 4 ) );
		}

		if ( strpos( $value, 'enc:' ) !== 0 ) {
			// Legacy: unencrypted value.
			return $value;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		$raw = base64_decode( substr( $value, 4 ) );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return false;
		}

		$key    = self::get_encryption_key();
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );

		$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return ( false !== $decrypted ) ? $decrypted : false;
	}

	/**
	 * Get the encryption key derived from WordPress salts.
	 *
	 * @return string 32-byte encryption key.
	 */
	private static function get_encryption_key() {
		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'learn-default-key';
		$salt .= defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '';
		return hash( 'sha256', $salt, true );
	}
}
