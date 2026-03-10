<?php
/**
 * Anthropic Messages API client.
 *
 * @package Jesuspended\Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Communicates with the Anthropic Messages API via wp_remote_post().
 */
class Learn_API_Client {

	/**
	 * Anthropic Messages API endpoint.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version header value.
	 *
	 * @var string
	 */
	const API_VERSION = '2023-06-01';

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 120;

	/**
	 * API key retrieved from settings.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * Retrieves the API key from Learn_Settings.
	 */
	public function __construct() {
		$settings      = new Learn_Settings();
		$this->api_key = $settings->get_api_key();
	}

	/**
	 * Send a message to the Anthropic Messages API.
	 *
	 * @param string $model        The model identifier (e.g. claude-sonnet-4-6).
	 * @param string $system_prompt The system prompt.
	 * @param string $user_message  The user message content.
	 * @param int    $max_tokens    Maximum tokens in the response.
	 * @return array|WP_Error Parsed response body on success, WP_Error on failure.
	 */
	public function send_message( $model, $system_prompt, $user_message, $max_tokens ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Anthropic API key is not configured.', '1111-learn' )
			);
		}

		$body = wp_json_encode(
			array(
				'model'      => $model,
				'max_tokens' => (int) $max_tokens,
				'system'     => $system_prompt,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $user_message,
					),
				),
			)
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'x-api-key'         => $this->api_key,
					'anthropic-version'  => self::API_VERSION,
					'content-type'       => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' ) ) {
				return new WP_Error(
					'timeout',
					__( 'The API request timed out.', '1111-learn' )
				);
			}

			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid Anthropic API key.', '1111-learn' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'rate_limited',
				__( 'Anthropic API rate limit exceeded. Please try again later.', '1111-learn' )
			);
		}

		if ( $code >= 500 && $code < 600 ) {
			return new WP_Error(
				'server_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Anthropic API server error (HTTP %d).', '1111-learn' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new WP_Error(
				'parse_error',
				__( 'Unable to parse the API response as JSON.', '1111-learn' )
			);
		}

		return $data;
	}

	/**
	 * Extract the text content from an Anthropic Messages API response.
	 *
	 * @param array $response Parsed response body from send_message().
	 * @return string|WP_Error The text content, or WP_Error if not found.
	 */
	public function extract_text( $response ) {
		if ( isset( $response['content'][0]['text'] ) ) {
			return $response['content'][0]['text'];
		}

		return new WP_Error(
			'parse_error',
			__( 'No text content found in the API response.', '1111-learn' )
		);
	}
}
