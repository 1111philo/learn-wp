<?php
/**
 * Lesson Planner agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Lesson Planner agent.
 *
 * Takes course context and a single objective, then produces the user
 * message sent to the Anthropic API for lesson planning.
 */
class Learn_Agent_Lesson_Planner {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'lesson-planner';

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
	 * Format the user message for the Lesson Planner agent.
	 *
	 * @param string $course_description Course narrative description.
	 * @param string $work_product       The course work product name.
	 * @param string $work_product_type  The work product type (page, post_series, site).
	 * @param string $objective          The current learning objective.
	 * @param string $preset_title       The preset lesson title from the course describer.
	 * @param int    $lesson_count       Number of lessons to produce for this objective.
	 * @param int    $objective_index    Zero-based index of the current objective.
	 * @param int    $total_objectives   Total number of objectives in the course.
	 * @param array  $all_objectives     All learning objectives for scope control.
	 * @param string $feedback           Optional feedback for regeneration.
	 * @return string Formatted user message.
	 */
	public function format_input( $course_description, $work_product, $work_product_type, $objective, $preset_title, $lesson_count, $objective_index, $total_objectives, $all_objectives, $feedback = '' ) {
		$position = $objective_index + 1;

		$message  = "Course Description:\n{$course_description}\n\n";
		$message .= "Work Product: {$work_product} (type: {$work_product_type})\n\n";
		$message .= "Current Objective ({$position} of {$total_objectives}): {$objective}\n";
		$message .= "Preset Lesson Title: {$preset_title}\n";
		$message .= "Number of lessons to produce: {$lesson_count}\n\n";

		// Scope control: list other objectives to avoid teaching.
		$other_objectives = array();
		foreach ( $all_objectives as $i => $obj ) {
			if ( $i !== $objective_index ) {
				$other_objectives[] = ( $i + 1 ) . '. ' . $obj;
			}
		}

		if ( ! empty( $other_objectives ) ) {
			$message .= "DO NOT teach these (they belong to other lessons):\n";
			$message .= implode( "\n", $other_objectives ) . "\n\n";
		}

		// Activity type guidance based on position in the course.
		if ( 1 === $position ) {
			$message .= "Activity type guidance: This is the first objective. Prefer an \"explore\" activity type.\n";
		} elseif ( $position === $total_objectives ) {
			$message .= "Activity type guidance: This is the final objective. Prefer a \"create\" activity type.\n";
		} else {
			$message .= "Activity type guidance: This is a middle objective. Prefer an \"apply\" activity type.\n";
		}

		if ( ! empty( $feedback ) ) {
			$message .= "\n---\n\nFeedback from reviewer:\n{$feedback}";
		}

		return $message;
	}
}
