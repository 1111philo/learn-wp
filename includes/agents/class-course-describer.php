<?php
/**
 * Course Describer agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Course Describer agent.
 *
 * Takes a course title, description, and objectives and produces
 * the user message sent to the Anthropic API.
 */
class Learn_Agent_Course_Describer {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'course-describer';

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
	 * Format the user message for the Course Describer agent.
	 *
	 * @param string $title       Course title.
	 * @param string $description Course description.
	 * @param array  $objectives  List of learning objectives.
	 * @return string Formatted user message.
	 */
	public function format_input( $title, $description, $objectives ) {
		$count            = count( $objectives );
		$objectives_list  = '';

		foreach ( $objectives as $i => $objective ) {
			$objectives_list .= ( $i + 1 ) . '. ' . $objective . "\n";
		}

		$message  = "Course Title: {$title}\n\n";
		$message .= "Course Description:\n{$description}\n\n";
		$message .= "Learning Objectives ({$count}):\n{$objectives_list}\n";
		$message .= "Produce one lesson entry for each of the {$count} objectives above.";

		return $message;
	}

	/**
	 * Format the user message with feedback for regeneration.
	 *
	 * @param string $title       Course title.
	 * @param string $description Course description.
	 * @param array  $objectives  List of learning objectives.
	 * @param string $feedback    Feedback from the reviewer.
	 * @return string Formatted user message with feedback.
	 */
	public function format_input_with_feedback( $title, $description, $objectives, $feedback ) {
		$message  = $this->format_input( $title, $description, $objectives );
		$message .= "\n\n---\n\nFeedback from reviewer:\n{$feedback}";

		return $message;
	}
}
