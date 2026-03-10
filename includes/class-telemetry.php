<?php
/**
 * Telemetry and System Monitoring
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Telemetry
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
     * Track an event
     */
    public function track_event($event_name, $details = array())
    {
        $events = get_option('_1111_learn_telemetry', array());

        $new_event = array(
            'event' => $event_name,
            'timestamp' => current_time('mysql'),
            'details' => $details
        );

        $events[] = $new_event;

        // Keep only last 1000 events to avoid bloating options table
        if (count($events) > 1000) {
            array_shift($events);
        }

        update_option('_1111_learn_telemetry', $events, false);
    }

    /**
     * Get telemetry data
     */
    public function get_events()
    {
        return get_option('_1111_learn_telemetry', array());
    }

    /**
     * helper to record generation success
     */
    public function record_generation_success($course_name, $duration)
    {
        $this->track_event('course_generation_success', array(
            'course' => $course_name,
            'duration' => $duration
        ));
    }

    /**
     * helper to record generation failure
     */
    public function record_generation_failure($course_name, $error)
    {
        $this->track_event('course_generation_failure', array(
            'course' => $course_name,
            'error' => $error
        ));
    }
}
