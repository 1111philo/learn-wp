<?php
/**
 * Course generation orchestrator.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Orchestrator {
	/**
	 * Agent user id.
	 *
	 * @var int
	 */
	private $agent_user_id = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->agent_user_id = (int) get_site_option( '_1111_learn_agent_user_id', 0 );
	}

	/**
	 * Run full generation pipeline.
	 *
	 * @param array $input Course input.
	 * @return array|WP_Error
	 */
	public function generate_course( $input ) {
		$input = apply_filters( '1111_learn_before_describe', $input );

		$describer = new Learn_Course_Describer();
		$described = $describer->run( $input );
		if ( is_wp_error( $described ) ) {
			return $described;
		}

		do_action( '1111_learn_course_described', $input, $described );

		$course_term = $this->create_course_term( $described );
		if ( is_wp_error( $course_term ) ) {
			return $course_term;
		}

		$created_posts = array();
		$total_xp      = 0;
		$planner       = new Learn_Lesson_Planner();
		$writer        = new Learn_Lesson_Writer();
		$activity_maker= new Learn_Activity_Creator();
		$reviewer      = new Learn_Activity_Reviewer();

		foreach ( $input['objectives'] as $index => $objective ) {
			$plan_input = apply_filters(
				'1111_learn_before_plan',
				array(
					'course'        => $described,
					'objective'     => $objective,
					'objective_idx' => $index + 1,
				)
			);
			$plan = $planner->run( $plan_input );
			if ( is_wp_error( $plan ) ) {
				return $plan;
			}
			do_action( '1111_learn_lesson_planned', $plan_input, $plan );

			$group_id = $this->upsert_lesson_group( $plan );
			if ( is_wp_error( $group_id ) ) {
				return $group_id;
			}

			$lessons = is_array( $plan['lessons'] ) ? $plan['lessons'] : array();
			foreach ( $lessons as $lesson ) {
				$lesson_input = apply_filters(
					'1111_learn_before_write',
					array(
						'course'   => $described,
						'objective'=> $objective,
						'plan'     => $plan,
						'outline'  => $lesson,
					)
				);
				$written = $writer->run( $lesson_input );
				if ( is_wp_error( $written ) ) {
					return $written;
				}
				do_action( '1111_learn_lesson_written', $lesson_input, $written );

				$activity_input = apply_filters(
					'1111_learn_before_activity',
					array(
						'course'  => $described,
						'plan'    => $plan,
						'lesson'  => $written,
					)
				);
				$activity = $activity_maker->run( $activity_input );
				if ( is_wp_error( $activity ) ) {
					return $activity;
				}
				do_action( '1111_learn_activity_created', $activity_input, $activity );

				$review = $reviewer->run(
					array(
						'plan'     => $plan,
						'lesson'   => $written,
						'activity' => $activity,
					)
				);
				if ( is_wp_error( $review ) ) {
					return $review;
				}

				if ( isset( $review['verdict'] ) && 'revision_needed' === $review['verdict'] ) {
					$activity['review_feedback'] = $review;
					$activity = $activity_maker->run(
						array(
							'course'         => $described,
							'plan'           => $plan,
							'lesson'         => $written,
							'prior_activity' => $activity,
							'suggestions'    => $review['suggestions'],
						)
					);
					if ( is_wp_error( $activity ) ) {
						return $activity;
					}
				}

				$post_id = $this->create_lesson_post( $written, $activity, $described, $course_term['term_id'], $group_id, $plan );
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}

				$total_xp += isset( $activity['xp_value'] ) ? absint( $activity['xp_value'] ) : 0;
				$created_posts[] = $post_id;
				do_action( '1111_learn_lesson_created', $post_id, $plan, $written, $activity );
			}
		}

		$assessment_agent = new Learn_Assessment_Creator();
		$assessment       = $assessment_agent->run(
			array(
				'course'       => $described,
				'objectives'   => $input['objectives'],
				'lesson_posts' => $created_posts,
			)
		);
		if ( is_wp_error( $assessment ) ) {
			return $assessment;
		}

		$assessment_post = $this->create_assessment_post( $assessment, $course_term['term_id'] );
		if ( is_wp_error( $assessment_post ) ) {
			return $assessment_post;
		}
		$created_posts[] = $assessment_post;

		update_term_meta( $course_term['term_id'], '_1111_total_course_xp', $total_xp );
		do_action( '1111_learn_course_complete', $course_term['term_id'], $created_posts );

		return array(
			'course_term_id' => (int) $course_term['term_id'],
			'post_ids'       => array_map( 'intval', $created_posts ),
			'total_xp'       => $total_xp,
		);
	}

	/**
	 * Creates or updates course term.
	 *
	 * @param array $described Describer output.
	 * @return array|WP_Error
	 */
	private function create_course_term( $described ) {
		$term = term_exists( $described['course_title'], 'course' );
		if ( ! $term ) {
			$term = wp_insert_term( $described['course_title'], 'course' );
		}

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = absint( $term['term_id'] );
		update_term_meta( $term_id, '_1111_narrative_description', wp_kses_post( $described['narrative_description'] ) );
		update_term_meta( $term_id, '_1111_work_product', sanitize_text_field( $described['work_product'] ) );
		update_term_meta( $term_id, '_1111_work_product_type', sanitize_key( $described['work_product_type'] ) );
		update_term_meta( $term_id, '_1111_work_product_description', wp_kses_post( $described['work_product_description'] ) );
		update_term_meta( $term_id, '_1111_learn_status', 'draft' );

		return array( 'term_id' => $term_id );
	}

	/**
	 * Creates lesson group term and stores plan metadata.
	 *
	 * @param array $plan Plan output.
	 * @return int|WP_Error
	 */
	private function upsert_lesson_group( $plan ) {
		$name = isset( $plan['lesson_group'] ) ? $plan['lesson_group'] : ( 'Objective: ' . $plan['objective'] );
		$term = term_exists( $name, 'lesson_group' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'lesson_group' );
		}

		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$term_id = absint( $term['term_id'] );
		update_term_meta( $term_id, '_1111_lesson_plan', wp_json_encode( $plan ) );
		return $term_id;
	}

	/**
	 * Creates draft lesson post.
	 *
	 * @param array $lesson Lesson payload.
	 * @param array $activity Activity payload.
	 * @param array $course Course payload.
	 * @param int   $course_term_id Course term id.
	 * @param int   $group_id Lesson group term.
	 * @param array $plan Lesson plan payload.
	 * @return int|WP_Error
	 */
	private function create_lesson_post( $lesson, $activity, $course, $course_term_id, $group_id, $plan ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'learn',
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $lesson['lesson_title'] ),
				'post_content' => $this->markdown_to_blocks( $lesson['lesson_body'] ),
				'post_excerpt' => isset( $lesson['lesson_summary'] ) ? sanitize_text_field( $lesson['lesson_summary'] ) : '',
				'post_author'  => $this->agent_user_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_post_terms( $post_id, array( $course_term_id ), 'course', false );
		wp_set_post_terms( $post_id, array( $group_id ), 'lesson_group', false );

		update_post_meta( $post_id, '_1111_mastery_criteria', wp_json_encode( $plan['mastery_criteria'] ) );
		update_post_meta( $post_id, '_1111_key_takeaways', wp_json_encode( $lesson['key_takeaways'] ) );
		update_post_meta( $post_id, '_1111_activity', wp_json_encode( $activity ) );
		update_post_meta( $post_id, '_1111_activity_type', sanitize_key( $activity['activity_type'] ) );
		update_post_meta( $post_id, '_1111_xp_value', absint( $activity['xp_value'] ) );
		update_post_meta( $post_id, '_1111_portfolio_contribution', isset( $activity['portfolio_contribution'] ) ? wp_kses_post( $activity['portfolio_contribution'] ) : '' );
		update_post_meta( $post_id, '_1111_work_product', sanitize_text_field( $course['work_product'] ) );
		update_post_meta( $post_id, '_1111_is_assessment', 0 );
		update_post_meta( $post_id, '_1111_generation_version', 1 );

		return $post_id;
	}

	/**
	 * Create final assessment post.
	 *
	 * @param array $assessment Assessment payload.
	 * @param int   $course_term_id Course term id.
	 * @return int|WP_Error
	 */
	private function create_assessment_post( $assessment, $course_term_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'learn',
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( $assessment['assessment_title'] ),
				'post_content' => $this->markdown_to_blocks( $assessment['instructions'] ),
				'post_author'  => $this->agent_user_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_post_terms( $post_id, array( $course_term_id ), 'course', false );
		update_post_meta( $post_id, '_1111_is_assessment', 1 );
		update_post_meta( $post_id, '_1111_assessment', wp_json_encode( $assessment ) );
		update_post_meta( $post_id, '_1111_generation_version', 1 );

		return $post_id;
	}

	/**
	 * Converts markdown-like text to blocks.
	 *
	 * @param string $markdown Markdown text.
	 * @return string
	 */
	private function markdown_to_blocks( $markdown ) {
		$paragraphs = preg_split( '/\n\n+/', trim( (string) $markdown ) );
		$blocks     = array();

		foreach ( $paragraphs as $paragraph ) {
			$blocks[] = '<!-- wp:paragraph --><p>' . esc_html( trim( $paragraph ) ) . '</p><!-- /wp:paragraph -->';
		}

		return implode( "\n\n", $blocks );
	}
}
