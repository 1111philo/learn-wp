<?php
/**
 * Learner Progress View (Admin)
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get all learners (users with sites)
$learners = get_users(); // This is simplified, in multisite we'd filter by site membership
?>

<div class="wrap" id="learn-progress-dashboard">
    <h1>
        <?php _e('Learner Progress', '1111-learn'); ?>
    </h1>

    <div class="card">
        <h2>
            <?php _e('Overview', '1111-learn'); ?>
        </h2>
        <p>
            <?php _e('Track all learners and their course completions.', '1111-learn'); ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <?php _e('Learner', '1111-learn'); ?>
                    </th>
                    <th>
                        <?php _e('Enrolled Courses', '1111-learn'); ?>
                    </th>
                    <th>
                        <?php _e('Lessons Completed', '1111-learn'); ?>
                    </th>
                    <th>
                        <?php _e('Total XP', '1111-learn'); ?>
                    </th>
                    <th>
                        <?php _e('Last Activity', '1111-learn'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($learners)):
                    foreach ($learners as $learner): ?>
                        <tr>
                            <td><strong>
                                    <?php echo esc_html($learner->display_name); ?>
                                </strong></td>
                            <td>
                                <?php echo count(get_blogs_of_user($learner->ID)) - 1; // Simplified ?>
                            </td>
                            <td>-</td>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="5">
                            <?php _e('No learners found.', '1111-learn'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>