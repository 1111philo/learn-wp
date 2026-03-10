<?php
/**
 * Seven-agent pipeline orchestrator.
 *
 * Coordinates all agents, handles step-by-step generation, and stores
 * progress for the course creation pipeline.
 *
 * @package Jesuspended\Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the seven-agent course generation pipeline.
 */
class Learn_Orchestrator {

	/**
	 * Anthropic API client.
	 *
	 * @var Learn_API_Client
	 */
	private $api_client;

	/**
	 * Prompt loader for agent system prompts.
	 *
	 * @var Learn_Prompt_Loader
	 */
	private $prompt_loader;

	/**
	 * JSON validator for agent output.
	 *
	 * @var Learn_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Learn_API_Client    $api_client    API client instance.
	 * @param Learn_Prompt_Loader $prompt_loader Prompt loader instance.
	 * @param Learn_Validator     $validator     Validator instance.
	 */
	public function __construct( Learn_API_Client $api_client, Learn_Prompt_Loader $prompt_loader, Learn_Validator $validator ) {
		$this->api_client    = $api_client;
		$this->prompt_loader = $prompt_loader;
		$this->validator     = $validator;
	}

	/**
	 * Generate a complete course from title, description, and objectives.
	 *
	 * Called from the AJAX handler to kick off the full pipeline.
	 *
	 * @param int    $term_id     Course taxonomy term ID.
	 * @param string $title       Course title.
	 * @param string $description Course description.
	 * @param array  $objectives  List of learning objective strings.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function generate_course( $term_id, $title, $description, $objectives ) {
		update_term_meta( $term_id, '_1111_generation_status', 'generating' );
		update_term_meta( $term_id, '_1111_generation_date', gmdate( 'c' ) );

		try {
			// Phase 0: Course Describer.
			$course_data = $this->run_phase_0( $term_id, $title, $description, $objectives );

			if ( is_wp_error( $course_data ) ) {
				update_term_meta( $term_id, '_1111_generation_status', 'failed' );
				return $course_data;
			}

			// Per-objective: Lesson Planner + Lesson Writer + Activity Creator + Activity Reviewer.
			$all_activities = array();

			foreach ( $objectives as $index => $objective ) {
				$result = $this->run_objective( $term_id, $index, $objective, $course_data );

				if ( is_wp_error( $result ) ) {
					update_term_meta( $term_id, '_1111_generation_status', 'failed' );
					return $result;
				}

				if ( is_array( $result ) ) {
					$all_activities = array_merge( $all_activities, $result );
				}
			}

			// Phase Final: Assessment Creator.
			$assessment = $this->run_assessment( $term_id, $course_data, $all_activities );

			if ( is_wp_error( $assessment ) ) {
				update_term_meta( $term_id, '_1111_generation_status', 'failed' );
				return $assessment;
			}

			update_term_meta( $term_id, '_1111_generation_status', 'complete' );

			/**
			 * Fires after a course has been fully generated.
			 *
			 * @param int   $term_id     Course term ID.
			 * @param array $course_data Course Describer output.
			 */
			do_action( '1111_learn_course_complete', $term_id, $course_data );

