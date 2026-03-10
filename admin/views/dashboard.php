<div class="wrap" id="learn-dashboard">
    <h1>
        <?php _e('Learn Dashboard', '1111-learn'); ?>
    </h1>

    <div class="card" id="learn-creation-card">
        <h2>
            <?php _e('Create a New Course', '1111-learn'); ?>
        </h2>
        <p>
            <?php _e('Enter your course title, a brief description, and your learning objectives to start the AI generation pipeline.', '1111-learn'); ?>
        </p>

        <form id="learn-generate-form" method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="course-title">
                            <?php _e('Course Title', '1111-learn'); ?>
                        </label></th>
                    <td><input name="title" type="text" id="course-title" value="" class="large-text" required
                            placeholder="e.g. Web Accessibility Fundamentals"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="course-description">
                            <?php _e('Course Description', '1111-learn'); ?>
                        </label></th>
                    <td><textarea name="description" id="course-description" rows="5" class="large-text" required
                            placeholder="<?php _e('What is this course about?', '1111-learn'); ?>"></textarea></td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php _e('Learning Objectives', '1111-learn'); ?>
                    </th>
                    <td>
                        <div id="objectives-container">
                            <div class="objective-row">
                                <input name="objectives[]" type="text" class="large-text objective-input" required
                                    placeholder="<?php _e('e.g. Identify common accessibility barriers', '1111-learn'); ?>">
                            </div>
                        </div>
                        <button type="button" id="add-objective" class="button button-secondary">
                            <?php _e('+ Add Objective', '1111-learn'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Each objective will produce one or more lessons.', '1111-learn'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Generate Course', '1111-learn'), 'primary', 'generate-course'); ?>
        </form>
    </div>

    <div id="learn-progress-stepper" style="display:none;">
        <div class="card">
            <h2>
                <?php _e('Generating Your Course', '1111-learn'); ?>
            </h2>
            <div class="stepper-progress">
                <div class="stepper-item" id="step-phase-0">
                    <span class="stepper-icon"></span>
                    <span class="stepper-label">
                        <?php _e('Establishing course narrative...', '1111-learn'); ?>
                    </span>
                </div>
                <div id="dynamic-steps">
                    <!-- Objective steps will be injected here -->
                </div>
                <div class="stepper-item" id="step-phase-final">
                    <span class="stepper-icon"></span>
                    <span class="stepper-label">
                        <?php _e('Creating summative assessment...', '1111-learn'); ?>
                    </span>
                </div>
            </div>
            <div id="stepper-status-message"></div>
        </div>
    </div>
</div>