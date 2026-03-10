# Learn (WordPress Multisite Plugin)

Learn is an AI-powered course creation and learner portfolio plugin for WordPress Multisite.

## Requirements

- WordPress 6.7+
- PHP 8.0+
- Multisite enabled
- Plugin network-activated
- Anthropic API key

## Install

1. Copy `learn-wp` into `wp-content/plugins/learn-wp`.
2. Network activate plugin from Network Admin > Plugins.
3. Go to Network Admin > Settings > Learn and save Anthropic API key.
4. On the main site, open Network Admin > Learn to generate courses.
5. Add `[learn_register]` to main site page for learner signup.
6. Add `[learn_learner_panel]` to learner subsite page to browse/enroll.

## Architecture

- CPT: `learn`
- Taxonomies: `course`, `lesson_group`
- Agent user: `1111-learn-agent`
- Pipeline: Course Describer -> Lesson Planner -> Lesson Writer -> Activity Creator -> Activity Reviewer -> Assessment Creator
- Assessment agent runs on learner submission

## Security and Workflow Rules

- Agent-authored content is locked against manual human editing.
- Published courses are immutable on main site.
- Regeneration should be feedback-driven.
- API key is stored encrypted in site options.

## Current coverage

This implementation includes Phase 1 and 2 core architecture plus executable admin/learner surfaces for generation, enrollment, and assessment submission.
