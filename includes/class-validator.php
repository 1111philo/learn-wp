<?php
/**
 * Learn_Validator
 *
 * Validates all agent output per the PRD Section 6 validation rules.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Validator
 *
 * Validates structured data returned by each AI agent, enforcing
 * field presence, type, length, and domain constraints.
 */
class Learn_Validator {

	/**
	 * Allowed activity types for activity_creator.
	 *
	 * @var array
	 */
	private $activity_types = array( 'explore', 'apply', 'create', 'final' );

	/**
	 * Allowed XP values for activities.
	 *
	 * @var array
	 */
	private $allowed_xp_values = array( 100, 150, 200, 300 );

	/**
	 * Unsafe content patterns.
	 *
	 * @var string
	 */
	private $unsafe_pattern = '/\b(kill yourself|self-harm|suicide method|how to hack|how to steal|how to attack)\b/i';

	/**
	 * Dispatch validation to the appropriate per-agent method.
	 *
	 * @param string $agent_name The agent identifier.
	 * @param array  $data       The parsed agent output.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate( $agent_name, $data ) {
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'Agent output must be an array.' );
		}

		$method = 'validate_' . sanitize_key( $agent_name );

		if ( ! method_exists( $this, $method ) ) {
			return new WP_Error( 'unknown_agent', sprintf( 'No validation rules for agent "%s".', $agent_name ) );
		}

		return $this->$method( $data );
	}

	/**
	 * Parse JSON from agent text output, stripping markdown code fences.
	 *
	 * @param string $text Raw text that may contain markdown-fenced JSON.
	 * @return array|WP_Error Parsed data or WP_Error on failure.
	 */
	public function parse_json( $text ) {
		if ( ! is_string( $text ) ) {
			return new WP_Error( 'invalid_input', 'parse_json expects a string.' );
		}

		// Strip markdown code fences (```json ... ``` or ``` ... ```).
		$stripped = preg_replace( '/^```(?:json)?\s*\n?/i', '', trim( $text ) );
		$stripped = preg_replace( '/\n?```\s*$/', '', $stripped );

		$decoded = json_decode( trim( $stripped ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'json_parse_error',
				sprintf( 'Failed to parse JSON: %s', json_last_error_msg() )
			);
		}

		return $decoded;
	}

	/**
	 * Check text for unsafe content patterns.
	 *
	 * @param string $text The text to check.
	 * @return true|WP_Error True if safe, WP_Error if unsafe content detected.
	 */
	public function check_safety( $text ) {
		if ( ! is_string( $text ) ) {
			return new WP_Error( 'invalid_input', 'check_safety expects a string.' );
		}

		if ( preg_match( $this->unsafe_pattern, $text ) ) {
			return new WP_Error( 'unsafe_content', 'Agent output contains unsafe or prohibited content.' );
		}

		return true;
	}

