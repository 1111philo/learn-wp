<?php
/**
 * Assessment Creator agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Assessment Creator agent.
 *
 * Takes full course context and produces the user message sent to the
 * Anthropic API for final assessment generation.
 */
class Learn_Agent_Assessment_Creator {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'assessment-creator';

	/**
	 * Model identifier.
	 *
	 * @var string
	 */
	public $model = LEARN_DEFAULT_MODEL;

	/**
	 * Maximum tokens for the response.
	 *
	 * @var int
	 */
	public $max_tokens = LEARN_CONTENT_MAX_TOKENS;

	/**
	 * Format the user message for the Assessment Creator agent.
	 *
	 * @param string $course_title          Course title.
	 * @param string $course_description    Course narrative description.
	 * @param string $work_product          The course work product name.
	 * @param string $work_product_type     The work product type (page, post_series, site).
	 * @param array  $objectives            All learning objectives.
	 * @param array  $all_mastery_criteria  Mastery criteria keyed by objective index.
	 * @param array  $all_activities_summary Summary of all activities and portfolio contributions.
	 * @param string $feedback              Optional feedback for regeneration.
	 * @return string Formatted user message.
	 */
	public function format_input( $course_title, $course_description, $work_product, $work_product_type, $objectives, $all_mastery_criteria, $all_activities_summary, $feedback = '' ) {
		$message  = "Course Title: {$course_title}\n\n";
		$message .= "Course Description:\n{$course_description}\n\n";
		$message .= "Work Product: {$work_product} (type: {$work_product_type})\n\n";

		$message .= "Objectives and Mastery Criteria:\n";
		foreach ( $objectives as $i => $objective ) {
			$number   = $i + 1;
			$message .= "\n{$number}. {$objective}\n";

			if ( isset( $all_mastery_criteria[ $i ] ) && is_array( $all_mastery_criteria[ $i ] ) ) {
				$message .= "   Mastery criteria:\n";
				foreach ( $all_mastery_criteria[ $i ] as $criterion ) {
					$message .= '   - ' . $criterion . "\n";
				}
			}
		}

		$message .= "\nActivities and Portfolio Contributions:\n";
		foreach ( $all_activities_summary as $summary ) {
			$message .= '- ' . $summary . "\n";
		}

		if ( ! empty( $feedback ) ) {
			$message .= "\n---\n\nFeedback from reviewer:\n{$feedback}";
		}

		return $message;
	}
}
