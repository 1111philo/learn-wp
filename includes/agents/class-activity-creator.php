<?php
/**
 * Activity Creator agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Activity Creator agent.
 *
 * Takes objective and activity context and produces the user message
 * sent to the Anthropic API for activity generation.
 */
class Learn_Agent_Activity_Creator {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'activity-creator';

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
	 * Format the user message for the Activity Creator agent.
	 *
	 * @param string $objective         The learning objective.
	 * @param string $activity_type     The activity type (explore, apply, create).
	 * @param string $work_product      The course work product name.
	 * @param string $work_product_type The work product type (page, post_series, site).
	 * @param string $course_position   Position description within the course.
	 * @param array  $mastery_criteria  The mastery criteria for this lesson.
	 * @param string $activity_seed     Seed data from the lesson planner's suggested activity.
	 * @param string $feedback          Optional feedback for regeneration.
	 * @return string Formatted user message.
	 */
	public function format_input( $objective, $activity_type, $work_product, $work_product_type, $course_position, $mastery_criteria, $activity_seed, $feedback = '' ) {
		$message  = "Learning Objective: {$objective}\n\n";
		$message .= "Activity Type: {$activity_type}\n";
		$message .= "Work Product: {$work_product} (type: {$work_product_type})\n";
		$message .= "Course Position: {$course_position}\n\n";

		$message .= "Mastery Criteria:\n";
		foreach ( $mastery_criteria as $criterion ) {
			$message .= '- ' . $criterion . "\n";
		}

		$message .= "\nActivity Seed:\n{$activity_seed}\n";

		if ( ! empty( $feedback ) ) {
			$message .= "\n---\n\nFeedback from reviewer:\n{$feedback}";
		}

		return $message;
	}
}
