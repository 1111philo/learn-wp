<?php
/**
 * Anthropic API client.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_API_Client {
	/**
	 * Endpoint URL.
	 */
	const ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/**
	 * Make a model call.
	 *
	 * @param string $model Model ID.
	 * @param string $system System prompt.
	 * @param string $user User prompt.
	 * @param int    $max_tokens Max token budget.
	 * @return string|WP_Error
	 */
	public function complete( $model, $system, $user, $max_tokens = 4000 ) {
		$api_key = Learn_Settings::instance()->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'learn_missing_api_key', __( 'Anthropic API key is not configured.', 'learn-wp' ) );
		}

		$body = wp_json_encode(
			array(
				'model'      => $model,
				'max_tokens' => absint( $max_tokens ),
				'system'     => $system,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => $user,
					),
				),
			)
		);

		$attempts = 0;
		do {
			++$attempts;
			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'timeout' => 60,
					'headers' => array(
						'Content-Type'      => 'application/json',
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
					),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				if ( $attempts < 3 ) {
					sleep( 1 );
					continue;
				}
				return $response;
			}

			$status = wp_remote_retrieve_response_code( $response );
			$raw    = wp_remote_retrieve_body( $response );
			$json   = json_decode( $raw, true );

			if ( $status >= 200 && $status < 300 && isset( $json['content'][0]['text'] ) ) {
				return (string) $json['content'][0]['text'];
			}

			if ( $attempts < 3 && $status >= 500 ) {
				sleep( 1 );
				continue;
			}

			return new WP_Error( 'learn_api_error', 'Anthropic API request failed.', array( 'status' => $status, 'body' => $raw ) );
		} while ( $attempts < 3 );

		return new WP_Error( 'learn_api_error', 'Anthropic API request exhausted retries.' );
	}
}
