<?php
/**
 * Base class for prompt-driven agents.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Learn_Agent_Base {
	/**
	 * API client.
	 *
	 * @var Learn_API_Client
	 */
	protected $client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new Learn_API_Client();
	}

	/**
	 * Returns prompt slug.
	 *
	 * @return string
	 */
	abstract protected function prompt_slug();

	/**
	 * Returns validator slug.
	 *
	 * @return string
	 */
	abstract protected function validator_slug();

	/**
	 * Returns model.
	 *
	 * @return string
	 */
	protected function model() {
		return 'claude-sonnet-4-6';
	}

	/**
	 * Execute the agent.
	 *
	 * @param array $context Agent context.
	 * @return array|WP_Error
	 */
	public function run( $context ) {
		$system = Learn_Prompt_Loader::load( $this->prompt_slug() );
		if ( is_wp_error( $system ) ) {
			return $system;
		}

		$user = wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( false === $user ) {
			$user = '{}';
		}

		$response = $this->client->complete( $this->model(), $system, $user );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = Learn_JSON_Parser::parse( $response );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$valid = Learn_Validator::validate( $this->validator_slug(), $parsed );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		return $parsed;
	}
}
