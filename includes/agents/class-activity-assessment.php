<?php
/**
 * Activity Assessment agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Activity Assessment agent.
 *
 * Takes a learner's submitted WordPress content and the activity context,
 * then produces the user message sent to the Anthropic API for assessment.
 */
class Learn_Agent_Activity_Assessment {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'activity-assessment';

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
	public $max_tokens = LEARN_PLAN_MAX_TOKENS;

	/**
	 * Format the user message for the Activity Assessment agent.
	 *
	 * @param string $activity_type          The activity type (explore, apply, create, final).
	 * @param string $objective              The learning objective.
	 * @param array  $mastery_criteria       The mastery criteria for this activity.
	 * @param array  $scoring_rubric         The scoring rubric criteria.
	 * @param string $work_product           The course work product name.
	 * @param string $work_product_type      The work product type (page, post_series, site).
	 * @param string $portfolio_contribution What this activity contributes to the portfolio.
	 * @param string $learner_content_title  The title of the learner's submitted content.
	 * @param string $learner_content_body   The body of the learner's submitted content.
	 * @return string Formatted user message.
	 */
	public function format_input( $activity_type, $objective, $mastery_criteria, $scoring_rubric, $work_product, $work_product_type, $portfolio_contribution, $learner_content_title, $learner_content_body ) {
		$message  = "Activity Type: {$activity_type}\n";
		$message .= "Learning Objective: {$objective}\n";
		$message .= "Work Product: {$work_product} (type: {$work_product_type})\n";
		$message .= "Portfolio Contribution: {$portfolio_contribution}\n\n";

		$message .= "Mastery Criteria:\n";
		foreach ( $mastery_criteria as $criterion ) {
			$message .= '- ' . $criterion . "\n";
		}

		$message .= "\nScoring Rubric:\n";
		foreach ( $scoring_rubric as $rubric_item ) {
			$message .= '- ' . $rubric_item . "\n";
		}

		$message .= "\n---\n\nLearner Submission:\n";
		$message .= "Title: {$learner_content_title}\n\n";
		$message .= "Body:\n{$learner_content_body}\n";

		return $message;
	}
}
