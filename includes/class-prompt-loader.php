<?php
/**
 * Loads agent system prompts from Markdown files.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Prompt_Loader
 *
 * Reads prompt files from the prompts/ directory at runtime.
 */
class Learn_Prompt_Loader {

	/**
	 * Cache of loaded prompts.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Load a prompt file by agent name.
	 *
	 * @param string $agent_name Agent name matching the filename (e.g., 'course-describer').
	 * @return string|WP_Error Prompt contents or WP_Error if file not found.
	 */
	public static function load( $agent_name ) {
		if ( isset( self::$cache[ $agent_name ] ) ) {
			return self::$cache[ $agent_name ];
		}

		$file = LEARN_PLUGIN_DIR . 'prompts/' . sanitize_file_name( $agent_name ) . '.md';

		if ( ! file_exists( $file ) ) {
			return new WP_Error(
				'learn_prompt_not_found',
				sprintf(
					/* translators: %s: prompt file name */
					__( 'Prompt file not found: %s', 'learn' ),
					$agent_name . '.md'
				)
			);
		}

		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return new WP_Error(
				'learn_prompt_read_error',
				sprintf(
					/* translators: %s: prompt file name */
					__( 'Failed to read prompt file: %s', 'learn' ),
					$agent_name . '.md'
				)
			);
		}

		self::$cache[ $agent_name ] = $contents;
		return $contents;
	}

	/**
	 * Get the hash of a prompt file (for telemetry).
	 *
	 * @param string $agent_name Agent name.
	 * @return string|false SHA-256 hash or false on failure.
	 */
	public static function get_hash( $agent_name ) {
		$contents = self::load( $agent_name );
		if ( is_wp_error( $contents ) ) {
			return false;
		}
		return hash( 'sha256', $contents );
	}

	/**
	 * Clear the prompt cache.
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
