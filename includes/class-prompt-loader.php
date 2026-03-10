<?php
/**
 * Prompt file loader.
 *
 * @package Jesuspended\Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads agent system prompts from Markdown files in the prompts/ directory.
 */
class Learn_Prompt_Loader {

	/**
	 * Static cache of loaded prompt contents keyed by prompt name.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Load a prompt file by name.
	 *
	 * Reads from `LEARN_PLUGIN_DIR . 'prompts/' . $prompt_name . '.md'` and
	 * caches the result to avoid repeated filesystem reads.
	 *
	 * @param string $prompt_name The prompt name (e.g. 'course-describer').
	 * @return string|WP_Error The prompt file contents, or WP_Error if the file does not exist.
	 */
	public function load( $prompt_name ) {
		if ( isset( self::$cache[ $prompt_name ] ) ) {
			return self::$cache[ $prompt_name ];
		}

		$file_path = LEARN_PLUGIN_DIR . 'prompts/' . $prompt_name . '.md';

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'prompt_not_found',
				/* translators: %s: prompt file name */
				sprintf( __( 'Prompt file not found: %s', '1111-learn' ), $prompt_name . '.md' )
			);
		}

		$contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents ) {
			return new WP_Error(
				'prompt_read_error',
				/* translators: %s: prompt file name */
				sprintf( __( 'Unable to read prompt file: %s', '1111-learn' ), $prompt_name . '.md' )
			);
		}

		self::$cache[ $prompt_name ] = $contents;

		return $contents;
	}

	/**
	 * Get the SHA-256 hash of a prompt file's contents.
	 *
	 * Useful for telemetry to track which prompt version was used without
	 * transmitting the full prompt text.
	 *
	 * @param string $prompt_name The prompt name (e.g. 'course-describer').
	 * @return string|WP_Error The SHA-256 hex hash, or WP_Error if the file cannot be loaded.
	 */
	public function get_hash( $prompt_name ) {
		$contents = $this->load( $prompt_name );

		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		return hash( 'sha256', $contents );
	}
}
