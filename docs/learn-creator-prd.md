# 1111 Learn Creator — Product Requirements Document

## WordPress Plugin for AI-Powered Course Content Creation

**Version:** 0.3.0-draft
**Date:** 2026-03-09
**Status:** Draft — awaiting review

---

## 1. Overview

**1111 Learn Creator** is a WordPress plugin that adds a "Learn" custom post type, a "Courses" taxonomy, and a "Lesson Groups" tag taxonomy. An administrator enters a course title, description, and learning objectives into a dashboard interface. A four-agent AI pipeline (powered by the Anthropic Claude API) then generates a cohesive course narrative, structured lesson plans, full lesson content, and practice activities — all saved as WordPress posts authored by a dedicated system agent user and organized under the appropriate Course and Lesson Group taxonomy terms. Generated content is immutable by human users; administrators review output and provide feedback that triggers regeneration through the same agent pipeline.

This plugin is **content-creation only** — it does not include assessments, learner profiles, progress tracking, or any learner-facing interactive features. Those concerns belong to a future companion plugin (1111 Learn Administrator).

### 1.1 Lineage

This plugin adapts proven agent patterns from two existing 1111 projects:

- **[1111 Learn](https://github.com/1111philo/learn-extension)** (Chrome extension) — Four-agent architecture: Course Creation → Activity Creation → Activity Assessment → Learner Profile. Prompts stored as Markdown files. Output validated deterministically before reaching the user. Retry-once on validation failure.
- **[1111 School](https://github.com/1111philo/learn)** (full-stack web app) — Seven PydanticAI agents with backward design methodology: Course Describer → Lesson Planner → Lesson Writer → Activity Creator → Activity Reviewer → Assessment Creator → Assessment Reviewer. Narrative threading across lessons. Scope control to prevent objective bleed. On-demand lesson generation.

The WordPress plugin takes the best of both: the narrative threading and backward design from School, the Markdown-file prompt editability from Learn, and a pipeline scoped to content creation only.

---

## 2. Goals

1. Let a WordPress administrator create a complete, structured course from three inputs: title, description, and learning objectives.
2. Generate pedagogically sound lesson content using a four-agent pipeline, with prompts stored as editable Markdown files so AI agents can iterate on output quality through telemetry-driven PRs.
3. Use backward design (define mastery → design evidence → build the path) to ensure lessons and activities are aligned to objectives.
4. Thread a narrative arc across all lessons so the course reads as a coherent journey, not disconnected topics.
5. Produce standard WordPress posts (custom post type `learn`) organized under a `course` taxonomy — compatible with any theme, page builder, or LMS plugin.
6. Keep the plugin self-contained: no build step, no JavaScript framework, no external dependencies beyond the Anthropic API.
7. Meet WCAG 2.1 AA accessibility standards in all admin UI and generated lesson content.
8. Continuously self-improve through telemetry: collect anonymous usage data from real course generations, feed it back to learn-service, and use it to automatically create PRs that refine agent prompts — so every generation makes the next one better, without human intervention in the feedback loop.

---

## 3. User Personas

### 3.1 Course Creator (WordPress Administrator)
- Has WordPress admin access
- Knows the subject matter and can write learning objectives
- May not be technical — needs a simple, guided interface
- Reviews generated content and provides **feedback** to trigger regeneration — does not directly edit generated post content
- Adds feedback at the appropriate level: course description, lesson plan, written lesson, or activity

### 3.2 Learner (End User)
- Consumes published lesson content on the WordPress frontend — the ultimate audience for everything this plugin generates
- May range from complete beginners to experienced practitioners depending on the course
- Expects a clear narrative arc: each lesson builds on the last and prepares for the next
- Needs activities that are challenging but achievable given the lesson content
- May have accessibility needs (screen readers, keyboard navigation, low vision) — generated content must be consumable by all
- **Does not interact with the plugin directly** — the learner experience is mediated entirely through WordPress posts and whatever theme or companion plugin (e.g., 1111 Learn Administrator) presents them

> Although the learner never touches the plugin, every design decision — backward design, narrative threading, mastery-aligned activities, accessible markup — exists to serve this persona. The learner is the reason the plugin exists.

### 3.3 Developer (PR Reviewer)
- Reviews agent-generated PRs that improve prompts based on telemetry data
- Uses Claude Code for all development work on the plugin
- Does not manually edit prompt files — AI agents propose changes, the developer reviews and merges

### 3.4 1111 Agent User (System User)

The plugin creates a dedicated WordPress user on activation — the **1111 Agent** user. This is the only user that owns and edits generated post content.

- **Username:** `1111-learn-agent`
- **Role:** Custom role `1111_learn_agent` with capabilities: `edit_learn_posts`, `edit_published_learn_posts`, `publish_learn_posts`, `delete_learn_posts`, `read`
- **Email:** `agent@1111-learn.local` (non-routable, placeholder)
- **Created on:** Plugin activation (`register_activation_hook`)
- **Removed on:** Plugin uninstall (`uninstall.php`)

All generated posts (`learn` CPT) are authored by this user. The `post_author` is set to the agent user's ID during generation, and the agent user is the only user with the `edit_learn_posts` capability for posts it authored. Human administrators **cannot directly edit** the `post_content`, `post_title`, or `post_excerpt` of agent-authored posts. Instead, they provide feedback that triggers regeneration (see Section 8.4).

**Why an agent user?**
- Creates a clean audit trail: every generated post is clearly marked as AI-authored
- Enforces the feedback-driven workflow — if you can't directly edit, you must provide feedback, which flows through the pipeline and produces better output
- The agent user's edit history becomes telemetry data: when the agent rewrites a post, the revision diff shows exactly what changed
- Future companion plugins (1111 Learn Administrator) can identify AI-generated content by author

---

## 4. Architecture

### 4.1 Four-Agent Pipeline

The generation pipeline uses four sequential agents. Each agent's output feeds into the next. This mirrors the proven patterns from 1111 School's generation service, adapted for WordPress.

```
Admin Input (title, description, objectives)
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Agent 1: Course Describer                       │
│  (fast model)                                    │
│  Establishes narrative arc + lesson titles        │
│  Output: narrative_description, lesson_previews   │
│  ◄── Course feedback triggers re-run             │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 2: Lesson Planner (per objective)         │
│  (fast model)                                    │
│  Backward design: mastery → activity → outline(s)│
│  May produce 1–4 lessons per objective            │
│  Output: mastery_criteria, activity_seed, lessons │
│  ◄── Lesson plan feedback triggers re-run        │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 3: Lesson Writer (per lesson)             │
│  (default model — needs more tokens)             │
│  Writes full lesson content from the plan         │
│  Output: lesson_body, key_takeaways               │
│  ◄── Lesson feedback triggers re-run             │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 4: Activity Creator (per lesson)          │
│  (fast model)                                    │
│  Designs practice activity from activity seed     │
│  Output: prompt, instructions, rubric, hints      │
│  ◄── Activity feedback triggers re-run           │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  WordPress Posts (CPT: learn)                     │
│  Author: 1111 Agent user (content locked)         │
│  Taxonomies: course + lesson_group                │
│                                                   │
│  Course: "Web Accessibility Fundamentals"         │
│    ├── Lesson Group: "Seeing the Barriers"        │
│    │   ├── Lesson 1a + Activity (draft)           │
│    │   └── Lesson 1b + Activity (draft)           │
│    ├── Lesson Group: "Auditing with Browser Tools"│
│    │   └── Lesson 2 + Activity (draft)            │
│    └── Lesson Group: "Writing Fix Recommendations"│
│        └── Lesson 3 + Activity (draft)            │
│                                                   │
│  Admin provides FEEDBACK → triggers regeneration  │
│  Admin CANNOT directly edit post content          │
└───────────────────────────────────────────────────┘
```

### 4.2 Why Four Agents Instead of Two

The original PRD used two agents (plan + write). After studying the 1111 School pipeline, four agents produce significantly better content because:

1. **Course Describer** ensures narrative coherence — lesson titles feel like chapters in the same story, not isolated topics. Without this, each lesson is planned in isolation and the course lacks an arc.
2. **Lesson Planner** uses backward design — defining mastery criteria first, then designing the activity, then planning the lesson content. This ensures the lesson teaches exactly what the learner needs to succeed at the activity.
3. **Lesson Writer** focuses solely on writing engaging content from a detailed plan, rather than simultaneously planning and writing.
4. **Activity Creator** designs activities anchored to specific mastery criteria, not generic "practice what you learned" exercises.

The incremental cost is minimal (3 fast-model calls + 1 default-model call per objective vs. 2 calls), but the quality improvement is substantial.

### 4.3 Agent Design Principles (from 1111 School)

These principles are proven in production and must carry forward:

- **Backward design:** Define the finish line (mastery criteria) → design the evidence (activity) → plan the path (lesson outline). Never start with content and hope it covers the objective.
- **Narrative threading:** The Course Describer identifies the PRIMARY objective and shows how others support it. Every lesson title and summary feels like a chapter in the same story.
- **Scope control:** Each lesson covers ONLY its assigned objective. The planner receives the full objective list but is explicitly told not to teach other objectives. This prevents scope creep and repetition.
- **Agents are functions, not frameworks:** Each agent takes typed input, returns typed output, validates against a schema, and retries on failure. No memory across invocations, no autonomous decisions.
- **Prompts are data, not code:** System prompts live in Markdown files. Dynamic context (course data, objectives) goes in the user message. Changing agent behavior never requires touching PHP — an AI agent can propose prompt improvements as a PR diff against `prompts/*.md`.
- **Built to be improved by agents:** Every design choice — Markdown prompt files, structured JSON output, deterministic validation with specific error messages, telemetry that captures failure patterns — exists so that an AI agent can diagnose what's wrong and propose a fix. The plugin is not just *used* by AI agents; it's *maintained* by them through the telemetry → PR pipeline (Section 15).

### 4.4 Custom Post Type: `learn`

| Property | Value |
|----------|-------|
| Post type slug | `learn` |
| Label (singular) | Lesson |
| Label (plural) | Lessons |
| Public | `true` |
| Has archive | `true` |
| Supports | `title`, `editor`, `excerpt`, `thumbnail`, `custom-fields`, `revisions` |
| Show in REST | `true` (Gutenberg compatible) |
| Menu icon | `dashicons-welcome-learn-more` |
| Menu position | 25 (below Comments) |

### 4.5 Custom Taxonomy: `course`

| Property | Value |
|----------|-------|
| Taxonomy slug | `course` |
| Label (singular) | Course |
| Label (plural) | Courses |
| Hierarchical | `false` (flat, like tags — courses don't nest) |
| Public | `true` |
| Show in REST | `true` |
| Associated post type | `learn` |

The course taxonomy term is where the admin provides **course description feedback** — the term edit screen includes a feedback field that triggers re-running the Course Describer agent (see Section 8.4).

### 4.6 Custom Taxonomy: `lesson_group` (Tag)

| Property | Value |
|----------|-------|
| Taxonomy slug | `lesson_group` |
| Label (singular) | Lesson Group |
| Label (plural) | Lesson Groups |
| Hierarchical | `false` (tag-style) |
| Public | `true` |
| Show in REST | `true` |
| Associated post type | `learn` |

Lesson groups organize lessons that were generated from the same lesson plan. A single lesson plan may produce multiple lessons (see Section 5.2 — lesson count setting), and all lessons from the same plan share a `lesson_group` tag.

The lesson group tag is where the admin provides **lesson plan feedback** — the tag edit screen shows the full lesson plan and includes a feedback field that triggers re-running the Lesson Planner and all downstream agents for that group (see Section 8.4).

**Term meta for `lesson_group`:**

| Meta key | Type | Description |
|----------|------|-------------|
| `_1111_lesson_plan_raw` | `array` | Full raw lesson plan from the Lesson Planner agent |
| `_1111_learning_objective` | `string` | The objective this lesson group covers |
| `_1111_lesson_count` | `int` | Number of lessons generated from this plan |
| `_1111_plan_feedback` | `string` | Admin's feedback on the lesson plan (cleared after regeneration) |
| `_1111_plan_version` | `int` | Incremented on each regeneration |

---

## 5. Agent Specifications

All agents use the Anthropic Claude API. The admin provides their own API key. System prompts are stored as Markdown files in `prompts/` and loaded at runtime.

### 5.1 Agent 1: Course Describer

**Purpose:** Given a course title, description, and learning objectives, produce a cohesive narrative description and pre-set lesson titles/summaries that thread all objectives into a single arc.

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 2048
**Prompt file:** `prompts/course-describer.md`

**Input (user message):**
```
Course title: Web Accessibility Fundamentals

Course description: Learn to identify, evaluate, and address common web accessibility barriers.

Learning objectives (3 total — produce one lesson entry for each):
1. Identify common accessibility barriers on web pages
2. Use browser developer tools to run basic accessibility audits
3. Propose concrete fixes for the accessibility issues you find
```

**Expected output (JSON):**
```json
{
  "narrative_description": "You'll start by learning to see the web through the eyes of users who face accessibility barriers every day — visual, motor, cognitive, and more. With that foundation, you'll pick up the browser tools that reveal these barriers in any webpage's code. By the end, you'll be writing specific, prioritized fix recommendations that developers can act on immediately.",
  "lessons": [
    {
      "lesson_title": "Seeing the Barriers",
      "lesson_summary": "Identify and categorize the most common accessibility barriers users face on the web."
    },
    {
      "lesson_title": "Auditing with Browser Tools",
      "lesson_summary": "Run systematic accessibility audits using built-in browser developer tools."
    },
    {
      "lesson_title": "Writing Fix Recommendations",
      "lesson_summary": "Produce specific, prioritized accessibility fix recommendations that developers can implement."
    }
  ]
}
```

**Key prompt rules (from 1111 School's course_describer):**
- `narrative_description` must identify the PRIMARY objective and show how others support it
- Give the learner a clear arc: where they start, what they build, where they end up
- Written in second person (you/your), energetic and specific
- One lesson entry per objective, in the same order — never merge, skip, or reorder
- Lesson titles must feel like chapters in the same story (foundation → application → mastery)
- Lesson summaries describe what the learner will be able to DO, not what the lesson covers

### 5.2 Agent 2: Lesson Planner

**Purpose:** Given one objective, the course narrative, the full objective list (for scope control), and a target lesson count, produce a backward-designed lesson plan: mastery criteria → activity seed → lesson outline(s). A single objective may require multiple lessons to cover adequately — the planner decides how to split the content across the target lesson count.

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 2048
**Prompt file:** `prompts/lesson-planner.md`

**Lesson count:** The admin sets a target number of lessons per objective on the Settings page (default: 1, range: 1–4). The Lesson Planner receives this as input and produces a plan with that many lesson outlines. When the count is greater than 1, the planner splits the objective's content across multiple lessons in a logical progression. All lessons from the same plan are grouped under a shared `lesson_group` tag.

**Input (user message):**
```
Course description: You'll start by learning to see the web through the eyes of users who face accessibility barriers...

Learning objective for THIS lesson group: Identify common accessibility barriers on web pages

Lesson title from Course Describer: Seeing the Barriers

Target lesson count: 2

Other objectives in this course (DO NOT teach these, they have their own lessons):
- Use browser developer tools to run basic accessibility audits
- Propose concrete fixes for the accessibility issues you find

Admin feedback on previous plan (if any): Split the visual barriers into their own lesson — there's too much for one lesson.
```

**Expected output (JSON) — single lesson (lesson_count=1):**
```json
{
  "learning_objective": "Identify and categorize at least five common web accessibility barriers across visual, motor, cognitive, and auditory categories.",
  "key_concepts": [
    "Visual barriers (contrast, color-only indicators, missing alt text)",
    "Motor barriers (small targets, keyboard traps, hover-only interactions)",
    "Cognitive barriers (complex layouts, auto-playing media, inconsistent navigation)",
    "Auditory barriers (missing captions, audio-only content)",
    "WCAG as a framework for categorizing barriers"
  ],
  "mastery_criteria": [
    "Names at least five distinct accessibility barriers with correct categorization",
    "Explains how each barrier affects real users (not just abstract rule violations)",
    "Identifies barriers from at least three different categories (visual, motor, cognitive, auditory)",
    "Uses specific examples rather than generic descriptions"
  ],
  "suggested_activity": {
    "activity_type": "short_answer",
    "prompt": "Visit any popular website and identify at least five accessibility barriers you can find.",
    "expected_evidence": [
      "Lists at least five barriers found on the chosen website",
      "Correctly categorizes each barrier",
      "Explains impact on real users for each barrier"
    ]
  },
  "lessons": [
    {
      "lesson_title": "Seeing the Barriers",
      "lesson_outline": [
        "Start with a scenario: a user with low vision trying to read a low-contrast form",
        "Define what accessibility barriers are and who they affect",
        "Walk through visual, motor, cognitive, and auditory barriers with real-world examples",
        "Introduce WCAG as the framework that organizes these barriers",
        "Show how to spot barriers on a real webpage (visual inspection technique)",
        "Recap: the five categories and why recognizing them matters"
      ]
    }
  ]
}
```

**Expected output (JSON) — multi-lesson (lesson_count=2):**
```json
{
  "learning_objective": "Identify and categorize at least five common web accessibility barriers...",
  "key_concepts": ["..."],
  "mastery_criteria": ["..."],
  "suggested_activity": { "..." },
  "lessons": [
    {
      "lesson_title": "Seeing the Visual Barriers",
      "lesson_outline": [
        "Start with a scenario: a user with low vision trying to read a low-contrast form",
        "Walk through visual barriers: contrast, color-only indicators, missing alt text",
        "Cover motor and keyboard barriers: small targets, keyboard traps, hover-only"
      ]
    },
    {
      "lesson_title": "Beyond What You Can See",
      "lesson_outline": [
        "Cover cognitive barriers: complex layouts, auto-playing media, inconsistent navigation",
        "Cover auditory barriers: missing captions, audio-only content",
        "Introduce WCAG as the unifying framework across all categories",
        "Recap: all categories and how they interconnect"
      ]
    }
  ]
}
```

**Key prompt rules (from 1111 School's lesson_planner):**
- **Backward design order:** Step 1: mastery_criteria (what does mastery look like?), Step 2: suggested_activity (what would demonstrate mastery?), Step 3: lesson outlines (what knowledge closes the gap?)
- **Scope control:** Cover ONLY the assigned objective. May briefly mention related topics for context but must NOT teach concepts belonging to other objectives.
- **Lesson splitting:** When `lesson_count` > 1, split the objective's content across lessons in a logical progression. Each lesson should build toward mastery, not stand alone. The last lesson in the group should connect all prior lessons to the mastery criteria.
- Mastery criteria must be specific and measurable — rubric-style checks a reviewer could use.
- Activity seed must directly exercise the mastery criteria, not just recall facts.
- Lesson outlines must collectively close the gap: after completing all lessons, a learner could plausibly meet every mastery criterion.
- The first lesson title uses the preset title from Course Describer. Additional lessons get planner-generated titles that read as continuations (e.g., "Part 2: Beyond What You Can See").
- **Feedback integration:** When admin feedback is provided, incorporate it into the new plan. The feedback may request structural changes (split/merge lessons), content emphasis changes, or scope adjustments.

### 5.3 Agent 3: Lesson Writer

**Purpose:** Given a specific lesson outline from the lesson plan (including mastery criteria and activity seed for context), write the full lesson content.

**Model:** Default model (`claude-sonnet-4-6` — needs more tokens for long-form content)
**Max tokens:** 8192
**Prompt file:** `prompts/lesson-writer.md`

**Input (user message):**
```
Course description: You'll start by learning to see the web through the eyes of users...

Lesson title: Seeing the Visual Barriers

Lesson outline:
["Start with a scenario: a user with low vision...", "Walk through visual barriers...", ...]

Mastery criteria (for the full lesson group — this lesson contributes to these):
["Names at least five distinct accessibility barriers...", ...]

Key concepts:
["Visual barriers (contrast, color-only indicators, missing alt text)", ...]

Admin feedback on previous version (if any): Add more concrete examples of contrast failures.
```

**Expected output (JSON):**
```json
{
  "lesson_title": "Seeing the Barriers",
  "key_takeaways": [
    "Accessibility barriers fall into four main categories: visual, motor, cognitive, and auditory.",
    "Each barrier affects real people in specific, concrete ways — not just abstract compliance violations.",
    "WCAG provides a systematic framework for identifying and categorizing barriers.",
    "Spotting barriers starts with a visual inspection: check contrast, keyboard access, alt text, and captions."
  ],
  "lesson_body": "## What Are Accessibility Barriers?\n\nImagine trying to fill out a loan application where the form labels are light gray on white..."
}
```

**Key prompt rules (from 1111 School's lesson_writer):**
- Start with a clear statement of the learning objective
- Explain why this topic matters (real-world relevance)
- Walk through key concepts with clear steps and explanations
- Include at least one concrete, worked example
- End with a brief recap tying back to the objective
- The plan includes mastery_criteria and suggested_activity — by the end of the lesson, the learner should have everything they need to attempt the activity and meet each criterion
- Use worked examples that mirror the skill demands of the activity
- Use Markdown: headings (`##`, `###`), lists, code blocks where appropriate
- Teach, don't lecture — clear, engaging voice
- `key_takeaways`: 3–6 short strings (1–2 sentences each). These are stored as post meta, NOT embedded in the lesson body.
- `lesson_body`: Minimum 200 characters. Full Markdown lesson content.

### 5.4 Agent 4: Activity Creator

**Purpose:** Given the activity seed and mastery criteria from the lesson plan, create a complete practice activity with instructions, rubric, and hints.

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 1024
**Prompt file:** `prompts/activity-creator.md`

**Input (user message):**
```
Learning objective: Identify common accessibility barriers on web pages

Mastery criteria:
- Names at least five distinct accessibility barriers with correct categorization
- Explains how each barrier affects real users
- Identifies barriers from at least three different categories
- Uses specific examples rather than generic descriptions

Activity seed:
{activity seed JSON from lesson plan}

Admin feedback on previous activity (if any): The rubric criteria are too vague — make them more specific.
```

**Expected output (JSON):**
```json
{
  "activity_type": "short_answer",
  "prompt": "Pick any popular website you use regularly. Explore it with accessibility in mind and identify at least five barriers you can find across different categories.",
  "instructions": "For each barrier, name it, categorize it (visual, motor, cognitive, or auditory), and explain in 1-2 sentences how it affects a real user. Aim for 200-400 words total.",
  "scoring_rubric": [
    "Identifies at least five distinct accessibility barriers",
    "Correctly categorizes each barrier into the right category",
    "Explains real user impact for each barrier (not just rule violations)",
    "Covers at least three different barrier categories",
    "Uses specific, concrete examples from the chosen website"
  ],
  "hints": [
    "Try navigating the website using only your keyboard — can you reach everything?",
    "Look at images: do they have alt text you can check in the browser?",
    "Check text contrast: can you read everything easily, especially in smaller sizes?",
    "Look for content that only works on hover or requires precise mouse movements"
  ]
}
```

**Key prompt rules (from 1111 School's activity_creator):**
- `prompt`: Core task question (1–2 sentences, min 20 chars). What the learner reads first — clear and direct.
- `instructions`: Format and constraint guidance ONLY (1–2 sentences, min 50 chars). Do NOT restate the prompt.
- `scoring_rubric`: 3–6 specific, checkable criteria that map to the mastery criteria.
- `hints`: 2–5 scaffolding hints that guide without giving the answer.
- Activity must directly test the learning objective — challenging but achievable.
- Anchor in real-world application, not hypothetical scenarios.

---

## 6. Output Validation

All agent output passes through deterministic validators before reaching WordPress. This pattern is proven in both 1111 Learn (browser-side) and 1111 School (Pydantic schema validation).

### 6.1 Validation Rules

**Course Describer output:**
- `narrative_description` is a non-empty string (min 100 chars)
- `lessons` is an array with exactly one entry per objective
- Each lesson has `lesson_title` (5–60 chars) and `lesson_summary` (min 30 chars)

**Lesson Plan output:**
- Has `lesson_title`, `learning_objective`, `key_concepts` (2–8 items), `mastery_criteria` (2–6 items)
- `suggested_activity` has `activity_type`, `prompt`, `expected_evidence` (2–5 items)
- `lesson_outline` has 3–10 items
- No unsafe content patterns

**Lesson Content output:**
- Has `lesson_title`, `lesson_body` (min 200 chars), `key_takeaways` (3–6 items)
- No unsafe content patterns

**Activity output:**
- Has `prompt` (min 20 chars), `instructions` (min 50 chars)
- `scoring_rubric` has 3–6 items
- `hints` has 2–5 items
- No unsafe content patterns

### 6.2 Retry Strategy

On validation failure, the agent call is retried once automatically (matching both Learn and School patterns). If the retry also fails, the admin sees an error with:
- Which agent failed and why
- A **Retry** button that resumes from the failed step
- Already-generated content is preserved (incremental recovery, per School's design)

### 6.3 Safety Patterns

All text output is checked against unsafe content patterns before saving:

```
/\b(kill yourself|self-harm|suicide method|how to hack|how to steal|how to attack)\b/i
```

Safety violations are never retried — the admin is shown an error and the generation stops.

---

## 7. Plugin File Structure

```
learned-wp-creator/
├── 1111-learn-creator.php          Main plugin file (plugin header, bootstrap)
├── README.md                       Plugin readme (WordPress-style + GitHub)
├── CLAUDE.md                       AI coding assistant instructions
├── LICENSE                         GPL v2+
├── uninstall.php                   Clean removal of plugin data + agent user
│
├── includes/
│   ├── class-post-type.php         Registers CPT and taxonomies (course + lesson_group)
│   ├── class-agent-user.php        Creates/manages the 1111 Agent user and custom role
│   ├── class-api-client.php        Anthropic API HTTP client
│   ├── class-orchestrator.php      Agent orchestration, pipeline, validation
│   ├── class-content-lock.php      Block editor content locking, wp_insert_post_data guard
│   ├── class-feedback.php          Feedback submission handling, regeneration triggers
│   ├── class-admin-page.php        Dashboard page registration and rendering
│   ├── class-settings.php          Settings page (API key, model config, lessons per objective)
│   └── class-telemetry.php         Event collection, buffering, learn-service transmission
│
├── admin/
│   ├── css/
│   │   └── admin.css               Dashboard + feedback panel styles
│   ├── js/
│   │   ├── admin.js                Dashboard interactivity (AJAX, form, progress)
│   │   └── editor-sidebar.js       Block editor sidebar panel (feedback for lesson + activity)
│   └── views/
│       ├── dashboard.php           Course creation form template
│       ├── settings.php            Settings page template
│       └── generating.php          Generation progress template (partial)
│
├── prompts/
│   ├── course-describer.md         System prompt — narrative arc + lesson titles
│   ├── lesson-planner.md           System prompt — backward design lesson plan
│   ├── lesson-writer.md            System prompt — full lesson content
│   └── activity-creator.md         System prompt — practice activity design
│
└── assets/
    └── icon.svg                    Plugin icon / branding
```

---

## 8. Admin Dashboard UI

### 8.1 Top-Level Menu

- **Menu title:** 1111 Learn
- **Icon:** `dashicons-welcome-learn-more`
- **Submenu items:**
  - **Dashboard** — Course creation form
  - **All Lessons** — Standard CPT list view (WordPress default)
  - **Courses** — Course taxonomy management (course-level feedback on term edit screen)
  - **Lesson Groups** — Lesson group tag management (plan-level feedback on tag edit screen)
  - **Settings** — API key, model configuration, lessons per objective

### 8.2 Dashboard Page — Course Creation Form

**Fields:**

| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Course Title | Text input | Required, max 200 chars | Becomes the taxonomy term name |
| Course Description | Textarea | Required, 10–1000 chars | Stored as taxonomy term description and passed to agents |
| Learning Objectives | Repeater (text inputs) | Min 1, max 8 items; each max 300 chars | Each objective is a measurable learning outcome |

**Objective count follows 1111 School's range:** 1–8 objectives (School uses 1–8 in its validation). Each objective produces one lesson.

**Interaction flow:**

1. Admin fills in the three fields and clicks **Generate Course**.
2. Form validates client-side. If invalid, inline errors appear next to fields.
3. On valid submission, an AJAX request sends data to the server.
4. The dashboard shows a **vertical stepper** progress view (matching School's generation UX):
   - Phase 0: "Establishing course narrative..." → shows lesson titles as they arrive
   - Per objective: "Planning lesson 1..." → "Writing lesson 1..." → "Creating activity 1..." (checkmarks on completion)
   - Overall: "Generation complete — N lessons created"
5. On completion, a success message with links to:
   - Review individual lessons in the block editor (read-only with feedback panel)
   - View the course archive page
   - Edit the course taxonomy term (to provide course-level feedback)
   - Return to dashboard to create another course
6. On error per objective: displayed inline in the stepper without blocking other objectives
7. On fatal error: error message with **Retry** button that resumes from the last successful step

### 8.3 Settings Page

| Field | Type | Notes |
|-------|------|-------|
| Anthropic API Key | Password input | Stored encrypted in `wp_options`. Masked in UI. |
| Fast Model | Select | Default: `claude-haiku-4-5-20251001`. Used by Describer, Planner, Activity Creator. |
| Default Model | Select | Default: `claude-sonnet-4-6`. Used by Lesson Writer (needs more tokens). |
| Max Tokens (Plan) | Number | Default: 2048. Range: 512–4096. |
| Max Tokens (Content) | Number | Default: 8192. Range: 1024–16384. |
| Lessons per Objective | Number | Default: 1. Range: 1–4. How many lessons the Lesson Planner generates per objective. |
| Share Data with 1111 | Checkbox | Default: OFF. Consent dialog on first enable. See Section 15. |

Settings are saved using the WordPress Settings API with nonce verification and capability checks.

### 8.4 Feedback-Driven Regeneration

Generated content is **immutable by human users** — only the 1111 Agent user (Persona 3.4) can modify post content. Human administrators provide feedback through dedicated UI surfaces, and that feedback is passed to the relevant agent(s) to regenerate content. This keeps the agent pipeline as the single source of truth for all generated content.

#### 8.4.1 Feedback Levels

Feedback can be provided at four levels, each triggering regeneration of different scope:

| Level | Where feedback is given | What gets regenerated | Agents re-run |
|-------|------------------------|----------------------|----------------|
| **Course description** | Course taxonomy term edit screen | Entire course — new narrative, new lesson plans, new lessons, new activities | All four agents |
| **Lesson plan** | Lesson group (`lesson_group`) tag edit screen | All lessons in that group + their activities | Lesson Planner → Lesson Writer → Activity Creator |
| **Written lesson** | Post editor — block editor sidebar panel | That lesson only (re-written from existing plan) | Lesson Writer only |
| **Activity** | Post editor — custom meta box below content | That lesson's activity only (re-created from existing plan) | Activity Creator only |

#### 8.4.2 Feedback UI: Course Description (Taxonomy Term Editor)

When the admin edits a `course` taxonomy term, the standard WordPress term edit screen includes:

- **Read-only display** of the current AI-generated `narrative_description` (rendered from meta, not the editable description field)
- **Read-only list** of current lesson titles and summaries
- **Feedback textarea:** "What should change about this course's narrative or structure?"
- **Regenerate Course button:** Submits feedback, re-runs the Course Describer with the original inputs plus feedback, and cascades regeneration through all downstream agents
- All fields use the block editor's `TextareaControl` component for consistency with WordPress admin UI

The original admin inputs (title, description, objectives) are preserved in term meta and always re-sent to the agent. Feedback is additive context, not a replacement for the original inputs.

#### 8.4.3 Feedback UI: Lesson Plan (Tag Editor)

When the admin edits a `lesson_group` tag, the term edit screen includes:

- **Read-only display** of the full lesson plan JSON rendered as readable HTML (mastery criteria, key concepts, activity seed, lesson outlines)
- **List of lessons** in this group with links to each post
- **Feedback textarea:** "What should change about this lesson plan?"
- **Regenerate Plan button:** Submits feedback, re-runs the Lesson Planner with original inputs plus feedback, then cascades through Lesson Writer and Activity Creator for all lessons in the group

The lesson count for this group may differ from the default if the admin has previously requested splitting or merging lessons via feedback.

#### 8.4.4 Feedback UI: Written Lesson (Post Editor — Sidebar Panel)

When viewing a `learn` post in the block editor, a **sidebar panel** (registered via `registerPlugin` / `PluginSidebar` from `@wordpress/edit-post`) provides:

- **Read-only view** of the post content (the block editor canvas itself shows the content but editing is disabled — see Section 8.5)
- **Feedback textarea:** "What should change about this lesson?"
- **Regenerate Lesson button:** Submits feedback, re-runs the Lesson Writer with the existing lesson plan plus feedback, and updates the post content
- **Version indicator:** Shows current generation version number (incremented on each regeneration)

#### 8.4.5 Feedback UI: Activity (Post Editor — Meta Box)

Below the block editor content area, a **custom meta box** displays:

- **Read-only rendered view** of the current activity (prompt, instructions, rubric, hints)
- **Feedback textarea:** "What should change about this activity?"
- **Regenerate Activity button:** Submits feedback, re-runs the Activity Creator with the existing mastery criteria and activity seed plus feedback, and updates the activity meta
- **Version indicator:** Shows current activity generation version number

#### 8.4.6 Regeneration Pipeline

When feedback is submitted at any level:

1. The feedback text is stored in the relevant meta field (term meta or post meta)
2. The appropriate agent(s) are called with the original inputs plus the feedback appended to the user message
3. Output is validated using the same schema validation and retry logic as initial generation
4. On success, the generated content is updated (post content, post meta, or term meta) — authored by the 1111 Agent user
5. WordPress revisions capture the before/after diff
6. The feedback field is cleared after successful regeneration
7. A `content_regenerated` telemetry event is emitted (see Section 15.3) with the feedback level and which agents were re-run

#### 8.4.7 Cascading Regeneration

When feedback triggers regeneration at a higher level, all downstream content is regenerated:

- **Course description feedback** → Course Describer → (for each objective) Lesson Planner → (for each lesson) Lesson Writer → Activity Creator
- **Lesson plan feedback** → Lesson Planner → (for each lesson in group) Lesson Writer → Activity Creator
- **Lesson feedback** → Lesson Writer (single lesson)
- **Activity feedback** → Activity Creator (single activity)

The progress stepper UI (Section 8.2) is reused for cascading regeneration, showing which agents are currently running and which lessons are being updated.

### 8.5 Block Editor Content Locking

For `learn` posts authored by the 1111 Agent user, the block editor content area is **read-only** for human administrators. This is implemented using WordPress's block locking API:

- All blocks in agent-authored posts are locked with `{ "lock": { "move": true, "remove": true } }` — blocks cannot be moved, removed, or edited
- The block editor toolbar is hidden for locked content via the `editor.BlockEdit` filter
- The post title is also locked (non-editable) via the `enter_title_here` filter returning the current title, combined with a read-only attribute on the title input
- A prominent notice at the top of the editor explains: "This lesson was generated by 1111 Learn. Use the feedback panel in the sidebar to request changes."
- The `post_content` is additionally protected server-side: the `wp_insert_post_data` filter rejects content changes from any user other than the 1111 Agent user

**Why block editing, not classic editor?** The plugin targets the latest WordPress version and the block editor is the standard editing experience. Block locking is a native Gutenberg API that provides the exact UX needed: content is visible and structured but not directly editable.

---

## 9. Data Model

### 9.1 Taxonomy Term Meta (Course)

| Meta key | Type | Description |
|----------|------|-------------|
| `_1111_learning_objectives` | `array` | Original learning objectives entered by admin |
| `_1111_course_description` | `string` | Original course description |
| `_1111_narrative_description` | `string` | AI-generated narrative arc from Course Describer |
| `_1111_lesson_titles` | `array` | Pre-set `[{lesson_title, lesson_summary}]` from Course Describer |
| `_1111_generation_date` | `string` | ISO 8601 timestamp of generation |
| `_1111_generation_status` | `string` | `generating`, `complete`, `failed` |
| `_1111_course_feedback` | `string` | Admin's feedback on the course description (cleared after regeneration) |
| `_1111_course_version` | `int` | Incremented on each course-level regeneration |

### 9.2 Post Meta (Lesson)

| Meta key | Type | Description |
|----------|------|-------------|
| `_1111_objective_index` | `int` | Zero-based index into the course's learning objectives |
| `_1111_lesson_order` | `int` | Sort order within the course (0-based) |
| `_1111_learning_objective` | `string` | Measurable objective from lesson plan |
| `_1111_key_concepts` | `array` | Key concepts from lesson plan |
| `_1111_mastery_criteria` | `array` | Mastery criteria from lesson plan |
| `_1111_key_takeaways` | `array` | Key takeaways from lesson writer |
| `_1111_activity` | `array` | Activity spec: `{activity_type, prompt, instructions, scoring_rubric, hints}` |
| `_1111_generated` | `bool` | `true` if AI-generated |
| `_1111_lesson_feedback` | `string` | Admin's feedback on the written lesson (cleared after regeneration) |
| `_1111_lesson_version` | `int` | Incremented on each lesson-level regeneration |
| `_1111_activity_feedback` | `string` | Admin's feedback on the activity (cleared after regeneration) |
| `_1111_activity_version` | `int` | Incremented on each activity-level regeneration |

### 9.3 Options (wp_options)

| Option key | Description |
|------------|-------------|
| `1111_learn_api_key` | Encrypted Anthropic API key |
| `1111_learn_fast_model` | Model ID for Describer, Planner, Activity Creator |
| `1111_learn_default_model` | Model ID for Lesson Writer |
| `1111_learn_plan_max_tokens` | Max tokens for planning agents |
| `1111_learn_content_max_tokens` | Max tokens for content generation |
| `1111_learn_lessons_per_objective` | Target lesson count per objective (default: 1, range: 1–4) |
| `1111_learn_agent_user_id` | User ID of the 1111 Agent user (set on activation) |
| `1111_learn_telemetry_enabled` | Boolean — telemetry opt-in status |
| `1111_learn_telemetry_consent_at` | ISO 8601 timestamp of consent |
| `1111_learn_service_credential` | Encrypted anonymous credential from learn-service |
| `1111_learn_anonymous_id` | Random installation identifier |

---

## 10. API Client

### 10.1 Anthropic API Integration

The plugin communicates with the Anthropic Messages API via `wp_remote_post()`.

```php
POST https://api.anthropic.com/v1/messages
Headers:
  x-api-key: {stored_api_key}
  anthropic-version: 2023-06-01
  content-type: application/json

Body:
{
  "model": "{configured_model}",
  "max_tokens": {configured_max_tokens},
  "system": "{contents_of_prompt_md_file}",
  "messages": [
    {
      "role": "user",
      "content": "{formatted_input}"
    }
  ]
}
```

**Error handling:**
- 401: Invalid API key — prompt admin to check settings
- 429: Rate limited — show "Rate limited, please wait and retry"
- 5xx: Server error — allow retry
- Timeout: 120-second timeout (Lesson Writer can produce 8000+ tokens) — allow retry
- Parse error: Non-JSON response — retry once automatically

### 10.2 Security

- API key stored encrypted in `wp_options` (`sodium_crypto_secretbox` if available, `AUTH_KEY`-based fallback)
- API key never exposed in client-side JavaScript — all calls server-side via AJAX
- All AJAX endpoints verify nonces and `manage_options` capability
- Prompt files loaded from plugin directory, never from user input

---

## 11. Generation Pipeline (Server-Side)

### 11.1 Pipeline Architecture

The pipeline follows 1111 School's generation service design: each step commits progress to the database, and errors at any step are recoverable without re-running completed steps.

**AJAX Endpoint:** `1111_generate_course`

```
Request → Validate → Create Course Term → Phase 0 → Per-Objective Loop → Complete
```

### 11.2 Phase 0: Course Description

1. Call Course Describer agent with title, description, objectives
2. Validate output (narrative_description + lessons array)
3. Store `_1111_narrative_description` and `_1111_lesson_titles` on the course term
4. Send progress update: lesson titles now visible in the stepper UI

### 11.3 Per-Objective Loop (Phase 1+)

For each objective (index 0 to N-1):

1. **Check for existing content** — if lesson group already has content (retry scenario), skip
2. **Lesson Planner** — call with objective, narrative description, all objectives (for scope control), preset title from Phase 0, target lesson count, and any admin feedback
3. Validate plan output. Retry once on failure.
4. **Create `lesson_group` tag** — store the full lesson plan on the tag's term meta
5. **For each lesson in the plan** (1 to lesson_count):
   a. **Lesson Writer** — call with the specific lesson outline, mastery criteria, and course description
   b. Validate content output. Retry once on failure.
   c. **Activity Creator** — call with activity seed, objective, and mastery criteria from the plan
   d. Validate activity output. Retry once on failure.
   e. **Create WordPress post** — `learn` CPT, authored by 1111 Agent user, assigned to `course` taxonomy term and `lesson_group` tag, with all meta
   f. **Commit and report progress** — save post, update stepper

### 11.4 Post Creation

| Post field | Value |
|------------|-------|
| `post_type` | `learn` |
| `post_author` | 1111 Agent user ID (Persona 3.4) |
| `post_title` | `lesson_title` from Lesson Writer |
| `post_content` | `lesson_body` converted from Markdown to WordPress block markup |
| `post_excerpt` | First sentence of `lesson_body`, or `lesson_summary` from Course Describer |
| `post_status` | `draft` (admin reviews before publishing) |
| `menu_order` | Objective index (for ordering) |
| `tax_input` | Assigned to the `course` taxonomy term and `lesson_group` tag |

Posts are created as **drafts** authored by the 1111 Agent user (Persona 3.4). The admin reviews generated content and provides feedback to trigger regeneration — they do not directly edit post content.

### 11.5 Markdown to Block Conversion

The Lesson Writer outputs Markdown (matching School's format). Before saving to WordPress, the orchestrator converts Markdown to WordPress block markup:

- `## Heading` → `<!-- wp:heading {"level":2} --><h2>Heading</h2><!-- /wp:heading -->`
- Paragraphs → `<!-- wp:paragraph --><p>...</p><!-- /wp:paragraph -->`
- Lists → `<!-- wp:list --><ul><li>...</li></ul><!-- /wp:list -->`
- Code blocks → `<!-- wp:code --><pre><code>...</code></pre><!-- /wp:code -->`

This ensures generated content is fully compatible with the Gutenberg block editor.

### 11.6 Incremental Recovery

Following School's incremental recovery pattern:

- Each lesson is committed to the database as it's created
- If the pipeline fails mid-course (e.g., objective 3 of 5 fails):
  - Objectives 1–2 are already saved as draft posts
  - Course term meta shows `generation_status: failed`
  - On retry, the pipeline checks which objectives already have a complete post and skips them
  - Only missing objectives are regenerated

### 11.7 Progress Reporting

Progress is reported via **polling** (WordPress hosting compatible):

- Generation updates a transient (`_1111_generation_progress_{term_id}`) after each step
- Client polls `wp_ajax_1111_generation_status` every 2 seconds
- Progress payload:

```json
{
  "status": "generating",
  "phase": "lesson",
  "current_objective": 1,
  "total_objectives": 3,
  "current_step": "writing",
  "steps_completed": [
    {"objective": 0, "steps": ["planned", "written", "activity_created"]},
    {"objective": 1, "steps": ["planned"]}
  ],
  "lesson_titles": ["Seeing the Barriers", "Auditing with Browser Tools", "Writing Fix Recommendations"],
  "error": null
}
```

---

## 12. Prompt Files

### 12.1 Why Markdown Files?

- **Machine-readable and machine-editable:** AI agents can parse, modify, and propose improvements to Markdown far more reliably than embedded PHP strings
- **Version controlled:** Changes are tracked in git, so every agent-proposed PR has a clear diff
- **Testable independently:** Agents (or developers) can copy a prompt into the Anthropic Console to validate changes outside WordPress
- **Hot-reloadable:** Changes take effect on the next generation — no cache to clear, so merged PRs improve output immediately

### 12.2 Prompt Files

| File | Agent | Model | Purpose |
|------|-------|-------|---------|
| `prompts/course-describer.md` | Course Describer | fast | Narrative arc + lesson titles/summaries |
| `prompts/lesson-planner.md` | Lesson Planner | fast | Backward design lesson plan |
| `prompts/lesson-writer.md` | Lesson Writer | default | Full lesson content from plan |
| `prompts/activity-creator.md` | Activity Creator | fast | Practice activity from activity seed |

### 12.3 Prompt File Structure

Each prompt file contains the full system prompt. The structure mirrors 1111 School's inline prompts but in an editable file:

```markdown
You are an expert [role description].

## Requirements
- requirement 1
- requirement 2

## Rules
- IMPORTANT — [constraint name]: [constraint description]

## Output Format
Respond with ONLY valid JSON, no markdown fencing:
{
  "field": "description"
}
```

---

## 13. Accessibility Requirements

### 13.1 Admin UI

All admin UI must meet WCAG 2.1 AA:

1. **Form inputs:** Every input has a visible `<label>` with `for` attribute matching the input `id`.
2. **Error messages:** Inline errors associated with inputs via `aria-describedby`. Error summary uses `role="alert"`.
3. **Progress updates:** Stepper progress conveyed via `aria-live="polite"` region. Each step completion is announced.
4. **Focus management:** After form submission, focus moves to the progress stepper. After error, focus moves to the first invalid field. After completion, focus moves to the success message.
5. **Keyboard navigation:** All interactive elements reachable and operable via keyboard. Objective repeater supports keyboard add/remove.
6. **Color independence:** Stepper uses icons + text (checkmark + "Complete", spinner + "Writing..."), never color alone.
7. **Screen reader announcements:** Dynamic content changes (step completions, errors, success) announced.
8. **Sufficient contrast:** All text meets 4.5:1 contrast ratio.
9. **Responsive layout:** Dashboard usable at min-width 782px (WordPress admin breakpoint).

### 13.2 Generated Lesson Content

Generated content consumed by learners (Persona 3.2) must also meet WCAG 2.1 AA. Since the Lesson Writer outputs Markdown converted to WordPress blocks, the plugin enforces accessible output at the generation and conversion layers:

1. **Heading hierarchy:** Lesson body starts at `##` (h2) — the post title occupies h1. No skipped heading levels. Enforced during Markdown-to-block conversion.
2. **Image alt text:** If the Lesson Writer prompt ever produces image references, alt text is required. (Current agents are text-only, but this guard prevents future regressions.)
3. **Link text:** Generated links must have descriptive text — never "click here" or bare URLs. Enforced as a validation rule on Lesson Writer output.
4. **List structure:** Markdown lists are converted to proper `<ul>`/`<ol>` block markup, not paragraph text with dashes.
5. **Code blocks:** Fenced code blocks are converted to `<!-- wp:code -->` blocks with `<pre><code>` — ensuring proper semantics and screen reader announcement.
6. **Reading level:** Agent prompts instruct the Lesson Writer to use clear, plain language. Content should be understandable without specialized vocabulary unless the course topic demands it.
7. **Logical reading order:** Content follows the lesson outline sequentially — no reliance on visual layout to convey meaning.

---

## 14. Security Requirements

1. **Capability checks:** All admin pages and AJAX handlers require `manage_options` capability. Content modification is restricted to the 1111 Agent user via the `wp_insert_post_data` filter.
2. **Nonce verification:** All form submissions and AJAX requests nonce-protected, including feedback submission.
3. **Input sanitization:** `sanitize_text_field()`, `sanitize_textarea_field()`, `wp_kses_post()` as appropriate. Feedback text is sanitized with `sanitize_textarea_field()` before being passed to agents.
4. **Output escaping:** `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` as appropriate.
5. **API key storage:** Encrypted at rest, never in client-side code or debug logs.
6. **No direct file access:** All PHP files check `defined('ABSPATH')`.
7. **Content sanitization:** Generated content run through `wp_kses_post()` before saving.
8. **Agent user isolation:** The `1111_learn_agent` role has only the minimum capabilities needed to create and edit `learn` posts. It cannot access other post types, admin pages, or settings.

---

## 15. Telemetry and Prompt Improvement

The plugin collects anonymous telemetry to continuously improve agent prompt quality. This follows the same pattern proven in [1111 Learn](https://github.com/1111philo/learn-extension), adapted from browser-side event buffering to server-side WordPress logging, and transmitted to the shared [learn-service](https://github.com/1111philo/learn-service) backend.

### 15.1 Purpose

Telemetry is the plugin's **primary self-improvement mechanism**. The goal is a closed loop: real usage data flows into learn-service, automated analysis identifies what's working and what isn't, and AI agents create PRs with prompt improvements — no human has to initiate the cycle. Over time, this means every course generation across every installation makes the prompts better for everyone.

Concretely, telemetry data is used to:
- Identify which agents produce weak output and why (validation failure patterns, retry rates)
- Detect which generated fields admins consistently edit (signaling the agent underperformed)
- Spot course topics or objective structures that cause disproportionate failures
- Automatically propose prompt changes as PRs that agents can build, test, and submit

### 15.2 Consent and Opt-In

- **Default:** Telemetry is OFF. No data is collected or transmitted until the admin explicitly enables it.
- **Toggle:** "Share anonymous usage data with 1111" checkbox on the Settings page.
- **Consent dialog:** On first enable, a modal explains exactly what is collected, what is never collected, how data is stored, and how to withdraw consent. The admin must confirm before telemetry activates.
- **Withdrawal:** Disabling the toggle immediately stops all data collection and transmission. No previously sent data is retroactively deleted (auto-expires per retention policy).

### 15.3 Events Collected

All events are recorded server-side during course generation and batched for transmission.

| Event Type | When | Data Collected |
|------------|------|----------------|
| `course_started` | Admin clicks Generate Course | objectiveCount, pluginVersion, wpVersion, phpVersion |
| `agent_request` | Before each agent call | agentName, model, promptFileHash (not contents), inputTokenEstimate |
| `agent_response` | After each agent call | agentName, model, outputTokens, latencyMs, responseJson |
| `validation_failure` | Agent output fails schema validation | agentName, validationErrors, failedResponseJson, retried (bool) |
| `retry_outcome` | After automatic retry | agentName, succeeded (bool), originalErrors, retryErrors |
| `course_completed` | All lessons generated successfully | objectiveCount, totalLatencyMs, totalTokens, lessonCount |
| `course_failed` | Pipeline fails fatally | failedAgent, failedObjectiveIndex, errorType, errorMessage |
| `feedback_submitted` | Admin submits feedback for regeneration | feedbackLevel (course/plan/lesson/activity), agentsToRerun (list) |
| `content_regenerated` | Regeneration completes from feedback | feedbackLevel, agentsRerun, succeeded (bool), lessonCount |

### 15.4 What Is Never Collected

Following the extension's `stripBinaries` pattern, these are explicitly excluded:

- **API keys** — never logged, never transmitted
- **Course content** — lesson bodies, activity text, and learner-facing content are never sent. Only agent response JSON structure is captured for schema analysis.
- **Personal information** — no admin names, emails, site URLs, or IP addresses
- **WordPress credentials** — no auth tokens, cookies, or session data
- **Prompt file contents** — only a hash of the prompt file is sent (to detect custom edits), never the full text

### 15.5 Transmission to learn-service

Telemetry is transmitted to the shared 1111 learn-service backend, the same service used by the Chrome extension.

- **Endpoint:** `POST {learn-service-url}/v1/events`
- **Authentication:** Anonymous credential obtained via `POST /v1/auth/register` on first enable (returns an opaque API key tied to a random anonymous ID)
- **Buffering:** Events are accumulated in a WordPress transient during generation and flushed in a single batch on pipeline completion (or failure). No per-event HTTP calls.
- **Fire-and-forget:** Transmission failures are silently discarded. Telemetry never blocks or delays course generation.
- **Credential storage:** The anonymous service credential is stored encrypted in `wp_options` alongside the Anthropic API key.

### 15.6 Anonymous Identification

- Each WordPress installation receives a random anonymous ID on first registration: `wp_{random_hex_16}`
- No correlation to site URL, admin identity, or Anthropic API key
- The anonymous ID allows grouping events from the same installation to identify per-site patterns (e.g., "this site consistently gets validation failures from the Lesson Planner")

### 15.7 Data Retention

- All telemetry data is automatically deleted after **90 days** via DynamoDB TTL (matching the extension's retention policy)
- No long-term archives or backups of telemetry data
- Aggregated, anonymized insights (e.g., "Lesson Planner validation failure rate dropped from 12% to 4%") may persist indefinitely

### 15.8 Prompt Improvement Pipeline

This is the core value of telemetry — an **automated, agent-driven feedback loop** from real usage to better prompts. The loop runs continuously without human initiation:

```
Usage data (all installations)
    │
    ▼
┌─────────────────────────────────────────────────┐
│  learn-service aggregates telemetry              │
│  Validation failures, retry rates, edit signals  │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Automated analysis (scheduled)                  │
│  Identifies patterns, ranks improvement targets  │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  AI agent creates PR against prompts/*.md        │
│  Cites telemetry evidence, proposes specific     │
│  prompt edits with before/after reasoning        │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Developer reviews PR → merge                    │
│  Reviews diff, checks telemetry evidence cited   │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Improved prompts take effect immediately        │
│  Next generation uses updated prompts/*.md       │
│  Telemetry measures whether the change helped    │
└───────────────────────────────────────────────────┘
```

**Step 1 — Collect:** Telemetry events flow into learn-service from both the WordPress plugin and the Chrome extension. All installations contribute to the same improvement pool.

**Step 2 — Analyze:** Scheduled analysis runs against aggregated telemetry to identify actionable patterns:
   - Which agents have the highest validation failure rates?
   - Which validation rules fire most often (e.g., `lesson_body` too short, `mastery_criteria` count out of range)?
   - Do certain course topics (inferred from objective structure, not content) cause disproportionate failures?
   - When admins edit generated content before publishing, which fields do they change most?
   - Are retry attempts succeeding or failing with the same errors?
   - After a previous prompt change shipped, did the target metric actually improve?

**Step 3 — Propose:** An AI agent reads the current prompt files and the telemetry analysis, then creates a PR against the plugin repository targeting `prompts/*.md` with specific improvements:
   - Tightened constraints where agents consistently under-deliver
   - Relaxed constraints where validation is too aggressive
   - Added examples where agents misinterpret the output format
   - Reworded instructions where a specific failure pattern recurs
   - Each PR cites the telemetry evidence (e.g., "Lesson Planner `mastery_criteria` count validation fails 18% of the time — adding an explicit count reminder to the prompt")

**Step 4 — Review:** The developer (Persona 3.3) reviews the PR in GitHub — checking the diff, the telemetry evidence cited in the PR description, and the before/after reasoning. This is the only human step — everything before it is automated.

**Step 5 — Ship and measure:** Merged prompt changes take effect immediately for all installations — no plugin update required, just a file change. Subsequent telemetry measures whether the change actually improved the target metric, closing the loop. If a change didn't help (or made things worse), the next analysis cycle will flag it for further iteration.

> **Design principle:** The plugin is built to be improved by agents. Telemetry data, prompt files as editable Markdown, and structured validation errors are all designed so that an AI agent has everything it needs to diagnose a problem and propose a fix. The developer's role is reviewing and merging PRs — not interpreting logs, manually editing prompts, or initiating the improvement cycle.

### 15.9 Feedback Tracking

Since admins cannot directly edit generated content, the telemetry signal shifts from "what did the admin change?" to "what feedback did the admin give, and at what level?" This is a stronger signal for prompt improvement because it captures *intent*, not just *diff*:

- If 80% of feedback targets lesson plans, the Lesson Planner prompt needs improvement.
- If most feedback at the lesson level requests "more examples," the Lesson Writer prompt should emphasize worked examples.
- If activity feedback frequently says "too vague," the Activity Creator prompt needs tighter rubric generation instructions.
- If course-level feedback is rare, the Course Describer is performing well.
- If admins frequently regenerate the same lesson multiple times, the agent is not incorporating feedback effectively.

Tracked via `feedback_submitted` and `content_regenerated` telemetry events. Feedback text itself is **never** collected — only the level (course/plan/lesson/activity), the number of regeneration cycles, and which agents were re-run.

### 15.10 Options (wp_options)

| Option key | Description |
|------------|-------------|
| `1111_learn_telemetry_enabled` | Boolean — telemetry opt-in status |
| `1111_learn_telemetry_consent_at` | ISO 8601 timestamp of consent |
| `1111_learn_service_credential` | Encrypted anonymous credential from learn-service |
| `1111_learn_anonymous_id` | Random installation identifier |

### 15.11 Privacy Documentation

Any change that adds, removes, or modifies collected data must update:

1. The consent dialog text on the Settings page
2. The plugin's privacy policy section (README)
3. The data-stripping logic that excludes sensitive fields
4. The learn-service validation and documentation

This mirrors [Rule #10 from the extension's CLAUDE.md](https://github.com/1111philo/learn-extension/blob/main/CLAUDE.md) — privacy changes are never a single-file edit.

---

## 16. Non-Goals (Explicitly Out of Scope)

> **Note:** Telemetry is no longer a non-goal — see Section 15.

These are intentionally excluded from 1111 Learn Creator:

1. **Assessments / activity grading** — No quizzes, scoring, or AI-powered review of learner submissions. Activities are generated as reference content for the admin; grading belongs to 1111 Learn Administrator.
2. **Learner profiles** — No tracking of individual learner progress, preferences, or personalization.
3. **Progress tracking** — No completion tracking or status indicators for learners.
4. **Frontend interactivity** — No JS-driven learner interactions. The plugin produces standard WordPress posts.
5. **Enrollment / access restrictions** — No learner enrollment or content gating. (The plugin does create one custom role — `1111_learn_agent` — for the system agent user, but this is not a user-facing role.)
6. **Certificates or badges** — No completion rewards.
7. **LMS integration** — No direct integration with LearnDash, LifterLMS, etc. (but generated posts are compatible).
8. **Multi-site support** — Single-site only for v1.
9. **Internationalization** — English only for v1 (all strings use `__()` / `_e()` for future translation readiness).
10. **On-demand generation** — Unlike School, all lessons are generated upfront (no need for on-demand since there's no learner progression to gate on).

---

## 17. Future: 1111 Learn Administrator (Companion Plugin)

A planned companion plugin will add:

- Learner-facing course navigation and progress tracking
- AI-powered activity grading (using the `scoring_rubric` and `mastery_criteria` already generated by this plugin)
- Learner profiles with adaptive content
- Enrollment and access control
- Analytics dashboard
- Integration with the `learn` CPT, `course` taxonomy, and lesson meta created by this plugin

The Learn Creator plugin is designed so the Administrator plugin can build on top of its data structures without modifications. Specifically, `_1111_mastery_criteria`, `_1111_activity`, and `_1111_key_takeaways` are stored as structured meta precisely so the Administrator plugin can use them for grading and progression.

---

## 18. Development Guidelines

1. **No build step.** Vanilla PHP, JS, CSS. No Webpack, Sass, or npm. Exception: the block editor sidebar panel (`editor-sidebar.js`) uses `wp.plugins.registerPlugin` and `wp.editPost.PluginSidebar` from the bundled `@wordpress/edit-post` and `@wordpress/plugins` packages — no npm install required, these ship with WordPress.
2. **WordPress coding standards.** Follow WordPress PHP and JavaScript coding standards.
3. **Minimum requirements:** WordPress 6.7+, PHP 8.0+. The plugin targets the **latest WordPress version** and relies on block editor APIs (block locking, PluginSidebar, SlotFill) that are stable in 6.7+. Do not add fallbacks for the classic editor — the block editor is required.
4. **Block editor first.** All post-editor UI (feedback panels, content locking, activity meta box) is built for the block editor using the `@wordpress/` JS packages bundled with WordPress. Taxonomy term editors use standard WordPress admin UI enhanced with custom meta boxes.
5. **Prefix everything.** `_1111_learn_` for meta/options, `Learn_Creator_` for classes.
6. **No Composer.** API client uses `wp_remote_post()`.
7. **Hooks and filters** for extensibility:
   - `1111_learn_before_describe` — filter course data before Course Describer
   - `1111_learn_course_described` — action after narrative + titles set
   - `1111_learn_before_plan` — filter objective context before Lesson Planner
   - `1111_learn_lesson_planned` — action after plan generated
   - `1111_learn_before_write` — filter plan before Lesson Writer
   - `1111_learn_lesson_written` — action after content generated
   - `1111_learn_before_activity` — filter activity seed before Activity Creator
   - `1111_learn_activity_created` — action after activity generated
   - `1111_learn_lesson_created` — action after WordPress post created
   - `1111_learn_course_complete` — action after all lessons created
   - `1111_learn_validate_{agent}` — filters for custom validation per agent
   - `1111_learn_feedback_submitted` — action when admin submits feedback at any level
   - `1111_learn_before_regenerate` — filter feedback + inputs before regeneration pipeline
   - `1111_learn_content_regenerated` — action after regeneration completes
8. **Prompts are data, not code.** `prompts/*.md` loaded at runtime. Editable without touching PHP.
9. **Agent user owns all content.** All generated posts are authored by the 1111 Agent user. Human administrators interact with content through feedback, not direct editing.

---

## 19. Implementation Phases

### Phase 1: Foundation
- [ ] Plugin bootstrap file with proper headers and ABSPATH checks
- [ ] Register `learn` custom post type with REST support
- [ ] Register `course` taxonomy
- [ ] Register `lesson_group` tag taxonomy
- [ ] Create 1111 Agent user and `1111_learn_agent` role on activation
- [ ] Clean up agent user and role on uninstall
- [ ] Settings page with encrypted API key storage, model selectors, and lessons-per-objective
- [ ] `CLAUDE.md` for the new repo
- [ ] `README.md` with install instructions

### Phase 2: Agents and Orchestrator
- [ ] Anthropic API HTTP client class (`wp_remote_post`, error handling, retries)
- [ ] Prompt file loader (reads `prompts/*.md`)
- [ ] JSON parser (handles markdown fencing, extracts JSON from response)
- [ ] Validation functions for each agent's output schema
- [ ] Orchestrator class wiring the four-agent pipeline
- [ ] Write all four prompt files:
  - [ ] `prompts/course-describer.md`
  - [ ] `prompts/lesson-planner.md`
  - [ ] `prompts/lesson-writer.md`
  - [ ] `prompts/activity-creator.md`

### Phase 3: Admin Dashboard
- [ ] Dashboard page registration and menu setup
- [ ] Course creation form (title, description, objectives repeater)
- [ ] Client-side validation with accessible error handling
- [ ] AJAX handler for course generation
- [ ] Polling endpoint for generation progress
- [ ] Vertical stepper progress UI
- [ ] Success / error / retry states

### Phase 4: Content Generation Pipeline
- [ ] Wire dashboard form → orchestrator → agents
- [ ] Phase 0: Course Describer → create taxonomy term with narrative + titles
- [ ] Per-objective loop: Planner → Writer → Activity Creator → create draft posts
- [ ] Create `lesson_group` tag per objective, assign all lessons from same plan
- [ ] Set post author to 1111 Agent user on all generated posts
- [ ] Markdown-to-block conversion for `post_content`
- [ ] Store all structured meta (mastery criteria, activity, takeaways)
- [ ] Store lesson plan on `lesson_group` term meta (not post meta)
- [ ] Support multi-lesson plans (lessons_per_objective > 1)
- [ ] Incremental recovery: skip already-generated objectives on retry
- [ ] Progress transients and polling responses

### Phase 5: Block Editor Integration
- [ ] Content locking: lock all blocks in agent-authored posts (move + remove)
- [ ] Server-side guard: `wp_insert_post_data` filter rejects content changes from non-agent users
- [ ] Block editor notice: "This lesson was generated by 1111 Learn. Use the feedback panel..."
- [ ] Sidebar panel via `registerPlugin` / `PluginSidebar`: lesson feedback textarea + regenerate button
- [ ] Activity meta box below content: activity display + feedback textarea + regenerate button
- [ ] Version indicators for lesson and activity regeneration counts

### Phase 6: Feedback and Regeneration
- [ ] Course taxonomy term edit screen: narrative display + feedback textarea + regenerate button
- [ ] Lesson group tag edit screen: plan display + feedback textarea + regenerate button
- [ ] AJAX handlers for feedback submission at all four levels
- [ ] Regeneration pipeline: re-run appropriate agents with original inputs + feedback
- [ ] Cascading regeneration: course feedback re-runs all agents, plan feedback re-runs planner + downstream
- [ ] Reuse progress stepper UI for regeneration progress
- [ ] WordPress revisions capture before/after diffs on regeneration
- [ ] Clear feedback field after successful regeneration

### Phase 7: Telemetry
- [ ] Telemetry class: event collection, buffering, batch flush
- [ ] learn-service anonymous registration (`/v1/auth/register`)
- [ ] Event transmission (`/v1/events`) with fire-and-forget error handling
- [ ] Opt-in toggle on Settings page with consent dialog
- [ ] Data stripping: ensure API keys, content, and PII are never included
- [ ] Feedback tracking: `feedback_submitted` and `content_regenerated` events (level + agents, never feedback text)
- [ ] Wire telemetry events into orchestrator pipeline (agent_request, agent_response, validation_failure, etc.)

### Phase 8: Polish and Quality
- [ ] Accessibility audit: focus management, ARIA, keyboard, contrast
- [ ] Security audit: nonces, capabilities, sanitization, escaping
- [ ] Uninstall cleanup (`uninstall.php` — remove options, term meta, post meta)
- [ ] Test with real API key across diverse course topics
- [ ] Evaluate: Do lessons teach? Are activities aligned to mastery criteria? Does the narrative thread hold?
- [ ] Iterate prompts until output quality is consistently good

---

## 20. Success Criteria

1. An administrator can generate a complete course (1–4 lessons per objective, 1–8 objectives) from title + description + objectives in under 3 minutes.
2. Generated lessons follow a visible narrative arc — they read as chapters in the same course, not disconnected topics.
3. Each lesson's content clearly prepares the learner for the associated activity. Backward design is evident.
4. Activities have specific, checkable rubric criteria — not vague "practice what you learned."
5. All generated content is saved as standard WordPress draft posts authored by the 1111 Agent user — viewable in any theme, reviewable in the block editor, improvable through feedback-driven regeneration.
6. The plugin installs with zero configuration beyond entering an API key.
7. All admin UI passes WCAG 2.1 AA.
8. Agent prompts live in Markdown files; merged PRs take effect immediately on the next generation without a plugin update.
9. A failed generation can be retried without losing already-generated lessons.
10. Administrators cannot directly edit generated post content — they provide feedback that triggers regeneration, and the output visibly improves with each feedback cycle.
11. The block editor shows generated content as read-only with a clear feedback panel in the sidebar and activity feedback in a meta box below content.
12. Telemetry flows from opted-in installations to learn-service, and an AI agent can use that data to autonomously create PRs improving `prompts/*.md` — completing a full collect → analyze → propose → review → ship cycle without human initiation.
11. Prompt quality measurably improves over time: validation failure rates decrease, retry rates decrease, and the percentage of generated fields that admins edit before publishing decreases.
