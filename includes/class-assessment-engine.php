<?php
/**
 * AI-driven Assessment Engine
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Assessment_Engine
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
        add_action('1111_learn_lesson_completed', array($this, 'maybe_trigger_assessment'), 10, 3);
    }

    /**
     * Trigger assessment if it's an activity
     */
    public function maybe_trigger_assessment($user_id, $course_id, $post_id)
    {
        $activity_data = get_post_meta($post_id, '_1111_activity_data', true);
        if (empty($activity_data['activity_type'])) {
            return;
        }

        // In a real scenario, this would be a background job.
        // For this POC, we'll trigger it immediately or via a cron hook.
        wp_schedule_single_event(time() + 5, '1111_learn_run_assessment', array($user_id, $course_id, $post_id));
    }

    /**
     * Run the AI assessment
     */
    public function run_assessment($user_id, $course_id, $post_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . '1111_learn_submissions';

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND lesson_post_id = %d ORDER BY submitted_at DESC LIMIT 1",
            $user_id,
            $post_id
        ));

        if (!$submission) {
            return;
        }

        $activity_data = get_post_meta($post_id, '_1111_activity_data', true);

        // Prepare prompt
        $prompt_args = array(
            'instructions' => $activity_data['instructions'],
            'rubric' => $activity_data['rubric'],
            'submission' => $submission->content_snapshot
        );

        $response = Learn_Orchestrator::get_instance()->call_agent('activity-assessment', $prompt_args);

        if (is_wp_error($response)) {
            return;
        }

        $assessment = json_decode($response, true);
        if (!$assessment) {
            return;
        }

        // Update submission
        $wpdb->update(
            $table_name,
            array(
                'score' => $assessment['score'],
                'recommendation' => $assessment['recommendation'],
                'assessment_json' => $response,
                'assessed_at' => current_time('mysql'),
                'status' => 'completed'
            ),
            array('id' => $submission->id)
        );

        // Track telemetry
        Learn_Telemetry::get_instance()->track_event('activity_assessed', array(
            'user_id' => $user_id,
            'course_id' => $course_id,
            'post_id' => $post_id,
            'score' => $assessment['score']
        ));
    }
}
