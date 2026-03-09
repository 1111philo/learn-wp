<?php
/**
 * Anonymous telemetry for prompt improvement.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Telemetry
 *
 * Collects anonymous telemetry events during course generation and
 * transmits them to the learn-service backend in batches. Opt-in only.
 */
class Learn_Telemetry {

	/**
	 * learn-service events endpoint.
	 *
	 * @var string
	 */
	const SERVICE_URL = 'https://learn-service.1111philo.com';

	/**
	 * Transient key prefix for event buffer.
	 *
	 * @var string
	 */
	const BUFFER_KEY = '_1111_telemetry_buffer';

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( '1111_learn_telemetry_flush', array( __CLASS__, 'flush_events' ) );
	}

	/**
	 * Check if telemetry is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( '1111_learn_telemetry_enabled', false );
	}

	/**
	 * Record a telemetry event.
	 *
	 * @param string $event_type Event type identifier.
	 * @param array  $data       Event data (must not contain PII or content).
	 */
	public static function record( $event_type, $data = array() ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$event = array(
			'event_type'     => $event_type,
			'timestamp'      => gmdate( 'c' ),
			'plugin_version' => LEARN_VERSION,
			'data'           => self::strip_sensitive( $data ),
		);

		$buffer = get_transient( self::BUFFER_KEY );
		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}

		$buffer[] = $event;
		set_transient( self::BUFFER_KEY, $buffer, HOUR_IN_SECONDS );
	}

	/**
	 * Flush buffered events to learn-service.
	 */
	public static function flush_events() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$buffer = get_transient( self::BUFFER_KEY );
		if ( ! is_array( $buffer ) || empty( $buffer ) ) {
			return;
		}

		$api_key = self::get_anonymous_key();
		if ( ! $api_key ) {
			$api_key = self::register_anonymous();
			if ( ! $api_key ) {
				return;
			}
		}

		$response = wp_remote_post(
			self::SERVICE_URL . '/v1/events',
			array(
				'timeout' => 15,
				'headers' => array(
					'content-type'  => 'application/json',
					'authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( array( 'events' => $buffer ) ),
			)
		);

		// Fire and forget — clear buffer regardless of response.
		delete_transient( self::BUFFER_KEY );
	}

	/**
	 * Trigger a flush (called at pipeline completion or failure).
	 */
	public static function schedule_flush() {
		if ( ! self::is_enabled() ) {
			return;
		}

		wp_schedule_single_event( time(), '1111_learn_telemetry_flush' );
		spawn_cron();
	}

	/**
	 * Register an anonymous credential with learn-service.
	 *
	 * @return string|false API key or false.
	 */
	private static function register_anonymous() {
		$response = wp_remote_post(
			self::SERVICE_URL . '/v1/auth/register',
			array(
				'timeout' => 15,
				'headers' => array( 'content-type' => 'application/json' ),
				'body'    => wp_json_encode( array(
					'source' => 'wordpress-plugin',
					'version' => LEARN_VERSION,
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['api_key'] ) ) {
			update_option( '1111_learn_telemetry_key', $body['api_key'] );
			return $body['api_key'];
		}

		return false;
	}

	/**
	 * Get the stored anonymous API key.
	 *
	 * @return string|false
	 */
	private static function get_anonymous_key() {
		return get_option( '1111_learn_telemetry_key', false );
	}

	/**
	 * Strip any potentially sensitive data from event payload.
	 *
	 * @param array $data Event data.
	 * @return array Sanitized data.
	 */
	private static function strip_sensitive( $data ) {
		$forbidden_keys = array(
			'api_key', 'password', 'email', 'username', 'user_login',
			'display_name', 'ip_address', 'site_url', 'home_url',
			'lesson_body', 'post_content', 'feedback_text', 'content_snapshot',
		);

		$clean = array();
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, $forbidden_keys, true ) ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$clean[ $key ] = self::strip_sensitive( $value );
			} else {
				$clean[ $key ] = $value;
			}
		}

		return $clean;
	}

	// -------------------------------------------------------------------------
	// Convenience methods for common events.
	// -------------------------------------------------------------------------

	/**
	 * Record course_started event.
	 *
	 * @param int $objective_count Number of objectives.
	 */
	public static function course_started( $objective_count ) {
		self::record( 'course_started', array(
			'objectiveCount' => $objective_count,
			'wpVersion'      => get_bloginfo( 'version' ),
			'phpVersion'     => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
		) );
	}

	/**
	 * Record agent_request event.
	 *
	 * @param string $agent_name      Agent name.
	 * @param string $model           Model used.
	 * @param string $prompt_file_hash SHA-256 of prompt file.
	 */
	public static function agent_request( $agent_name, $model, $prompt_file_hash ) {
		self::record( 'agent_request', array(
			'agentName'      => $agent_name,
			'model'          => $model,
			'promptFileHash' => $prompt_file_hash,
		) );
	}

	/**
	 * Record agent_response event.
	 *
	 * @param string $agent_name Agent name.
	 * @param string $model      Model used.
	 * @param int    $latency_ms Latency in ms.
	 */
	public static function agent_response( $agent_name, $model, $latency_ms ) {
		self::record( 'agent_response', array(
			'agentName' => $agent_name,
			'model'     => $model,
			'latencyMs' => $latency_ms,
		) );
	}

	/**
	 * Record validation_failure event.
	 *
	 * @param string $agent_name       Agent name.
	 * @param array  $validation_errors Validation errors.
	 * @param bool   $retried          Whether a retry was attempted.
	 */
	public static function validation_failure( $agent_name, $validation_errors, $retried ) {
		self::record( 'validation_failure', array(
			'agentName'        => $agent_name,
			'validationErrors' => $validation_errors,
			'retried'          => $retried,
		) );
	}

	/**
	 * Record course_completed event.
	 *
	 * @param int $objective_count  Number of objectives.
	 * @param int $lesson_count     Number of lessons.
	 * @param int $total_latency_ms Total latency.
	 */
	public static function course_completed( $objective_count, $lesson_count, $total_latency_ms ) {
		self::record( 'course_completed', array(
			'objectiveCount' => $objective_count,
			'lessonCount'    => $lesson_count,
			'totalLatencyMs' => $total_latency_ms,
		) );
		self::schedule_flush();
	}

	/**
	 * Record course_failed event.
	 *
	 * @param string $failed_agent Agent that failed.
	 * @param int    $objective_index Objective index.
	 * @param string $error_type   Error type.
	 * @param string $error_message Error message.
	 */
	public static function course_failed( $failed_agent, $objective_index, $error_type, $error_message ) {
		self::record( 'course_failed', array(
			'failedAgent'         => $failed_agent,
			'failedObjectiveIndex' => $objective_index,
			'errorType'           => $error_type,
			'errorMessage'        => $error_message,
		) );
		self::schedule_flush();
	}

	/**
	 * Record feedback_submitted event.
	 *
	 * @param string $level          Feedback level.
	 * @param array  $agents_to_rerun Agents that will be re-run.
	 */
	public static function feedback_submitted( $level, $agents_to_rerun ) {
		self::record( 'feedback_submitted', array(
			'feedbackLevel' => $level,
			'agentsToRerun' => $agents_to_rerun,
		) );
	}

	/**
	 * Record submission_assessed event.
	 *
	 * @param string $activity_type   Activity type.
	 * @param float  $score           Score.
	 * @param string $recommendation  Recommendation.
	 * @param int    $attempt_number  Attempt number.
	 */
	public static function submission_assessed( $activity_type, $score, $recommendation, $attempt_number ) {
		self::record( 'submission_assessed', array(
			'activityType'   => $activity_type,
			'score'          => $score,
			'recommendation' => $recommendation,
			'attemptNumber'  => $attempt_number,
		) );
		self::schedule_flush();
	}
}
