# Learn — WordPress Plugin

AI-powered course creation plugin for WordPress Multisite by 11:11 Philosopher's Group.

## Key Document

`docs/learn-prd.md` is the single source of truth. Read it before any implementation work.

## What This Plugin Does

A super admin enters a course title, description, and learning objectives. A seven-agent AI pipeline generates the entire course (narrative, lessons, activities, assessment). Learners sign up, get their own WordPress subsite, select courses, and learn by building real WordPress content (pages, posts, sites) as portfolio artifacts. An AI agent assesses their work against generated rubrics.

## Architecture at a Glance

- **WordPress Multisite** — main site for course creation, learner subsites for learning
- **Custom post type:** `learn` — all lessons, activities, and assessments
- **Taxonomies:** `course` (flat), `lesson_group` (tag)
- **1111 Agent user** — system user that authors all generated content; humans interact via feedback, never direct editing
- **Seven agents:** Course Describer → Lesson Planner → Lesson Writer → Activity Creator → Activity Reviewer → Assessment Creator → Activity Assessment (on-demand)
- **Prompts as data:** `prompts/*.md` files loaded at runtime

## Hard Rules

1. **No build step.** Vanilla PHP, JS, CSS. No Webpack, Sass, npm, or Composer.
2. **No frameworks.** Block editor JS uses `@wordpress/*` packages bundled with WordPress — no npm install.
3. **WordPress coding standards.** PHP and JS.
4. **Prefix everything.** `_1111_learn_` for meta/options/tables, `Learn_` for PHP classes, `1111_learn_` for hooks.
5. **Agent user owns all content.** All generated posts authored by `1111-learn-agent`. No human can directly edit `post_content` of agent-authored posts.
6. **Feedback, not editing.** Super admins and learners provide feedback text → agents regenerate. Content is never manually edited.
7. **Publish immutability.** Published courses on the main site cannot be modified. Super admin must create a new course.
8. **Multisite required.** Bail with admin notice if not network activated on Multisite.
9. **Block editor required.** No classic editor fallbacks.
10. **WCAG 2.1 AA.** All UI and generated content must be accessible.

## Implementation Phases

Build in this order (see PRD Section 19 for full checklists):

1. **Foundation** — Plugin bootstrap, CPT, taxonomies, agent user, settings page, Multisite checks
2. **Agents and Orchestrator** — API client, prompt loader, JSON parser, validation, orchestrator, all 7 prompt files
3. **Admin Dashboard** — Course creation form, generation progress UI, course review panel, publish flow, learner progress dashboard
4. **Content Generation Pipeline** — Wire form → orchestrator → agents → draft posts with full meta
5. **Block Editor Integration** — Content locking, feedback sidebar panels, activity meta box, publish immutability
6. **Feedback and Regeneration** — Feedback UI at all 5 levels, cascading regeneration, learner feedback on their copy
7. **Learner Registration and Content Distribution** — Registration form, subsite provisioning, content copy, learner panel, course navigation
8. **Telemetry** — Opt-in event collection, learn-service integration, feedback tracking
9. **Learner Assessment Pipeline** — Submission endpoint, content extraction, Activity Assessment Agent, results display
10. **Polish and Quality** — Accessibility audit, security audit, prompt iteration, end-to-end testing

## File Structure

```
learn-wp/
├── learn.php                        Main plugin file
├── uninstall.php                    Cleanup on uninstall
├── includes/
│   ├── class-api-client.php         Anthropic API (wp_remote_post)
│   ├── class-orchestrator.php       Seven-agent pipeline
│   ├── class-validator.php          JSON schema validation per agent
│   ├── class-prompt-loader.php      Loads prompts/*.md
│   ├── class-admin-page.php         Dashboard (main site, super admin)
│   ├── class-settings.php           API key + data sharing
│   ├── class-content-lock.php       Block locking + post_data filter
│   ├── class-learner-panel.php      Learner panel (learner subsites)
│   ├── class-learner-registration.php  Registration + subsite provisioning
│   ├── class-content-copy.php       Copy course content to learner subsites
│   ├── class-assessment.php         Activity Assessment Agent integration
│   ├── class-telemetry.php          Anonymous telemetry
│   ├── class-course-navigation.php  Frontend course/lesson navigation
│   └── agents/
│       ├── class-course-describer.php
│       ├── class-lesson-planner.php
│       ├── class-lesson-writer.php
│       ├── class-activity-creator.php
│       ├── class-activity-reviewer.php
│       ├── class-assessment-creator.php
│       └── class-activity-assessment.php
├── prompts/                         Agent system prompts (Markdown)
├── admin/
│   ├── css/admin.css
│   ├── js/admin.js
│   └── js/editor-sidebar.js         Block editor sidebar (feedback panels)
├── public/
│   ├── css/learn-public.css          Frontend styles (course navigation)
│   └── js/learn-public.js            Frontend JS (submission, navigation)
└── assets/
    ├── learn-logo.svg
    └── 1111-logo.svg
```

## Key Conventions

- **API calls:** `wp_remote_post()` only. No Composer, no Guzzle.
- **Meta keys:** Always prefixed `_1111_` (underscore = hidden from custom fields UI).
- **Hooks:** `1111_learn_` prefix. Filters before agent calls, actions after.
- **Views:** PHP templates in `includes/views/`. No template engine.
- **Settings:** WordPress Settings API with nonce verification.
- **Multisite API:** `switch_to_blog()` / `restore_current_blog()` for cross-site reads.
- **Models:** Fast model (`claude-haiku-4-5-20251001`) for planning agents, default model (`claude-sonnet-4-6`) for content generation and assessment.

## Testing

No test framework in v1 (no build step). Verify manually:
- Super admin creates course → reviews → provides feedback → regenerates → publishes
- Learner signs up → gets subsite → selects course → navigates lessons → completes activities → submits → gets assessed
- Published content cannot be modified
- Feedback-driven regeneration improves output
- All UI accessible via keyboard and screen reader