			return true;

		} catch ( \Exception $e ) {
			update_term_meta( $term_id, '_1111_generation_status', 'failed' );

			return new WP_Error(
				'generation_exception',
				/* translators: %s: exception message */
				sprintf( __( 'Course generation failed: %s', '1111-learn' ), $e->getMessage() )
			);
		}
	}

	/**
	 * Phase 0: Run the Course Describer agent.
	 *
	 * Establishes the narrative arc, lesson titles, and work product.
	 *
	 * @param int    $term_id     Course taxonomy term ID.
	 * @param string $title       Course title.
	 * @param string $description Course description.
	 * @param array  $objectives  List of learning objective strings.
	 * @return array|WP_Error Parsed course describer output, or WP_Error on failure.
	 */
	public function run_phase_0( $term_id, $title, $description, $objectives ) {
		/**
		 * Fires before the Course Describer agent runs.
		 *
		 * @param int    $term_id     Course term ID.
		 * @param string $title       Course title.
		 * @param string $description Course description.
		 * @param array  $objectives  Learning objectives.
		 */
		do_action( '1111_learn_before_describe', $term_id, $title, $description, $objectives );

		$objectives_list = '';
		foreach ( $objectives as $i => $obj ) {
			$objectives_list .= ( $i + 1 ) . '. ' . $obj . "\n";
		}

		$user_message = sprintf(
			"Course Title: %s\n\nCourse Description: %s\n\nLearning Objectives:\n%s",
			$title,
			$description,
			$objectives_list
		);

		$data = $this->call_agent( 'course_describer', LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS, $user_message );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Store course describer output on the course term.
		if ( isset( $data['narrative_description'] ) ) {
			update_term_meta( $term_id, '_1111_narrative_description', $data['narrative_description'] );
		}

		if ( isset( $data['lessons'] ) ) {
			update_term_meta( $term_id, '_1111_lesson_titles', $data['lessons'] );
		}

		if ( isset( $data['work_product'] ) ) {
			update_term_meta( $term_id, '_1111_work_product', $data['work_product'] );
		}

		if ( isset( $data['work_product_type'] ) ) {
			update_term_meta( $term_id, '_1111_work_product_type', $data['work_product_type'] );
		}

		if ( isset( $data['work_product_description'] ) ) {
			update_term_meta( $term_id, '_1111_work_product_description', $data['work_product_description'] );
		}

		$this->update_progress( $term_id, array(
			'phase'         => 'describe',
			'status'        => 'complete',
			'lesson_titles' => isset( $data['lessons'] ) ? $data['lessons'] : array(),
			'work_product'  => isset( $data['work_product'] ) ? $data['work_product'] : '',
		) );

		/**
		 * Fires after the Course Describer agent completes.
		 *
		 * @param int   $term_id Course term ID.
		 * @param array $data    Course Describer output.
		 */
		do_action( '1111_learn_course_described', $term_id, $data );

		return $data;
	}

	/**
	 * Run the Lesson Planner and downstream agents for one objective.
	 *
	 * @param int    $term_id         Course taxonomy term ID.
	 * @param int    $objective_index Zero-based objective index.
	 * @param string $objective       The learning objective.
	 * @param array  $course_data     Course Describer output.
	 * @return array|WP_Error Array of activity data from all lessons, or WP_Error.
	 */
	public function run_objective( $term_id, $objective_index, $objective, $course_data ) {
		/**
		 * Fires before the Lesson Planner agent runs for an objective.
		 *
		 * @param int    $term_id         Course term ID.
		 * @param int    $objective_index Objective index.
		 * @param string $objective       Learning objective.
		 */
		do_action( '1111_learn_before_plan', $term_id, $objective_index, $objective );

		$all_objectives_list = '';
		if ( isset( $course_data['objectives'] ) && is_array( $course_data['objectives'] ) ) {
			foreach ( $course_data['objectives'] as $i => $obj ) {
				$marker               = ( $i === $objective_index ) ? ' (CURRENT)' : '';
				$all_objectives_list .= ( $i + 1 ) . '. ' . $obj . $marker . "\n";
			}
		}

		$lesson_titles = isset( $course_data['lessons'] ) ? $course_data['lessons'] : array();
		$preset_titles = '';
		foreach ( $lesson_titles as $lt ) {
			if ( isset( $lt['lesson_title'] ) ) {
				$preset_titles .= '- ' . $lt['lesson_title'] . "\n";
			}
		}

		$narrative     = isset( $course_data['narrative_description'] ) ? $course_data['narrative_description'] : '';
		$work_product  = isset( $course_data['work_product'] ) ? $course_data['work_product'] : '';
		$wp_type       = isset( $course_data['work_product_type'] ) ? $course_data['work_product_type'] : '';
		$wp_desc       = isset( $course_data['work_product_description'] ) ? $course_data['work_product_description'] : '';

		$user_message = sprintf(
			"Learning Objective: %s\n\nNarrative Description:\n%s\n\nAll Objectives (for scope control — cover ONLY the current objective):\n%s\n\nPreset Lesson Titles:\n%s\n\nTarget Lesson Count: %d\n\nWork Product: %s\nWork Product Type: %s\nWork Product Description: %s",
			$objective,
			$narrative,
			$all_objectives_list,
			$preset_titles,
			LEARN_LESSONS_PER_OBJECTIVE,
			$work_product,
			$wp_type,
			$wp_desc
		);

		$plan_data = $this->call_agent( 'lesson_planner', LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS, $user_message );

		if ( is_wp_error( $plan_data ) ) {
			return $plan_data;
		}

		// Create lesson_group tag for this objective.
		$group_name = sprintf(
			/* translators: 1: objective index, 2: objective text */
			__( 'Objective %1$d: %2$s', '1111-learn' ),
			$objective_index + 1,
			$objective
		);

		$group_result = wp_insert_term( $group_name, 'lesson_group' );

		if ( is_wp_error( $group_result ) ) {
			return $group_result;
		}

		$lesson_group_id = $group_result['term_id'];

		// Store plan data on the lesson group.
		update_term_meta( $lesson_group_id, '_1111_lesson_plan_raw', $plan_data );
		update_term_meta( $lesson_group_id, '_1111_learning_objective', $objective );

		$lessons     = isset( $plan_data['lessons'] ) ? $plan_data['lessons'] : array();
		$lesson_count = count( $lessons );

		update_term_meta( $lesson_group_id, '_1111_lesson_count', $lesson_count );

		/**
		 * Fires after the Lesson Planner agent completes for an objective.
		 *
		 * @param int   $term_id         Course term ID.
		 * @param int   $lesson_group_id Lesson group term ID.
		 * @param array $plan_data       Lesson Planner output.
		 */
		do_action( '1111_learn_lesson_planned', $term_id, $lesson_group_id, $plan_data );

		$this->update_progress( $term_id, array(
			'phase'           => 'plan',
			'objective_index' => $objective_index,
			'status'          => 'complete',
			'lesson_count'    => $lesson_count,
		) );

		// Run each lesson through Lesson Writer + Activity Creator + Activity Reviewer.
		$activities = array();

		foreach ( $lessons as $lesson_index => $lesson_data ) {
			$result = $this->run_lesson(
				$term_id,
				$lesson_group_id,
				$lesson_index,
				$lesson_data,
				$plan_data,
				$course_data
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( isset( $result['activity'] ) ) {
				$activities[] = $result['activity'];
			}
		}

		return $activities;
	}

	/**
	 * Run Lesson Writer, Activity Creator, and Activity Reviewer for one lesson.
	 *
	 * @param int   $term_id         Course taxonomy term ID.
	 * @param int   $lesson_group_id Lesson group term ID.
	 * @param int   $lesson_index    Zero-based lesson index within the group.
	 * @param array $lesson_data     Lesson plan data for this lesson.
	 * @param array $plan_data       Full Lesson Planner output.
	 * @param array $course_data     Course Describer output.
	 * @return array|WP_Error Result array with post_id and activity, or WP_Error.
	 */
	public function run_lesson( $term_id, $lesson_group_id, $lesson_index, $lesson_data, $plan_data, $course_data ) {
		/**
		 * Fires before the Lesson Writer agent runs.
		 *
		 * @param int   $term_id         Course term ID.
		 * @param int   $lesson_group_id Lesson group term ID.
		 * @param int   $lesson_index    Lesson index.
		 * @param array $lesson_data     Lesson plan data.
		 */
		do_action( '1111_learn_before_write', $term_id, $lesson_group_id, $lesson_index, $lesson_data );

		$narrative    = isset( $course_data['narrative_description'] ) ? $course_data['narrative_description'] : '';
		$work_product = isset( $course_data['work_product'] ) ? $course_data['work_product'] : '';

		$writer_message = sprintf(
			"Lesson Outline:\n%s\n\nNarrative Description:\n%s\n\nWork Product: %s\n\nMastery Criteria: %s\n\nKey Concepts: %s",
			wp_json_encode( $lesson_data ),
			$narrative,
			$work_product,
			isset( $plan_data['mastery_criteria'] ) ? $plan_data['mastery_criteria'] : '',
			isset( $lesson_data['key_concepts'] ) ? wp_json_encode( $lesson_data['key_concepts'] ) : ''
		);

		// Agent 3: Lesson Writer.
		$writer_data = $this->call_agent( 'lesson_writer', LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS, $writer_message );

		if ( is_wp_error( $writer_data ) ) {
			return $writer_data;
		}

		// Safety check on lesson content.
		if ( isset( $writer_data['lesson_body'] ) ) {
			$safety_result = $this->validator->check_safety( $writer_data['lesson_body'] );

			if ( is_wp_error( $safety_result ) ) {
				return $safety_result;
			}
		}

		/**
		 * Fires after the Lesson Writer agent completes.
		 *
		 * @param int   $term_id     Course term ID.
		 * @param array $writer_data Lesson Writer output.
		 */
		do_action( '1111_learn_lesson_written', $term_id, $writer_data );

		// Agent 4: Activity Creator.
		$activity_data = $this->run_activity_creator( $term_id, $lesson_data, $plan_data, $course_data, $writer_data );

		if ( is_wp_error( $activity_data ) ) {
			return $activity_data;
		}

		// Agent 5: Activity Reviewer.
		$review_data = $this->run_activity_reviewer( $term_id, $activity_data, $lesson_data, $plan_data );

		if ( is_wp_error( $review_data ) ) {
			return $review_data;
		}

		// If reviewer says revision_needed, retry Activity Creator once.
		if ( isset( $review_data['verdict'] ) && 'revision_needed' === $review_data['verdict'] ) {
			$suggestions = isset( $review_data['suggestions'] ) ? $review_data['suggestions'] : '';

			$retry_data = $this->run_activity_creator(
				$term_id,
				$lesson_data,
				$plan_data,
				$course_data,
				$writer_data,
				$suggestions
			);

			if ( ! is_wp_error( $retry_data ) ) {
				$activity_data = $retry_data;

				// Re-review the revised activity.
				$review_data = $this->run_activity_reviewer( $term_id, $activity_data, $lesson_data, $plan_data );

				if ( is_wp_error( $review_data ) ) {
					// Use the activity as-is if re-review fails.
					$review_data = array( 'verdict' => 'approved' );
				}
			}
		}

		// Create the WordPress draft post.
		$post_id = $this->create_lesson_post(
			$term_id,
			$lesson_group_id,
			$lesson_index,
			$writer_data,
			$activity_data,
			$review_data,
			$plan_data,
			$course_data
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		/**
		 * Fires after a lesson post has been created.
		 *
		 * @param int   $post_id      The new post ID.
		 * @param int   $term_id      Course term ID.
		 * @param array $writer_data  Lesson Writer output.
		 * @param array $activity_data Activity Creator output.
		 */
		do_action( '1111_learn_lesson_created', $post_id, $term_id, $writer_data, $activity_data );

		$this->update_progress( $term_id, array(
			'phase'        => 'lesson',
			'lesson_index' => $lesson_index,
			'status'       => 'complete',
			'post_id'      => $post_id,
		) );

		return array(
			'post_id'  => $post_id,
			'activity' => $activity_data,
		);
	}

	/**
	 * Run the Activity Creator agent.
	 *
	 * @param int         $term_id     Course term ID.
	 * @param array       $lesson_data Lesson plan data.
	 * @param array       $plan_data   Lesson Planner output.
	 * @param array       $course_data Course Describer output.
	 * @param array       $writer_data Lesson Writer output.
	 * @param string|null $suggestions Reviewer suggestions for retry, or null.
	 * @return array|WP_Error Activity Creator output or WP_Error.
	 */
	private function run_activity_creator( $term_id, $lesson_data, $plan_data, $course_data, $writer_data, $suggestions = null ) {
		/**
		 * Fires before the Activity Creator agent runs.
		 *
		 * @param int   $term_id     Course term ID.
		 * @param array $lesson_data Lesson plan data.
		 */
		do_action( '1111_learn_before_activity', $term_id, $lesson_data );

		$work_product = isset( $course_data['work_product'] ) ? $course_data['work_product'] : '';
		$wp_type      = isset( $course_data['work_product_type'] ) ? $course_data['work_product_type'] : '';
		$mastery      = isset( $plan_data['mastery_criteria'] ) ? $plan_data['mastery_criteria'] : '';
		$activity_seed = isset( $plan_data['activity_seed'] ) ? wp_json_encode( $plan_data['activity_seed'] ) : '';
		$key_takeaways = isset( $writer_data['key_takeaways'] ) ? wp_json_encode( $writer_data['key_takeaways'] ) : '';
		$activity_type = isset( $lesson_data['activity_type'] ) ? $lesson_data['activity_type'] : 'explore';

		$activity_message = sprintf(
			"Lesson Outline:\n%s\n\nActivity Type: %s\nActivity Seed:\n%s\n\nMastery Criteria: %s\n\nKey Takeaways:\n%s\n\nWork Product: %s\nWork Product Type: %s",
			wp_json_encode( $lesson_data ),
			$activity_type,
			$activity_seed,
			$mastery,
			$key_takeaways,
			$work_product,
			$wp_type
		);

		if ( null !== $suggestions ) {
			$activity_message .= sprintf(
				"\n\nReviewer Suggestions (address these in the revision):\n%s",
				is_array( $suggestions ) ? wp_json_encode( $suggestions ) : $suggestions
			);
		}

		$data = $this->call_agent( 'activity_creator', LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS, $activity_message );

		if ( ! is_wp_error( $data ) ) {
			/**
			 * Fires after the Activity Creator agent completes.
			 *
			 * @param int   $term_id Course term ID.
			 * @param array $data    Activity Creator output.
			 */
			do_action( '1111_learn_activity_created', $term_id, $data );
		}

		return $data;
	}

	/**
	 * Run the Activity Reviewer agent.
	 *
	 * @param int   $term_id       Course term ID.
	 * @param array $activity_data Activity Creator output.
	 * @param array $lesson_data   Lesson plan data.
	 * @param array $plan_data     Lesson Planner output.
	 * @return array|WP_Error Activity Reviewer output or WP_Error.
	 */
	private function run_activity_reviewer( $term_id, $activity_data, $lesson_data, $plan_data ) {
		$mastery       = isset( $plan_data['mastery_criteria'] ) ? $plan_data['mastery_criteria'] : '';
		$activity_type = isset( $lesson_data['activity_type'] ) ? $lesson_data['activity_type'] : 'explore';

		$review_message = sprintf(
			"Activity to Review:\n%s\n\nActivity Type: %s\nMastery Criteria: %s\n\nLesson Outline:\n%s",
			wp_json_encode( $activity_data ),
			$activity_type,
			$mastery,
			wp_json_encode( $lesson_data )
		);

		return $this->call_agent( 'activity_reviewer', LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS, $review_message );
	}

	/**
	 * Create a WordPress draft post for a generated lesson.
	 *
	 * @param int   $term_id         Course term ID.
	 * @param int   $lesson_group_id Lesson group term ID.
	 * @param int   $lesson_index    Lesson index within the group.
	 * @param array $writer_data     Lesson Writer output.
	 * @param array $activity_data   Activity Creator output.
	 * @param array $review_data     Activity Reviewer output.
	 * @param array $plan_data       Lesson Planner output.
	 * @param array $course_data     Course Describer output.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	private function create_lesson_post( $term_id, $lesson_group_id, $lesson_index, $writer_data, $activity_data, $review_data, $plan_data, $course_data ) {
		$agent_user_id = Learn_Agent_User::get_instance()->get_agent_user_id();

		if ( ! $agent_user_id ) {
			return new WP_Error(
				'no_agent_user',
				__( 'Agent user not found. Please reactivate the plugin.', '1111-learn' )
			);
		}

		$title        = isset( $writer_data['title'] ) ? $writer_data['title'] : '';
		$lesson_body  = isset( $writer_data['lesson_body'] ) ? $writer_data['lesson_body'] : '';
		$post_content = $this->markdown_to_blocks( $lesson_body );

		$post_data = array(
			'post_type'    => 'learn',
			'post_author'  => $agent_user_id,
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => $post_content,
			'post_status'  => 'draft',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Assign to course taxonomy and lesson_group tag.
		wp_set_object_terms( $post_id, array( (int) $term_id ), 'course' );
		wp_set_object_terms( $post_id, array( (int) $lesson_group_id ), 'lesson_group' );

		// Store all post meta.
		$objective_index = isset( $plan_data['objective_index'] ) ? $plan_data['objective_index'] : 0;
		$objective       = isset( $plan_data['learning_objective'] ) ? $plan_data['learning_objective'] : '';

		update_post_meta( $post_id, '_1111_objective_index', $objective_index );
		update_post_meta( $post_id, '_1111_lesson_order', $lesson_index );
		update_post_meta( $post_id, '_1111_learning_objective', $objective );

		if ( isset( $writer_data['key_concepts'] ) ) {
			update_post_meta( $post_id, '_1111_key_concepts', $writer_data['key_concepts'] );
		}

		if ( isset( $plan_data['mastery_criteria'] ) ) {
			update_post_meta( $post_id, '_1111_mastery_criteria', $plan_data['mastery_criteria'] );
		}

		if ( isset( $writer_data['key_takeaways'] ) ) {
			update_post_meta( $post_id, '_1111_key_takeaways', $writer_data['key_takeaways'] );
		}

		update_post_meta( $post_id, '_1111_activity', $activity_data );
		update_post_meta( $post_id, '_1111_activity_type', isset( $activity_data['activity_type'] ) ? $activity_data['activity_type'] : 'explore' );
		update_post_meta( $post_id, '_1111_activity_review', $review_data );
		update_post_meta( $post_id, '_1111_xp_value', isset( $activity_data['xp_value'] ) ? (int) $activity_data['xp_value'] : 0 );
		update_post_meta( $post_id, '_1111_milestone', isset( $activity_data['milestone'] ) ? $activity_data['milestone'] : '' );
		update_post_meta( $post_id, '_1111_portfolio_contribution', isset( $activity_data['portfolio_contribution'] ) ? $activity_data['portfolio_contribution'] : '' );
		update_post_meta( $post_id, '_1111_generated', true );
		update_post_meta( $post_id, '_1111_lesson_version', 1 );
		update_post_meta( $post_id, '_1111_activity_version', 1 );

		return $post_id;
	}

	/**
	 * Phase Final: Run the Assessment Creator agent.
	 *
	 * Creates a summative assessment across all objectives.
	 *
	 * @param int   $term_id        Course taxonomy term ID.
	 * @param array $course_data    Course Describer output.
	 * @param array $all_activities All activity data from the course.
	 * @return array|WP_Error Assessment Creator output or WP_Error.
	 */
	public function run_assessment( $term_id, $course_data, $all_activities ) {
		$narrative     = isset( $course_data['narrative_description'] ) ? $course_data['narrative_description'] : '';
		$work_product  = isset( $course_data['work_product'] ) ? $course_data['work_product'] : '';
		$wp_type       = isset( $course_data['work_product_type'] ) ? $course_data['work_product_type'] : '';
		$wp_desc       = isset( $course_data['work_product_description'] ) ? $course_data['work_product_description'] : '';

		$objectives_summary = '';
		if ( isset( $course_data['objectives'] ) && is_array( $course_data['objectives'] ) ) {
			foreach ( $course_data['objectives'] as $i => $obj ) {
				$objectives_summary .= ( $i + 1 ) . '. ' . $obj . "\n";
			}
		}

		$activities_summary = wp_json_encode( $all_activities );

		$assessment_message = sprintf(
			"Narrative Description:\n%s\n\nLearning Objectives:\n%s\n\nWork Product: %s\nWork Product Type: %s\nWork Product Description: %s\n\nAll Activities:\n%s",
			$narrative,
			$objectives_summary,
			$work_product,
			$wp_type,
			$wp_desc,
			$activities_summary
		);

		$data = $this->call_agent( 'assessment_creator', LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS, $assessment_message );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Create assessment draft post.
		$agent_user_id = Learn_Agent_User::get_instance()->get_agent_user_id();

		if ( ! $agent_user_id ) {
			return new WP_Error(
				'no_agent_user',
				__( 'Agent user not found. Please reactivate the plugin.', '1111-learn' )
			);
		}

		$assessment_title   = isset( $data['title'] ) ? $data['title'] : __( 'Final Assessment', '1111-learn' );
		$assessment_body    = isset( $data['assessment_body'] ) ? $data['assessment_body'] : '';
		$assessment_content = $this->markdown_to_blocks( $assessment_body );

		$post_data = array(
			'post_type'    => 'learn',
			'post_author'  => $agent_user_id,
			'post_title'   => sanitize_text_field( $assessment_title ),
			'post_content' => $assessment_content,
			'post_status'  => 'draft',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Assign to course taxonomy.
		wp_set_object_terms( $post_id, array( (int) $term_id ), 'course' );

		// Store assessment meta.
		update_post_meta( $post_id, '_1111_activity_type', 'final' );
		update_post_meta( $post_id, '_1111_generated', true );
		update_post_meta( $post_id, '_1111_activity', $data );

		$assessment_xp = isset( $data['xp_value'] ) ? (int) $data['xp_value'] : 0;
		update_post_meta( $post_id, '_1111_xp_value', $assessment_xp );

		// Store assessment spec on course term.
		update_term_meta( $term_id, '_1111_assessment', $data );
		update_term_meta( $term_id, '_1111_assessment_post_id', $post_id );

		// Calculate total XP across all activities + assessment.
		$total_xp = $assessment_xp;
		foreach ( $all_activities as $activity ) {
			if ( isset( $activity['xp_value'] ) ) {
				$total_xp += (int) $activity['xp_value'];
			}
		}
		update_term_meta( $term_id, '_1111_total_xp', $total_xp );

		$this->update_progress( $term_id, array(
			'phase'    => 'assessment',
			'status'   => 'complete',
			'post_id'  => $post_id,
			'total_xp' => $total_xp,
		) );

		return $data;
	}

	/**
	 * Convert markdown to WordPress block markup.
	 *
	 * Handles headings, paragraphs, unordered lists, ordered lists, and code blocks.
	 *
	 * @param string $markdown Markdown content.
	 * @return string WordPress block markup.
	 */
	public function markdown_to_blocks( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}

		$lines  = explode( "\n", $markdown );
		$blocks = array();
		$i      = 0;
		$count  = count( $lines );

		while ( $i < $count ) {
			$line = $lines[ $i ];

			// Code block (fenced).
			if ( preg_match( '/^```/', $line ) ) {
				$code_lines = array();
				$i++;

				while ( $i < $count && ! preg_match( '/^```/', $lines[ $i ] ) ) {
					$code_lines[] = $lines[ $i ];
					$i++;
				}

				$i++; // Skip closing ```.
				$code = esc_html( implode( "\n", $code_lines ) );
				$blocks[] = '<!-- wp:code -->' . "\n" . '<pre class="wp-block-code"><code>' . $code . '</code></pre>' . "\n" . '<!-- /wp:code -->';
				continue;
			}

			// Heading level 2.
			if ( preg_match( '/^## (.+)$/', $line, $matches ) ) {
				$text     = esc_html( trim( $matches[1] ) );
				$blocks[] = '<!-- wp:heading {"level":2} -->' . "\n" . '<h2 class="wp-block-heading">' . $text . '</h2>' . "\n" . '<!-- /wp:heading -->';
				$i++;
				continue;
			}

			// Heading level 3.
			if ( preg_match( '/^### (.+)$/', $line, $matches ) ) {
				$text     = esc_html( trim( $matches[1] ) );
				$blocks[] = '<!-- wp:heading {"level":3} -->' . "\n" . '<h3 class="wp-block-heading">' . $text . '</h3>' . "\n" . '<!-- /wp:heading -->';
				$i++;
				continue;
			}

			// Unordered list.
			if ( preg_match( '/^[\-\*] (.+)$/', $line ) ) {
				$items = array();

				while ( $i < $count && preg_match( '/^[\-\*] (.+)$/', $lines[ $i ], $matches ) ) {
					$items[] = '<li>' . esc_html( trim( $matches[1] ) ) . '</li>';
					$i++;
				}

				$blocks[] = '<!-- wp:list -->' . "\n" . '<ul class="wp-block-list">' . implode( '', $items ) . '</ul>' . "\n" . '<!-- /wp:list -->';
				continue;
			}

			// Ordered list.
			if ( preg_match( '/^\d+\. (.+)$/', $line ) ) {
				$items = array();

				while ( $i < $count && preg_match( '/^\d+\. (.+)$/', $lines[ $i ], $matches ) ) {
					$items[] = '<li>' . esc_html( trim( $matches[1] ) ) . '</li>';
					$i++;
				}

				$blocks[] = '<!-- wp:list {"ordered":true} -->' . "\n" . '<ol class="wp-block-list">' . implode( '', $items ) . '</ol>' . "\n" . '<!-- /wp:list -->';
				continue;
			}

			// Empty line — skip.
			if ( '' === trim( $line ) ) {
				$i++;
				continue;
			}

			// Paragraph (default).
			$text     = esc_html( trim( $line ) );
			$blocks[] = '<!-- wp:paragraph -->' . "\n" . '<p>' . $text . '</p>' . "\n" . '<!-- /wp:paragraph -->';
			$i++;
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Store generation progress in a transient.
	 *
	 * @param int   $term_id Course taxonomy term ID.
	 * @param array $data    Progress data to store.
	 */
	public function update_progress( $term_id, $data ) {
		$transient_key = '_1111_generation_progress_' . (int) $term_id;
		$existing      = get_transient( $transient_key );

		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		$existing[] = array_merge(
			$data,
			array( 'timestamp' => gmdate( 'c' ) )
		);

		set_transient( $transient_key, $existing, HOUR_IN_SECONDS );
	}

	/**
	 * Get generation progress data.
	 *
	 * @param int $term_id Course taxonomy term ID.
	 * @return array|false Progress data or false if not found.
	 */
	public function get_progress( $term_id ) {
		return get_transient( '_1111_generation_progress_' . (int) $term_id );
	}

	/**
	 * Call an agent: load prompt, send message, parse and validate output.
	 *
	 * Retries once automatically on validation failure.
	 *
	 * @param string $agent_name Agent identifier (e.g., 'course_describer').
	 * @param string $model      Model identifier.
	 * @param int    $max_tokens Maximum tokens for the response.
	 * @param string $user_message The user message content.
	 * @return array|WP_Error Parsed and validated data, or WP_Error.
	 */
	public function call_agent( $agent_name, $model, $max_tokens, $user_message ) {
		$system_prompt = $this->prompt_loader->load( $agent_name );

		if ( is_wp_error( $system_prompt ) ) {
			return $system_prompt;
		}

		/**
		 * Filters the user message before sending to an agent.
		 *
		 * @param string $user_message The user message.
		 * @param string $agent_name   The agent name.
		 */
		$user_message = apply_filters( "1111_learn_validate_{$agent_name}", $user_message, $agent_name );

		$response = $this->api_client->send_message( $model, $system_prompt, $user_message, $max_tokens );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = $this->api_client->extract_text( $response );

		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$parsed = $this->validator->parse_json( $text );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$valid = $this->validator->validate( $agent_name, $parsed );

		if ( is_wp_error( $valid ) ) {
			// Retry once on validation failure.
			$retry_response = $this->api_client->send_message( $model, $system_prompt, $user_message, $max_tokens );

			if ( is_wp_error( $retry_response ) ) {
				return $retry_response;
			}

			$retry_text = $this->api_client->extract_text( $retry_response );

			if ( is_wp_error( $retry_text ) ) {
				return $retry_text;
			}

			$retry_parsed = $this->validator->parse_json( $retry_text );

			if ( is_wp_error( $retry_parsed ) ) {
				return $retry_parsed;
			}

			$retry_valid = $this->validator->validate( $agent_name, $retry_parsed );

			if ( is_wp_error( $retry_valid ) ) {
				return $retry_valid;
			}

			return $retry_parsed;
		}

		return $parsed;
	}
}
