<?php
/**
 * Anonymous telemetry collection and transmission.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles anonymous telemetry collection and transmission to learn-service.
 */
class Learn_Telemetry {

	/**
	 * Service URL for telemetry backend.
	 *
	 * @var string
	 */
	const SERVICE_URL = 'https://learn-service.1111philo.com';

	/**
	 * Option key for the service credential (API key).
	 *
	 * @var string
	 */
	const CREDENTIAL_OPTION = '1111_learn_service_credential';

	/**
	 * Option key for the anonymous ID.
	 *
	 * @var string
	 */
	const ANONYMOUS_ID_OPTION = '1111_learn_anonymous_id';

	/**
	 * Transient key for the event buffer.
	 *
	 * @var string
	 */
	const BUFFER_TRANSIENT = '_1111_learn_telemetry_buffer';

	// Event type constants.
	const COURSE_STARTED              = 'course_started';
	const AGENT_REQUEST               = 'agent_request';
	const AGENT_RESPONSE              = 'agent_response';
	const VALIDATION_FAILURE          = 'validation_failure';
	const RETRY_OUTCOME               = 'retry_outcome';
	const COURSE_COMPLETED            = 'course_completed';
	const COURSE_FAILED               = 'course_failed';
	const FEEDBACK_SUBMITTED          = 'feedback_submitted';
	const CONTENT_REGENERATED         = 'content_regenerated';
	const SUBMISSION_ASSESSED         = 'submission_assessed';
	const LEARNER_REGISTERED          = 'learner_registered';
	const COURSE_ENROLLED             = 'course_enrolled';
	const LEARNER_FEEDBACK_SUBMITTED  = 'learner_feedback_submitted';
	const LEARNER_CONTENT_REGENERATED = 'learner_content_regenerated';

	/**
	 * Buffer of events to send.
	 *
	 * @var array
	 */
	private $buffer = array();

	/**
	 * Constructor. Loads existing buffer from transient.
	 */
	public function __construct() {
		$stored = get_transient( self::BUFFER_TRANSIENT );

		if ( is_array( $stored ) ) {
			$this->buffer = $stored;
		}
	}

	/**
	 * Check whether telemetry is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) get_option( '1111_learn_telemetry_enabled', false );
	}

	/**
	 * Record a telemetry event.
	 *
	 * @param string $event_type One of the event type constants.
	 * @param array  $data       Event data (will be stripped of sensitive fields).
	 */
	public function record( $event_type, $data = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$anonymous_id = get_option( self::ANONYMOUS_ID_OPTION, '' );

		$this->buffer[] = array(
			'event_type'   => sanitize_key( $event_type ),
			'data'         => $this->strip_sensitive( $data ),
			'timestamp'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'anonymous_id' => $anonymous_id,
		);

		set_transient( self::BUFFER_TRANSIENT, $this->buffer, HOUR_IN_SECONDS );
	}

	/**
	 * Flush buffered events to the telemetry service.
	 *
	 * Fire and forget: does not block on failure.
	 */
	public function flush() {
		if ( ! $this->is_enabled() || empty( $this->buffer ) ) {
			return;
		}

		$credential = get_option( self::CREDENTIAL_OPTION, '' );

		if ( empty( $credential ) ) {
			$credential = $this->register();

			if ( empty( $credential ) ) {
				return;
			}
		}

		$response = wp_remote_post(
			self::SERVICE_URL . '/v1/events',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $credential,
				),
				'body'     => wp_json_encode(
					array(
						'events' => $this->buffer,
					)
				),
			)
		);

		// Clear buffer on send attempt (fire and forget).
		if ( ! is_wp_error( $response ) ) {
			$this->buffer = array();
			delete_transient( self::BUFFER_TRANSIENT );
		}
	}

	/**
	 * Register with the telemetry service and store credentials.
	 *
	 * @return string The service credential (API key), or empty string on failure.
	 */
	private function register() {
		$anonymous_id = 'wp_' . bin2hex( random_bytes( 8 ) );

		$response = wp_remote_post(
			self::SERVICE_URL . '/v1/auth/register',
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'anonymous_id' => $anonymous_id,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status && 201 !== $status ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['api_key'] ) ) {
			return '';
		}

		$credential = $body['api_key'];

		// Store encrypted credential and anonymous ID.
		$encrypted = $this->encrypt( $credential );
		update_option( self::CREDENTIAL_OPTION, $encrypted );
		update_option( self::ANONYMOUS_ID_OPTION, $anonymous_id );

		return $credential;
	}

	/**
	 * Strip sensitive fields from event data.
	 *
	 * Removes any fields that could contain API keys, content text, or PII.
	 * Keeps only structural and metric data.
	 *
	 * @param array $data Raw event data.
	 * @return array Sanitized data with sensitive fields removed.
	 */
	public function strip_sensitive( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$sensitive_keys = array(
			'api_key',
			'api_secret',
			'password',
			'token',
			'secret',
			'credential',
			'content',
			'post_content',
			'body',
			'text',
			'prompt',
			'response_text',
			'email',
			'user_email',
			'name',
			'user_login',
			'display_name',
			'first_name',
			'last_name',
			'ip',
			'ip_address',
			'user_agent',
			'cookie',
			'session',
			'auth',
			'authorization',
			'feedback_text',
			'message',
		);

		$cleaned = array();

		foreach ( $data as $key => $value ) {
			$lower_key = strtolower( $key );

			// Skip sensitive keys.
			$is_sensitive = false;
			foreach ( $sensitive_keys as $sensitive ) {
				if ( false !== strpos( $lower_key, $sensitive ) ) {
					$is_sensitive = true;
					break;
				}
			}

			if ( $is_sensitive ) {
				continue;
			}

			// Recursively strip nested arrays.
			if ( is_array( $value ) ) {
				$cleaned[ $key ] = $this->strip_sensitive( $value );
			} else {
				$cleaned[ $key ] = $value;
			}
		}

		return $cleaned;
	}

	/**
	 * Encrypt a value using AUTH_KEY.
	 *
	 * Uses sodium when available, otherwise falls back to base64 encoding.
	 *
	 * @param string $plaintext The value to encrypt.
	 * @return string Encrypted string.
	 */
	private function encrypt( $plaintext ) {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'learn-default-key';
			$key      = sodium_crypto_generichash( $auth_key, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
			$nonce    = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher   = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			return 'sodium:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return 'b64:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Create custom table for telemetry (placeholder for activation hook).
	 *
	 * Currently telemetry events are buffered in transients and flushed to the
	 * remote service. No local table is required.
	 */
	public static function create_table() {
		// No local table needed. Events are buffered via transients
		// and sent to the remote learn-service.
	}
}
