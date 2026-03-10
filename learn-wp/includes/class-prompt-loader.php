<?php
/**
 * Prompt file loader.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Prompt_Loader {
	/**
	 * Returns prompt text for an agent.
	 *
	 * @param string $slug Prompt slug.
	 * @return string|WP_Error
	 */
	public static function load( $slug ) {
		$slug = sanitize_key( $slug );
		$file = LEARN_WP_PLUGIN_DIR . 'prompts/' . $slug . '.md';

		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'learn_missing_prompt', sprintf( 'Prompt file not found: %s', $slug ) );
		}

		$contents = file_get_contents( $file );
		if ( false === $contents || '' === trim( $contents ) ) {
			return new WP_Error( 'learn_empty_prompt', sprintf( 'Prompt file is empty: %s', $slug ) );
		}

		return $contents;
	}
}
