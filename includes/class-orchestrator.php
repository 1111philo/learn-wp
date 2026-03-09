<?php
/**
 * Seven-agent pipeline orchestrator.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Orchestrator
 *
 * Manages the seven-agent course generation pipeline using WordPress background processing.
 * Each step runs as its own PHP request via wp_schedule_single_event().
 */
class Learn_Orchestrator {

	/**
	 * Start course generation.
	 *
	 * @param string $title       Course title.
	 * @param string $description Course description.
	 * @param array  $objectives  Learning objectives.
	 * @return int|WP_Error Course term ID or WP_Error.
	 */
	public static function start_generation( $title, $description, $objectives ) {
		$term = wp_insert_term( $title, 'course' );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = $term['term_id'];

		update_term_meta( $term_id, '_1111_course_description', $description );
		update_term_meta( $term_id, '_1111_learning_objectives', $objectives );
		update_term_meta( $term_id, '_1111_generation_status', 'generating' );
		update_term_meta( $term_id, '_1111_generation_date', gmdate( 'c' ) );
		update_term_meta( $term_id, '_1111_course_version', 1 );

		self::update_progress( $term_id, array(
			'status'            => 'generating',
			'phase'             => 'course_description',
			'current_objective' => 0,
			'total_objectives'  => count( $objectives ),
			'current_step'      => 'describing',
			'steps_completed'   => array(),
			'lesson_titles'     => array(),
			'error'             => null,
		) );

		self::schedule_next( $term_id, 'phase_0' );

		return $term_id;
	}

	/**
	 * Execute a single pipeline step.
	 *
	 * @param int    $term_id Course term ID.
	 * @param string $step    Step identifier.
	 */
	public static function execute_step( $term_id, $step ) {
		$status = get_term_meta( $term_id, '_1111_generation_status', true );
		if ( 'generating' !== $status ) {
			return;
		}

		if ( 'phase_0' === $step ) {
			self::run_phase_0( $term_id );
		} elseif ( preg_match( '/^plan_(\d+)$/', $step, $m ) ) {
			self::run_lesson_planner( $term_id, (int) $m[1] );
		} elseif ( preg_match( '/^write_(\d+)_(\d+)$/', $step, $m ) ) {
			self::run_lesson_writer( $term_id, (int) $m[1], (int) $m[2] );
		} elseif ( preg_match( '/^activity_(\d+)_(\d+)$/', $step, $m ) ) {
			self::run_activity_creator( $term_id, (int) $m[1], (int) $m[2] );
		} elseif ( preg_match( '/^review_(\d+)_(\d+)$/', $step, $m ) ) {
			self::run_activity_reviewer( $term_id, (int) $m[1], (int) $m[2] );
		} elseif ( 'phase_final' === $step ) {
			self::run_assessment_creator( $term_id );
		}
	}

	/**
	 * Phase 0: Course Describer.
	 *
	 * @param int $term_id Course term ID.
	 */
	private static function run_phase_0( $term_id ) {
		$term        = get_term( $term_id, 'course' );
		$description = get_term_meta( $term_id, '_1111_course_description', true );
		$objectives  = get_term_meta( $term_id, '_1111_learning_objectives', true );
		$feedback    = get_term_meta( $term_id, '_1111_course_feedback', true );

		$prompt = Learn_Prompt_Loader::load( 'course-describer' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'phase_0', $prompt );
			return;
		}

		$user_message = self::build_course_describer_input( $term->name, $description, $objectives, $feedback );

		$result = self::call_agent_with_retry(
			$prompt,
			$user_message,
			LEARN_FAST_MODEL,
			LEARN_PLAN_MAX_TOKENS,
			function ( $data ) use ( $objectives ) {
				return Learn_Validator::validate_course_describer( $data, count( $objectives ) );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'phase_0', $result );
			return;
		}

		update_term_meta( $term_id, '_1111_narrative_description', $result['narrative_description'] );
		update_term_meta( $term_id, '_1111_work_product', $result['work_product'] );
		update_term_meta( $term_id, '_1111_work_product_type', $result['work_product_type'] );
		update_term_meta( $term_id, '_1111_work_product_description', $result['work_product_description'] );
		update_term_meta( $term_id, '_1111_lesson_titles', $result['lessons'] );

		if ( $feedback ) {
			delete_term_meta( $term_id, '_1111_course_feedback' );
		}

		$titles   = wp_list_pluck( $result['lessons'], 'lesson_title' );
		$progress = self::get_progress( $term_id );
		$progress['lesson_titles'] = $titles;
		$progress['phase']         = 'planning';
		$progress['current_step']  = 'planning';
		self::update_progress( $term_id, $progress );

