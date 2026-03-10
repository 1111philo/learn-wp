<?php
/**
 * Agent Orchestrator
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Orchestrator
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
        add_action('wp_ajax_1111_generate_course', array($this, 'ajax_generate_course'));
        add_action('wp_ajax_1111_generation_status', array($this, 'ajax_generation_status'));
        add_action('wp_ajax_1111_submit_feedback', array($this, 'ajax_submit_feedback'));
        // Background step handler
        add_action('1111_learn_generation_step', array($this, 'execute_step'), 10, 2);
        add_action('1111_learn_run_assessment', array('Learn_Assessment_Engine', 'get_instance'), 0); // Ensure instance exists
        add_action('1111_learn_run_assessment', array(Learn_Assessment_Engine::get_instance(), 'run_assessment'), 10, 3);
    }

    /**
     * Load prompt from Markdown file
     *
     * @param string $agent Agent name (matches filename).
     * @return string|WP_Error
     */
    public function load_prompt($agent, $term_id = 0)
    {
        $file = LEARN_PATH . "prompts/{$agent}.md";
        if (!file_exists($file)) {
            return new WP_Error('missing_prompt', sprintf(__('Prompt file not found: %s', '1111-learn'), "{$agent}.md"));
        }
        $prompt = file_get_contents($file);

        if ($term_id) {
            $feedback = get_term_meta($term_id, "_1111_feedback_{$agent}", true);
            if ($feedback) {
                $prompt .= "\n\n## Feedback for Regeneration\nThe user has provided the following feedback for this step: \"{$feedback}\". Please adjust your output accordingly.";
            }
        }

        return $prompt;
    }

    /**
     * AJAX handler to start course generation
     */
    public function ajax_generate_course()
    {
        check_ajax_referer('1111_learn_generate', 'nonce');

        if (!is_super_admin() || !is_main_site()) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', '1111-learn')));
        }

        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $objectives = array_map('sanitize_text_field', $_POST['objectives']);

        if (empty($title) || empty($description) || empty($objectives)) {
            wp_send_json_error(array('message' => __('Title, description, and at least one objective are required.', '1111-learn')));
        }

        // Create course term
        $term = wp_insert_term($title, 'course', array('description' => $description));
        if (is_wp_error($term)) {
            wp_send_json_error(array('message' => $term->get_error_message()));
        }

        $term_id = $term['term_id'];

        // Store metadata
        update_term_meta($term_id, '_1111_learning_objectives', $objectives);
        update_term_meta($term_id, '_1111_course_description', $description);
        update_term_meta($term_id, '_1111_generation_status', 'generating');
        update_term_meta($term_id, '_1111_generation_date', current_time('iso'));

        // Schedule first step (Phase 0: Course Describer)
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, 'course-describer'));

        wp_send_json_success(array(
            'term_id' => $term_id,
            'message' => __('Course generation started.', '1111-learn')
        ));
    }

    /**
     * Execute a single pipeline step
     */
    public function execute_step($term_id, $agent)
    {
        $status = get_term_meta($term_id, '_1111_generation_status', true);
        if ('failed' === $status)
            return;

        if (strpos($agent, 'lesson-planner-') === 0) {
            $objective_index = (int) str_replace('lesson-planner-', '', $agent);
            $this->step_lesson_planner($term_id, $objective_index);
        } elseif (strpos($agent, 'lesson-writer-') === 0) {
            $parts = explode('-', str_replace('lesson-writer-', '', $agent));
            $this->step_lesson_writer($term_id, (int) $parts[0], (int) $parts[1]);
        } elseif (strpos($agent, 'activity-creator-') === 0) {
            $parts = explode('-', str_replace('activity-creator-', '', $agent));
            $this->step_activity_creator($term_id, (int) $parts[0], (int) $parts[1]);
        } else {
            switch ($agent) {
                case 'course-describer':
                    $this->step_course_describer($term_id);
                    break;
                case 'assessment-creator':
                    $this->step_assessment_creator($term_id);
                    break;
            }
        }
    }

    /**
     * Phase 0: Course Describer
     */
    private function step_course_describer($term_id)
    {
        $term = get_term($term_id, 'course');
        $title = $term->name;
        $description = get_term_meta($term_id, '_1111_course_description', true);
        $objectives = get_term_meta($term_id, '_1111_learning_objectives', true);

        $prompt = $this->load_prompt('course-describer', $term_id);
        if (is_wp_error($prompt)) {
            $this->mark_failed($term_id, $prompt->get_error_message());
            return;
        }

        $user_message = sprintf(
            "Course title: %s\n\nCourse description: %s\n\nLearning objectives:\n%s",
            $title,
            $description,
            implode("\n", $objectives)
        );

        $response = Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );

        Learn_Telemetry::get_instance()->track_event('agent_call', array('agent' => 'course-describer'));

        if (is_wp_error($response)) {
            $this->mark_failed($term_id, $response->get_error_message());
            return;
        }

        $data = Learn_Parser::get_instance()->parse_json($response);
        if (is_wp_error($data)) {
            $this->mark_failed($term_id, $data->get_error_message());
            return;
        }

        $validation = Learn_Parser::get_instance()->validate($data, 'course-describer');
        if (is_wp_error($validation)) {
            $this->mark_failed($term_id, $validation->get_error_message());
            return;
        }

        // Save results
        update_term_meta($term_id, '_1111_narrative_description', $data['narrative_description']);
        update_term_meta($term_id, '_1111_work_product', $data['work_product']);
        update_term_meta($term_id, '_1111_work_product_type', $data['work_product_type']);
        update_term_meta($term_id, '_1111_work_product_description', $data['work_product_description']);
        update_term_meta($term_id, '_1111_lesson_info', $data['lessons']);

        // Move to first objective
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, 'lesson-planner-0'));

        $this->update_progress($term_id, array(
            'phase' => 'lesson',
            'current_objective' => 0,
            'total_objectives' => count($objectives),
            'lesson_titles' => array_column($data['lessons'], 'lesson_title')
        ));
    }

    /**
     * Objective Phase: Lesson Planner
     */
    private function step_lesson_planner($term_id, $index)
    {
        $objectives = get_term_meta($term_id, '_1111_learning_objectives', true);
        if (!isset($objectives[$index])) {
            // No more objectives, go to final assessment
            wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, 'assessment-creator'));
            return;
        }

        $objective = $objectives[$index];
        $narrative = get_term_meta($term_id, '_1111_narrative_description', true);
        $lesson_info = get_term_meta($term_id, '_1111_lesson_info', true);
        $current_lesson_info = isset($lesson_info[$index]) ? $lesson_info[$index] : array();

        $prompt = $this->load_prompt('lesson-planner', $term_id);
        if (is_wp_error($prompt)) {
            $this->mark_failed($term_id, $prompt->get_error_message());
            return;
        }

        $user_message = sprintf(
            "Objective: %s\nCourse Narrative: %s\nLesson Title: %s\nLesson Summary: %s",
            $objective,
            $narrative,
            isset($current_lesson_info['lesson_title']) ? $current_lesson_info['lesson_title'] : '',
            isset($current_lesson_info['lesson_summary']) ? $current_lesson_info['lesson_summary'] : ''
        );

        $response = Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );

        if (is_wp_error($response)) {
            $this->mark_failed($term_id, $response->get_error_message());
            return;
        }

        $data = Learn_Parser::get_instance()->parse_json($response);
        if (is_wp_error($data)) {
            $this->mark_failed($term_id, $data->get_error_message());
            return;
        }

        // Store plan and move to lesson writer
        update_term_meta($term_id, "_1111_lesson_plan_{$index}", $data);
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, "lesson-writer-{$index}-0"));

        $this->update_progress($term_id, array(
            'phase' => 'lesson',
            'current_objective' => $index,
            'status_text' => sprintf(__('Planning lessons for objective: %s', '1111-learn'), $objective)
        ));
    }

    /**
     * Final Phase: Assessment Creator
     */
    private function step_assessment_creator($term_id)
    {
        $term = get_term($term_id, 'course');
        $narrative = get_term_meta($term_id, '_1111_narrative_description', true);
        $objectives = get_term_meta($term_id, '_1111_learning_objectives', true);
        $work_product = get_term_meta($term_id, '_1111_work_product', true);

        $prompt = $this->load_prompt('assessment-creator', $term_id);
        if (is_wp_error($prompt)) {
            $this->mark_failed($term_id, $prompt->get_error_message());
            return;
        }

        $user_message = sprintf(
            "Course: %s\nNarrative: %s\nObjectives: %s\nWork Product: %s",
            $term->name,
            $narrative,
            implode("\n", $objectives),
            $work_product
        );

        $response = Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );

        if (is_wp_error($response)) {
            $this->mark_failed($term_id, $response->get_error_message());
            return;
        }

        $data = Learn_Parser::get_instance()->parse_json($response);
        if (is_wp_error($data)) {
            $this->mark_failed($term_id, $data->get_error_message());
            return;
        }

        // Store assessment data in term meta
        update_term_meta($term_id, '_1111_final_assessment', $data);
        update_term_meta($term_id, '_1111_generation_status', 'complete');

        Learn_Telemetry::get_instance()->record_generation_success($term->name, 0); // TODO: duration

        $this->update_progress($term_id, array(
            'phase' => 'complete',
            'status' => 'complete',
            'status_text' => __('Course generation complete!', '1111-learn')
        ));
    }

    /**
     * Objective Phase: Lesson Writer
     */
    private function step_lesson_writer($term_id, $obj_index, $lsn_index)
    {
        $plan = get_term_meta($term_id, "_1111_lesson_plan_{$obj_index}", true);
        if (!isset($plan['lessons'][$lsn_index])) {
            // No more lessons for this objective, Move to next objective
            wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, 'lesson-planner-' . ($obj_index + 1)));
            return;
        }

        $lesson_plan = $plan['lessons'][$lsn_index];
        $prompt = $this->load_prompt('lesson-writer', $term_id);
        if (is_wp_error($prompt)) {
            $this->mark_failed($term_id, $prompt->get_error_message());
            return;
        }

        $user_message = sprintf(
            "Lesson Title: %s\nLesson Outline: %s\nMastery Criteria: %s",
            $lesson_plan['lesson_title'],
            implode("\n", $lesson_plan['lesson_outline']),
            implode("\n", $plan['mastery_criteria'])
        );

        $response = Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );

        if (is_wp_error($response)) {
            $this->mark_failed($term_id, $response->get_error_message());
            return;
        }

        $data = Learn_Parser::get_instance()->parse_json($response);
        if (is_wp_error($data)) {
            $this->mark_failed($term_id, $data->get_error_message());
            return;
        }

        // Convert Markdown to Blocks
        $content = Learn_Converter::get_instance()->markdown_to_blocks($data['lesson_body']);

        // Create Lesson Post
        $post_id = wp_insert_post(array(
            'post_title' => $data['lesson_title'],
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'learn',
            'post_author' => get_option('1111_learn_agent_user_id'),
            'menu_order' => ($obj_index * 10) + $lsn_index,
        ));

        if (is_wp_error($post_id)) {
            $this->mark_failed($term_id, $post_id->get_error_message());
            return;
        }

        wp_set_object_terms($post_id, $term_id, 'course');
        update_post_meta($post_id, '_1111_key_takeaways', $data['key_takeaways']);
        update_post_meta($post_id, '_1111_objective_index', $obj_index);
        update_post_meta($post_id, '_1111_lesson_index', $lsn_index);

        // Move to Activity Creator
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, "activity-creator-{$obj_index}-{$lsn_index}"));

        $this->update_progress($term_id, array(
            'status_text' => sprintf(__('Writing lesson: %s', '1111-learn'), $data['lesson_title'])
        ));
    }

    /**
     * Objective Phase: Activity Creator
     */
    private function step_activity_creator($term_id, $obj_index, $lsn_index)
    {
        $plan = get_term_meta($term_id, "_1111_lesson_plan_{$obj_index}", true);
        $work_product = get_term_meta($term_id, '_1111_work_product', true);

        $prompt = $this->load_prompt('activity-creator', $term_id);
        if (is_wp_error($prompt)) {
            $this->mark_failed($term_id, $prompt->get_error_message());
            return;
        }

        $user_message = sprintf(
            "Work Product: %s\nActivity Seed: %s\nMastery Criteria: %s\nActivity Type: %s",
            $work_product,
            $plan['suggested_activity']['prompt'],
            implode("\n", $plan['mastery_criteria']),
            $plan['suggested_activity']['activity_type']
        );

        $response = Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );

        if (is_wp_error($response)) {
            $this->mark_failed($term_id, $response->get_error_message());
            return;
        }

        $data = Learn_Parser::get_instance()->parse_json($response);
        if (is_wp_error($data)) {
            $this->mark_failed($term_id, $data->get_error_message());
            return;
        }

        // Find the lesson post to attach activity
        $posts = get_posts(array(
            'post_type' => 'learn',
            'meta_query' => array(
                array('key' => '_1111_objective_index', 'value' => $obj_index),
                array('key' => '_1111_lesson_index', 'value' => $lsn_index),
            ),
            'tax_query' => array(
                array('taxonomy' => 'course', 'field' => 'term_id', 'terms' => $term_id),
            ),
        ));

        if (!empty($posts)) {
            $post_id = $posts[0]->ID;
            update_post_meta($post_id, '_1111_activity_data', $data);
        }

        // Move to next lesson
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, "lesson-writer-{$obj_index}-" . ($lsn_index + 1)));

        $this->update_progress($term_id, array(
            'status_text' => __('Creating activity...', '1111-learn')
        ));
    }

    /**
     * Mark generation as failed
     */
    private function mark_failed($term_id, $message)
    {
        update_term_meta($term_id, '_1111_generation_status', 'failed');
        $this->update_progress($term_id, array('status' => 'failed', 'error' => $message));
    }

    /**
     * Update progress transient
     */
    private function update_progress($term_id, $data)
    {
        $current = get_transient("_1111_generation_progress_{$term_id}");
        $new_data = array_merge(is_array($current) ? $current : array(), $data);
        set_transient("_1111_generation_progress_{$term_id}", $new_data, HOUR_IN_SECONDS);
    }

    /**
     * AJAX handler for status polling
     */
    public function ajax_generation_status()
    {
        $term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
        $status = get_term_meta($term_id, '_1111_generation_status', true);

        // Return progress transient if available
        $progress = get_transient("_1111_generation_progress_{$term_id}");

        wp_send_json_success(array(
            'status' => $status,
            'progress' => $progress ?: array('status' => $status)
        ));
    }

    /**
     * AJAX handler for feedback submission
     */
    public function ajax_submit_feedback()
    {
        check_ajax_referer('1111_learn_editor', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $feedback = sanitize_textarea_field($_POST['feedback']);

        if (!$post_id || empty($feedback)) {
            wp_send_json_error(array('message' => __('Invalid feedback.', '1111-learn')));
        }

        $post = get_post($post_id);
        if (!$post || 'learn' !== $post->post_type) {
            wp_send_json_error(array('message' => __('Invalid post.', '1111-learn')));
        }

        $terms = wp_get_post_terms($post_id, 'course');
        if (empty($terms)) {
            wp_send_json_error(array('message' => __('Post not assigned to a course.', '1111-learn')));
        }

        $term_id = $terms[0]->term_id;
        $obj_index = get_post_meta($post_id, '_1111_objective_index', true);
        $lsn_index = get_post_meta($post_id, '_1111_lesson_index', true);

        // Start regeneration from lesson writer
        $this->trigger_regeneration($term_id, "lesson-writer-{$obj_index}-{$lsn_index}", $feedback);

        wp_send_json_success(array(
            'message' => __('Feedback received. Regeneration started.', '1111-learn')
        ));
    }

    /**
     * Trigger cascading regeneration
     */
    public function trigger_regeneration($term_id, $from_agent, $feedback = '')
    {
        // Store feedback context
        update_term_meta($term_id, "_1111_feedback_{$from_agent}", $feedback);
        update_term_meta($term_id, '_1111_generation_status', 'generating');

        // Identify what to delete
        if (strpos($from_agent, 'lesson-writer-') === 0) {
            $parts = explode('-', str_replace('lesson-writer-', '', $from_agent));
            $obj_index = (int) $parts[0];
            $lsn_index = (int) $parts[1];

            // Delete current lesson post and all downstream lessons in this objective
            $posts = get_posts(array(
                'post_type' => 'learn',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array('key' => '_1111_objective_index', 'value' => $obj_index),
                    array('key' => '_1111_lesson_index', 'value' => $lsn_index, 'compare' => '>='),
                ),
                'tax_query' => array(
                    array('taxonomy' => 'course', 'field' => 'term_id', 'terms' => $term_id),
                ),
            ));

            foreach ($posts as $p) {
                wp_delete_post($p->ID, true);
            }
        }

        // Schedule the step
        wp_schedule_single_event(time(), '1111_learn_generation_step', array($term_id, $from_agent));
    }

    /**
     * Public method to call an agent directly
     */
    public function call_agent($agent, $args)
    {
        $prompt = $this->load_prompt($agent);
        if (is_wp_error($prompt)) {
            return $prompt;
        }

        $user_message = json_encode($args);

        return Learn_API_Client::get_instance()->send_message(
            $prompt,
            $user_message,
            'claude-3-5-sonnet-20241022'
        );
    }
}
