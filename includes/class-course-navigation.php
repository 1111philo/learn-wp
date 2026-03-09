<?php
/**
 * Frontend course navigation for learner subsites.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Learn_Course_Navigation
 *
 * Provides shortcodes for course landing page and lesson navigation,
 * plus activity submission UI.
 */
class Learn_Course_Navigation {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_shortcode( 'learn_course', array( __CLASS__, 'render_course_page' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_lesson_navigation' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_public_assets' ) );
	}

	/**
	 * Render the course landing page shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public static function render_course_page( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'learn_course' );

		$term_id = absint( $atts['id'] );
		if ( ! $term_id ) {
			// Get the first course on this site.
			$terms = get_terms( array(
				'taxonomy' => 'course',
				'hide_empty' => false,
				'number' => 1,
			) );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term_id = $terms[0]->term_id;
			}
		}

		if ( ! $term_id ) {
			return '<p>' . esc_html__( 'No course found.', 'learn' ) . '</p>';
		}

		$term      = get_term( $term_id, 'course' );
		$narrative = get_term_meta( $term_id, '_1111_narrative_description', true );
		$wp_name   = get_term_meta( $term_id, '_1111_work_product', true );
		$wp_desc   = get_term_meta( $term_id, '_1111_work_product_description', true );
		$total_xp  = (int) get_term_meta( $term_id, '_1111_total_xp', true );

		$posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'tax_query'   => array( array(
				'taxonomy' => 'course',
				'terms'    => $term_id,
			) ),
		) );

		ob_start();
		?>
		<div class="learn-course-landing">
			<h2 class="learn-course-title"><?php echo esc_html( $term->name ); ?></h2>

			<?php if ( $narrative ) : ?>
			<div class="learn-course-narrative">
				<p><?php echo esc_html( $narrative ); ?></p>
			</div>
			<?php endif; ?>

			<?php if ( $wp_name ) : ?>
			<div class="learn-work-product-info">
				<h3><?php esc_html_e( 'What You\'ll Build', 'learn' ); ?></h3>
				<p><strong><?php echo esc_html( $wp_name ); ?></strong></p>
				<?php if ( $wp_desc ) : ?>
				<p><?php echo esc_html( $wp_desc ); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<div class="learn-course-meta">
				<span><?php printf( esc_html__( '%d lessons', 'learn' ), count( $posts ) ); ?></span>
				<span><?php printf( esc_html__( '%s total XP', 'learn' ), number_format_i18n( $total_xp ) ); ?></span>
			</div>

			<?php if ( ! empty( $posts ) ) : ?>
			<nav class="learn-lesson-list" aria-label="<?php esc_attr_e( 'Course lessons', 'learn' ); ?>">
				<h3><?php esc_html_e( 'Lessons', 'learn' ); ?></h3>
				<ol>
					<?php foreach ( $posts as $i => $post ) :
						$activity_type = get_post_meta( $post->ID, '_1111_activity_type', true );
						$xp            = (int) get_post_meta( $post->ID, '_1111_xp_value', true );
						$is_final      = ( 'final' === $activity_type );
					?>
					<li class="learn-lesson-item<?php echo $is_final ? ' learn-lesson-item--final' : ''; ?>">
						<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
							<span class="learn-lesson-number"><?php echo esc_html( $i + 1 ); ?>.</span>
							<span class="learn-lesson-name"><?php echo esc_html( $post->post_title ); ?></span>
							<?php if ( $activity_type && ! $is_final ) : ?>
							<span class="learn-badge learn-badge--<?php echo esc_attr( $activity_type ); ?>">
								<?php echo esc_html( ucfirst( $activity_type ) ); ?>
							</span>
							<?php endif; ?>
							<?php if ( $is_final ) : ?>
							<span class="learn-badge learn-badge--final">
								<?php esc_html_e( 'Final', 'learn' ); ?>
							</span>
							<?php endif; ?>
							<span class="learn-xp"><?php echo esc_html( $xp ); ?> XP</span>
						</a>
					</li>
					<?php endforeach; ?>
				</ol>
			</nav>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Append lesson navigation to learn post content on the frontend.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public static function append_lesson_navigation( $content ) {
		if ( ! is_singular( 'learn' ) || is_admin() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$course_terms = wp_get_object_terms( $post_id, 'course', array( 'fields' => 'ids' ) );
		if ( empty( $course_terms ) ) {
			return $content;
		}

		$course_term_id = $course_terms[0];

		// Get all lessons in order.
		$all_posts = get_posts( array(
			'post_type'   => 'learn',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'tax_query'   => array( array(
				'taxonomy' => 'course',
				'terms'    => $course_term_id,
			) ),
		) );

		$current_index = -1;
		foreach ( $all_posts as $i => $p ) {
			if ( $p->ID === $post_id ) {
				$current_index = $i;
				break;
			}
		}

		if ( $current_index < 0 ) {
			return $content;
		}

		// Key takeaways.
		$takeaways = get_post_meta( $post_id, '_1111_key_takeaways', true );

		// Activity.
		$activity      = get_post_meta( $post_id, '_1111_activity', true );
		$activity_type = get_post_meta( $post_id, '_1111_activity_type', true );
		$xp_value      = (int) get_post_meta( $post_id, '_1111_xp_value', true );
		$milestone     = get_post_meta( $post_id, '_1111_milestone', true );

		// Navigation.
		$prev = $current_index > 0 ? $all_posts[ $current_index - 1 ] : null;
		$next = $current_index < count( $all_posts ) - 1 ? $all_posts[ $current_index + 1 ] : null;
		$total = count( $all_posts );

		ob_start();

		// Key takeaways section.
		if ( ! empty( $takeaways ) ) {
			echo '<div class="learn-takeaways">';
			echo '<h2>' . esc_html__( 'Key Takeaways', 'learn' ) . '</h2>';
			echo '<ul>';
			foreach ( $takeaways as $t ) {
				echo '<li>' . esc_html( $t ) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		// Activity section.
		if ( $activity ) {
			echo '<div class="learn-activity-block">';
			echo '<div class="learn-activity-header">';
			echo '<h2>' . esc_html__( 'Activity', 'learn' ) . '</h2>';
			if ( $activity_type ) {
				echo '<span class="learn-badge learn-badge--' . esc_attr( $activity_type ) . '">' . esc_html( ucfirst( $activity_type ) ) . '</span>';
			}
			echo '<span class="learn-xp">' . esc_html( $xp_value ) . ' XP</span>';
			if ( $milestone ) {
				echo '<span class="learn-milestone">' . esc_html( $milestone ) . '</span>';
			}
			echo '</div>';

			if ( isset( $activity['prompt'] ) ) {
				echo '<p class="learn-activity-prompt">' . esc_html( $activity['prompt'] ) . '</p>';
			}
			if ( isset( $activity['instructions'] ) ) {
				echo '<p class="learn-activity-instructions">' . esc_html( $activity['instructions'] ) . '</p>';
			}
			if ( ! empty( $activity['hints'] ) ) {
				echo '<details class="learn-hints"><summary>' . esc_html__( 'Hints', 'learn' ) . '</summary><ul>';
				foreach ( $activity['hints'] as $hint ) {
					echo '<li>' . esc_html( $hint ) . '</li>';
				}
				echo '</ul></details>';
			}

			// Submit button (for learner subsites only).
			if ( ! is_main_site() && is_user_logged_in() ) {
				echo '<div class="learn-submit-activity">';
				echo '<button type="button" class="learn-submit-btn" data-post-id="' . esc_attr( $post_id ) . '">';
				echo esc_html__( 'Submit for Assessment', 'learn' );
				echo '</button>';
				echo '<div class="learn-assessment-result" id="learn-result-' . esc_attr( $post_id ) . '" hidden></div>';
				echo '</div>';
			}

			echo '</div>';
		}

		// Prev/Next navigation.
		echo '<nav class="learn-lesson-nav" aria-label="' . esc_attr__( 'Lesson navigation', 'learn' ) . '">';
		echo '<div class="learn-nav-progress">';
		printf( esc_html__( 'Lesson %1$d of %2$d', 'learn' ), $current_index + 1, $total );
		echo '</div>';
		echo '<div class="learn-nav-links">';
		if ( $prev ) {
			echo '<a href="' . esc_url( get_permalink( $prev->ID ) ) . '" class="learn-nav-prev">';
			echo '&larr; ' . esc_html( $prev->post_title );
			echo '</a>';
		}
		if ( $next ) {
			echo '<a href="' . esc_url( get_permalink( $next->ID ) ) . '" class="learn-nav-next">';
			echo esc_html( $next->post_title ) . ' &rarr;';
			echo '</a>';
		}
		echo '</div>';
		echo '</nav>';

		return $content . ob_get_clean();
	}

	/**
	 * Enqueue public CSS and JS on learn post pages.
	 */
	public static function enqueue_public_assets() {
		if ( ! is_singular( 'learn' ) && ! self::is_learn_shortcode_page() ) {
			return;
		}

		wp_enqueue_style(
			'learn-public',
			LEARN_PLUGIN_URL . 'public/css/learn-public.css',
			array(),
			LEARN_VERSION
		);

		if ( is_singular( 'learn' ) && ! is_main_site() ) {
			wp_enqueue_script(
				'learn-public',
				LEARN_PLUGIN_URL . 'public/js/learn-public.js',
				array(),
				LEARN_VERSION,
				true
			);

			wp_localize_script( 'learn-public', 'learnPublic', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( '1111_learn_submit' ),
				'i18n'    => array(
					'submitting' => __( 'Submitting...', 'learn' ),
					'submit'     => __( 'Submit for Assessment', 'learn' ),
					'error'      => __( 'Submission failed. Please try again.', 'learn' ),
				),
			) );
		}
	}

	/**
	 * Check if the current page contains a learn shortcode.
	 *
	 * @return bool
	 */
	private static function is_learn_shortcode_page() {
		global $post;
		if ( ! $post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'learn_course' )
			|| has_shortcode( $post->post_content, 'learn_register' );
	}
}
