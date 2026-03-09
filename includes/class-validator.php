<?php
/**
 * Validates agent output against schemas.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Validator
 *
 * Deterministic validation for each agent's JSON output.
 */
class Learn_Validator {

	/**
	 * Unsafe content pattern.
	 *
	 * @var string
	 */
	const UNSAFE_PATTERN = '/\b(kill yourself|self-harm|suicide method|how to hack|how to steal|how to attack)\b/i';

	/**
	 * Validate Course Describer output.
	 *
	 * @param array $data            Parsed JSON output.
	 * @param int   $objective_count Number of learning objectives.
	 * @return true|WP_Error True on success, WP_Error with specific message on failure.
	 */
	public static function validate_course_describer( $data, $objective_count ) {
		$errors = array();

		if ( empty( $data['narrative_description'] ) || strlen( $data['narrative_description'] ) < 100 ) {
			$errors[] = 'narrative_description must be at least 100 characters.';
		}

		if ( empty( $data['work_product'] ) || strlen( $data['work_product'] ) < 2 || strlen( $data['work_product'] ) > 60 ) {
			$errors[] = 'work_product must be 2-60 characters.';
		}

		$valid_types = array( 'page', 'post_series', 'site' );
		if ( empty( $data['work_product_type'] ) || ! in_array( $data['work_product_type'], $valid_types, true ) ) {
			$errors[] = 'work_product_type must be one of: page, post_series, site.';
		}

		if ( empty( $data['work_product_description'] ) ) {
			$errors[] = 'work_product_description is required.';
		}

		if ( ! isset( $data['lessons'] ) || ! is_array( $data['lessons'] ) ) {
			$errors[] = 'lessons must be an array.';
		} elseif ( count( $data['lessons'] ) !== $objective_count ) {
			$errors[] = sprintf( 'lessons must have exactly %d entries (one per objective), got %d.', $objective_count, count( $data['lessons'] ) );
		} else {
			foreach ( $data['lessons'] as $i => $lesson ) {
				if ( empty( $lesson['lesson_title'] ) || strlen( $lesson['lesson_title'] ) < 5 || strlen( $lesson['lesson_title'] ) > 60 ) {
					$errors[] = sprintf( 'lessons[%d].lesson_title must be 5-60 characters.', $i );
				}
				if ( empty( $lesson['lesson_summary'] ) || strlen( $lesson['lesson_summary'] ) < 30 ) {
					$errors[] = sprintf( 'lessons[%d].lesson_summary must be at least 30 characters.', $i );
				}
			}
		}

		$safety = self::check_safety( $data );
		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'course_describer', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Lesson Planner output.
	 *
	 * @param array $data Parsed JSON output.
	 * @return true|WP_Error
	 */
	public static function validate_lesson_planner( $data ) {
		$errors = array();

		if ( empty( $data['learning_objective'] ) ) {
			$errors[] = 'learning_objective is required.';
		}

		if ( ! isset( $data['key_concepts'] ) || ! is_array( $data['key_concepts'] ) ) {
			$errors[] = 'key_concepts must be an array.';
		} elseif ( count( $data['key_concepts'] ) < 2 || count( $data['key_concepts'] ) > 8 ) {
			$errors[] = 'key_concepts must have 2-8 items.';
		}

		if ( ! isset( $data['mastery_criteria'] ) || ! is_array( $data['mastery_criteria'] ) ) {
			$errors[] = 'mastery_criteria must be an array.';
		} elseif ( count( $data['mastery_criteria'] ) < 2 || count( $data['mastery_criteria'] ) > 6 ) {
			$errors[] = 'mastery_criteria must have 2-6 items.';
		}

		if ( ! isset( $data['suggested_activity'] ) || ! is_array( $data['suggested_activity'] ) ) {
			$errors[] = 'suggested_activity is required.';
		} else {
			$activity = $data['suggested_activity'];
			$valid_types = array( 'explore', 'apply', 'create' );
			if ( empty( $activity['activity_type'] ) || ! in_array( $activity['activity_type'], $valid_types, true ) ) {
				$errors[] = 'suggested_activity.activity_type must be one of: explore, apply, create.';
			}
			if ( empty( $activity['prompt'] ) ) {
				$errors[] = 'suggested_activity.prompt is required.';
			}
			if ( ! isset( $activity['expected_evidence'] ) || ! is_array( $activity['expected_evidence'] ) ) {
				$errors[] = 'suggested_activity.expected_evidence must be an array.';
			} elseif ( count( $activity['expected_evidence'] ) < 2 || count( $activity['expected_evidence'] ) > 5 ) {
				$errors[] = 'suggested_activity.expected_evidence must have 2-5 items.';
			}
		}

		if ( ! isset( $data['lessons'] ) || ! is_array( $data['lessons'] ) ) {
			$errors[] = 'lessons must be an array.';
		} elseif ( count( $data['lessons'] ) < 1 || count( $data['lessons'] ) > 4 ) {
			$errors[] = 'lessons must have 1-4 entries.';
		} else {
			foreach ( $data['lessons'] as $i => $lesson ) {
				if ( empty( $lesson['lesson_title'] ) ) {
					$errors[] = sprintf( 'lessons[%d].lesson_title is required.', $i );
				}
				if ( ! isset( $lesson['lesson_outline'] ) || ! is_array( $lesson['lesson_outline'] ) ) {
					$errors[] = sprintf( 'lessons[%d].lesson_outline must be an array.', $i );
				} elseif ( count( $lesson['lesson_outline'] ) < 3 || count( $lesson['lesson_outline'] ) > 10 ) {
					$errors[] = sprintf( 'lessons[%d].lesson_outline must have 3-10 items.', $i );
				}
			}
		}

		$safety = self::check_safety( $data );
		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'lesson_planner', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Lesson Writer output.
	 *
	 * @param array $data Parsed JSON output.
	 * @return true|WP_Error
	 */
	public static function validate_lesson_writer( $data ) {
		$errors = array();

		if ( empty( $data['lesson_title'] ) ) {
			$errors[] = 'lesson_title is required.';
		}

		if ( empty( $data['lesson_body'] ) || strlen( $data['lesson_body'] ) < 200 ) {
			$errors[] = 'lesson_body must be at least 200 characters.';
		}

		if ( ! isset( $data['key_takeaways'] ) || ! is_array( $data['key_takeaways'] ) ) {
			$errors[] = 'key_takeaways must be an array.';
		} elseif ( count( $data['key_takeaways'] ) < 3 || count( $data['key_takeaways'] ) > 6 ) {
			$errors[] = 'key_takeaways must have 3-6 items.';
		}

		$safety = self::check_safety( $data );
		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'lesson_writer', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Activity Creator output.
	 *
	 * @param array $data Parsed JSON output.
	 * @return true|WP_Error
	 */
	public static function validate_activity_creator( $data ) {
		$errors = array();

		$valid_types = array( 'explore', 'apply', 'create', 'final' );
		if ( empty( $data['activity_type'] ) || ! in_array( $data['activity_type'], $valid_types, true ) ) {
			$errors[] = 'activity_type must be one of: explore, apply, create, final.';
		}

		if ( empty( $data['prompt'] ) || strlen( $data['prompt'] ) < 20 ) {
			$errors[] = 'prompt must be at least 20 characters.';
		}

		if ( empty( $data['instructions'] ) || strlen( $data['instructions'] ) < 50 ) {
			$errors[] = 'instructions must be at least 50 characters.';
		}

		if ( ! isset( $data['scoring_rubric'] ) || ! is_array( $data['scoring_rubric'] ) ) {
			$errors[] = 'scoring_rubric must be an array.';
		} elseif ( count( $data['scoring_rubric'] ) < 3 || count( $data['scoring_rubric'] ) > 6 ) {
			$errors[] = 'scoring_rubric must have 3-6 items.';
		}

		if ( ! isset( $data['hints'] ) || ! is_array( $data['hints'] ) ) {
			$errors[] = 'hints must be an array.';
		} elseif ( count( $data['hints'] ) < 2 || count( $data['hints'] ) > 5 ) {
			$errors[] = 'hints must have 2-5 items.';
		}

		if ( empty( $data['portfolio_contribution'] ) || strlen( $data['portfolio_contribution'] ) < 20 ) {
			$errors[] = 'portfolio_contribution must be at least 20 characters.';
		}

		if ( ! isset( $data['xp_value'] ) || ! is_int( $data['xp_value'] ) ) {
			$errors[] = 'xp_value must be an integer.';
		} else {
			$valid_xp = array( 100, 150, 200, 300 );
			if ( ! in_array( $data['xp_value'], $valid_xp, true ) ) {
				$errors[] = 'xp_value must be one of: 100, 150, 200, 300.';
			}
		}

		if ( array_key_exists( 'milestone', $data ) && null !== $data['milestone'] && empty( $data['milestone'] ) ) {
			$errors[] = 'milestone must be null or a non-empty string.';
		}

		$safety = self::check_safety( $data );
		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'activity_creator', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Activity Reviewer output.
	 *
	 * @param array $data Parsed JSON output.
	 * @return true|WP_Error
	 */
	public static function validate_activity_reviewer( $data ) {
		$errors = array();

		$valid_verdicts = array( 'approved', 'revision_needed' );
		if ( empty( $data['verdict'] ) || ! in_array( $data['verdict'], $valid_verdicts, true ) ) {
			$errors[] = 'verdict must be one of: approved, revision_needed.';
		}

		$required_fields = array( 'rubric_alignment', 'difficulty_assessment', 'portfolio_check', 'gamification_check' );
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$errors[] = $field . ' is required.';
			}
		}

		if ( ! isset( $data['suggestions'] ) || ! is_array( $data['suggestions'] ) ) {
			$errors[] = 'suggestions must be an array.';
		} elseif ( isset( $data['verdict'] ) && 'revision_needed' === $data['verdict'] && ( count( $data['suggestions'] ) < 1 || count( $data['suggestions'] ) > 5 ) ) {
			$errors[] = 'suggestions must have 1-5 items when verdict is revision_needed.';
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'activity_reviewer', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Assessment Creator output.
	 *
	 * @param array $data            Parsed JSON output.
	 * @param int   $objective_count Number of learning objectives.
	 * @return true|WP_Error
	 */
	public static function validate_assessment_creator( $data, $objective_count ) {
		$errors = array();

		if ( empty( $data['assessment_title'] ) || strlen( $data['assessment_title'] ) < 5 || strlen( $data['assessment_title'] ) > 100 ) {
			$errors[] = 'assessment_title must be 5-100 characters.';
		}

		if ( ! isset( $data['assessment_type'] ) || 'final' !== $data['assessment_type'] ) {
			$errors[] = 'assessment_type must be "final".';
		}

		if ( empty( $data['prompt'] ) || strlen( $data['prompt'] ) < 20 ) {
			$errors[] = 'prompt must be at least 20 characters.';
		}

		if ( empty( $data['instructions'] ) || strlen( $data['instructions'] ) < 50 ) {
			$errors[] = 'instructions must be at least 50 characters.';
		}

		if ( ! isset( $data['portfolio_rubric'] ) || ! is_array( $data['portfolio_rubric'] ) ) {
			$errors[] = 'portfolio_rubric must be an array.';
		} elseif ( count( $data['portfolio_rubric'] ) !== $objective_count ) {
			$errors[] = sprintf( 'portfolio_rubric must have %d entries (one per objective).', $objective_count );
		} else {
			foreach ( $data['portfolio_rubric'] as $i => $rubric ) {
				if ( empty( $rubric['objective'] ) ) {
					$errors[] = sprintf( 'portfolio_rubric[%d].objective is required.', $i );
				}
				if ( ! isset( $rubric['criteria'] ) || ! is_array( $rubric['criteria'] ) ) {
					$errors[] = sprintf( 'portfolio_rubric[%d].criteria must be an array.', $i );
				} elseif ( count( $rubric['criteria'] ) < 2 || count( $rubric['criteria'] ) > 6 ) {
					$errors[] = sprintf( 'portfolio_rubric[%d].criteria must have 2-6 items.', $i );
				}
			}
		}

		$scoring_fields = array( 'mastery', 'proficient', 'developing', 'beginning' );
		if ( ! isset( $data['scoring_guide'] ) || ! is_array( $data['scoring_guide'] ) ) {
			$errors[] = 'scoring_guide is required.';
		} else {
			foreach ( $scoring_fields as $field ) {
				if ( empty( $data['scoring_guide'][ $field ] ) ) {
					$errors[] = 'scoring_guide.' . $field . ' is required.';
				}
			}
		}

		if ( ! isset( $data['xp_value'] ) || 500 !== $data['xp_value'] ) {
			$errors[] = 'xp_value must be 500.';
		}

		if ( ! isset( $data['milestone'] ) || 'Portfolio Delivered' !== $data['milestone'] ) {
			$errors[] = 'milestone must be "Portfolio Delivered".';
		}

		if ( empty( $data['completion_message'] ) || strlen( $data['completion_message'] ) < 50 ) {
			$errors[] = 'completion_message must be at least 50 characters.';
		}

		$safety = self::check_safety( $data );
		if ( is_wp_error( $safety ) ) {
			return $safety;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'assessment_creator', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Validate Activity Assessment Agent output.
	 *
	 * @param array $data         Parsed JSON output.
	 * @param int   $rubric_count Number of rubric criteria.
	 * @return true|WP_Error
	 */
	public static function validate_activity_assessment( $data, $rubric_count ) {
		$errors = array();

		if ( ! isset( $data['score'] ) || ! is_numeric( $data['score'] ) || $data['score'] < 0 || $data['score'] > 1 ) {
			$errors[] = 'score must be a number between 0.0 and 1.0.';
		}

		$valid_recs = array( 'advance', 'continue', 'revise' );
		if ( empty( $data['recommendation'] ) || ! in_array( $data['recommendation'], $valid_recs, true ) ) {
			$errors[] = 'recommendation must be one of: advance, continue, revise.';
		}

		// Check recommendation-score consistency.
		if ( isset( $data['score'] ) && is_numeric( $data['score'] ) && ! empty( $data['recommendation'] ) ) {
			$score = (float) $data['score'];
			$rec   = $data['recommendation'];
			if ( 'advance' === $rec && $score < 0.7 ) {
				$errors[] = 'recommendation "advance" requires score >= 0.7.';
			} elseif ( 'continue' === $rec && ( $score < 0.5 || $score >= 0.7 ) ) {
				$errors[] = 'recommendation "continue" requires 0.5 <= score < 0.7.';
			} elseif ( 'revise' === $rec && $score >= 0.5 ) {
				$errors[] = 'recommendation "revise" requires score < 0.5.';
			}
		}

		if ( ! isset( $data['strengths'] ) || ! is_array( $data['strengths'] ) ) {
			$errors[] = 'strengths must be an array.';
		} elseif ( count( $data['strengths'] ) < 2 || count( $data['strengths'] ) > 4 ) {
			$errors[] = 'strengths must have 2-4 items.';
		}

		if ( ! isset( $data['improvements'] ) || ! is_array( $data['improvements'] ) ) {
			$errors[] = 'improvements must be an array.';
		} elseif ( count( $data['improvements'] ) < 1 || count( $data['improvements'] ) > 3 ) {
			$errors[] = 'improvements must have 1-3 items.';
		}

		if ( ! isset( $data['rubric_results'] ) || ! is_array( $data['rubric_results'] ) ) {
			$errors[] = 'rubric_results must be an array.';
		} elseif ( count( $data['rubric_results'] ) !== $rubric_count ) {
			$errors[] = sprintf( 'rubric_results must have %d entries (one per rubric criterion).', $rubric_count );
		} else {
			foreach ( $data['rubric_results'] as $i => $result ) {
				if ( empty( $result['criterion'] ) ) {
					$errors[] = sprintf( 'rubric_results[%d].criterion is required.', $i );
				}
				if ( ! isset( $result['met'] ) || ! is_bool( $result['met'] ) ) {
					$errors[] = sprintf( 'rubric_results[%d].met must be a boolean.', $i );
				}
				if ( empty( $result['note'] ) ) {
					$errors[] = sprintf( 'rubric_results[%d].note is required.', $i );
				}
			}
		}

		if ( empty( $data['portfolio_check'] ) || strlen( $data['portfolio_check'] ) < 20 ) {
			$errors[] = 'portfolio_check must be at least 20 characters.';
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'learn_validation_failed', implode( ' ', $errors ), array( 'agent' => 'activity_assessment', 'errors' => $errors ) );
		}

		return true;
	}

	/**
	 * Check all string values in data for unsafe content patterns.
	 *
	 * @param array $data Data to check.
	 * @return true|WP_Error True if safe, WP_Error if unsafe content found.
	 */
	public static function check_safety( $data ) {
		$strings = self::extract_strings( $data );
		foreach ( $strings as $str ) {
			if ( preg_match( self::UNSAFE_PATTERN, $str ) ) {
				return new WP_Error(
					'learn_unsafe_content',
					__( 'Generated content contains unsafe patterns and has been blocked.', 'learn' )
				);
			}
		}
		return true;
	}

	/**
	 * Recursively extract all string values from a nested array.
	 *
	 * @param mixed $data Data to extract strings from.
	 * @return array Array of strings.
	 */
	private static function extract_strings( $data ) {
		$strings = array();
		if ( is_string( $data ) ) {
			$strings[] = $data;
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$strings = array_merge( $strings, self::extract_strings( $value ) );
			}
		}
		return $strings;
	}
}
