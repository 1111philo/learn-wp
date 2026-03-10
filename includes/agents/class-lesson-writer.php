<?php
/**
 * Lesson Writer agent.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats input for the Lesson Writer agent.
 *
 * Takes lesson planning data and produces the user message sent
 * to the Anthropic API for full lesson content generation.
 */
class Learn_Agent_Lesson_Writer {

	/**
	 * Prompt file name (without extension).
	 *
	 * @var string
	 */
	public $prompt_name = 'lesson-writer';

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
	 * Format the user message for the Lesson Writer agent.
	 *
	 * @param string $course_description Course narrative description.
	 * @param string $lesson_title       The lesson title.
	 * @param array  $lesson_outline     The lesson outline points.
	 * @param array  $mastery_criteria   The mastery criteria for this lesson.
	 * @param array  $key_concepts       The key concepts for this lesson.
	 * @param string $feedback           Optional feedback for regeneration.
	 * @return string Formatted user message.
	 */
	public function format_input( $course_description, $lesson_title, $lesson_outline, $mastery_criteria, $key_concepts, $feedback = '' ) {
		$message  = "Course Description:\n{$course_description}\n\n";
		$message .= "Lesson Title: {$lesson_title}\n\n";

		$message .= "Lesson Outline:\n";
		foreach ( $lesson_outline as $i => $point ) {
			$message .= ( $i + 1 ) . '. ' . $point . "\n";
		}

		$message .= "\nMastery Criteria:\n";
		foreach ( $mastery_criteria as $i => $criterion ) {
			$message .= '- ' . $criterion . "\n";
		}

		$message .= "\nKey Concepts:\n";
		foreach ( $key_concepts as $i => $concept ) {
			$message .= '- ' . $concept . "\n";
		}

		if ( ! empty( $feedback ) ) {
			$message .= "\n---\n\nFeedback from reviewer:\n{$feedback}";
		}

		return $message;
	}
}
