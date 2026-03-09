<?php
/**
 * Anthropic API HTTP client.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_API_Client
 *
 * Handles communication with the Anthropic Messages API via wp_remote_post().
 */
class Learn_API_Client {

	/**
	 * Anthropic Messages API endpoint.
	 *
	 * @var string
	 */
	const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API version header.
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
	 * Send a message to the Anthropic API.
	 *
	 * @param string $system_prompt System prompt content.
	 * @param string $user_message  User message content.
	 * @param string $model         Model identifier (LEARN_FAST_MODEL or LEARN_DEFAULT_MODEL).
	 * @param int    $max_tokens    Maximum tokens for the response.
	 * @return array|WP_Error Parsed response array or WP_Error on failure.
	 */
	public static function send_message( $system_prompt, $user_message, $model, $max_tokens ) {
		$api_key = Learn_Settings::get_api_key();
		if ( ! $api_key ) {
			return new WP_Error(
				'learn_no_api_key',
				__( 'Anthropic API key is not configured. Go to Learn > Settings to add your API key.', 'learn' )
			);
		}

		$body = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'system'     => $system_prompt,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $user_message,
				),
			),
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version'  => self::API_VERSION,
					'content-type'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'learn_api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'API request failed: %s', 'learn' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );

		if ( 401 === $status_code ) {
			return new WP_Error(
				'learn_api_unauthorized',
				__( 'Invalid API key. Check your Anthropic API key in Learn > Settings.', 'learn' )
			);
		}

		if ( 429 === $status_code ) {
			return new WP_Error(
				'learn_api_rate_limited',
				__( 'Rate limited by the Anthropic API. Please wait a moment and retry.', 'learn' )
			);
		}

		if ( $status_code >= 500 ) {
			return new WP_Error(
				'learn_api_server_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Anthropic API server error (HTTP %d). Please retry.', 'learn' ),
					$status_code
				)
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'learn_api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body */
					__( 'API error (HTTP %1$d): %2$s', 'learn' ),
					$status_code,
					wp_trim_words( $body_raw, 50 )
				)
			);
		}

		$decoded = json_decode( $body_raw, true );
		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'learn_api_parse_error',
				__( 'Failed to parse API response as JSON.', 'learn' )
			);
		}

		return $decoded;
	}

	/**
	 * Extract text content from an Anthropic Messages API response.
	 *
	 * @param array $response Decoded API response.
	 * @return string|WP_Error Text content or WP_Error if not found.
	 */
	public static function extract_text( $response ) {
		if ( ! isset( $response['content'] ) || ! is_array( $response['content'] ) ) {
			return new WP_Error(
				'learn_api_no_content',
				__( 'API response missing content field.', 'learn' )
			);
		}

		foreach ( $response['content'] as $block ) {
			if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
				return $block['text'];
			}
		}

		return new WP_Error(
			'learn_api_no_text',
			__( 'API response contained no text content.', 'learn' )
		);
	}

	/**
	 * Extract JSON from an API response text.
	 *
	 * Handles cases where the model wraps JSON in markdown code fences.
	 *
	 * @param string $text Raw text from the API response.
	 * @return array|WP_Error Decoded JSON array or WP_Error on failure.
	 */
	public static function extract_json( $text ) {
		$text = trim( $text );

		// Try direct JSON parse first.
		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Strip markdown code fences.
		if ( preg_match( '/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $text, $matches ) ) {
			$decoded = json_decode( trim( $matches[1] ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		// Try to find JSON object in the text.
		if ( preg_match( '/\{[\s\S]*\}/s', $text, $matches ) ) {
			$decoded = json_decode( $matches[0], true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return new WP_Error(
			'learn_json_parse_error',
			__( 'Failed to parse agent output as JSON.', 'learn' ),
			array( 'raw_text' => substr( $text, 0, 500 ) )
		);
	}

	/**
	 * Call an agent: send a message, extract text, parse JSON.
	 *
	 * @param string $system_prompt System prompt content.
	 * @param string $user_message  User message content.
	 * @param string $model         Model identifier.
	 * @param int    $max_tokens    Maximum tokens.
	 * @return array|WP_Error Parsed JSON output or WP_Error.
	 */
	public static function call_agent( $system_prompt, $user_message, $model, $max_tokens ) {
		$response = self::send_message( $system_prompt, $user_message, $model, $max_tokens );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = self::extract_text( $response );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		return self::extract_json( $text );
	}
}
