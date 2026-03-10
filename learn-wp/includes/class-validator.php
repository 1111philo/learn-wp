<?php
/**
 * Agent output validators.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Validator {
	/**
	 * Validate payload for an agent.
	 *
	 * @param string $agent   Agent slug.
	 * @param array  $payload Parsed payload.
	 * @return true|WP_Error
	 */
	public static function validate( $agent, $payload ) {
		$method = 'validate_' . str_replace( '-', '_', sanitize_key( $agent ) );
		if ( ! method_exists( __CLASS__, $method ) ) {
			return new WP_Error( 'learn_unknown_validator', sprintf( 'No validator for agent: %s', $agent ) );
		}

		$result = call_user_func( array( __CLASS__, $method ), $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return apply_filters( '1111_learn_validate_' . str_replace( '-', '_', $agent ), true, $payload );
	}

	/**
	 * Course Describer validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_course_describer( $payload ) {
		$required = array( 'course_title', 'narrative_description', 'work_product', 'work_product_type', 'work_product_description', 'lesson_titles' );
		return self::require_keys( $payload, $required );
	}

	/**
	 * Lesson Planner validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_lesson_planner( $payload ) {
		$required = array( 'objective', 'mastery_criteria', 'lessons' );
		return self::require_keys( $payload, $required );
	}

	/**
	 * Lesson Writer validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_lesson_writer( $payload ) {
		$required = array( 'lesson_title', 'lesson_body', 'key_takeaways' );
		return self::require_keys( $payload, $required );
	}

	/**
	 * Activity Creator validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_activity_creator( $payload ) {
		$required = array( 'activity_title', 'activity_type', 'prompt', 'instructions', 'scoring_rubric', 'xp_value' );
		return self::require_keys( $payload, $required );
	}

	/**
	 * Activity Reviewer validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_activity_reviewer( $payload ) {
		$required = array( 'verdict', 'reasoning', 'suggestions' );
		return self::require_keys( $payload, $required );
	}

	/**
	 * Assessment Creator validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_assessment_creator( $payload ) {
		$required = array( 'assessment_title', 'assessment_type', 'instructions', 'portfolio_rubric', 'completion_message' );
		$valid    = self::require_keys( $payload, $required );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( 'final' !== $payload['assessment_type'] ) {
			return new WP_Error( 'learn_invalid_assessment_type', 'Assessment type must be final.' );
		}

		return true;
	}

	/**
	 * Activity Assessment validator.
	 *
	 * @param array $payload Output payload.
	 * @return true|WP_Error
	 */
	private static function validate_activity_assessment( $payload ) {
		$required = array( 'score', 'recommendation', 'strengths', 'improvements', 'rubric_results' );
		$valid    = self::require_keys( $payload, $required );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$score = floatval( $payload['score'] );
		if ( $score < 0 || $score > 1 ) {
			return new WP_Error( 'learn_invalid_score', 'Score must be between 0 and 1.' );
		}

		return true;
	}

	/**
	 * Ensure required keys exist.
	 *
	 * @param array $payload Payload.
	 * @param array $keys Required keys.
	 * @return true|WP_Error
	 */
	private static function require_keys( $payload, $keys ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $payload ) ) {
				return new WP_Error( 'learn_missing_key', sprintf( 'Missing key: %s', $key ) );
			}
		}

		return true;
	}
}
