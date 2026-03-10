<?php
/**
 * Activity Reviewer agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Activity Reviewer agent.
 *
 * Takes a generated activity and its context and produces the user
 * message sent to the Anthropic API for quality review.
 */
class Learn_Agent_Activity_Reviewer {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'activity-reviewer';

	/**
	 * Model identifier.
	 *
	 * @var string
	 */
	public $model = LEARN_FAST_MODEL;

	/**
	 * Maximum tokens for the response.
	 *
	 * @var int
	 */
	public $max_tokens = LEARN_PLAN_MAX_TOKENS;

	/**
	 * Format the user message for the Activity Reviewer agent.
	 *
	 * @param string $objective       The learning objective.
	 * @param string $activity_type   The activity type (explore, apply, create).
	 * @param string $work_product    The course work product name.
	 * @param array  $mastery_criteria The mastery criteria for this lesson.
	 * @param string $activity_json   The full generated activity JSON to review.
	 * @return string Formatted user message.
	 */
	public function format_input( $objective, $activity_type, $work_product, $mastery_criteria, $activity_json ) {
		$message  = "Learning Objective: {$objective}\n\n";
		$message .= "Expected Activity Type: {$activity_type}\n";
		$message .= "Work Product: {$work_product}\n\n";

		$message .= "Mastery Criteria:\n";
		foreach ( $mastery_criteria as $criterion ) {
			$message .= '- ' . $criterion . "\n";
		}

		$message .= "\nGenerated Activity JSON:\n{$activity_json}\n";

		return $message;
	}
}
