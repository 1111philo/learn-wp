<?php
/**
 * Course Review View
 */

if (!defined('ABSPATH')) {
    exit;
}

$term_id = isset($_GET['term_id']) ? intval($_GET['term_id']) : 0;
$term = get_term($term_id, 'course');

if (!$term || is_wp_error($term)) {
    echo '<div class="wrap"><h1>' . __('Course Not Found', '1111-learn') . '</h1></div>';
    return;
}

$status = get_term_meta($term_id, '_1111_generation_status', true);
$narrative = get_term_meta($term_id, '_1111_narrative_description', true);
$work_product = get_term_meta($term_id, '_1111_work_product', true);

// Get lessons
$lessons = new WP_Query(array(
    'post_type' => 'learn',
    'tax_query' => array(
        array(
            'taxonomy' => 'course',
            'field' => 'term_id',
            'terms' => $term_id,
        ),
    ),
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'post_status' => array('draft', 'publish'),
));
?>

<div class="wrap" id="learn-course-review">
    <h1>
        <?php printf(__('Review Course: %s', '1111-learn'), esc_html($term->name)); ?>
    </h1>

    <div class="card">
        <h2>
            <?php _e('Course Narrative', '1111-learn'); ?>
        </h2>
        <p class="description">
            <?php echo esc_html($narrative); ?>
        </p>
        <p><strong>
                <?php _e('Work Product:', '1111-learn'); ?>
            </strong>
            <?php echo esc_html($work_product); ?>
        </p>
    </div>

    <h2>
        <?php _e('Course Lessons', '1111-learn'); ?>
    </h2>
    <div id="learn-lessons-review-list">
        <?php if ($lessons->have_posts()):
            while ($lessons->have_posts()):
                $lessons->the_post(); ?>
                <div class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <?php the_title(); ?>
                        </h2>
                    </div>
                    <div class="inside">
                        <?php the_excerpt(); ?>
                        <hr>
                        <a href="<?php echo get_edit_post_link(); ?>" class="button">
                            <?php _e('Edit / Feedback', '1111-learn'); ?>
                        </a>
                    </div>
                </div>
            <?php endwhile;
            wp_reset_postdata(); else: ?>
            <p>
                <?php _e('No lessons generated for this course yet.', '1111-learn'); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ('published' !== $status): ?>
        <div id="learn-publish-actions" style="margin-top: 30px;">
            <form id="learn-publish-form" method="post">
                <input type="hidden" name="term_id" value="<?php echo $term_id; ?>">
                <?php submit_button(__('Publish Course', '1111-learn'), 'primary', 'publish-course'); ?>
            </form>
        </div>
    <?php endif; ?>
</div>