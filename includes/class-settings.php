<?php
/**
 * Plugin settings management.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin settings: API key (encrypted) and telemetry opt-in.
 */
class Learn_Settings {

	/**
	 * Option name for the encrypted API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION = '1111_learn_api_key';

	/**
	 * Option name for the telemetry toggle.
	 *
	 * @var string
	 */
	const TELEMETRY_OPTION = '1111_learn_telemetry_enabled';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = '1111-learn-settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = '1111_learn_settings';

	/**
	 * Settings section ID.
	 *
	 * @var string
	 */
	const SECTION_ID = '1111_learn_settings_section';

	/**
	 * Initialize hooks.
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the settings submenu page.
	 *
	 * Called by Learn_Admin_Page to add the submenu item.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'1111-learn',
			__( 'Learn Settings', '1111-learn' ),
			__( 'Settings', '1111-learn' ),
			'manage_network',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings, sections, and fields via the Settings API.
	 */
	public function register_settings() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			return;
		}

		register_setting(
			self::SETTINGS_GROUP,
			self::API_KEY_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::TELEMETRY_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_telemetry' ),
				'default'           => false,
			)
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'General Settings', '1111-learn' ),
			array( $this, 'render_section_description' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'1111_learn_api_key_field',
			__( 'Anthropic API Key', '1111-learn' ),
			array( $this, 'render_api_key_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);

		add_settings_field(
			'1111_learn_telemetry_field',
			__( 'Share Data', '1111-learn' ),
			array( $this, 'render_telemetry_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID
		);
	}

	/**
	 * Sanitize the API key before saving. Encrypts the value.
	 *
	 * @param string $value Raw API key input.
	 * @return string Encrypted API key, or existing value if input is empty.
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );

		// If the field is empty, keep the existing value.
		if ( empty( $value ) ) {
			return get_option( self::API_KEY_OPTION, '' );
		}

		return $this->encrypt( $value );
	}

	/**
	 * Sanitize the telemetry checkbox value.
	 *
	 * @param mixed $value Checkbox value.
	 * @return bool
	 */
	public function sanitize_telemetry( $value ) {
		return (bool) $value;
	}

	/**
	 * Get the decrypted API key.
	 *
	 * @return string Decrypted API key, or empty string on failure.
	 */
	public function get_api_key() {
		$encrypted = get_option( self::API_KEY_OPTION, '' );

		if ( empty( $encrypted ) ) {
			return '';
		}

		return $this->decrypt( $encrypted );
	}

	/**
	 * Check whether telemetry is enabled.
	 *
	 * @return bool
	 */
	public function is_telemetry_enabled() {
		return (bool) get_option( self::TELEMETRY_OPTION, false );
	}

	/**
	 * Encrypt a plaintext string.
	 *
	 * Uses sodium_crypto_secretbox when available, otherwise falls back
	 * to AUTH_KEY-based XOR with base64 encoding.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Encrypted and encoded string.
	 */
	private function encrypt( $plaintext ) {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			return $this->sodium_encrypt( $plaintext );
		}

		return $this->xor_encrypt( $plaintext );
	}

	/**
	 * Decrypt an encrypted string.
	 *
	 * @param string $encrypted The encrypted value.
	 * @return string Decrypted plaintext, or empty string on failure.
	 */
	private function decrypt( $encrypted ) {
		if ( function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$result = $this->sodium_decrypt( $encrypted );
			if ( false !== $result ) {
				return $result;
			}
		}

		return $this->xor_decrypt( $encrypted );
	}

	/**
	 * Encrypt using sodium_crypto_secretbox.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded nonce + ciphertext.
	 */
	private function sodium_encrypt( $plaintext ) {
		$key   = $this->derive_sodium_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.sodium_crypto_secretboxFound
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );

		return 'sodium:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt using sodium_crypto_secretbox_open.
	 *
	 * @param string $encrypted Base64-encoded nonce + ciphertext with sodium: prefix.
	 * @return string|false Decrypted plaintext or false on failure.
	 */
	private function sodium_decrypt( $encrypted ) {
		if ( 0 !== strpos( $encrypted, 'sodium:' ) ) {
			return false;
		}

		$encrypted = substr( $encrypted, 7 );
		$decoded   = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return false;
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;

		if ( strlen( $decoded ) < $nonce_length ) {
			return false;
		}

		$nonce  = substr( $decoded, 0, $nonce_length );
		$cipher = substr( $decoded, $nonce_length );
		$key    = $this->derive_sodium_key();

		// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.sodium_crypto_secretbox_openFound
		$result = sodium_crypto_secretbox_open( $cipher, $nonce, $key );

		return ( false === $result ) ? false : $result;
	}

	/**
	 * Derive a 32-byte key from AUTH_KEY for sodium.
	 *
	 * @return string 32-byte key.
	 */
	private function derive_sodium_key() {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'learn-default-key';
		return sodium_crypto_generichash( $auth_key, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Simple XOR encryption with AUTH_KEY, base64-encoded.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded result with xor: prefix.
	 */
	private function xor_encrypt( $plaintext ) {
		$key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'learn-default-key';
		$result = '';

		for ( $i = 0, $len = strlen( $plaintext ); $i < $len; $i++ ) {
			$result .= $plaintext[ $i ] ^ $key[ $i % strlen( $key ) ];
		}

		return 'xor:' . base64_encode( $result ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a XOR-encrypted string.
	 *
	 * @param string $encrypted Base64-encoded XOR result with xor: prefix.
	 * @return string Decrypted plaintext.
	 */
	private function xor_decrypt( $encrypted ) {
		if ( 0 !== strpos( $encrypted, 'xor:' ) ) {
			return '';
		}

		$encrypted = substr( $encrypted, 4 );
		$decoded   = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			return '';
		}

		$key    = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'learn-default-key';
		$result = '';

		for ( $i = 0, $len = strlen( $decoded ); $i < $len; $i++ ) {
			$result .= $decoded[ $i ] ^ $key[ $i % strlen( $key ) ];
		}

		return $result;
	}

	/**
	 * Render the settings section description.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Configure the Anthropic API key and data sharing preferences for the Learn plugin.', '1111-learn' ) . '</p>';
	}

	/**
	 * Render the API key field.
	 */
	public function render_api_key_field() {
		$has_key = ! empty( get_option( self::API_KEY_OPTION, '' ) );
		?>
		<input
			type="password"
			id="1111_learn_api_key"
			name="<?php echo esc_attr( self::API_KEY_OPTION ); ?>"
			value=""
			class="regular-text"
			autocomplete="off"
			aria-describedby="1111-learn-api-key-description"
		/>
		<p class="description" id="1111-learn-api-key-description">
			<?php if ( $has_key ) : ?>
				<?php esc_html_e( 'An API key is saved. Enter a new key to replace it, or leave blank to keep the current key.', '1111-learn' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Enter your Anthropic API key. It will be stored encrypted.', '1111-learn' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render the telemetry checkbox field.
	 */
	public function render_telemetry_field() {
		$enabled = $this->is_telemetry_enabled();
		?>
		<label for="1111_learn_telemetry_enabled">
			<input
				type="checkbox"
				id="1111_learn_telemetry_enabled"
				name="<?php echo esc_attr( self::TELEMETRY_OPTION ); ?>"
				value="1"
				<?php checked( $enabled ); ?>
				aria-describedby="1111-learn-telemetry-description"
			/>
			<?php esc_html_e( 'Share anonymous usage data', '1111-learn' ); ?>
		</label>
		<p class="description" id="1111-learn-telemetry-description">
			<?php esc_html_e( 'When enabled, anonymous usage data is sent to help improve the plugin. No personal information is collected.', '1111-learn' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the full settings page using the view template.
	 */
	public function render_settings_page() {
		if ( ! is_main_site() || ! is_super_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', '1111-learn' ) );
		}

		include plugin_dir_path( __DIR__ ) . 'admin/views/settings.php';
	}
}
