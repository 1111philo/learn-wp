<?php
/**
 * JSON parsing helpers for model output.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_JSON_Parser {
	/**
	 * Parse model output.
	 *
	 * @param string $text Raw model text.
	 * @return array|WP_Error
	 */
	public static function parse( $text ) {
		$text = trim( (string) $text );

		if ( preg_match( '/```(?:json)?\s*(\{.*\}|\[.*\])\s*```/is', $text, $matches ) ) {
			$text = $matches[1];
		}

		if ( preg_match( '/(\{(?:[^{}]|(?R))*\}|\[(?:[^\[\]]|(?R))*\])$/s', $text, $matches ) ) {
			$decoded = json_decode( $matches[1], true );
		} else {
			$decoded = json_decode( $text, true );
		}

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'learn_invalid_json', __( 'Model output is not valid JSON.', 'learn-wp' ), array( 'raw' => $text ) );
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'learn_invalid_json_shape', __( 'Model JSON output must decode to an array/object.', 'learn-wp' ) );
		}

		return $decoded;
	}
}
