<?php
/**
 * Anonymous telemetry collector.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Telemetry {
	/**
	 * Singleton instance.
	 *
	 * @var Learn_Telemetry|null
	 */
	private static $instance = null;

	/**
	 * Singleton getter.
	 *
	 * @return Learn_Telemetry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( '1111_learn_course_described', array( $this, 'track_course_described' ) );
		add_action( '1111_learn_submission_assessed', array( $this, 'track_submission_assessed' ), 10, 3 );
	}

	/**
	 * Track course generation milestone.
	 *
	 * @param array $context Event context.
	 * @return void
	 */
	public function track_course_described( $context ) {
		$this->log_event( 'course_described', array( 'objective_count' => isset( $context['objectives'] ) ? count( $context['objectives'] ) : 0 ) );
	}

	/**
	 * Track assessment events.
	 *
	 * @param int   $submission_id Submission ID.
	 * @param float $score Score.
	 * @param string $recommendation Recommendation.
	 * @return void
	 */
	public function track_submission_assessed( $submission_id, $score, $recommendation ) {
		$this->log_event(
			'submission_assessed',
			array(
				'submission_id'  => absint( $submission_id ),
				'score'          => round( (float) $score, 3 ),
				'recommendation' => sanitize_key( $recommendation ),
			)
		);
	}

	/**
	 * Local telemetry sink for now.
	 *
	 * @param string $event Event name.
	 * @param array  $data Event data.
	 * @return void
	 */
	public function log_event( $event, $data = array() ) {
		$enabled = (bool) get_site_option( Learn_Settings::OPTION_TELEMETRY, false );
		if ( ! $enabled ) {
			return;
		}

		error_log( wp_json_encode( array( 'event' => $event, 'data' => $data, 'timestamp' => gmdate( 'c' ) ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