		self::schedule_next( $term_id, 'plan_0' );
	}

	/**
	 * Run Lesson Planner for one objective.
	 *
	 * @param int $term_id         Course term ID.
	 * @param int $objective_index Zero-based objective index.
	 */
	private static function run_lesson_planner( $term_id, $objective_index ) {
		$objectives  = get_term_meta( $term_id, '_1111_learning_objectives', true );

		if ( $objective_index >= count( $objectives ) ) {
			self::schedule_next( $term_id, 'phase_final' );
			return;
		}

		// Skip if already generated (retry scenario).
		if ( self::get_lesson_group_for_objective( $term_id, $objective_index ) ) {
			self::schedule_next( $term_id, 'plan_' . ( $objective_index + 1 ) );
			return;
		}

		$narrative         = get_term_meta( $term_id, '_1111_narrative_description', true );
		$titles            = get_term_meta( $term_id, '_1111_lesson_titles', true );
		$work_product      = get_term_meta( $term_id, '_1111_work_product', true );
		$work_product_type = get_term_meta( $term_id, '_1111_work_product_type', true );
		$preset_title      = isset( $titles[ $objective_index ]['lesson_title'] ) ? $titles[ $objective_index ]['lesson_title'] : '';

		$prompt = Learn_Prompt_Loader::load( 'lesson-planner' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'plan_' . $objective_index, $prompt );
			return;
		}

		$user_message = self::build_lesson_planner_input(
			$narrative, $work_product, $work_product_type,
			$objectives[ $objective_index ], $preset_title,
			LEARN_LESSONS_PER_OBJECTIVE,
			$objective_index, count( $objectives ), $objectives
		);

		$result = self::call_agent_with_retry(
			$prompt, $user_message, LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS,
			function ( $data ) {
				return Learn_Validator::validate_lesson_planner( $data );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'plan_' . $objective_index, $result );
			return;
		}

		// Create lesson_group tag.
		$group_name = $preset_title ? $preset_title : $result['lessons'][0]['lesson_title'];
		$group_term = wp_insert_term( $group_name, 'lesson_group' );
		if ( is_wp_error( $group_term ) ) {
			$existing = get_term_by( 'name', $group_name, 'lesson_group' );
			if ( $existing ) {
				$group_term_id = $existing->term_id;
			} else {
				self::handle_error( $term_id, 'plan_' . $objective_index, $group_term );
				return;
			}
		} else {
			$group_term_id = $group_term['term_id'];
		}

		update_term_meta( $group_term_id, '_1111_lesson_plan_raw', $result );
		update_term_meta( $group_term_id, '_1111_learning_objective', $objectives[ $objective_index ] );
		update_term_meta( $group_term_id, '_1111_lesson_count', count( $result['lessons'] ) );
		update_term_meta( $group_term_id, '_1111_plan_version', 1 );
		update_term_meta( $group_term_id, '_1111_objective_index', $objective_index );
		update_term_meta( $group_term_id, '_1111_course_term_id', $term_id );

		$progress = self::get_progress( $term_id );
		$progress['current_objective'] = $objective_index;
		$progress['current_step']      = 'planned';
		if ( ! isset( $progress['steps_completed'][ $objective_index ] ) ) {
			$progress['steps_completed'][ $objective_index ] = array( 'steps' => array(), 'post_ids' => array() );
		}
		$progress['steps_completed'][ $objective_index ]['steps'][]       = 'planned';
		$progress['steps_completed'][ $objective_index ]['group_term_id'] = $group_term_id;
		self::update_progress( $term_id, $progress );

		self::schedule_next( $term_id, 'write_' . $objective_index . '_0' );
	}

	/**
	 * Run Lesson Writer for one lesson.
	 *
	 * @param int $term_id         Course term ID.
	 * @param int $objective_index Zero-based objective index.
	 * @param int $lesson_index    Zero-based lesson index within the objective.
	 */
	private static function run_lesson_writer( $term_id, $objective_index, $lesson_index ) {
		$progress      = self::get_progress( $term_id );
		$group_term_id = $progress['steps_completed'][ $objective_index ]['group_term_id'];
		$plan          = get_term_meta( $group_term_id, '_1111_lesson_plan_raw', true );
		$narrative     = get_term_meta( $term_id, '_1111_narrative_description', true );

		if ( $lesson_index >= count( $plan['lessons'] ) ) {
			// All lessons for this objective written; move to next objective.
			self::schedule_next( $term_id, 'plan_' . ( $objective_index + 1 ) );
			return;
		}

		$lesson_outline = $plan['lessons'][ $lesson_index ];

		$prompt = Learn_Prompt_Loader::load( 'lesson-writer' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'write_' . $objective_index . '_' . $lesson_index, $prompt );
			return;
		}

		$user_message = self::build_lesson_writer_input(
			$narrative,
			$lesson_outline['lesson_title'],
			$lesson_outline['lesson_outline'],
			$plan['mastery_criteria'],
			$plan['key_concepts']
		);

		$result = self::call_agent_with_retry(
			$prompt, $user_message, LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS,
			function ( $data ) {
				return Learn_Validator::validate_lesson_writer( $data );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'write_' . $objective_index . '_' . $lesson_index, $result );
			return;
		}

		// Calculate global lesson order.
		$lesson_order = self::calculate_lesson_order( $progress, $objective_index, $lesson_index );

		// Convert Markdown to blocks and create post.
		$block_content = self::markdown_to_blocks( $result['lesson_body'] );
		$agent_user_id = Learn_Agent_User::get_id();

		$post_id = wp_insert_post( array(
			'post_type'    => 'learn',
			'post_author'  => $agent_user_id,
			'post_title'   => $result['lesson_title'],
			'post_content' => wp_kses_post( $block_content ),
			'post_excerpt' => wp_trim_words( $result['lesson_body'], 30 ),
			'post_status'  => 'draft',
			'menu_order'   => $lesson_order,
		), true );

		if ( is_wp_error( $post_id ) ) {
			self::handle_error( $term_id, 'write_' . $objective_index . '_' . $lesson_index, $post_id );
			return;
		}

		wp_set_object_terms( $post_id, $term_id, 'course' );
		wp_set_object_terms( $post_id, $group_term_id, 'lesson_group' );

		update_post_meta( $post_id, '_1111_objective_index', $objective_index );
		update_post_meta( $post_id, '_1111_lesson_order', $lesson_order );
		update_post_meta( $post_id, '_1111_learning_objective', $plan['learning_objective'] );
		update_post_meta( $post_id, '_1111_key_concepts', $plan['key_concepts'] );
		update_post_meta( $post_id, '_1111_mastery_criteria', $plan['mastery_criteria'] );
		update_post_meta( $post_id, '_1111_key_takeaways', $result['key_takeaways'] );
		update_post_meta( $post_id, '_1111_generated', true );
		update_post_meta( $post_id, '_1111_lesson_version', 1 );

		$progress['current_step'] = 'written';
		$progress['steps_completed'][ $objective_index ]['steps'][]                     = 'written_' . $lesson_index;
		$progress['steps_completed'][ $objective_index ]['post_ids'][ $lesson_index ] = $post_id;
		self::update_progress( $term_id, $progress );

		self::schedule_next( $term_id, 'activity_' . $objective_index . '_' . $lesson_index );
	}

	/**
	 * Run Activity Creator for one lesson.
	 *
	 * @param int $term_id         Course term ID.
	 * @param int $objective_index Zero-based objective index.
	 * @param int $lesson_index    Zero-based lesson index.
	 */
	private static function run_activity_creator( $term_id, $objective_index, $lesson_index ) {
		$progress          = self::get_progress( $term_id );
		$group_term_id     = $progress['steps_completed'][ $objective_index ]['group_term_id'];
		$plan              = get_term_meta( $group_term_id, '_1111_lesson_plan_raw', true );
		$objectives        = get_term_meta( $term_id, '_1111_learning_objectives', true );
		$work_product      = get_term_meta( $term_id, '_1111_work_product', true );
		$work_product_type = get_term_meta( $term_id, '_1111_work_product_type', true );

		$global_pos    = self::calculate_lesson_order( $progress, $objective_index, $lesson_index );
		$total_lessons = self::estimate_total_lessons( $progress, $objectives, $objective_index );
		$activity_type = self::determine_activity_type( $global_pos, $total_lessons );

		// Check for reviewer revision suggestions.
		$feedback = '';
		$suggestions_key = '_1111_revision_suggestions_' . $term_id . '_' . $objective_index . '_' . $lesson_index;
		$revision_suggestions = get_transient( $suggestions_key );
		if ( $revision_suggestions ) {
			$feedback = "Reviewer suggestions from previous attempt:\n" . implode( "\n- ", $revision_suggestions );
			delete_transient( $suggestions_key );
		}

		$prompt = Learn_Prompt_Loader::load( 'activity-creator' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'activity_' . $objective_index . '_' . $lesson_index, $prompt );
			return;
		}

		$user_message = self::build_activity_creator_input(
			$plan['learning_objective'], $activity_type,
			$work_product, $work_product_type,
			$global_pos + 1, $total_lessons,
			$plan['mastery_criteria'], $plan['suggested_activity'],
			$feedback
		);

		$result = self::call_agent_with_retry(
			$prompt, $user_message, LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS,
			function ( $data ) {
				return Learn_Validator::validate_activity_creator( $data );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'activity_' . $objective_index . '_' . $lesson_index, $result );
			return;
		}

		$post_id = $progress['steps_completed'][ $objective_index ]['post_ids'][ $lesson_index ];
		update_post_meta( $post_id, '_1111_activity', $result );
		update_post_meta( $post_id, '_1111_activity_type', $result['activity_type'] );
		update_post_meta( $post_id, '_1111_xp_value', $result['xp_value'] );
		update_post_meta( $post_id, '_1111_milestone', isset( $result['milestone'] ) ? $result['milestone'] : null );
		update_post_meta( $post_id, '_1111_portfolio_contribution', $result['portfolio_contribution'] );
		update_post_meta( $post_id, '_1111_activity_version', 1 );

		$progress['current_step'] = 'activity_created';
		$progress['steps_completed'][ $objective_index ]['steps'][] = 'activity_created_' . $lesson_index;
		self::update_progress( $term_id, $progress );

		self::schedule_next( $term_id, 'review_' . $objective_index . '_' . $lesson_index );
	}

	/**
	 * Run Activity Reviewer for one lesson.
	 *
	 * @param int $term_id         Course term ID.
	 * @param int $objective_index Zero-based objective index.
	 * @param int $lesson_index    Zero-based lesson index.
	 */
	private static function run_activity_reviewer( $term_id, $objective_index, $lesson_index ) {
		$progress      = self::get_progress( $term_id );
		$group_term_id = $progress['steps_completed'][ $objective_index ]['group_term_id'];
		$plan          = get_term_meta( $group_term_id, '_1111_lesson_plan_raw', true );
		$post_id       = $progress['steps_completed'][ $objective_index ]['post_ids'][ $lesson_index ];
		$activity      = get_post_meta( $post_id, '_1111_activity', true );
		$work_product  = get_term_meta( $term_id, '_1111_work_product', true );

		$prompt = Learn_Prompt_Loader::load( 'activity-reviewer' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'review_' . $objective_index . '_' . $lesson_index, $prompt );
			return;
		}

		$user_message = self::build_activity_reviewer_input(
			$plan['learning_objective'], $activity['activity_type'],
			$work_product, $plan['mastery_criteria'], $activity
		);

		$result = self::call_agent_with_retry(
			$prompt, $user_message, LEARN_FAST_MODEL, LEARN_PLAN_MAX_TOKENS,
			function ( $data ) {
				return Learn_Validator::validate_activity_reviewer( $data );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'review_' . $objective_index . '_' . $lesson_index, $result );
			return;
		}

		update_post_meta( $post_id, '_1111_activity_review', $result );

		// Revision loop: max 1 retry.
		if ( 'revision_needed' === $result['verdict'] ) {
			$revision_count = (int) get_post_meta( $post_id, '_1111_activity_revision_count', true );
			if ( $revision_count < 1 ) {
				update_post_meta( $post_id, '_1111_activity_revision_count', $revision_count + 1 );
				set_transient(
					'_1111_revision_suggestions_' . $term_id . '_' . $objective_index . '_' . $lesson_index,
					$result['suggestions'],
					HOUR_IN_SECONDS
				);
				self::schedule_next( $term_id, 'activity_' . $objective_index . '_' . $lesson_index );
				return;
			}
		}

		$progress['current_step'] = 'reviewed';
		$progress['steps_completed'][ $objective_index ]['steps'][] = 'reviewed_' . $lesson_index;
		self::update_progress( $term_id, $progress );

		// Next lesson, next objective, or final assessment.
		$next_lesson = $lesson_index + 1;
		if ( $next_lesson < count( $plan['lessons'] ) ) {
			self::schedule_next( $term_id, 'write_' . $objective_index . '_' . $next_lesson );
		} else {
			$objectives     = get_term_meta( $term_id, '_1111_learning_objectives', true );
			$next_objective = $objective_index + 1;
			if ( $next_objective < count( $objectives ) ) {
				self::schedule_next( $term_id, 'plan_' . $next_objective );
			} else {
				self::schedule_next( $term_id, 'phase_final' );
			}
		}
	}

	/**
	 * Phase Final: Assessment Creator.
	 *
	 * @param int $term_id Course term ID.
	 */
	private static function run_assessment_creator( $term_id ) {
		$term              = get_term( $term_id, 'course' );
		$narrative         = get_term_meta( $term_id, '_1111_narrative_description', true );
		$objectives        = get_term_meta( $term_id, '_1111_learning_objectives', true );
		$work_product      = get_term_meta( $term_id, '_1111_work_product', true );
		$work_product_type = get_term_meta( $term_id, '_1111_work_product_type', true );
		$feedback          = get_term_meta( $term_id, '_1111_assessment_feedback', true );

		// Gather mastery criteria and activity summaries from all lesson posts.
		$lesson_posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'draft',
			'numberposts' => -1,
			'tax_query'   => array( array( 'taxonomy' => 'course', 'terms' => $term_id ) ),
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
		) );

		$all_mastery    = array();
		$all_activities = array();
		$total_xp       = 0;

		foreach ( $lesson_posts as $post ) {
			$obj_idx  = (int) get_post_meta( $post->ID, '_1111_objective_index', true );
			$mastery  = get_post_meta( $post->ID, '_1111_mastery_criteria', true );
			$activity = get_post_meta( $post->ID, '_1111_activity', true );
			$xp       = (int) get_post_meta( $post->ID, '_1111_xp_value', true );

			if ( $mastery && ! isset( $all_mastery[ $obj_idx ] ) ) {
				$all_mastery[ $obj_idx ] = array(
					'objective' => $objectives[ $obj_idx ],
					'criteria'  => $mastery,
				);
			}
			if ( $activity ) {
				$all_activities[] = array(
					'lesson_title'           => $post->post_title,
					'activity_type'          => $activity['activity_type'],
					'prompt'                 => $activity['prompt'],
					'portfolio_contribution' => $activity['portfolio_contribution'],
				);
			}
			$total_xp += $xp;
		}

		$prompt = Learn_Prompt_Loader::load( 'assessment-creator' );
		if ( is_wp_error( $prompt ) ) {
			self::handle_error( $term_id, 'phase_final', $prompt );
			return;
		}

		$user_message = self::build_assessment_creator_input(
			$term->name, $narrative, $work_product, $work_product_type,
			$objectives, $all_mastery, $all_activities, $feedback
		);

		$result = self::call_agent_with_retry(
			$prompt, $user_message, LEARN_DEFAULT_MODEL, LEARN_CONTENT_MAX_TOKENS,
			function ( $data ) use ( $objectives ) {
				return Learn_Validator::validate_assessment_creator( $data, count( $objectives ) );
			}
		);

		if ( is_wp_error( $result ) ) {
			self::handle_error( $term_id, 'phase_final', $result );
			return;
		}

		$agent_user_id      = Learn_Agent_User::get_id();
		$assessment_content = self::build_assessment_block_content( $result );

		$post_id = wp_insert_post( array(
			'post_type'    => 'learn',
			'post_author'  => $agent_user_id,
			'post_title'   => $result['assessment_title'],
			'post_content' => wp_kses_post( $assessment_content ),
			'post_status'  => 'draft',
			'menu_order'   => 999,
		), true );

		if ( is_wp_error( $post_id ) ) {
			self::handle_error( $term_id, 'phase_final', $post_id );
			return;
		}

		wp_set_object_terms( $post_id, $term_id, 'course' );

		update_post_meta( $post_id, '_1111_activity_type', 'final' );
		update_post_meta( $post_id, '_1111_activity', $result );
		update_post_meta( $post_id, '_1111_xp_value', 500 );
		update_post_meta( $post_id, '_1111_milestone', 'Portfolio Delivered' );
		update_post_meta( $post_id, '_1111_generated', true );

		$total_xp += 500;
		update_term_meta( $term_id, '_1111_assessment', $result );
		update_term_meta( $term_id, '_1111_assessment_post_id', $post_id );
		update_term_meta( $term_id, '_1111_total_xp', $total_xp );

		if ( $feedback ) {
			delete_term_meta( $term_id, '_1111_assessment_feedback' );
		}

		update_term_meta( $term_id, '_1111_generation_status', 'complete' );

		$progress = self::get_progress( $term_id );
		$progress['status']       = 'complete';
		$progress['phase']        = 'complete';
		$progress['current_step'] = 'complete';
		$progress['error']        = null;
		self::update_progress( $term_id, $progress );
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	/**
	 * Call an agent with retry-once on validation or parse failure.
	 *
	 * @param string   $system_prompt System prompt.
	 * @param string   $user_message  User message.
	 * @param string   $model         Model ID.
	 * @param int      $max_tokens    Max tokens.
	 * @param callable $validator     Validation callback returning true|WP_Error.
	 * @return array|WP_Error Validated data or error.
	 */
	private static function call_agent_with_retry( $system_prompt, $user_message, $model, $max_tokens, $validator ) {
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$result = Learn_API_Client::call_agent( $system_prompt, $user_message, $model, $max_tokens );

			if ( is_wp_error( $result ) ) {
				if ( 0 === $attempt && 'learn_json_parse_error' === $result->get_error_code() ) {
					continue;
				}
				return $result;
			}

			$valid = $validator( $result );
			if ( true === $valid ) {
				return $result;
			}

			// Never retry safety violations.
			if ( is_wp_error( $valid ) && 'learn_unsafe_content' === $valid->get_error_code() ) {
				return $valid;
			}

			if ( 0 === $attempt ) {
				continue;
			}

			return $valid;
		}

		return new WP_Error( 'learn_agent_failed', __( 'Agent failed after retry.', 'learn' ) );
	}

	/**
	 * Handle a pipeline error.
	 *
	 * @param int      $term_id Course term ID.
	 * @param string   $step    Step that failed.
	 * @param WP_Error $error   The error.
	 */
	private static function handle_error( $term_id, $step, $error ) {
		update_term_meta( $term_id, '_1111_generation_status', 'failed' );
		update_term_meta( $term_id, '_1111_failed_step', $step );

		$progress = self::get_progress( $term_id );
		if ( ! $progress ) {
			$progress = array();
		}
		$progress['status'] = 'failed';
		$progress['error']  = array(
			'step'    => $step,
			'code'    => $error->get_error_code(),
			'message' => $error->get_error_message(),
		);
		self::update_progress( $term_id, $progress );
	}

	/**
	 * Retry generation from the failed step.
	 *
	 * @param int $term_id Course term ID.
	 * @return true|WP_Error
	 */
	public static function retry_generation( $term_id ) {
		$failed_step = get_term_meta( $term_id, '_1111_failed_step', true );
		if ( ! $failed_step ) {
			return new WP_Error( 'learn_no_failed_step', __( 'No failed step to retry.', 'learn' ) );
		}

		update_term_meta( $term_id, '_1111_generation_status', 'generating' );
		delete_term_meta( $term_id, '_1111_failed_step' );

		$progress = self::get_progress( $term_id );
		if ( $progress ) {
			$progress['status'] = 'generating';
			$progress['error']  = null;
			self::update_progress( $term_id, $progress );
		}

		self::schedule_next( $term_id, $failed_step );
		return true;
	}

	/**
	 * Schedule the next pipeline step via WP-Cron.
	 *
	 * @param int    $term_id Course term ID.
	 * @param string $step    Next step identifier.
	 */
	public static function schedule_next( $term_id, $step ) {
		wp_schedule_single_event( time(), '1111_learn_generation_step', array( $term_id, $step ) );
		spawn_cron();
	}

	/**
	 * Update progress transient.
	 *
	 * @param int   $term_id  Course term ID.
	 * @param array $progress Progress data.
	 */
	public static function update_progress( $term_id, $progress ) {
		set_transient( '_1111_generation_progress_' . $term_id, $progress, HOUR_IN_SECONDS );
	}

	/**
	 * Get progress transient.
	 *
	 * @param int $term_id Course term ID.
	 * @return array|false Progress data or false.
	 */
	public static function get_progress( $term_id ) {
		return get_transient( '_1111_generation_progress_' . $term_id );
	}

	/**
	 * Calculate global lesson order for a lesson.
	 *
	 * @param array $progress        Current progress data.
	 * @param int   $objective_index Objective index.
	 * @param int   $lesson_index    Lesson index within objective.
	 * @return int Global lesson order (0-based).
	 */
	private static function calculate_lesson_order( $progress, $objective_index, $lesson_index ) {
		$order = 0;
		for ( $i = 0; $i < $objective_index; $i++ ) {
			if ( isset( $progress['steps_completed'][ $i ]['group_term_id'] ) ) {
				$count = get_term_meta( $progress['steps_completed'][ $i ]['group_term_id'], '_1111_lesson_count', true );
				$order += (int) $count;
			}
		}
		return $order + $lesson_index;
	}

	/**
	 * Estimate total lessons in the course.
	 *
	 * @param array $progress        Current progress.
	 * @param array $objectives      All objectives.
	 * @param int   $current_obj_idx Current objective index.
	 * @return int Estimated total lessons.
	 */
	private static function estimate_total_lessons( $progress, $objectives, $current_obj_idx ) {
		$total = 0;
		foreach ( $objectives as $i => $obj ) {
			if ( isset( $progress['steps_completed'][ $i ]['group_term_id'] ) ) {
				$total += (int) get_term_meta( $progress['steps_completed'][ $i ]['group_term_id'], '_1111_lesson_count', true );
			} else {
				$total += LEARN_LESSONS_PER_OBJECTIVE;
			}
		}
		return max( $total, 1 );
	}

	/**
	 * Determine activity type based on lesson position.
	 *
	 * @param int $position Zero-based global lesson position.
	 * @param int $total    Total lessons in the course.
	 * @return string explore, apply, or create.
	 */
	private static function determine_activity_type( $position, $total ) {
		if ( $total <= 1 ) {
			return 'create';
		}
		$ratio = $position / ( $total - 1 );
		if ( $ratio < 0.34 ) {
			return 'explore';
		} elseif ( $ratio < 0.67 ) {
			return 'apply';
		}
		return 'create';
	}

	/**
	 * Find existing lesson_group for an objective (for incremental recovery).
	 *
	 * @param int $term_id         Course term ID.
	 * @param int $objective_index Objective index.
	 * @return int|false Lesson group term ID or false.
	 */
	private static function get_lesson_group_for_objective( $term_id, $objective_index ) {
		$groups = get_terms( array(
			'taxonomy'   => 'lesson_group',
			'hide_empty' => false,
			'meta_query' => array(
				'relation' => 'AND',
				array( 'key' => '_1111_course_term_id', 'value' => $term_id ),
				array( 'key' => '_1111_objective_index', 'value' => $objective_index ),
			),
		) );

		if ( ! is_wp_error( $groups ) && ! empty( $groups ) ) {
			return $groups[0]->term_id;
		}
		return false;
	}

	// -------------------------------------------------------------------------
	// Markdown to Block Conversion.
	// -------------------------------------------------------------------------

	/**
	 * Convert Markdown to WordPress block markup.
	 *
	 * @param string $markdown Markdown content.
	 * @return string WordPress block markup.
	 */
	public static function markdown_to_blocks( $markdown ) {
		$lines       = explode( "\n", $markdown );
		$blocks      = array();
		$buffer      = '';
		$in_list     = false;
		$list_items  = array();
		$list_ordered = false;
		$in_code     = false;
		$code_buffer = '';

		foreach ( $lines as $line ) {
			// Code fence toggle.
			if ( preg_match( '/^```/', $line ) ) {
				if ( $in_code ) {
					$blocks[]    = '<!-- wp:code --><pre class="wp-block-code"><code>' . esc_html( $code_buffer ) . '</code></pre><!-- /wp:code -->';
					$code_buffer = '';
					$in_code     = false;
				} else {
					self::flush_buffer( $buffer, $blocks );
					self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
					$in_code = true;
				}
				continue;
			}

			if ( $in_code ) {
				$code_buffer .= ( '' !== $code_buffer ? "\n" : '' ) . $line;
				continue;
			}

			// Heading.
			if ( preg_match( '/^(#{2,6})\s+(.+)$/', $line, $m ) ) {
				self::flush_buffer( $buffer, $blocks );
				self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
				$level    = strlen( $m[1] );
				$text     = self::inline_markdown( trim( $m[2] ) );
				$tag      = 'h' . $level;
				$blocks[] = sprintf(
					'<!-- wp:heading {"level":%d} --><%s class="wp-block-heading">%s</%s><!-- /wp:heading -->',
					$level, $tag, $text, $tag
				);
				continue;
			}

			// Unordered list item.
			if ( preg_match( '/^[\-\*]\s+(.+)$/', $line, $m ) ) {
				self::flush_buffer( $buffer, $blocks );
				if ( $in_list && $list_ordered ) {
					self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
				}
				$in_list      = true;
				$list_ordered = false;
				$list_items[] = self::inline_markdown( trim( $m[1] ) );
				continue;
			}

			// Ordered list item.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $m ) ) {
				self::flush_buffer( $buffer, $blocks );
				if ( $in_list && ! $list_ordered ) {
					self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
				}
				$in_list      = true;
				$list_ordered = true;
				$list_items[] = self::inline_markdown( trim( $m[1] ) );
				continue;
			}

			// Empty line.
			if ( '' === trim( $line ) ) {
				self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
				self::flush_buffer( $buffer, $blocks );
				continue;
			}

			// Regular text.
			$buffer .= ( '' !== $buffer ? ' ' : '' ) . $line;
		}

		// Flush remaining content.
		if ( $in_code ) {
			$blocks[] = '<!-- wp:code --><pre class="wp-block-code"><code>' . esc_html( $code_buffer ) . '</code></pre><!-- /wp:code -->';
		}
		self::flush_list( $in_list, $list_items, $list_ordered, $blocks );
		self::flush_buffer( $buffer, $blocks );

		return implode( "\n\n", $blocks );
	}

	/**
	 * Flush paragraph buffer to blocks array.
	 *
	 * @param string $buffer Paragraph buffer (passed by reference, cleared).
	 * @param array  $blocks Blocks array (passed by reference).
	 */
	private static function flush_buffer( &$buffer, &$blocks ) {
		if ( '' !== $buffer ) {
			$blocks[] = '<!-- wp:paragraph --><p>' . self::inline_markdown( trim( $buffer ) ) . '</p><!-- /wp:paragraph -->';
			$buffer   = '';
		}
	}

	/**
	 * Flush list items to blocks array.
	 *
	 * @param bool  $in_list      Whether currently in a list (passed by reference, cleared).
	 * @param array $list_items   List items (passed by reference, cleared).
	 * @param bool  $list_ordered Whether ordered (passed by reference).
	 * @param array $blocks       Blocks array (passed by reference).
	 */
	private static function flush_list( &$in_list, &$list_items, &$list_ordered, &$blocks ) {
		if ( $in_list && ! empty( $list_items ) ) {
			$tag     = $list_ordered ? 'ol' : 'ul';
			$li_html = '';
			foreach ( $list_items as $item ) {
				$li_html .= '<!-- wp:list-item --><li>' . $item . '</li><!-- /wp:list-item -->';
			}
			$attrs    = $list_ordered ? ' {"ordered":true}' : '';
			$blocks[] = '<!-- wp:list' . $attrs . ' --><' . $tag . '>' . $li_html . '</' . $tag . '><!-- /wp:list -->';
		}
		$in_list    = false;
		$list_items = array();
	}

	/**
	 * Process inline Markdown (bold, italic, code, links).
	 *
	 * @param string $text Text with inline Markdown.
	 * @return string HTML.
	 */
	private static function inline_markdown( $text ) {
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );
		$text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );
		$text = preg_replace( '/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $text );
		return $text;
	}

	/**
	 * Build block content for the assessment post.
	 *
	 * @param array $assessment Assessment data.
	 * @return string Block markup.
	 */
	public static function build_assessment_block_content_static( $assessment ) {
		return self::build_assessment_block_content( $assessment );
	}

	/**
	 * Build block content for the assessment post.
	 *
	 * @param array $assessment Assessment data.
	 * @return string Block markup.
	 */
	private static function build_assessment_block_content( $assessment ) {
		$blocks = array();

		$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( $assessment['prompt'] ) . '</p><!-- /wp:paragraph -->';
		$blocks[] = '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Instructions</h2><!-- /wp:heading -->';
		$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( $assessment['instructions'] ) . '</p><!-- /wp:paragraph -->';

		if ( ! empty( $assessment['completion_message'] ) ) {
			$blocks[] = '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">What You\'ve Built</h2><!-- /wp:heading -->';
			$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( $assessment['completion_message'] ) . '</p><!-- /wp:paragraph -->';
		}

		return implode( "\n\n", $blocks );
	}

	// -------------------------------------------------------------------------
	// Input Builders — format user messages for each agent.
	// -------------------------------------------------------------------------

	/**
	 * Build user message for Course Describer.
	 */
	private static function build_course_describer_input( $title, $description, $objectives, $feedback = '' ) {
		$msg  = "Course title: $title\n\n";
		$msg .= "Course description: $description\n\n";
		$msg .= 'Learning objectives (' . count( $objectives ) . " total \u2014 produce one lesson entry for each):\n";
		foreach ( $objectives as $i => $obj ) {
			$msg .= ( $i + 1 ) . ". $obj\n";
		}
		if ( $feedback ) {
			$msg .= "\nFeedback on previous version: $feedback";
		}
		return $msg;
	}

	/**
	 * Build user message for Lesson Planner.
	 */
	private static function build_lesson_planner_input( $narrative, $work_product, $wp_type, $objective, $preset_title, $lesson_count, $obj_index, $obj_total, $all_objectives, $feedback = '' ) {
		$msg  = "Course description: $narrative\n\n";
		$msg .= "Work product: $work_product (WordPress $wp_type on learner's subsite)\n\n";
		$msg .= "Learning objective for THIS lesson group: $objective\n\n";
		$msg .= "Lesson title from Course Describer: $preset_title\n\n";
		$msg .= "Target lesson count: $lesson_count\n\n";
		$msg .= 'This is objective ' . ( $obj_index + 1 ) . " of $obj_total. Assign activity types accordingly (early = explore/apply, later = create).\n\n";

		$others = array();
		foreach ( $all_objectives as $i => $obj ) {
			if ( $i !== $obj_index ) {
				$others[] = "- $obj";
			}
		}
		if ( ! empty( $others ) ) {
			$msg .= "Other objectives in this course (DO NOT teach these, they have their own lessons):\n";
			$msg .= implode( "\n", $others ) . "\n";
		}

		if ( $feedback ) {
			$msg .= "\nFeedback on previous plan: $feedback";
		}
		return $msg;
	}

	/**
	 * Build user message for Lesson Writer.
	 */
	public static function build_lesson_writer_input_static( $narrative, $lesson_title, $lesson_outline, $mastery, $key_concepts, $feedback = '' ) {
		return self::build_lesson_writer_input( $narrative, $lesson_title, $lesson_outline, $mastery, $key_concepts, $feedback );
	}

	/**
	 * Build user message for Lesson Writer.
	 */
	private static function build_lesson_writer_input( $narrative, $lesson_title, $lesson_outline, $mastery, $key_concepts, $feedback = '' ) {
		$msg  = "Course description: $narrative\n\n";
		$msg .= "Lesson title: $lesson_title\n\n";
		$msg .= "Lesson outline:\n";
		foreach ( $lesson_outline as $item ) {
			$msg .= "- $item\n";
		}
		$msg .= "\nMastery criteria (for the full lesson group \u2014 this lesson contributes to these):\n";
		foreach ( $mastery as $item ) {
			$msg .= "- $item\n";
		}
		$msg .= "\nKey concepts:\n";
		foreach ( $key_concepts as $item ) {
			$msg .= "- $item\n";
		}
		if ( $feedback ) {
			$msg .= "\nFeedback on previous version: $feedback";
		}
		return $msg;
	}

	/**
	 * Build user message for Activity Creator.
	 */
	private static function build_activity_creator_input( $objective, $activity_type, $work_product, $wp_type, $lesson_position, $total_lessons, $mastery, $activity_seed, $feedback = '' ) {
		$msg  = "Learning objective: $objective\n\n";
		$msg .= "Activity type: $activity_type\n\n";
		$msg .= "Work product: $work_product\n";
		$msg .= "Work product type: $wp_type (WordPress $wp_type on learner's subsite)\n\n";
		$msg .= "Course position: Lesson $lesson_position of $total_lessons";
		if ( 1 === $lesson_position ) {
			$msg .= ' (first activity in the course)';
		}
		$msg .= "\n\nMastery criteria:\n";
		foreach ( $mastery as $item ) {
			$msg .= "- $item\n";
		}
		$msg .= "\nActivity seed:\n" . wp_json_encode( $activity_seed, JSON_PRETTY_PRINT ) . "\n";
		if ( $feedback ) {
			$msg .= "\nFeedback on previous activity: $feedback";
		}
		return $msg;
	}

	/**
	 * Build user message for Activity Reviewer.
	 */
	private static function build_activity_reviewer_input( $objective, $activity_type, $work_product, $mastery, $activity ) {
		$msg  = "Learning objective: $objective\n\n";
		$msg .= "Activity type: $activity_type\n\n";
		$msg .= "Work product: $work_product\n\n";
		$msg .= "Mastery criteria:\n";
		foreach ( $mastery as $item ) {
			$msg .= "- $item\n";
		}
		$msg .= "\nGenerated activity:\n" . wp_json_encode( $activity, JSON_PRETTY_PRINT ) . "\n";
		return $msg;
	}

	/**
	 * Build user message for Assessment Creator.
	 */
	private static function build_assessment_creator_input( $title, $narrative, $work_product, $wp_type, $objectives, $all_mastery, $all_activities, $feedback = '' ) {
		$msg  = "Course title: $title\n\n";
		$msg .= "Course description: $narrative\n\n";
		$msg .= "Work product: $work_product\n";
		$msg .= "Work product type: $wp_type (WordPress $wp_type on learner's subsite)\n\n";
		$msg .= "Learning objectives (all):\n";
		foreach ( $objectives as $i => $obj ) {
			$msg .= ( $i + 1 ) . ". $obj\n";
		}
		$msg .= "\nMastery criteria (all, by objective):\n";
		foreach ( $all_mastery as $m ) {
			$msg .= 'Objective: ' . $m['objective'] . "\n";
			foreach ( $m['criteria'] as $c ) {
				$msg .= "  - $c\n";
			}
		}
		$msg .= "\nActivities completed before this assessment:\n";
		foreach ( $all_activities as $a ) {
			$msg .= "- [{$a['activity_type']}] {$a['lesson_title']}: {$a['prompt']}\n  Portfolio contribution: {$a['portfolio_contribution']}\n";
		}
		if ( $feedback ) {
			$msg .= "\nFeedback on previous assessment: $feedback";
		}
		return $msg;
	}
}
