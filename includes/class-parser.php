<?php
/**
 * Agent Response Parser and Validator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Parser
{

    /**
     * Instance of this class
     */
    private static $instance;

    /**
     * Get instance of this class
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
    }

    /**
     * Extract and parse JSON from agent response
     *
     * @param string $text Raw text from agent.
     * @return array|WP_Error
     */
    public function parse_json($text)
    {
        // Try to find JSON block if fenced
        if (preg_match('/```json\s*(.*?)\s*```/s', $text, $matches)) {
            $json_text = $matches[1];
        } elseif (preg_match('/\{(.*)\}/s', $text, $matches)) {
            // Try to find anything between braces
            $json_text = $matches[0];
        } else {
            $json_text = $text;
        }

        $data = json_decode($json_text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Failed to parse JSON from agent response.', '1111-learn'), array('raw' => $text));
        }

        return $data;
    }

    /**
     * Validate response against agent-specific schema
     *
     * @param array  $data  Parsed JSON data.
     * @param string $agent Agent name.
     * @return bool|WP_Error
     */
    public function validate($data, $agent)
    {
        $errors = array();

        switch ($agent) {
            case 'course-describer':
                if (empty($data['narrative_description']) || strlen($data['narrative_description']) < 100) {
                    $errors[] = 'narrative_description is missing or too short.';
                }
                if (empty($data['work_product'])) {
                    $errors[] = 'work_product is missing.';
                }
                if (empty($data['work_product_type']) || !in_array($data['work_product_type'], array('page', 'post_series', 'site'))) {
                    $errors[] = 'invalid work_product_type.';
                }
                if (empty($data['lessons']) || !is_array($data['lessons'])) {
                    $errors[] = 'lessons array is missing or empty.';
                }
                break;

            case 'lesson-planner':
                if (empty($data['learning_objective']))
                    $errors[] = 'learning_objective is missing.';
                if (empty($data['mastery_criteria']) || !is_array($data['mastery_criteria']))
                    $errors[] = 'mastery_criteria is missing or invalid.';
                if (empty($data['lessons']) || !is_array($data['lessons']))
                    $errors[] = 'lessons array is missing or invalid.';
                break;

            case 'lesson-writer':
                if (empty($data['lesson_title']))
                    $errors[] = 'lesson_title is missing.';
                if (empty($data['lesson_body']) || strlen($data['lesson_body']) < 200)
                    $errors[] = 'lesson_body is missing or too short.';
                if (empty($data['key_takeaways']) || !is_array($data['key_takeaways']))
                    $errors[] = 'key_takeaways is missing or invalid.';
                break;

            case 'activity-creator':
                if (empty($data['activity_type']))
                    $errors[] = 'activity_type is missing.';
                if (empty($data['prompt']))
                    $errors[] = 'prompt is missing.';
                if (empty($data['instructions']))
                    $errors[] = 'instructions is missing.';
                if (empty($data['scoring_rubric']))
                    $errors[] = 'scoring_rubric is missing.';
                break;

            case 'activity-reviewer':
                if (empty($data['verdict']) || !in_array($data['verdict'], array('approved', 'revision_needed'))) {
                    $errors[] = 'invalid verdict.';
                }
                break;

            case 'assessment-creator':
                if (empty($data['assessment_title']))
                    $errors[] = 'assessment_title is missing.';
                if (empty($data['portfolio_rubric']))
                    $errors[] = 'portfolio_rubric is missing.';
                break;

            case 'activity-assessment':
                if (!isset($data['score']))
                    $errors[] = 'score is missing.';
                if (empty($data['recommendation']))
                    $errors[] = 'recommendation is missing.';
                break;
        }

        if (!empty($errors)) {
            return new WP_Error('validation_error', __('Agent output validation failed.', '1111-learn'), $errors);
        }

        return true;
    }
}