	/**
	 * Validate course_describer agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_course_describer( $data ) {
		// narrative_description: non-empty string, min 100 chars.
		$check = $this->require_string( $data, 'narrative_description', 100 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// work_product: non-empty string, 2–60 chars.
		$check = $this->require_string_range( $data, 'work_product', 2, 60 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// work_product_type: one of page, post_series, site.
		$check = $this->require_enum( $data, 'work_product_type', array( 'page', 'post_series', 'site' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// work_product_description: non-empty string.
		$check = $this->require_string( $data, 'work_product_description', 1 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// lessons: array with entries.
		if ( ! isset( $data['lessons'] ) || ! is_array( $data['lessons'] ) || empty( $data['lessons'] ) ) {
			return new WP_Error( 'missing_lessons', 'course_describer output must include a non-empty "lessons" array.' );
		}

		foreach ( $data['lessons'] as $i => $lesson ) {
			$pos = $i + 1;

			$check = $this->require_string_range( $lesson, 'lesson_title', 5, 60 );
			if ( is_wp_error( $check ) ) {
				return new WP_Error( $check->get_error_code(), sprintf( 'Lesson %d: %s', $pos, $check->get_error_message() ) );
			}

			$check = $this->require_string( $lesson, 'lesson_summary', 30 );
			if ( is_wp_error( $check ) ) {
				return new WP_Error( $check->get_error_code(), sprintf( 'Lesson %d: %s', $pos, $check->get_error_message() ) );
			}
		}

		return true;
	}

	/**
	 * Validate lesson_planner agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_lesson_planner( $data ) {
		// learning_objective: non-empty string.
		$check = $this->require_string( $data, 'learning_objective', 1 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// key_concepts: 2–8 items.
		$check = $this->require_array_range( $data, 'key_concepts', 2, 8 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// mastery_criteria: 2–6 items.
		$check = $this->require_array_range( $data, 'mastery_criteria', 2, 6 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// suggested_activity validation.
		if ( ! isset( $data['suggested_activity'] ) || ! is_array( $data['suggested_activity'] ) ) {
			return new WP_Error( 'missing_suggested_activity', 'lesson_planner output must include "suggested_activity" object.' );
		}

		$sa = $data['suggested_activity'];

		$check = $this->require_enum( $sa, 'activity_type', array( 'explore', 'apply', 'create' ) );
		if ( is_wp_error( $check ) ) {
			return new WP_Error( $check->get_error_code(), 'suggested_activity: ' . $check->get_error_message() );
		}

		$check = $this->require_string( $sa, 'prompt', 1 );
		if ( is_wp_error( $check ) ) {
			return new WP_Error( $check->get_error_code(), 'suggested_activity: ' . $check->get_error_message() );
		}

		$check = $this->require_array_range( $sa, 'expected_evidence', 2, 5 );
		if ( is_wp_error( $check ) ) {
			return new WP_Error( $check->get_error_code(), 'suggested_activity: ' . $check->get_error_message() );
		}

		// lessons: 1–4 entries.
		$check = $this->require_array_range( $data, 'lessons', 1, 4 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		foreach ( $data['lessons'] as $i => $lesson ) {
			$pos = $i + 1;

			$check = $this->require_string( $lesson, 'lesson_title', 1 );
			if ( is_wp_error( $check ) ) {
				return new WP_Error( $check->get_error_code(), sprintf( 'Lesson %d: %s', $pos, $check->get_error_message() ) );
			}

			$check = $this->require_array_range( $lesson, 'lesson_outline', 3, 10 );
			if ( is_wp_error( $check ) ) {
				return new WP_Error( $check->get_error_code(), sprintf( 'Lesson %d: %s', $pos, $check->get_error_message() ) );
			}
		}

		return true;
	}

	/**
	 * Validate lesson_writer agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_lesson_writer( $data ) {
		$check = $this->require_string( $data, 'lesson_title', 1 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$check = $this->require_string( $data, 'lesson_body', 200 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$check = $this->require_array_range( $data, 'key_takeaways', 3, 6 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return true;
	}

	/**
	 * Validate activity_creator agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_activity_creator( $data ) {
		// activity_type: one of explore, apply, create, final.
		$check = $this->require_enum( $data, 'activity_type', $this->activity_types );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// prompt: min 20 chars.
		$check = $this->require_string( $data, 'prompt', 20 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// instructions: min 50 chars.
		$check = $this->require_string( $data, 'instructions', 50 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// scoring_rubric: 3–6 items.
		$check = $this->require_array_range( $data, 'scoring_rubric', 3, 6 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// hints: 2–5 items.
		$check = $this->require_array_range( $data, 'hints', 2, 5 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// portfolio_contribution: non-empty string min 20 chars.
		$check = $this->require_string( $data, 'portfolio_contribution', 20 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// xp_value: integer, one of 100, 150, 200, 300.
		if ( ! isset( $data['xp_value'] ) || ! is_int( $data['xp_value'] ) || ! in_array( $data['xp_value'], $this->allowed_xp_values, true ) ) {
			return new WP_Error(
				'invalid_xp_value',
				sprintf( '"xp_value" must be one of: %s.', implode( ', ', $this->allowed_xp_values ) )
			);
		}

		// milestone: null or non-empty string.
		if ( array_key_exists( 'milestone', $data ) ) {
			if ( null !== $data['milestone'] && ( ! is_string( $data['milestone'] ) || '' === trim( $data['milestone'] ) ) ) {
				return new WP_Error( 'invalid_milestone', '"milestone" must be null or a non-empty string.' );
			}
		} else {
			return new WP_Error( 'missing_milestone', '"milestone" field is required (may be null).' );
		}

		return true;
	}

	/**
	 * Validate activity_reviewer agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_activity_reviewer( $data ) {
		// verdict: one of approved, revision_needed.
		$check = $this->require_enum( $data, 'verdict', array( 'approved', 'revision_needed' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Required non-empty string fields.
		$string_fields = array( 'rubric_alignment', 'difficulty_assessment', 'portfolio_check', 'gamification_check' );
		foreach ( $string_fields as $field ) {
			$check = $this->require_string( $data, $field, 1 );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		// suggestions: array, empty if approved, 1–5 if revision_needed.
		if ( ! isset( $data['suggestions'] ) || ! is_array( $data['suggestions'] ) ) {
			return new WP_Error( 'missing_suggestions', '"suggestions" must be an array.' );
		}

		$verdict = $data['verdict'];
		$count   = count( $data['suggestions'] );

		if ( 'approved' === $verdict && $count > 0 ) {
			return new WP_Error( 'invalid_suggestions', '"suggestions" must be empty when verdict is "approved".' );
		}

		if ( 'revision_needed' === $verdict && ( $count < 1 || $count > 5 ) ) {
			return new WP_Error( 'invalid_suggestions_count', '"suggestions" must have 1-5 items when verdict is "revision_needed".' );
		}

		return true;
	}

	/**
	 * Validate assessment_creator agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_assessment_creator( $data ) {
		// assessment_title: 5–100 chars.
		$check = $this->require_string_range( $data, 'assessment_title', 5, 100 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// assessment_type: must be 'final'.
		if ( ! isset( $data['assessment_type'] ) || 'final' !== $data['assessment_type'] ) {
			return new WP_Error( 'invalid_assessment_type', '"assessment_type" must be "final".' );
		}

		// prompt: min 20 chars.
		$check = $this->require_string( $data, 'prompt', 20 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// instructions: min 50 chars.
		$check = $this->require_string( $data, 'instructions', 50 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// portfolio_rubric: array, each entry has objective (string) and criteria (2–6 items).
		if ( ! isset( $data['portfolio_rubric'] ) || ! is_array( $data['portfolio_rubric'] ) || empty( $data['portfolio_rubric'] ) ) {
			return new WP_Error( 'missing_portfolio_rubric', '"portfolio_rubric" must be a non-empty array.' );
		}

		foreach ( $data['portfolio_rubric'] as $i => $entry ) {
			$pos = $i + 1;

			if ( ! isset( $entry['objective'] ) || ! is_string( $entry['objective'] ) || '' === trim( $entry['objective'] ) ) {
				return new WP_Error( 'invalid_rubric_objective', sprintf( 'portfolio_rubric item %d: "objective" must be a non-empty string.', $pos ) );
			}

			if ( ! isset( $entry['criteria'] ) || ! is_array( $entry['criteria'] ) ) {
				return new WP_Error( 'missing_rubric_criteria', sprintf( 'portfolio_rubric item %d: "criteria" must be an array.', $pos ) );
			}

			$criteria_count = count( $entry['criteria'] );
			if ( $criteria_count < 2 || $criteria_count > 6 ) {
				return new WP_Error( 'invalid_rubric_criteria_count', sprintf( 'portfolio_rubric item %d: "criteria" must have 2-6 items, got %d.', $pos, $criteria_count ) );
			}
		}

		// scoring_guide: has mastery, proficient, developing, beginning (all non-empty strings).
		if ( ! isset( $data['scoring_guide'] ) || ! is_array( $data['scoring_guide'] ) ) {
			return new WP_Error( 'missing_scoring_guide', '"scoring_guide" must be an object.' );
		}

		$levels = array( 'mastery', 'proficient', 'developing', 'beginning' );
		foreach ( $levels as $level ) {
			if ( ! isset( $data['scoring_guide'][ $level ] ) || ! is_string( $data['scoring_guide'][ $level ] ) || '' === trim( $data['scoring_guide'][ $level ] ) ) {
				return new WP_Error( 'invalid_scoring_guide', sprintf( 'scoring_guide.%s must be a non-empty string.', $level ) );
			}
		}

		// xp_value: must be 500.
		if ( ! isset( $data['xp_value'] ) || 500 !== $data['xp_value'] ) {
			return new WP_Error( 'invalid_xp_value', '"xp_value" must be 500 for assessment_creator.' );
		}

		// milestone: must be "Portfolio Delivered".
		if ( ! isset( $data['milestone'] ) || 'Portfolio Delivered' !== $data['milestone'] ) {
			return new WP_Error( 'invalid_milestone', '"milestone" must be "Portfolio Delivered".' );
		}

		// completion_message: non-empty string min 50 chars.
		$check = $this->require_string( $data, 'completion_message', 50 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return true;
	}

	/**
	 * Validate activity_assessment agent output.
	 *
	 * @param array $data Agent output.
	 * @return true|WP_Error
	 */
	private function validate_activity_assessment( $data ) {
		// score: float 0.0–1.0.
		if ( ! isset( $data['score'] ) || ! is_numeric( $data['score'] ) ) {
			return new WP_Error( 'missing_score', '"score" must be a numeric value.' );
		}

		$score = (float) $data['score'];
		if ( $score < 0.0 || $score > 1.0 ) {
			return new WP_Error( 'invalid_score_range', '"score" must be between 0.0 and 1.0.' );
		}

		// recommendation: one of advance, continue, revise.
		$check = $this->require_enum( $data, 'recommendation', array( 'advance', 'continue', 'revise' ) );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// Recommendation consistency with score.
		$recommendation = $data['recommendation'];
		if ( 'advance' === $recommendation && $score < 0.7 ) {
			return new WP_Error( 'inconsistent_recommendation', '"advance" recommendation requires a score of 0.7 or higher.' );
		}
		if ( 'continue' === $recommendation && ( $score < 0.5 || $score >= 0.7 ) ) {
			return new WP_Error( 'inconsistent_recommendation', '"continue" recommendation requires a score between 0.5 and 0.69.' );
		}
		if ( 'revise' === $recommendation && $score >= 0.5 ) {
			return new WP_Error( 'inconsistent_recommendation', '"revise" recommendation requires a score below 0.5.' );
		}

		// strengths: array 2–4 non-empty strings.
		$check = $this->require_string_array( $data, 'strengths', 2, 4 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// improvements: array 1–3 non-empty strings.
		$check = $this->require_string_array( $data, 'improvements', 1, 3 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		// rubric_results: array, each has criterion (string), met (boolean), note (string).
		if ( ! isset( $data['rubric_results'] ) || ! is_array( $data['rubric_results'] ) || empty( $data['rubric_results'] ) ) {
			return new WP_Error( 'missing_rubric_results', '"rubric_results" must be a non-empty array.' );
		}

		foreach ( $data['rubric_results'] as $i => $result ) {
			$pos = $i + 1;

			if ( ! isset( $result['criterion'] ) || ! is_string( $result['criterion'] ) || '' === trim( $result['criterion'] ) ) {
				return new WP_Error( 'invalid_rubric_result', sprintf( 'rubric_results item %d: "criterion" must be a non-empty string.', $pos ) );
			}

			if ( ! isset( $result['met'] ) || ! is_bool( $result['met'] ) ) {
				return new WP_Error( 'invalid_rubric_result', sprintf( 'rubric_results item %d: "met" must be a boolean.', $pos ) );
			}

			if ( ! isset( $result['note'] ) || ! is_string( $result['note'] ) || '' === trim( $result['note'] ) ) {
				return new WP_Error( 'invalid_rubric_result', sprintf( 'rubric_results item %d: "note" must be a non-empty string.', $pos ) );
			}
		}

		// portfolio_check: non-empty string min 20 chars.
		$check = $this->require_string( $data, 'portfolio_check', 20 );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Private helper methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Require a non-empty string field with a minimum length.
	 *
	 * @param array  $data      Data array.
	 * @param string $field     Field name.
	 * @param int    $min_chars Minimum character count.
	 * @return true|WP_Error
	 */
	private function require_string( $data, $field, $min_chars = 1 ) {
		if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || '' === trim( $data[ $field ] ) ) {
			return new WP_Error( 'missing_' . $field, sprintf( '"%s" must be a non-empty string.', $field ) );
		}

		if ( mb_strlen( trim( $data[ $field ] ) ) < $min_chars ) {
			return new WP_Error(
				'invalid_' . $field,
				sprintf( '"%s" must be at least %d characters, got %d.', $field, $min_chars, mb_strlen( trim( $data[ $field ] ) ) )
			);
		}

		return true;
	}

	/**
	 * Require a string field within a character range.
	 *
	 * @param array  $data Data array.
	 * @param string $field Field name.
	 * @param int    $min  Minimum characters.
	 * @param int    $max  Maximum characters.
	 * @return true|WP_Error
	 */
	private function require_string_range( $data, $field, $min, $max ) {
		if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || '' === trim( $data[ $field ] ) ) {
			return new WP_Error( 'missing_' . $field, sprintf( '"%s" must be a non-empty string.', $field ) );
		}

		$length = mb_strlen( trim( $data[ $field ] ) );
		if ( $length < $min || $length > $max ) {
			return new WP_Error(
				'invalid_' . $field,
				sprintf( '"%s" must be %d-%d characters, got %d.', $field, $min, $max, $length )
			);
		}

		return true;
	}

	/**
	 * Require a field to be one of an allowed set of values.
	 *
	 * @param array  $data    Data array.
	 * @param string $field   Field name.
	 * @param array  $allowed Allowed values.
	 * @return true|WP_Error
	 */
	private function require_enum( $data, $field, $allowed ) {
		if ( ! isset( $data[ $field ] ) || ! in_array( $data[ $field ], $allowed, true ) ) {
			return new WP_Error(
				'invalid_' . $field,
				sprintf( '"%s" must be one of: %s.', $field, implode( ', ', $allowed ) )
			);
		}

		return true;
	}

	/**
	 * Require an array field with a count within a range.
	 *
	 * @param array  $data  Data array.
	 * @param string $field Field name.
	 * @param int    $min   Minimum item count.
	 * @param int    $max   Maximum item count.
	 * @return true|WP_Error
	 */
	private function require_array_range( $data, $field, $min, $max ) {
		if ( ! isset( $data[ $field ] ) || ! is_array( $data[ $field ] ) ) {
			return new WP_Error( 'missing_' . $field, sprintf( '"%s" must be an array.', $field ) );
		}

		$count = count( $data[ $field ] );
		if ( $count < $min || $count > $max ) {
			return new WP_Error(
				'invalid_' . $field . '_count',
				sprintf( '"%s" must have %d-%d items, got %d.', $field, $min, $max, $count )
			);
		}

		return true;
	}

	/**
	 * Require an array of non-empty strings within a count range.
	 *
	 * @param array  $data  Data array.
	 * @param string $field Field name.
	 * @param int    $min   Minimum item count.
	 * @param int    $max   Maximum item count.
	 * @return true|WP_Error
	 */
	private function require_string_array( $data, $field, $min, $max ) {
		$check = $this->require_array_range( $data, $field, $min, $max );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		foreach ( $data[ $field ] as $i => $item ) {
			if ( ! is_string( $item ) || '' === trim( $item ) ) {
				return new WP_Error(
					'invalid_' . $field . '_item',
					sprintf( '"%s" item %d must be a non-empty string.', $field, $i + 1 )
				);
			}
		}

		return true;
	}
}
