<?php
/**
 * Plugin Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Settings
{

    /**
     * Instance of this class
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
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register settings page
     */
    public function register_settings_page()
    {
        if (!is_main_site() || !is_super_admin()) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=learn',
            __('Settings', '1111-learn'),
            __('Settings', '1111-learn'),
            'manage_network',
            'learn-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('1111_learn_settings', '1111_learn_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'encrypt_api_key'),
        ));

        register_setting('1111_learn_settings', '1111_learn_telemetry_enabled', array(
            'type' => 'boolean',
            'default' => false,
        ));
    }

    /**
     * Encrypt API key before saving
     */
    public function encrypt_api_key($key)
    {
        if (empty($key)) {
            return '';
        }
        // Basic encryption using AUTH_KEY as salt
        // In a real product-ready plugin, we might use something more robust if available
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'default_salt';
        return base64_encode(openssl_encrypt($key, 'aes-256-cbc', substr(hash('sha256', $salt), 0, 32), 0, substr(hash('sha256', $salt), 32, 16)));
    }

    /**
     * Decrypt API key for use
     */
    public function get_api_key()
    {
        $encrypted_key = get_option('1111_learn_api_key');
        if (empty($encrypted_key)) {
            return '';
        }
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'default_salt';
        return openssl_decrypt(base64_decode($encrypted_key), 'aes-256-cbc', substr(hash('sha256', $salt), 0, 32), 0, substr(hash('sha256', $salt), 32, 16));
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Learn Settings', '1111-learn'); ?>
            </h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('1111_learn_settings');
                do_settings_sections('1111_learn_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Anthropic API Key', '1111-learn'); ?>
                        </th>
                        <td>
                            <input type="password" name="1111_learn_api_key"
                                value="<?php echo esc_attr($this->get_api_key()); ?>" class="large-text"
                                placeholder="sk-ant-..." />
                            <p class="description">
                                <?php _e('Your Anthropic API key is stored encrypted.', '1111-learn'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Share Data with 11:11 Philosopher\'s Group', '1111-learn'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="1111_learn_telemetry_enabled" value="1" <?php checked(get_option('1111_learn_telemetry_enabled'), 1); ?> />
                            <p class="description">
                                <?php _e('Enable anonymous telemetry to help us improve the agent prompts.', '1111-learn'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
