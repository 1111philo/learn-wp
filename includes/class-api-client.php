<?php
/**
 * Anthropic API Client
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_API_Client
{

    /**
     * Instance of this class
     */
    private static $instance;

    /**
     * API Endpoint
     */
    private $endpoint = 'https://api.anthropic.com/v1/messages';

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
    }

    /**
     * Send a message to Anthropic
     *
     * @param string $system_prompt The system prompt.
     * @param string $user_message  The user message.
     * @param string $model         The model to use.
     * @param int    $max_tokens    Max tokens to generate.
     * @return array|WP_Error       Response array or WP_Error.
     */
    public function send_message($system_prompt, $user_message, $model, $max_tokens = 2048)
    {
        $api_key = Learn_Settings::get_instance()->get_api_key();

        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('Anthropic API key is missing. Please set it in the Learn settings.', '1111-learn'));
        }

        $body = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'system' => $system_prompt,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $user_message,
                ),
            ),
        );

        $response = wp_remote_post($this->endpoint, array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (200 !== $status_code) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : __('Unknown API error.', '1111-learn');
            return new WP_Error('api_error_' . $status_code, $error_message, $data);
        }

        if (empty($data['content'][0]['text'])) {
            return new WP_Error('empty_response', __('The API returned an empty response.', '1111-learn'));
        }

        return $data['content'][0]['text'];
    }
}
