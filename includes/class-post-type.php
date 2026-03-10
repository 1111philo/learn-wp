<?php
/**
 * Post Type and Taxonomy Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Learn_Post_Type
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
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_taxonomies'));
        add_action('add_meta_boxes', array($this, 'add_learn_meta_boxes'));

        // Course taxonomy feedback
        add_action('course_edit_form_fields', array($this, 'course_edit_form_fields'));
        add_action('edited_course', array($this, 'save_course_meta'));
    }

    /**
     * Register Custom Post Types
     */
    public function register_post_types()
    {
        $labels = array(
            'name' => _x('Lessons', 'Post Type General Name', '1111-learn'),
            'singular_name' => _x('Lesson', 'Post Type Singular Name', '1111-learn'),
            'menu_name' => __('Learn', '1111-learn'),
            'name_admin_bar' => __('Lesson', '1111-learn'),
            'archives' => __('Lesson Archives', '1111-learn'),
            'attributes' => __('Lesson Attributes', '1111-learn'),
            'parent_item_colon' => __('Parent Lesson:', '1111-learn'),
            'all_items' => __('All Lessons', '1111-learn'),
            'add_new_item' => __('Add New Lesson', '1111-learn'),
            'add_new' => __('Add New', '1111-learn'),
            'new_item' => __('New Lesson', '1111-learn'),
            'edit_item' => __('Edit Lesson', '1111-learn'),
            'update_item' => __('Update Lesson', '1111-learn'),
            'view_item' => __('View Lesson', '1111-learn'),
            'view_items' => __('View Lessons', '1111-learn'),
            'search_items' => __('Search Lesson', '1111-learn'),
            'not_found' => __('Not found', '1111-learn'),
            'not_found_in_trash' => __('Not found in Trash', '1111-learn'),
            'featured_image' => __('Featured Image', '1111-learn'),
            'set_featured_image' => __('Set featured image', '1111-learn'),
            'remove_featured_image' => __('Remove featured image', '1111-learn'),
            'use_featured_image' => __('Use as featured image', '1111-learn'),
            'insert_into_item' => __('Insert into lesson', '1111-learn'),
            'uploaded_to_this_item' => __('Uploaded to this lesson', '1111-learn'),
            'items_list' => __('Lessons list', '1111-learn'),
            'items_list_navigation' => __('Lessons list navigation', '1111-learn'),
            'filter_items_list' => __('Filter lessons list', '1111-learn'),
        );
        $args = array(
            'label' => __('Lesson', '1111-learn'),
            'description' => __('Learn Course Lessons', '1111-learn'),
            'labels' => $labels,
            'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'),
            'taxonomies' => array('course', 'lesson_group'),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-welcome-learn-more', // Fallback, will use SVG logo
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => true,
            'capability_type' => 'post',
            'show_in_rest' => true,
        );
        register_post_type('learn', $args);
    }

    /**
     * Register Custom Taxonomies
     */
    public function register_taxonomies()
    {
        // Course Taxonomy
        $course_labels = array(
            'name' => _x('Courses', 'Taxonomy General Name', '1111-learn'),
            'singular_name' => _x('Course', 'Taxonomy Singular Name', '1111-learn'),
            'menu_name' => __('Courses', '1111-learn'),
            'all_items' => __('All Courses', '1111-learn'),
            'parent_item' => __('Parent Course', '1111-learn'),
            'parent_item_colon' => __('Parent Course:', '1111-learn'),
            'new_item_name' => __('New Course Name', '1111-learn'),
            'add_new_item' => __('Add New Course', '1111-learn'),
            'edit_item' => __('Edit Course', '1111-learn'),
            'update_item' => __('Update Course', '1111-learn'),
            'view_item' => __('View Course', '1111-learn'),
            'separate_items_with_commas' => __('Separate courses with commas', '1111-learn'),
            'add_or_remove_items' => __('Add or remove courses', '1111-learn'),
            'choose_from_most_used' => __('Choose from the most used', '1111-learn'),
            'popular_items' => __('Popular Courses', '1111-learn'),
            'search_items' => __('Search Courses', '1111-learn'),
            'not_found' => __('Not Found', '1111-learn'),
            'no_terms' => __('No courses', '1111-learn'),
            'items_list' => __('Courses list', '1111-learn'),
            'items_list_navigation' => __('Courses list navigation', '1111-learn'),
        );
        $course_args = array(
            'labels' => $course_labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
        );
        register_taxonomy('course', array('learn'), $course_args);

        // Lesson Group Taxonomy
        $group_labels = array(
            'name' => _x('Lesson Groups', 'Taxonomy General Name', '1111-learn'),
            'singular_name' => _x('Lesson Group', 'Taxonomy Singular Name', '1111-learn'),
            'menu_name' => __('Lesson Groups', '1111-learn'),
            'all_items' => __('All Lesson Groups', '1111-learn'),
            'parent_item' => __('Parent Lesson Group', '1111-learn'),
            'parent_item_colon' => __('Parent Lesson Group:', '1111-learn'),
            'new_item_name' => __('New Lesson Group Name', '1111-learn'),
            'add_new_item' => __('Add New Lesson Group', '1111-learn'),
            'edit_item' => __('Edit Lesson Group', '1111-learn'),
            'update_item' => __('Update Lesson Group', '1111-learn'),
            'view_item' => __('View Lesson Group', '1111-learn'),
            'separate_items_with_commas' => __('Separate lesson groups with commas', '1111-learn'),
            'add_or_remove_items' => __('Add or remove lesson groups', '1111-learn'),
            'choose_from_most_used' => __('Choose from the most used', '1111-learn'),
            'popular_items' => __('Popular Lesson Groups', '1111-learn'),
            'search_items' => __('Search Lesson Groups', '1111-learn'),
            'not_found' => __('Not Found', '1111-learn'),
            'no_terms' => __('No lesson groups', '1111-learn'),
            'items_list' => __('Lesson Groups list', '1111-learn'),
            'items_list_navigation' => __('Lesson Groups list navigation', '1111-learn'),
        );
        $group_args = array(
            'labels' => $group_labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
        );
        register_taxonomy('lesson_group', array('learn'), $group_args);
    }

    /**
     * Add Meta Boxes
     */
    public function add_learn_meta_boxes()
    {
        add_meta_box(
            'learn-activity-data',
            __('Activity Details', '1111-learn'),
            array($this, 'render_activity_meta_box'),
            'learn',
            'normal',
            'high'
        );
    }

    /**
     * Render Activity Meta Box
     */
    public function render_activity_meta_box($post)
    {
        $data = get_post_meta($post->ID, '_1111_activity_data', true);
        if (empty($data)) {
            echo '<p>' . __('No activity data for this lesson.', '1111-learn') . '</p>';
            return;
        }
        ?>
        <div class="learn-meta-box">
            <p><strong><?php _e('Activity Type:', '1111-learn'); ?></strong> <?php echo esc_html($data['activity_type']); ?></p>
            <p><strong><?php _e('Prompt:', '1111-learn'); ?></strong><br><?php echo esc_html($data['prompt']); ?></p>
            <p><strong><?php _e('Instructions:', '1111-learn'); ?></strong><br><?php echo esc_html($data['instructions']); ?>
            </p>
            <p><strong><?php _e('Scoring Rubric:', '1111-learn'); ?></strong></p>
            <ul>
                <?php foreach ($data['scoring_rubric'] as $criterion): ?>
                    <li><?php echo esc_html($criterion); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><strong><?php _e('XP Value:', '1111-learn'); ?></strong> <?php echo esc_html($data['xp_value']); ?></p>
        </div>
        <?php
    }

    /**
     * Add feedback fields to course edit screen
     */
    public function course_edit_form_fields($term)
    {
        $feedback = get_term_meta($term->term_id, '_1111_feedback_course-describer', true);
        ?>
        <tr class="form-field">
            <th scope="row"><label
                    for="course_regeneration_feedback"><?php _e('Regeneration Feedback', '1111-learn'); ?></label></th>
            <td>
                <textarea name="course_regeneration_feedback" id="course_regeneration_feedback" rows="5" cols="50"
                    style="width: 95%;"><?php echo esc_textarea($feedback); ?></textarea>
                <p class="description">
                    <?php _e('Enter feedback to regenerate the entire course. Note: This will delete and recreate all lessons.', '1111-learn'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save course meta and trigger regeneration if feedback changed
     */
    public function save_course_meta($term_id)
    {
        if (isset($_POST['course_regeneration_feedback'])) {
            $feedback = sanitize_textarea_field($_POST['course_regeneration_feedback']);
            $old_feedback = get_term_meta($term_id, '_1111_feedback_course-describer', true);

            if ($feedback && $feedback !== $old_feedback) {
                // Trigger full regeneration
                $orchestrator = new Learn_Orchestrator();
                $orchestrator->trigger_regeneration($term_id, 'course-describer', $feedback);
            }
        }
    }
}
