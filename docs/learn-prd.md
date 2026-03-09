# Learn — Product Requirements Document

## WordPress Plugin for AI-Powered Course Creation, Portfolio Building, and Assessment

**Version:** 0.5.0-draft
**Date:** 2026-03-09
**Status:** Draft — awaiting review
**Author:** 11:11 Philosopher's Group

---

## 1. Overview

**Learn** is a WordPress Multisite plugin by 11:11 Philosopher's Group for AI-powered course creation. A super admin creates courses; learners sign up, get their own WordPress site, and learn by building real WordPress content.

### 1.1 How It Works

**The super admin creates a course.** From the Learn admin panel on the network's main site, the super admin enters a course title, description, and learning objectives. A seven-agent AI pipeline (powered by the Anthropic Claude API) generates everything: a cohesive course narrative, structured lesson plans, full lesson content, practice activities with gamification mechanics, and a summative assessment. All content is saved as WordPress posts (`learn` custom post type) organized under Course and Lesson Group taxonomies, authored by a dedicated system agent user.

**The super admin reviews and refines.** The admin panel shows all generated material in one place — the course name and description, every lesson plan, every written lesson, every activity, and the final assessment. The super admin can add feedback at any level (course description, lesson plan, individual lesson, activity, or assessment) to trigger regeneration through the agent pipeline. Content is never directly edited — feedback drives the AI to produce better output.

**The super admin publishes.** Once satisfied, the super admin publishes the course. **Published content is permanently immutable** — it cannot be updated or regenerated. To make changes, the super admin must create a new course. This gives learners a stable version they can rely on.

**Learners sign up and get their own site.** Learners register through a form the plugin provides on the main site. On registration, they receive their own WordPress subsite — their learning environment, workspace, and portfolio.

**Learners select courses.** From a learner panel on their subsite, learners browse a catalog of published courses and select the ones they want to take. When a learner selects a course, the plugin copies that course's content (lessons, activities, assessment) to their subsite. Each learner's copy is independent.

**Learners can personalize their content.** Learners can add feedback to any content on their own site — the same feedback mechanism the super admin uses during review. Regeneration runs against **their copy only**, personalizing the learning experience (different examples, deeper explanations, alternative approaches) without affecting the source material or other learners.

**Learners complete activities by building WordPress content.** Every activity directs the learner to create or modify real WordPress content on their subsite — pages, posts, even entire sites. Each activity contributes to a single portfolio work product that grows across the course. The learner's subsite IS their portfolio, with a shareable URL.

**An AI agent assesses learner work.** When a learner submits their WordPress content, the Activity Assessment Agent evaluates it against the activity's rubric and mastery criteria, returning a score, strengths, improvements, and an advance/revise recommendation.

**The super admin tracks progress.** From the admin panel, the super admin can see all learners, their enrolled courses, completion status, and assessment scores.

### 1.2 Lineage

This plugin adapts proven agent patterns from two existing 1111 projects:

- **[1111 Learn](https://github.com/1111philo/learn-extension)** (Chrome extension) — Four-agent architecture: Course Creation → Activity Creation → Activity Assessment → Learner Profile. Prompts stored as Markdown files. Output validated deterministically before reaching the user. Retry-once on validation failure.
- **[1111 School](https://github.com/1111philo/learn)** (full-stack web app) — Seven PydanticAI agents with backward design methodology: Course Describer → Lesson Planner → Lesson Writer → Activity Creator → Activity Reviewer → Assessment Creator → Assessment Reviewer. Narrative threading across lessons. Scope control to prevent objective bleed. On-demand lesson generation.

The WordPress plugin takes the best of both: the narrative threading, backward design, and assessment pipeline from School; the Markdown-file prompt editability, portfolio work-product model, activity type progression, and assessment scoring from Learn; expanded with gamification mechanics, WordPress-native portfolio creation on a Multisite network, learner self-registration with personal content copies, and an Activity Assessment Agent that evaluates learner-submitted WordPress content against generated rubrics.

---

## 2. Goals

**Course creation:**
1. Let a super admin create a complete course from three inputs (title, description, objectives), review all generated material, and publish it.
2. Generate lessons, activities, and assessments using a seven-agent pipeline with backward design (mastery → evidence → path).
3. Thread a narrative arc across lessons so the course reads as a coherent journey, not disconnected topics.
4. Enforce **publish immutability** — published content cannot be updated; the super admin must create a new course.

**Learner experience:**
5. Provide **learner self-registration** — learners sign up, get their own WordPress subsite, and select courses from a catalog.
6. Make every activity a **portfolio artifact** — learners build work products within WordPress on their own subsite, assessed by the Activity Assessment Agent.
7. Let learners **personalize content** — feedback on their copy triggers regeneration for them only.
8. Use **gamification mechanics** (progressive activity types, mastery scoring, milestones) to sustain engagement.

**Technical:**
9. Produce standard WordPress posts (`learn` CPT) under a `course` taxonomy — compatible with any theme, page builder, or LMS plugin.
10. Keep the plugin self-contained: no build step, no JavaScript framework, no external dependencies beyond the Anthropic API.
11. Store prompts as editable Markdown files so AI agents can iterate on output quality through telemetry-driven PRs.
12. Meet WCAG 2.1 AA accessibility standards in all UI and generated content.

---

## 3. User Personas

### 3.1 Course Creator (Super Admin)
- Has **super admin** access on the WordPress Multisite network
- Knows the subject matter and can write learning objectives
- May not be technical — needs a simple, guided interface
- Creates courses in the **Learn admin panel** on the main site
- Reviews all generated material: lesson plans, lessons, activities, assessments, course description
- Provides **feedback** at any level to trigger regeneration — does not directly edit generated post content
- **Publishes** courses when satisfied — published content becomes permanently immutable
- Must create a new course to make changes after publishing
- Can track learner progress from within the admin panel

### 3.2 Learner (End User)
- **Signs up through a plugin-generated registration form** — no super admin enrollment step required
- Receives their own WordPress subsite with a **copy** of published course content
- Consumes lesson content on their own subsite — the ultimate audience for everything this plugin generates
- May range from complete beginners to experienced practitioners depending on the course
- Expects a clear narrative arc: each lesson builds on the last and prepares for the next
- Needs activities that are challenging but achievable given the lesson content
- May have accessibility needs (screen readers, keyboard navigation, low vision) — generated content must be consumable by all
- **Creates portfolio content within WordPress** — builds work product by creating pages, posts, or other WordPress content as directed by activities on their own subsite
- **Provides feedback on their own copy** of content — triggering regeneration that personalizes their version without affecting other learners or the source material
- Submits their WordPress content for assessment — the Activity Assessment Agent reads and evaluates what they've built
- **Cannot create new courses** — selects from published courses via a learner panel on their subsite
- Tracks course progress from within their learner panel

> The learner is an active participant in WordPress, not just a reader. They create real content on their own subsite — pages, posts, custom post types — and that content is their portfolio. They can also personalize their learning by providing feedback on their copy of the course material. Every design decision exists to serve this persona.

### 3.3 Developer (PR Reviewer)
- Reviews agent-generated PRs that improve prompts based on telemetry data
- Uses Claude Code for all development work on the plugin
- Does not manually edit prompt files — AI agents propose changes, the developer reviews and merges

### 3.4 1111 Agent User (System User)

The plugin creates a dedicated WordPress user on activation — the **1111 Agent** user. This is the only user that owns and edits generated post content.

- **Username:** `1111-learn-agent`
- **Display name:** 1111
- **Avatar:** 1111 logo — `assets/1111-logo.svg` (see Section 4.8.2)
- **Role:** Custom role `1111_learn_agent` with capabilities: `edit_learn_posts`, `edit_published_learn_posts`, `publish_learn_posts`, `delete_learn_posts`, `read`
- **Email:** `agent@1111-learn.local` (non-routable, placeholder)
- **Created on:** Plugin activation (`register_activation_hook`)
- **Removed on:** Plugin uninstall (`uninstall.php`)

All generated posts (`learn` CPT) are authored by this user. The `post_author` is set to the agent user's ID during generation, and the agent user is the only user with the `edit_learn_posts` capability for posts it authored. Human users **cannot directly edit** the `post_content`, `post_title`, or `post_excerpt` of agent-authored posts. Instead, they provide feedback that triggers regeneration (see Section 8.4).

**Why an agent user?**
- Creates a clean audit trail: every generated post is clearly marked as AI-authored
- Enforces the feedback-driven workflow — if you can't directly edit, you must provide feedback, which flows through the pipeline and produces better output
- The agent user's edit history becomes telemetry data: when the agent rewrites a post, the revision diff shows exactly what changed
- Future companion plugins (Learn Administrator) can identify AI-generated content by author

---

## 4. Architecture

### 4.1 Seven-Agent Pipeline

The generation pipeline uses seven agents. The first six are sequential content-generation agents; the seventh (Activity Assessment Agent) runs on-demand when a learner submits their WordPress content for evaluation. This extends the proven four-agent content pipeline with assessment agents from 1111 School, plus the portfolio work-product model, activity type progression, and activity assessment from 1111 Learn (Chrome extension).

```
═══════════════════════════════════════════════════
  PHASE 1: COURSE CREATION (Main Site, Super Admin)
═══════════════════════════════════════════════════

Super Admin Input (title, description, objectives)
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Agent 1: Course Describer                       │
│  (fast model)                                    │
│  Establishes narrative arc + lesson titles        │
│  Defines work product (portfolio artifact)        │
│  Output: narrative, lessons, workProduct          │
│  ◄── Feedback triggers re-run (draft only)       │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 2: Lesson Planner (per objective)         │
│  (fast model)                                    │
│  Backward design: mastery → activity → outline(s)│
│  May produce 1–4 lessons per objective            │
│  Assigns activity types (explore/apply/create)    │
│  Output: mastery_criteria, activity_seed, lessons │
│  ◄── Feedback triggers re-run (draft only)       │
└─────────────────────┬───────────────────────────┘
                      │
                      │  For EACH lesson in the plan:
                      │  ┌──────────────────────────────────────────┐
                      ▼  ▼                                          │
┌─────────────────────────────────────────────────┐ │
│  Agent 3: Lesson Writer                          │ │
│  (default model — needs more tokens)             │ │
│  Writes full lesson content from ONE outline      │ │
│  Output: lesson_body, key_takeaways               │ │
│  ◄── Feedback triggers re-run (draft only)       │ │
└─────────────────────┬───────────────────────────┘ │
                      │                              │
                      ▼                              │
┌─────────────────────────────────────────────────┐ │
│  Agent 4: Activity Creator                       │ │
│  (fast model)                                    │ │
│  Designs activity as portfolio contribution       │ │
│  Gamification: type progression, XP, milestones  │ │
│  Output: prompt, instructions, rubric, hints,     │ │
│          xp_value, portfolio_contribution          │ │
│  ◄── Feedback triggers re-run (draft only)       │ │
└─────────────────────┬───────────────────────────┘ │
                      │                              │
                      ▼                              │
┌─────────────────────────────────────────────────┐ │
│  Agent 5: Activity Reviewer                      │ │
│  (fast model)                                    │ │
│  Reviews activity against mastery criteria        │ │
│  Checks rubric alignment, difficulty calibration  │ │
│  Output: approved/revision_needed, suggestions    │ │
│  ◄── Loops back to Activity Creator if needed    │ │
└─────────────────────┬───────────────────────────┘ │
                      │                              │
                      └──── next lesson ─────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 6: Assessment Creator (per course)        │
│  (default model)                                  │
│  Summative assessment across ALL objectives       │
│  Portfolio-based: "finalize your work product"    │
│  Gamification: course mastery score, completion   │
│  Output: assessment with portfolio rubric         │
│  ◄── Feedback triggers re-run (draft only)       │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Draft Posts (CPT: learn) — Main Site             │
│  Author: 1111 Agent user (content locked)         │
│  Taxonomies: course + lesson_group                │
│                                                   │
│  Course: "Web Accessibility Fundamentals"         │
│  Work Product: "Accessibility Audit Report"       │
│    ├── Lesson Group: "Seeing the Barriers"        │
│    │   ├── Lesson 1a [explore] + Activity         │
│    │   └── Lesson 1b [apply] + Activity           │
│    ├── Lesson Group: "Auditing with Browser Tools"│
│    │   └── Lesson 2 [create] + Activity           │
│    ├── Lesson Group: "Writing Fix Recommendations"│
│    │   └── Lesson 3 [create] + Activity           │
│    └── Final Assessment [final]                   │
│                                                   │
│  Super admin REVIEWS → adds FEEDBACK if needed    │
│  Super admin PUBLISHES when satisfied             │
│  ═══ PUBLISHED = PERMANENTLY IMMUTABLE ═══       │
└───────────────────────────────────────────────────┘

═══════════════════════════════════════════════════
  PHASE 2: LEARNER EXPERIENCE (Learner Subsites)
═══════════════════════════════════════════════════

Learner signs up → gets own WordPress subsite
    │
    ▼
┌─────────────────────────────────────────────────┐
│  Learner Panel (on learner's subsite)            │
│  Browse course catalog → select a course         │
│  Plugin COPIES course content to their subsite   │
│  Each learner's copy is independent              │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Learner's Copy of Course Content                │
│  Same lessons, activities, assessment            │
│  Learner can add FEEDBACK → personalized         │
│  regeneration on THEIR copy only                 │
│                                                   │
│  Learner completes activities on their subsite   │
│  (creates pages, posts, WordPress content)       │
│  Each activity builds the portfolio work product │
└─────────────────────┬───────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────┐
│  Agent 7: Activity Assessment (on-demand)        │
│  (default model)                                  │
│  Reads learner's WordPress content from subsite   │
│  Evaluates against rubric + mastery criteria      │
│  Output: score 0.0–1.0, recommendation,           │
│          strengths, improvements, portfolio check  │
│  ◄── Learner can resubmit after revision         │
└───────────────────────────────────────────────────┘
```

### 4.2 Why Seven Agents

The original PRD used two agents (plan + write). After studying the 1111 School pipeline (seven agents) and the 1111 Learn extension (four agents with assessment), seven agents is the right number for WordPress:

1. **Course Describer** ensures narrative coherence — lesson titles feel like chapters in the same story, not isolated topics. Also defines the course **work product** (the portfolio artifact learners build within WordPress across all activities).
2. **Lesson Planner** uses backward design — defining mastery criteria first, then designing the activity, then planning the lesson content. Assigns **activity types** (`explore` → `apply` → `create`) following the learn-extension's progression model.
3. **Lesson Writer** focuses solely on writing engaging content from a detailed plan, rather than simultaneously planning and writing.
4. **Activity Creator** designs activities as **portfolio contributions** anchored to specific mastery criteria, with gamification mechanics (XP, milestones). Each activity directs the learner to create WordPress content (pages, posts) on their own subsite.
5. **Activity Reviewer** (from School) quality-checks each activity against mastery criteria and rubric alignment before it reaches the super admin. This automated review step catches misalignment that would otherwise require feedback.
6. **Assessment Creator** (from School) produces a summative, portfolio-based final assessment that spans all objectives — "finalize and present your work product." This closes the backward design loop: the assessment is the ultimate evidence of mastery.
7. **Activity Assessment Agent** (from Learn extension) evaluates the learner's submitted WordPress content against the activity's rubric and mastery criteria. Runs on-demand when a learner submits their work. Returns a score (0.0–1.0), recommendation (advance/revise/continue), strengths, and improvements. This is the grading engine that makes the portfolio model work — without it, rubrics are aspirational; with it, learners get real feedback on what they've built.

### 4.3 Agent Design Principles (from 1111 School)

These principles are proven in production and must carry forward:

- **Backward design:** Define the finish line (mastery criteria) → design the evidence (activity) → plan the path (lesson outline). Never start with content and hope it covers the objective.
- **Narrative threading:** The Course Describer identifies the PRIMARY objective and shows how others support it. Every lesson title and summary feels like a chapter in the same story.
- **Scope control:** Each lesson covers ONLY its assigned objective. The planner receives the full objective list but is explicitly told not to teach other objectives. This prevents scope creep and repetition.
- **Agents are functions, not frameworks:** Each agent takes typed input, returns typed output, validates against a schema, and retries on failure. No memory across invocations, no autonomous decisions.
- **Portfolio-first design (from Learn extension):** Every activity contributes to a single, persistent work product — the learner's portfolio artifact for the course, built as **WordPress content on the learner's own subsite**. Activities don't exist in isolation; they build on each other. The final assessment is "finalize and present your work product." This follows the learn-extension's "Single Document Rule" — one work product per course, created in the first activity, refined through every subsequent one — but the work product lives inside WordPress where the Activity Assessment Agent can read and evaluate it natively.
- **Activity type progression (from Learn extension):** Activities follow a four-type progression: `explore` (research and discover) → `apply` (practice a skill) → `create` (build and refine) → `final` (polish and deliver). This progression mirrors the learn-extension's activity types and ensures learners move from understanding to mastery to demonstration.
- **Gamification through mastery, not gimmicks:** XP values, mastery scores, achievement milestones, and streaks are designed to reflect genuine learning progress. An `explore` activity earns less XP than a `create` activity because it requires less synthesis. Milestones mark real accomplishments ("First draft complete," "All objectives covered"). This is gamification in service of pedagogy, not engagement hacking.
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
| Menu icon | Custom SVG — Learn logo (see Section 4.8) |
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

The course taxonomy term is where the super admin provides **course description feedback** — the term edit screen includes a feedback field that triggers re-running the Course Describer agent (see Section 8.4).

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

The lesson group tag is where the super admin provides **lesson plan feedback** — the tag edit screen shows the full lesson plan and includes a feedback field that triggers re-running the Lesson Planner and all downstream agents for that group (see Section 8.4).

**Term meta for `lesson_group`:**

| Meta key | Type | Description |
|----------|------|-------------|
| `_1111_lesson_plan_raw` | `array` | Full raw lesson plan from the Lesson Planner agent |
| `_1111_learning_objective` | `string` | The objective this lesson group covers |
| `_1111_lesson_count` | `int` | Number of lessons generated from this plan |
| `_1111_plan_feedback` | `string` | Super admin's feedback on the lesson plan (cleared after regeneration) |
| `_1111_plan_version` | `int` | Incremented on each regeneration |

### 4.7 Multisite Architecture

The plugin requires a **WordPress Multisite** network. The super admin creates and reviews courses on the **main site**. Each learner signs up through a plugin-generated registration form and receives their own WordPress subsite with a **copy** of the published course content. Learners complete activities, build portfolio work products, and can personalize their copy through feedback — all within their own WordPress site.

#### 4.7.1 Why Multisite?

The core design goal is that learners build portfolio items **within WordPress itself** — creating pages, posts, or even full sites. This requires giving each learner their own WordPress space where they can:

- Receive a copy of course content (lessons, activities, assessments) on their own site
- Add feedback to any content on their site to trigger personalized regeneration
- Create and edit pages and posts as directed by activity instructions
- Build a portfolio work product that persists across the entire course
- Have their content read and assessed by the Activity Assessment Agent
- Own a shareable URL to their finished portfolio

A Multisite network is the natural WordPress solution: the super admin manages course creation on the main site, and each learner's subsite is their learning environment, workspace, and portfolio.

#### 4.7.2 Network Structure

```
Multisite Network
├── Main Site (super admin domain)
│   ├── learn CPT (source lessons, activities, assessments)
│   ├── course taxonomy
│   ├── lesson_group taxonomy
│   ├── Learn admin panel (create, review, publish courses)
│   ├── Learner progress dashboard
│   └── Source content generated and managed here
│
├── Learner Subsite: jane.example.com
│   ├── learn CPT (COPY of published course content)
│   ├── Learner panel (course selection, progress tracking)
│   ├── Course navigation (lesson pages, prev/next, activity submission)
│   ├── Pages/posts created by the learner as portfolio work
│   ├── Personalized content (via learner feedback → regeneration)
│   ├── Work product content assessed by Activity Assessment Agent
│   └── Shareable portfolio URL
│
├── Learner Subsite: alex.example.com
│   └── ...
└── ...
```

#### 4.7.3 Learner Registration and Subsite Provisioning

Learners sign up through a **registration form generated by the plugin** — there is no super admin enrollment step. The plugin handles the entire flow:

1. **Registration form:** The plugin provides a frontend registration page (shortcode or block) on the main site where learners enter a username, email, and password.
2. **Subsite creation:** On successful registration, the plugin automatically creates a new subsite for the learner.
   - **Subdomain pattern:** `{username}.{network-domain}` (or subdirectory: `{network-domain}/{username}/`)
   - **Default theme:** Inherited from the network's default, or a specific portfolio theme if configured
3. **Redirect to learner panel:** The learner is redirected to their new subsite's learner panel, where they can browse and select courses.
4. **No content on signup:** The subsite starts empty. Course content is copied when the learner **selects a course** from the catalog (see Section 4.7.4).
5. **Capabilities:** The learner has the `administrator` role on their own subsite — they can create and manage all content on their site. However, the Learn admin panel (course creation) is **not available** on learner subsites. Learners see a **learner panel** instead, with course selection and progress tracking.
6. **Cross-site access:** The Activity Assessment Agent (running on the main site) reads learner content from subsites using `switch_to_blog()` / `restore_current_blog()` — standard WordPress Multisite API.

#### 4.7.4 Content Copy Model

When a learner **selects a course** from the catalog on their learner panel, the plugin copies that course's published content from the main site to the learner's subsite:

- All `learn` posts for the course are duplicated to the learner's site, preserving all post meta (activity specs, mastery criteria, gamification data, etc.)
- The `course` and `lesson_group` taxonomy terms are replicated on the learner's subsite
- Copied posts are authored by the 1111 Agent user (which exists on every subsite via network activation)
- The learner's copy is independent — feedback and regeneration on their site does not propagate back to the main site or to other learners' sites
- The `_1111_source_post_id` and `_1111_source_blog_id` meta fields link each copied post back to its origin on the main site (for telemetry and progress tracking)

#### 4.7.5 Work Product, Activities, and Lesson Artifacts

Instead of external tools (Google Docs, Notion, etc.), the work product is WordPress content on the learner's subsite. Every activity is attached to a specific lesson, and the WordPress artifacts the learner creates when completing that activity are **related to the lesson where the activity takes place**. This means a learner's portfolio grows lesson by lesson — each activity adds to the work product, and the artifact created is traceable back to the lesson that taught the underlying skill.

**How it works:**

1. Each lesson post on the learner's subsite has an activity stored in its post meta (`_1111_activity`).
2. The activity instructions direct the learner to create or modify specific WordPress content on their subsite (a page, a post, or a section of their site).
3. When the learner submits their work for assessment, the submission records the `lesson_post_id` — linking the artifact back to the lesson.
4. The Activity Assessment Agent evaluates the learner's content against that lesson's specific rubric and mastery criteria.
5. Over the course, each lesson's activity builds on the previous ones, so the work product accumulates as a coherent whole.

**Example — `page` work product:**

| Lesson | Activity type | What the learner does on their subsite | Artifact relationship |
|--------|--------------|---------------------------------------|----------------------|
| "Seeing the Barriers" | `explore` | Creates a new page titled "Accessibility Audit Report" and documents initial barrier research | Page created; linked to Lesson 1 |
| "Auditing with Browser Tools" | `apply` | Edits the same page — adds an "Audit Methodology" section with screenshots from browser dev tools | Page updated; submission linked to Lesson 2 |
| "Writing Fix Recommendations" | `create` | Edits the same page — adds a "Recommendations" section with prioritized fixes | Page updated; submission linked to Lesson 3 |
| Final Assessment | `final` | Reviews and polishes the full page, publishes it as a shareable portfolio piece | Page published; submission linked to assessment |

**Example — `site` work product (entire subsite is the portfolio):**

| Lesson | Activity type | What the learner does on their subsite | Artifact relationship |
|--------|--------------|---------------------------------------|----------------------|
| "Planning Your Portfolio Site" | `explore` | Configures their subsite theme, creates an "About" page and a "Projects" page with placeholder structure | Site scaffolded; linked to Lesson 1 |
| "Building Your First Case Study" | `apply` | Creates a new post documenting a real project — includes context, process, outcome, and images | New post created; linked to Lesson 2 |
| "Designing for Your Audience" | `create` | Customizes site navigation, adds a custom header, refines the "About" page for a target audience | Site-wide changes; linked to Lesson 3 |
| "Adding Depth with a Second Case Study" | `create` | Creates a second case study post using a different format or angle than the first | New post created; linked to Lesson 4 |
| Final Assessment | `final` | Reviews the entire site for consistency, publishes all drafts, ensures navigation works | Full site published; submission linked to assessment |

In the `site` work product type, the learner's entire subsite IS the deliverable — the URL they share is their root domain, not a single page.

The Course Describer's `work_product_type` determines the pattern: `page` (a single WordPress page built across the course), `post_series` (a series of blog posts), or `site` (the entire subsite is the portfolio).

#### 4.7.6 Plugin Activation on Multisite

- The plugin is **network activated** — it runs across the entire Multisite network
- The Activity Assessment Agent can read content from any subsite in the network
- **Main site only:** The Learn admin panel (course creation, review, publish) is only available on the main site to super admins
- **Learner subsites** show the **learner panel** instead — course selection, progress tracking, and feedback on their personal copy of content. Learners cannot create courses.
- The 1111 Agent user is created on every site in the network (needed for content authorship on learner subsites)

#### 4.7.7 Publish Immutability

Once a super admin publishes a course, the source content on the main site becomes **permanently immutable** — neither the super admin nor the agent pipeline can modify it. This ensures:

- Learners who already have a copy are never out of sync with a silently-updated source
- The super admin's review and feedback cycle has a definitive endpoint
- Content versioning is explicit: a new course is a new course, not a stealth update

**To change published content, the super admin must create a new course.** The published course remains available to existing learners. The super admin can optionally mark an old course as superseded.

The feedback-driven regeneration pipeline (Section 8.4) only operates on **draft** courses (pre-publish) on the main site, and on **learner copies** on learner subsites.

### 4.8 Branding and Visual Identity

**Author:** 11:11 Philosopher's Group

#### 4.8.1 Learn Logo (Plugin Identity)

The plugin logo is the word **LEARN** in all capitals, set in **SF Pro Display Bold** (Cupertino system font) or the closest available bold geometric sans-serif, rendered as white text on a solid black rectangle with a thin white border (1–2px). This logo is used for:

- WordPress admin menu icon (rendered as a 20×20 SVG)
- Plugin header on the Plugins screen
- Dashboard page header
- Notification banners in the block editor

File: `assets/learn-logo.svg`

#### 4.8.2 1111 Logo (Agent User Identity)

The 1111 Agent user's avatar uses the same visual treatment — **1111** in all caps, white text, black box, thin white border, same typeface — so that AI-generated content is visually attributable at a glance. Used for:

- The 1111 Agent user's WordPress avatar (via `get_avatar` filter)
- Author byline on generated lesson posts

File: `assets/1111-logo.svg`

#### 4.8.3 Design Direction

The admin UI follows **WordPress design patterns** — standard admin components, default color palette, native form controls, and wp-admin spacing conventions. The Learn logo's black-and-white aesthetic is used only where plugin identity is needed (menu icon, page headers, agent attribution) rather than as a full theme applied to every surface. Accent colors, button styles, and layout follow whatever the active WordPress admin color scheme provides.

---

## 5. Agent Specifications

All agents use the Anthropic Claude API. The super admin provides their API key on the Settings page. System prompts are stored as Markdown files in `prompts/` and loaded at runtime.

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
  "work_product": "Accessibility Audit Report",
  "work_product_type": "page",
  "work_product_description": "A professional accessibility audit report for a real website, built as a WordPress page on your own site — documenting barriers found, audit methodology, and prioritized fix recommendations. This is a portfolio piece you can share with employers or clients by sending them the link to your published page.",
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

**Key prompt rules (from 1111 School's course_describer + Learn extension's work product model):**
- `narrative_description` must identify the PRIMARY objective and show how others support it
- Give the learner a clear arc: where they start, what they build, where they end up
- Written in second person (you/your), energetic and specific
- One lesson entry per objective, in the same order — never merge, skip, or reorder
- Lesson titles must feel like chapters in the same story (foundation → application → mastery)
- Lesson summaries describe what the learner will be able to DO, not what the lesson covers
- **Work product (from Learn extension):** Define a single, concrete portfolio artifact that the learner builds **within WordPress** across the entire course. `work_product` is a short name (2–4 words), `work_product_type` is one of `page` (single WordPress page built across the course), `post_series` (a series of blog posts), or `site` (the entire subsite is the portfolio), `work_product_description` is 2–3 sentences framing it as a portfolio piece the learner owns on their own WordPress subsite and can share via URL. The work product must be achievable given the objectives — not aspirational.

### 5.2 Agent 2: Lesson Planner

**Purpose:** Given one objective, the course narrative, the full objective list (for scope control), and a target lesson count, produce a backward-designed lesson plan: mastery criteria → activity seed → lesson outline(s). A single objective may require multiple lessons to cover adequately — the planner decides how to split the content across the target lesson count.

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 2048
**Prompt file:** `prompts/lesson-planner.md`

**Lesson count:** The plugin defines a target number of lessons per objective as a constant (`LEARN_LESSONS_PER_OBJECTIVE`, default: 1, range: 1–4). The Lesson Planner receives this as input and produces a plan with that many lesson outlines. When the count is greater than 1, the planner splits the objective's content across multiple lessons in a logical progression. All lessons from the same plan are grouped under a shared `lesson_group` tag.

**Input (user message):**
```
Course description: You'll start by learning to see the web through the eyes of users who face accessibility barriers...

Work product: Accessibility Audit Report (WordPress page on learner's subsite)

Learning objective for THIS lesson group: Identify common accessibility barriers on web pages

Lesson title from Course Describer: Seeing the Barriers

Target lesson count: 2

This is objective 1 of 3. Assign activity types accordingly (early = explore/apply, later = create).

Other objectives in this course (DO NOT teach these, they have their own lessons):
- Use browser developer tools to run basic accessibility audits
- Propose concrete fixes for the accessibility issues you find

Feedback on previous plan (if any): Split the visual barriers into their own lesson — there's too much for one lesson.
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

When `lesson_count` > 1, the output structure is identical but the `lessons` array contains multiple entries (e.g., "Seeing the Visual Barriers" + "Beyond What You Can See"), each with its own outline that together cover the full objective.

**Key prompt rules (from 1111 School's lesson_planner):**
- **Backward design order:** Step 1: mastery_criteria (what does mastery look like?), Step 2: suggested_activity (what would demonstrate mastery?), Step 3: lesson outlines (what knowledge closes the gap?)
- **Scope control:** Cover ONLY the assigned objective. May briefly mention related topics for context but must NOT teach concepts belonging to other objectives.
- **Lesson splitting:** When `lesson_count` > 1, split the objective's content across lessons in a logical progression. Each lesson should build toward mastery, not stand alone. The last lesson in the group should connect all prior lessons to the mastery criteria.
- Mastery criteria must be specific and measurable — rubric-style checks a reviewer could use.
- **Activity type assignment (from Learn extension):** Each lesson's `suggested_activity` must have an `activity_type` from the progression: `explore` → `apply` → `create`. Early lessons in a course should be `explore` (research and discover), middle lessons `apply` (practice a skill), later lessons `create` (build and refine). The planner assigns types based on position in the course and the nature of the objective. The `final` type is reserved for the Assessment Creator (Agent 6).
- Activity seed must directly exercise the mastery criteria, not just recall facts. The seed must reference the work product by name.
- Lesson outlines must collectively close the gap: after completing all lessons, a learner could plausibly meet every mastery criterion.
- The first lesson title uses the preset title from Course Describer. Additional lessons get planner-generated titles that read as continuations (e.g., "Part 2: Beyond What You Can See").
- **Feedback integration:** When feedback is provided, incorporate it into the new plan. The feedback may request structural changes (split/merge lessons), content emphasis changes, or scope adjustments.

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

Feedback on previous version (if any): Add more concrete examples of contrast failures.
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

**Purpose:** Given the activity seed, mastery criteria, activity type, and work product context from the lesson plan, create a complete practice activity that contributes to the learner's portfolio work product. Includes gamification metadata (XP value, milestone, portfolio contribution description).

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 1024
**Prompt file:** `prompts/activity-creator.md`

**Input (user message):**
```
Learning objective: Identify common accessibility barriers on web pages

Activity type: explore

Work product: Accessibility Audit Report
Work product type: page (WordPress page on learner's subsite)

Course position: Lesson 1 of 4 (first activity in the course)

Mastery criteria:
- Names at least five distinct accessibility barriers with correct categorization
- Explains how each barrier affects real users
- Identifies barriers from at least three different categories
- Uses specific examples rather than generic descriptions

Activity seed:
{activity seed JSON from lesson plan}

Feedback on previous activity (if any): The rubric criteria are too vague — make them more specific.
```

**Expected output (JSON):**
```json
{
  "activity_type": "explore",
  "prompt": "Research common types of web accessibility barriers and start your Accessibility Audit Report.",
  "instructions": "On your WordPress site, create a new page called 'Accessibility Audit Report'. Research common accessibility barriers that affect real users. Write about what you found in your own words — what surprised you or stood out. Save your page when you're done.",
  "scoring_rubric": [
    "Document is created with a clear title",
    "Identifies at least five distinct accessibility barriers",
    "Correctly categorizes barriers into visual, motor, cognitive, or auditory",
    "Explains real user impact in the learner's own words (not copied)",
    "Covers at least three different barrier categories"
  ],
  "hints": [
    "Try navigating a website using only your keyboard — what do you notice?",
    "Look at images: do they have alt text you can check in the browser?",
    "Check text contrast: can you read everything easily, especially in smaller sizes?"
  ],
  "portfolio_contribution": "Creates the Accessibility Audit Report and documents initial barrier research — the foundation for all subsequent work.",
  "xp_value": 100,
  "milestone": "Portfolio Started"
}
```

**Key prompt rules (adapted from 1111 School + Learn extension):**
- **Portfolio-first:** Every activity adds to the work product on the learner's WordPress subsite. The first activity creates it (e.g., "Create a new page called..."); subsequent activities return to it (e.g., "Open your Accessibility Audit Report page and add a new section..."). Never create throwaway exercises — the learner is always building WordPress content they'll keep and can share.
- **Activity type determines tone:** `explore` = research and discover; `apply` = practice a skill; `create` = build or refine; `final` = polish and deliver. Follow the learn-extension's activity type definitions.
- **Guide, don't dictate (from Learn extension):** Tell the learner WHAT to learn and WHERE to put it — never tell them WHAT to write or HOW to structure it. No prescribed headings, templates, or bullet points to copy.
- `prompt`: Core task question (1–2 sentences, min 20 chars). References the work product by name.
- `instructions`: Format and constraint guidance ONLY (1–2 sentences, min 50 chars). Do NOT restate the prompt.
- `scoring_rubric`: 3–6 specific, checkable criteria that map to the mastery criteria.
- `hints`: 2–5 scaffolding hints that guide without giving the answer.
- `portfolio_contribution`: 1–2 sentences describing what this activity adds to the work product. Used in portfolio progress displays.
- `xp_value`: Integer XP reward (100 for `explore`, 150 for `apply`, 200 for `create`, 300 for `final`). Reflects the synthesis required.
- `milestone`: Optional achievement label (null if none). Examples: "Portfolio Started", "First Draft Complete", "All Objectives Covered", "Portfolio Delivered".
- Activity must directly test the learning objective — challenging but achievable.
- Anchor in real-world application, not hypothetical scenarios.
- **Learner subsite context:** Activity instructions should direct the learner to create content on their own WordPress subsite. Each learner has a fresh subsite provisioned at registration, so activities can assume a clean WordPress environment.

### 5.5 Agent 5: Activity Reviewer

**Purpose:** Given the generated activity and the lesson plan's mastery criteria, review the activity for quality — checking that the rubric aligns with mastery criteria, that the difficulty is appropriate, that the activity actually contributes to the work product, and that gamification values are calibrated correctly. This is an automated quality gate that catches problems before they reach the super admin.

**Model:** Fast model (`claude-haiku-4-5-20251001`)
**Max tokens:** 1024
**Prompt file:** `prompts/activity-reviewer.md`

**Input (user message):**
```
Learning objective: Identify common accessibility barriers on web pages

Activity type: explore

Work product: Accessibility Audit Report

Mastery criteria:
- Names at least five distinct accessibility barriers with correct categorization
- Explains how each barrier affects real users
- Identifies barriers from at least three different categories
- Uses specific examples rather than generic descriptions

Generated activity:
{full activity JSON from Activity Creator}
```

**Expected output (JSON):**
```json
{
  "verdict": "approved",
  "rubric_alignment": "All 5 rubric criteria map directly to mastery criteria. No gaps.",
  "difficulty_assessment": "Appropriate for an explore activity — research-based, no prior knowledge assumed.",
  "portfolio_check": "Activity creates the work product document and begins substantive content. Not just scaffolding.",
  "gamification_check": "XP value of 100 matches explore type. 'Portfolio Started' milestone is appropriate for first activity.",
  "suggestions": []
}
```

**On revision_needed:**
```json
{
  "verdict": "revision_needed",
  "rubric_alignment": "Rubric criterion 3 ('covers three categories') is not reflected in the activity instructions — learner might only cover one category.",
  "difficulty_assessment": "Instructions assume the learner already knows WCAG categories — too advanced for an explore activity.",
  "portfolio_check": "Activity asks learner to 'list barriers' but doesn't connect this to the Audit Report document.",
  "gamification_check": "XP value of 200 is too high for an explore activity (expected 100).",
  "suggestions": [
    "Add instruction to organize findings by barrier category in the document",
    "Remove assumption about WCAG — let the learner discover categories through research",
    "Reference the Accessibility Audit Report by name in the prompt",
    "Reduce XP to 100 for explore type"
  ]
}
```

**Key prompt rules:**
- The reviewer is a quality gate, not a rewriter. It approves or sends back with specific suggestions.
- If `verdict` is `revision_needed`, the orchestrator sends the suggestions back to the Activity Creator as feedback for a new attempt. Maximum 1 revision loop (matching the retry-once pattern).
- Review criteria: rubric-mastery alignment, difficulty calibration for the activity type, work product contribution, gamification value accuracy.
- Never approve an activity that doesn't reference the work product by name.

### 5.6 Agent 6: Assessment Creator

**Purpose:** After all lessons and activities are generated for a course, produce a summative assessment that spans all learning objectives. The assessment is **portfolio-based** — it asks the learner to finalize, present, and defend their work product. This is always the `final` activity type.

**Model:** Default model (`claude-sonnet-4-6` — needs nuanced rubric design across multiple objectives)
**Max tokens:** 4096
**Prompt file:** `prompts/assessment-creator.md`

**Input (user message):**
```
Course title: Web Accessibility Fundamentals

Course description: You'll start by learning to see the web through the eyes of users who face accessibility barriers...

Work product: Accessibility Audit Report
Work product type: page (WordPress page on learner's subsite)

Learning objectives (all):
1. Identify common accessibility barriers on web pages
2. Use browser developer tools to run basic accessibility audits
3. Propose concrete fixes for the accessibility issues you find

Mastery criteria (all, by objective):
Objective 1: [...]
Objective 2: [...]
Objective 3: [...]

Activities completed before this assessment:
[summary of all generated activities and their portfolio contributions]

Feedback on previous assessment (if any): The rubric doesn't test objective 2 well enough.
```

**Expected output (JSON):**
```json
{
  "assessment_title": "Finalize Your Accessibility Audit Report",
  "assessment_type": "final",
  "prompt": "Your Accessibility Audit Report page has grown across every lesson in this course. Now it's time to finalize it. Review your entire page, fill any gaps, and make sure it demonstrates everything you've learned.",
  "instructions": "Open your Accessibility Audit Report page on your WordPress site. Review it from start to finish. Make sure it covers all three areas: identifying barriers, audit methodology, and fix recommendations. Polish your writing and ensure every section shows your own understanding — not copied text. When you're satisfied, publish the page — it's now a live portfolio piece with a shareable URL.",
  "portfolio_rubric": [
    {
      "objective": "Identify common accessibility barriers on web pages",
      "criteria": [
        "Report documents at least five distinct barriers with correct categorization",
        "Each barrier includes a real-user impact explanation in the learner's own words",
        "At least three barrier categories (visual, motor, cognitive, auditory) are represented"
      ]
    },
    {
      "objective": "Use browser developer tools to run basic accessibility audits",
      "criteria": [
        "Report includes evidence of using at least one browser audit tool",
        "Audit findings are documented with specific elements and issues found",
        "Methodology section explains the audit process step by step"
      ]
    },
    {
      "objective": "Propose concrete fixes for the accessibility issues you find",
      "criteria": [
        "At least three specific, actionable fix recommendations are provided",
        "Recommendations are prioritized by impact or severity",
        "Each recommendation references a specific issue documented earlier in the report"
      ]
    }
  ],
  "scoring_guide": {
    "mastery": "Meets all criteria across all objectives — the report is complete, specific, and demonstrates genuine understanding. Score: 0.85–1.0",
    "proficient": "Meets most criteria with minor gaps — the report covers all objectives but some areas lack depth or specificity. Score: 0.70–0.84",
    "developing": "Meets some criteria — significant gaps in one or more objectives, or content is too generic/copied. Score: 0.50–0.69",
    "beginning": "Major gaps across multiple objectives — the report is incomplete or doesn't demonstrate understanding. Score: 0.0–0.49"
  },
  "xp_value": 500,
  "milestone": "Portfolio Delivered",
  "completion_message": "You've completed your Accessibility Audit Report — a real portfolio piece that demonstrates your ability to identify, audit, and fix web accessibility issues. This is work you can share with employers, clients, or teammates."
}
```

**Key prompt rules:**
- The assessment is always type `final` — the last step in the course.
- It must be **portfolio-based**: the learner finalizes and presents their work product, not a separate quiz or test.
- `portfolio_rubric` is organized by objective with specific criteria for each. Every learning objective must be represented.
- The rubric evaluates the **work product itself** — what the learner built across the entire course — not isolated knowledge recall.
- `scoring_guide` maps score ranges to mastery levels (following learn-extension's 0.0–1.0 scale with recommendation thresholds: advance ≥ 0.7, continue ≥ 0.5, revise < 0.5).
- `completion_message` reinforces that this is a portfolio piece the learner owns and can use. This is critical for the "building portfolio items" goal.
- `xp_value` for the final assessment is always 500 (the highest single reward, reflecting the synthesis required).
- The assessment must be completable — it asks the learner to finalize existing work, not produce something entirely new.

### 5.7 Agent 7: Activity Assessment Agent

**Purpose:** Given a learner's submitted WordPress content (read from their subsite) and the activity's scoring rubric and mastery criteria, evaluate the submission and provide a structured assessment. This agent runs **on-demand** — not during course generation, but when a learner submits their work for grading. Follows the learn-extension's Activity Assessment Agent pattern.

**Model:** Default model (`claude-sonnet-4-6` — needs nuanced evaluation of learner work)
**Max tokens:** 2048
**Prompt file:** `prompts/activity-assessment.md`

**Input (user message):**
```
Activity type: explore

Learning objective: Identify common accessibility barriers on web pages

Mastery criteria:
- Names at least five distinct accessibility barriers with correct categorization
- Explains how each barrier affects real users (not just abstract rule violations)
- Identifies barriers from at least three different categories (visual, motor, cognitive, auditory)
- Uses specific examples rather than generic descriptions

Scoring rubric:
- Document is created with a clear title
- Identifies at least five distinct accessibility barriers
- Correctly categorizes barriers into visual, motor, cognitive, or auditory
- Explains real user impact in the learner's own words (not copied)
- Covers at least three different barrier categories

Work product: Accessibility Audit Report
Work product type: page

Portfolio contribution expected: Creates the Accessibility Audit Report and documents initial barrier research — the foundation for all subsequent work.

Learner's submitted WordPress content:
---
Page title: Accessibility Audit Report

Page content:
## Web Accessibility Barriers

I visited the local library's website and found several accessibility issues...

[full page content extracted from learner's subsite]
---
```

**Expected output (JSON):**
```json
{
  "score": 0.82,
  "recommendation": "advance",
  "strengths": [
    "Identified six distinct barriers across four categories — exceeds the minimum",
    "Each barrier includes a specific example from the library website, not generic descriptions",
    "Clear explanation of how missing alt text affects screen reader users"
  ],
  "improvements": [
    "The cognitive barriers section could be stronger — 'confusing layout' is vague. Which specific layout elements cause confusion, and for whom?",
    "Consider adding the impact severity for each barrier to strengthen the audit"
  ],
  "rubric_results": [
    {"criterion": "Document is created with a clear title", "met": true, "note": "Page titled 'Accessibility Audit Report'"},
    {"criterion": "Identifies at least five distinct accessibility barriers", "met": true, "note": "Six barriers identified"},
    {"criterion": "Correctly categorizes barriers", "met": true, "note": "Visual, motor, cognitive, and auditory categories used"},
    {"criterion": "Explains real user impact in learner's own words", "met": true, "note": "Personal observations from testing the library site"},
    {"criterion": "Covers at least three different barrier categories", "met": true, "note": "All four categories represented"}
  ],
  "portfolio_check": "The page establishes a solid foundation for the Accessibility Audit Report. Barrier categories are well-organized and the real-website approach gives the document authenticity."
}
```

**Key prompt rules (from 1111 Learn extension's Activity Assessment Agent):**
- Score on a 0.0–1.0 scale. Be calibrated: 0.85+ is genuinely excellent, 0.5 is mediocre, below 0.3 is missing the mark.
- `recommendation` is one of: `advance` (score ≥ 0.7 — learner should proceed to next activity), `continue` (score 0.5–0.69 — acceptable but could improve), `revise` (score < 0.5 — learner should revise and resubmit).
- `strengths`: 2–4 specific things the learner did well, referencing their actual content.
- `improvements`: 1–3 specific, actionable suggestions. Never vague ("try harder") — always concrete ("the cognitive barriers section lists 'confusing layout' without specifying which elements or who is affected").
- `rubric_results`: One entry per rubric criterion, with `met` (boolean) and `note` (brief explanation).
- `portfolio_check`: How well this submission advances the work product. Does it build meaningfully on previous activities?
- **Read, don't assume:** The agent receives the actual WordPress content. Evaluate what's there, not what you hope is there.
- **Encourage, don't gatekeep:** The tone should be supportive and specific. Even low scores should feel like useful feedback, not punishment.
- **Content is read from the learner's subsite** using `switch_to_blog()` — the orchestrator extracts the page/post content and passes it as text in the user message.

---

## 6. Output Validation

All agent output passes through deterministic validators before reaching WordPress. This pattern is proven in both 1111 Learn (browser-side) and 1111 School (Pydantic schema validation).

### 6.1 Validation Rules

**Course Describer output:**
- `narrative_description` is a non-empty string (min 100 chars)
- `work_product` is a non-empty string (2–60 chars) — the portfolio artifact name
- `work_product_type` is one of: `page`, `post_series`, `site`
- `lessons` is an array with exactly one entry per objective
- Each lesson has `lesson_title` (5–60 chars) and `lesson_summary` (min 30 chars)

**Lesson Plan output:**
- Has `learning_objective`, `key_concepts` (2–8 items), `mastery_criteria` (2–6 items)
- `suggested_activity` has `activity_type` (one of: `explore`, `apply`, `create`), `prompt`, `expected_evidence` (2–5 items)
- `lessons` array with 1–4 entries, each having `lesson_title` and `lesson_outline` (3–10 items)
- No unsafe content patterns

**Lesson Content output:**
- Has `lesson_title`, `lesson_body` (min 200 chars), `key_takeaways` (3–6 items)
- No unsafe content patterns

**Activity output:**
- Has `activity_type` (one of: `explore`, `apply`, `create`, `final`)
- Has `prompt` (min 20 chars), `instructions` (min 50 chars)
- `scoring_rubric` has 3–6 items
- `hints` has 2–5 items
- `portfolio_contribution` is a non-empty string (min 20 chars)
- `xp_value` is an integer: 100 (`explore`), 150 (`apply`), 200 (`create`), or 300 (`final` on per-lesson activities)
- `milestone` is null or a non-empty string
- No unsafe content patterns

**Activity Review output:**
- Has `verdict` (one of: `approved`, `revision_needed`)
- Has `rubric_alignment`, `difficulty_assessment`, `portfolio_check`, `gamification_check` (all non-empty strings)
- `suggestions` is an array (empty if approved, 1–5 items if revision_needed)

**Assessment output:**
- Has `assessment_title` (5–100 chars), `assessment_type` (must be `final`)
- Has `prompt` (min 20 chars), `instructions` (min 50 chars)
- `portfolio_rubric` is an array with one entry per objective, each having `objective` (string) and `criteria` (2–6 items)
- `scoring_guide` has `mastery`, `proficient`, `developing`, `beginning` (all non-empty strings)
- `xp_value` is 500
- `milestone` must be "Portfolio Delivered"
- `completion_message` is a non-empty string (min 50 chars) — must reference the work product as a portfolio piece
- No unsafe content patterns

**Activity Assessment output:**
- `score` is a float between 0.0 and 1.0
- `recommendation` is one of: `advance`, `continue`, `revise`
- `recommendation` must be consistent with `score`: `advance` requires score ≥ 0.7, `continue` requires 0.5 ≤ score < 0.7, `revise` requires score < 0.5
- `strengths` is an array of 2–4 non-empty strings
- `improvements` is an array of 1–3 non-empty strings
- `rubric_results` is an array with one entry per rubric criterion, each having `criterion` (string), `met` (boolean), `note` (string)
- `portfolio_check` is a non-empty string (min 20 chars)

### 6.2 Retry Strategy

On validation failure, the agent call is retried once automatically (matching both Learn and School patterns). If the retry also fails, the user sees an error with:
- Which agent failed and why
- A **Retry** button that resumes from the failed step
- Already-generated content is preserved (incremental recovery, per School's design)

### 6.3 Safety Patterns

All text output is checked against unsafe content patterns before saving:

```
/\b(kill yourself|self-harm|suicide method|how to hack|how to steal|how to attack)\b/i
```

Safety violations are never retried — an error is shown and the generation stops.

---

## 7. Plugin File Structure

```
1111-learn/
├── 1111-learn.php                  Main plugin file (plugin header, bootstrap)
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
│   ├── class-learner-assessment.php  Learner submission handling, Activity Assessment Agent orchestration
│   ├── class-admin-page.php        Dashboard page registration and rendering (main site, super admin)
│   ├── class-learner-panel.php     Learner panel: course selection, progress tracking (learner subsites)
│   ├── class-registration.php      Learner registration form, subsite provisioning
│   ├── class-content-copy.php      Copies published course content from main site to learner subsites
│   ├── class-publish.php           Course publish flow, immutability enforcement
│   ├── class-settings.php          Settings page (API key, data sharing opt-in)
│   └── class-telemetry.php         Event collection, buffering, learn-service transmission
│
├── admin/
│   ├── css/
│   │   └── admin.css               Dashboard + feedback panel styles
│   ├── js/
│   │   ├── admin.js                Dashboard interactivity (AJAX, form, progress)
│   │   └── editor-sidebar.js       Block editor sidebar panel (feedback for lesson + activity)
│   └── views/
│       ├── dashboard.php           Course creation form template (main site)
│       ├── course-review.php       Course review panel template (main site)
│       ├── learner-progress.php    Learner progress dashboard template (main site)
│       ├── learner-panel.php       Learner panel template (learner subsites)
│       ├── course-catalog.php      Course selection catalog template (learner subsites)
│       ├── course-landing.php      Course landing page with lesson list (frontend, learner subsites)
│       ├── lesson-navigation.php   Lesson page with prev/next, breadcrumb, activity submission (frontend)
│       ├── registration.php        Learner registration form template (frontend)
│       ├── settings.php            Settings page template
│       └── generating.php          Generation progress template (partial)
│
├── prompts/
│   ├── course-describer.md         System prompt — narrative arc + lesson titles + work product
│   ├── lesson-planner.md           System prompt — backward design lesson plan + activity types
│   ├── lesson-writer.md            System prompt — full lesson content
│   ├── activity-creator.md         System prompt — portfolio activity + gamification
│   ├── activity-reviewer.md        System prompt — quality gate for activities
│   ├── assessment-creator.md       System prompt — summative portfolio assessment
│   └── activity-assessment.md      System prompt — evaluates learner WordPress content
│
└── assets/
    ├── learn-logo.svg              Plugin logo — "LEARN" wordmark
    └── 1111-logo.svg               Agent user avatar — "1111" wordmark
```

---

## 8. User Interface

### 8.1 Top-Level Menu (Main Site — Super Admin Only)

- **Menu title:** Learn
- **Icon:** Custom SVG (see Section 4.8 — Branding)
- **Visibility:** Main site only (`is_main_site()` + `is_super_admin()`)
- **Submenu items:**
  - **Dashboard** — Course creation form
  - **All Lessons** — Standard CPT list view (WordPress default)
  - **Courses** — Course taxonomy management (course-level feedback on term edit screen)
  - **Lesson Groups** — Lesson group tag management (plan-level feedback on tag edit screen)
  - **Learner Progress** — View all learners and their progress across courses (see Section 8.6)
  - **Settings** — API key, data sharing opt-in

### 8.2 Dashboard Page — Course Creation Form

**Fields:**

| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| Course Title | Text input | Required, max 200 chars | Becomes the taxonomy term name |
| Course Description | Textarea | Required, 10–1000 chars | Stored as taxonomy term description and passed to agents |
| Learning Objectives | Repeater (text inputs) | Min 1, max 8 items; each max 300 chars | Each objective is a measurable learning outcome |

**Objective count follows 1111 School's range:** 1–8 objectives (School uses 1–8 in its validation). Each objective produces one lesson.

**Interaction flow:**

1. Super admin fills in the three fields and clicks **Generate Course**.
2. Form validates client-side. If invalid, inline errors appear next to fields.
3. On valid submission, an AJAX request sends data to the server.
4. The dashboard shows a **vertical stepper** progress view (matching School's generation UX):
   - Phase 0: "Establishing course narrative..." → shows lesson titles as they arrive
   - Per objective: "Planning lesson 1..." → "Writing lesson 1..." → "Creating activity 1..." (checkmarks on completion)
   - Overall: "Generation complete — N lessons created"
5. On completion, a success message with links to:
   - **Review the course** in the course review panel (see Section 8.2.1)
   - Review individual lessons in the block editor (read-only with feedback panel)
   - Edit the course taxonomy term (to provide course-level feedback)
   - Return to dashboard to create another course
6. On error per objective: displayed inline in the stepper without blocking other objectives
7. On fatal error: error message with **Retry** button that resumes from the last successful step

#### 8.2.1 Course Review Panel

After generation, the super admin reviews all course material from a single **course review panel** accessible from the Dashboard. This panel shows:

- **Course name and description** — the taxonomy term name and AI-generated narrative
- **Course category** — the `course` taxonomy term
- **Lesson plan overview** — all lesson groups with their objectives, mastery criteria, and activity seeds
- **All lessons** — expandable list of every generated lesson with:
  - Lesson title, content preview, key takeaways
  - Activity details (type badge, prompt, rubric, XP, milestone)
  - Activity Reviewer verdict
- **Final assessment** — assessment title, prompt, portfolio rubric, scoring guide
- **Feedback entry points** — at every level (course, lesson plan, lesson, activity, assessment), each with a feedback textarea and regenerate button. These link to the same feedback UI surfaces described in Section 8.4.

The review panel is the super admin's central place to evaluate all generated material before publishing.

#### 8.2.2 Publish Flow

Once the super admin is satisfied with all course material:

1. Super admin clicks **Publish Course** from the course review panel.
2. A confirmation dialog warns: "Publishing this course makes it available to learners. Published course content cannot be updated — you'll need to create a new course to make changes."
3. On confirm, all `learn` posts for the course are changed from `draft` to `published` status.
4. The course taxonomy term's `_1111_generation_status` is set to `published`.
5. The course appears in the **learner course catalog** (available for learner selection).
6. **All feedback UI is removed** from the course's posts and taxonomy terms on the main site — no more regeneration once published.
7. To make changes, the super admin must create a new course from scratch.

### 8.3 Settings Page

The settings page is intentionally minimal — two fields only:

| Field | Type | Notes |
|-------|------|-------|
| Anthropic API Key | Password input | Stored encrypted in `wp_options`. Masked in UI. |
| Share Data with 11:11 Philosopher's Group | Checkbox | Default: OFF. Consent dialog on first enable. See Section 15. |

All other configuration (model selection, max tokens, lessons per objective) is **hardcoded in the plugin** and tuned through the telemetry → PR pipeline (Section 15.8). Exposing these as admin settings would create a support surface for values that most users shouldn't need to change. If telemetry reveals that a default needs adjustment, an agent proposes a code change — not a per-site override.

**Hardcoded defaults (defined in plugin constants):**

| Constant | Value | Purpose |
|----------|-------|---------|
| `LEARN_FAST_MODEL` | `claude-haiku-4-5-20251001` | Describer, Planner, Activity Creator, Activity Reviewer |
| `LEARN_DEFAULT_MODEL` | `claude-sonnet-4-6` | Lesson Writer, Assessment Creator, Activity Assessment |
| `LEARN_PLAN_MAX_TOKENS` | `2048` | Max tokens for planning agents |
| `LEARN_CONTENT_MAX_TOKENS` | `8192` | Max tokens for content generation |
| `LEARN_LESSONS_PER_OBJECTIVE` | `1` | Target lesson count per objective (1–4) |

These can be overridden via `wp-config.php` `define()` for advanced users or development, but are not exposed in the admin UI.

Settings are saved using the WordPress Settings API with nonce verification and capability checks.

### 8.4 Feedback-Driven Regeneration

Generated content is **immutable by human users** — only the 1111 Agent user (Persona 3.4) can modify post content. Both super admins (on the main site, pre-publish) and learners (on their own subsite) provide feedback through dedicated UI surfaces, and that feedback is passed to the relevant agent(s) to regenerate content. This keeps the agent pipeline as the single source of truth for all generated content.

**Super admin feedback** operates on draft content on the main site, before publishing. Once a course is published, feedback UI is removed from the main site.

**Learner feedback** operates on the learner's personal copy on their subsite. Regeneration affects only their copy — other learners and the main site source are unaffected.

#### 8.4.1 Feedback Levels

Feedback can be provided at five levels, each triggering regeneration of different scope. These levels apply to both super admin feedback (on draft content, main site) and learner feedback (on their copy, learner subsite):

| Level | Where feedback is given | What gets regenerated | Agents re-run |
|-------|------------------------|----------------------|----------------|
| **Course description** | Course taxonomy term edit screen | Entire course — new narrative, new plans, new lessons, new activities, new assessment | All six content-generation agents |
| **Lesson plan** | Lesson group (`lesson_group`) tag edit screen | All lessons in that group + their activities | Lesson Planner → Lesson Writer → Activity Creator → Activity Reviewer |
| **Written lesson** | Post editor — block editor sidebar panel | That lesson only (re-written from existing plan) | Lesson Writer only |
| **Activity** | Post editor — custom meta box below content | That lesson's activity only (re-created from existing plan) | Activity Creator → Activity Reviewer |
| **Assessment** | Assessment post editor — block editor sidebar panel | The final assessment only | Assessment Creator only |

#### 8.4.2 Feedback UI: Course Description (Taxonomy Term Editor)

When the super admin edits a `course` taxonomy term, the standard WordPress term edit screen includes:

- **Read-only display** of the current AI-generated `narrative_description` (rendered from meta, not the editable description field)
- **Read-only list** of current lesson titles and summaries
- **Feedback textarea:** "What should change about this course's narrative or structure?"
- **Regenerate Course button:** Submits feedback, re-runs the Course Describer with the original inputs plus feedback, and cascades regeneration through all downstream agents
- All fields use the block editor's `TextareaControl` component for consistency with WordPress admin UI

The original inputs (title, description, objectives) are preserved in term meta and always re-sent to the agent. Feedback is additive context, not a replacement for the original inputs.

#### 8.4.3 Feedback UI: Lesson Plan (Tag Editor)

When the super admin edits a `lesson_group` tag, the term edit screen includes:

- **Read-only display** of the full lesson plan JSON rendered as readable HTML (mastery criteria, key concepts, activity seed, lesson outlines)
- **List of lessons** in this group with links to each post
- **Feedback textarea:** "What should change about this lesson plan?"
- **Regenerate Plan button:** Submits feedback, re-runs the Lesson Planner with original inputs plus feedback, then cascades through Lesson Writer and Activity Creator for all lessons in the group

The lesson count for this group may differ from the default if the super admin has previously requested splitting or merging lessons via feedback.

#### 8.4.4 Feedback UI: Written Lesson (Post Editor — Sidebar Panel)

When viewing a `learn` post in the block editor, a **sidebar panel** (registered via `registerPlugin` / `PluginSidebar` from `@wordpress/edit-post`) provides:

- **Read-only view** of the post content (the block editor canvas itself shows the content but editing is disabled — see Section 8.5)
- **Feedback textarea:** "What should change about this lesson?"
- **Regenerate Lesson button:** Submits feedback, re-runs the Lesson Writer with the existing lesson plan plus feedback, and updates the post content
- **Version indicator:** Shows current generation version number (incremented on each regeneration)

#### 8.4.5 Feedback UI: Activity (Post Editor — Meta Box)

Below the block editor content area, a **custom meta box** displays:

- **Activity type badge:** Color-coded label showing the activity type (`explore` / `apply` / `create`)
- **Read-only rendered view** of the current activity (prompt, instructions, rubric, hints)
- **Portfolio contribution:** How this activity adds to the work product
- **Gamification details:** XP value, milestone (if any)
- **Activity Reviewer verdict:** Shows whether the activity was `approved` or required revision, with the reviewer's alignment assessment
- **Feedback textarea:** "What should change about this activity?"
- **Regenerate Activity button:** Submits feedback, re-runs the Activity Creator → Activity Reviewer with the existing mastery criteria and activity seed plus feedback, and updates the activity meta
- **Version indicator:** Shows current activity generation version number

#### 8.4.6 Feedback UI: Assessment (Assessment Post Editor)

The final assessment is stored as a separate `learn` post (with `_1111_activity_type` = `final`) and has its own feedback UI in the block editor:

- **Block editor sidebar panel** (same pattern as lesson feedback) with:
  - **Read-only rendered view** of the assessment: title, prompt, instructions, portfolio rubric (organized by objective), scoring guide
  - **Gamification summary:** Total course XP, milestone progression, completion message
  - **Feedback textarea:** "What should change about this assessment?"
  - **Regenerate Assessment button:** Re-runs the Assessment Creator with all course context plus feedback
  - **Version indicator**

The super admin can also see the assessment from the **Course taxonomy term edit screen**, which shows a summary of the assessment alongside the course narrative.

#### 8.4.7 Regeneration Pipeline

When feedback is submitted at any level:

1. The feedback text is stored in the relevant meta field (term meta or post meta)
2. The appropriate agent(s) are called with the original inputs plus the feedback appended to the user message
3. Output is validated using the same schema validation and retry logic as initial generation
4. On success, the generated content is updated (post content, post meta, or term meta) — authored by the 1111 Agent user
5. WordPress revisions capture the before/after diff
6. The feedback field is cleared after successful regeneration
7. A `content_regenerated` telemetry event is emitted (see Section 15.3) with the feedback level and which agents were re-run

#### 8.4.8 Cascading Regeneration

When feedback triggers regeneration at a higher level, all downstream content is regenerated:

- **Course description feedback** → Course Describer → (for each objective) Lesson Planner → (for each lesson) Lesson Writer → Activity Creator → Activity Reviewer → Assessment Creator
- **Lesson plan feedback** → Lesson Planner → (for each lesson in group) Lesson Writer → Activity Creator → Activity Reviewer
- **Lesson feedback** → Lesson Writer (single lesson)
- **Activity feedback** → Activity Creator → Activity Reviewer (single activity)
- **Assessment feedback** → Assessment Creator (single assessment)

The progress stepper UI (Section 8.2) is reused for cascading regeneration, showing which agents are currently running and which lessons are being updated.

### 8.5 Block Editor Content Locking

For `learn` posts authored by the 1111 Agent user, the block editor content area is **read-only** for human administrators. This is implemented using WordPress's block locking API:

- All blocks in agent-authored posts are locked with `{ "lock": { "move": true, "remove": true } }` — blocks cannot be moved, removed, or edited
- The block editor toolbar is hidden for locked content via the `editor.BlockEdit` filter
- The post title is also locked (non-editable) via the `enter_title_here` filter returning the current title, combined with a read-only attribute on the title input
- A prominent notice at the top of the editor explains: "This lesson was generated by Learn. Use the feedback panel in the sidebar to request changes."
- The `post_content` is additionally protected server-side: the `wp_insert_post_data` filter rejects content changes from any user other than the 1111 Agent user

**Why block editing, not classic editor?** The plugin targets the latest WordPress version and the block editor is the standard editing experience. Block locking is a native Gutenberg API that provides the exact UX needed: content is visible and structured but not directly editable.

### 8.6 Learner Progress Dashboard (Admin View)

The super admin can track all learner progress from the **Learner Progress** submenu on the main site:

- **Learner list** — all registered learners with their subsite URL
- **Per-learner view** — courses enrolled, progress per course (lessons completed, activities submitted, assessment scores)
- **Per-course view** — all learners enrolled, aggregate completion rates, average scores
- **Submission details** — drill down into individual submissions and assessment results

This view reads from the `_1111_learn_submissions` table and from learner subsite data via `switch_to_blog()`.

### 8.7 Learner Panel (Learner Subsite)

On learner subsites, the Learn menu shows a **learner panel** instead of the course creation UI. The learner panel has:

#### 8.7.1 Registration Form

The plugin provides a **frontend registration page** (shortcode `[learn_register]` or block) on the main site. The form collects:

- Username
- Email
- Password

On successful registration, the plugin creates the user, provisions their subsite, and redirects them to their new site's learner panel.

#### 8.7.2 Course Selection

- **Course catalog** — lists all published courses from the main site with title, description, and narrative arc
- **Select Course** button — copies the course content from the main site to the learner's subsite and enrolls them
- Learners can select multiple courses; each is tracked independently

#### 8.7.3 Course Navigation

When a learner is enrolled in a course, their subsite provides a **frontend course navigation UI** for browsing and progressing through lessons:

- **Course landing page** — overview of the course narrative, work product description, and a list of all lesson groups with their lessons. Available as a shortcode (`[learn_course]`) or block.
- **Lesson navigation** — each lesson page includes:
  - **Previous / Next links** — sequential navigation between lessons in course order
  - **Breadcrumb** — Course → Lesson Group → Current Lesson
  - **Progress indicator** — shows position in the course (e.g., "Lesson 3 of 7") and completion status of each lesson
  - **Activity status badge** — whether the lesson's activity is pending, submitted, or assessed (with score)
- **Lesson group headers** — when navigating between lesson groups, a transition page shows the group's objective and how it connects to the narrative arc
- **Activity submission** — at the bottom of each lesson, the activity prompt and instructions are displayed with a **Submit for Assessment** button. The learner creates their WordPress content as directed, then submits from this UI.
- **Assessment results** — after submission, the lesson page shows the Activity Assessment Agent's feedback inline: score, strengths, improvements, and advance/revise recommendation

The navigation respects the learner's theme — it outputs semantic HTML with CSS classes that themes can style. The plugin provides minimal default styling sufficient for usability but designed to be overridden.

#### 8.7.4 Progress Tracking

- **Active courses** — list of enrolled courses with progress indicators
- Per-course view shows:
  - Lesson completion status (read/unread, activity submitted/pending)
  - Activity scores and assessment feedback
  - XP earned vs. total available
  - Milestone achievements
  - Current position in the course sequence

#### 8.7.5 Learner Feedback on Content

Learners can provide feedback on **their own copy** of course content — the same feedback UI surfaces used during course review (sidebar panels, meta boxes, taxonomy term editors) are available on learner subsites. When a learner submits feedback:

1. The regeneration pipeline runs against **their copy only** — on their subsite, using their subsite's `learn` posts
2. The regenerated content replaces only that learner's version
3. Other learners and the main site source content are unaffected
4. The learner's personalized content is tracked via `_1111_learner_regeneration_count` meta
5. Telemetry captures learner feedback events (level + agents re-run, never feedback text)

This allows each learner to tailor the course material to their needs — requesting different examples, deeper explanations, or alternative activity approaches — without affecting anyone else's experience.

**Note:** Learner feedback is only available on content they received through the content copy. They cannot provide feedback on content they haven't enrolled in.

---

## 9. Data Model

### 9.1 Taxonomy Term Meta (Course)

| Meta key | Type | Description |
|----------|------|-------------|
| `_1111_learning_objectives` | `array` | Original learning objectives entered by super admin |
| `_1111_course_description` | `string` | Original course description |
| `_1111_narrative_description` | `string` | AI-generated narrative arc from Course Describer |
| `_1111_lesson_titles` | `array` | Pre-set `[{lesson_title, lesson_summary}]` from Course Describer |
| `_1111_work_product` | `string` | Portfolio artifact name (e.g., "Accessibility Audit Report") |
| `_1111_work_product_type` | `string` | Work product WordPress content type: `page`, `post_series`, or `site` |
| `_1111_work_product_description` | `string` | 2–3 sentence description framing portfolio value |
| `_1111_assessment` | `array` | Full assessment spec from Assessment Creator (Agent 6) |
| `_1111_assessment_post_id` | `int` | Post ID of the assessment post |
| `_1111_assessment_feedback` | `string` | Feedback on the assessment (cleared after regeneration) |
| `_1111_total_xp` | `int` | Total XP available across all activities + assessment |
| `_1111_generation_date` | `string` | ISO 8601 timestamp of generation |
| `_1111_generation_status` | `string` | `generating`, `complete`, `failed`, `published` |
| `_1111_course_feedback` | `string` | Feedback on the course description (cleared after regeneration) |
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
| `_1111_activity` | `array` | Activity spec: `{activity_type, prompt, instructions, scoring_rubric, hints, portfolio_contribution, xp_value, milestone}` |
| `_1111_activity_type` | `string` | One of: `explore`, `apply`, `create`, `final` |
| `_1111_activity_review` | `array` | Activity Reviewer output: `{verdict, rubric_alignment, suggestions}` |
| `_1111_xp_value` | `int` | XP reward for this lesson's activity |
| `_1111_milestone` | `string|null` | Achievement milestone label (if any) |
| `_1111_portfolio_contribution` | `string` | How this activity adds to the work product |
| `_1111_generated` | `bool` | `true` if AI-generated |
| `_1111_lesson_feedback` | `string` | Feedback on the written lesson (cleared after regeneration) |
| `_1111_lesson_version` | `int` | Incremented on each lesson-level regeneration |
| `_1111_activity_feedback` | `string` | Feedback on the activity (cleared after regeneration) |
| `_1111_activity_version` | `int` | Incremented on each activity-level regeneration |
| `_1111_source_post_id` | `int` | On learner subsites: post ID of the source lesson on the main site |
| `_1111_source_blog_id` | `int` | On learner subsites: blog ID of the main site (source) |
| `_1111_learner_regeneration_count` | `int` | On learner subsites: number of times this lesson was regenerated via learner feedback |

### 9.3 Learner Submission Data (Main Site — Custom Table)

Learner submissions and assessment results are stored in a custom table on the main site. This tracks which learner submitted which content for which activity, and the assessment result.

**Table:** `{prefix}_1111_learn_submissions`

| Column | Type | Description |
|--------|------|-------------|
| `id` | `bigint` | Auto-increment primary key |
| `user_id` | `bigint` | WordPress user ID of the learner |
| `blog_id` | `bigint` | Subsite ID where the learner's content lives |
| `post_id` | `bigint` | Post/page ID on the learner's subsite (the submitted content) |
| `lesson_post_id` | `bigint` | Post ID of the `learn` lesson on the main site |
| `course_term_id` | `bigint` | Term ID of the course taxonomy term |
| `activity_type` | `varchar(20)` | `explore`, `apply`, `create`, or `final` |
| `submitted_at` | `datetime` | When the learner submitted |
| `score` | `decimal(3,2)` | Assessment score (0.00–1.00), NULL until assessed |
| `recommendation` | `varchar(20)` | `advance`, `continue`, or `revise` — NULL until assessed |
| `assessment_json` | `longtext` | Full Activity Assessment Agent output (JSON) |
| `assessed_at` | `datetime` | When the assessment completed, NULL until assessed |
| `attempt_number` | `int` | Submission attempt (1 for first, incremented on resubmit) |
| `content_snapshot` | `longtext` | Snapshot of the learner's content at submission time (in case they edit after submitting) |

**Indexes:** `(user_id, course_term_id)`, `(lesson_post_id, user_id)`, `(blog_id, post_id)`

### 9.4 Learner Enrollment Data (Main Site — Custom Table)

Tracks which learners are enrolled in which courses and when content was copied to their subsite.

**Table:** `{prefix}_1111_learn_enrollments`

| Column | Type | Description |
|--------|------|-------------|
| `id` | `bigint` | Auto-increment primary key |
| `user_id` | `bigint` | WordPress user ID of the learner |
| `blog_id` | `bigint` | Subsite ID of the learner's site |
| `course_term_id` | `bigint` | Term ID of the course taxonomy term (on main site) |
| `enrolled_at` | `datetime` | When the learner selected this course |
| `content_copied_at` | `datetime` | When course content was copied to the learner's subsite |
| `lessons_completed` | `int` | Count of completed lessons (cached, updated on activity submission) |
| `total_lessons` | `int` | Total lessons in the course at time of enrollment |
| `current_xp` | `int` | XP earned so far in this course |
| `total_xp` | `int` | Total XP available in this course |
| `status` | `varchar(20)` | `active`, `completed`, `abandoned` |

**Indexes:** `(user_id, course_term_id)`, `(blog_id)`, `(course_term_id, status)`

### 9.5 Options (wp_options)

| Option key | Description |
|------------|-------------|
| `1111_learn_api_key` | Encrypted Anthropic API key |
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
  "model": "{LEARN_FAST_MODEL or LEARN_DEFAULT_MODEL}",
  "max_tokens": {LEARN_PLAN_MAX_TOKENS or LEARN_CONTENT_MAX_TOKENS},
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
- 401: Invalid API key — prompt super admin to check settings
- 429: Rate limited — show "Rate limited, please wait and retry"
- 5xx: Server error — allow retry
- Timeout: 120-second timeout (Lesson Writer can produce 8000+ tokens) — allow retry
- Parse error: Non-JSON response — retry once automatically

### 10.2 Security

- API key stored encrypted in `wp_options` (`sodium_crypto_secretbox` if available, `AUTH_KEY`-based fallback)
- API key never exposed in client-side JavaScript — all calls server-side via AJAX
- Super admin AJAX endpoints (generation, settings, feedback) verify nonces and `manage_network` capability
- Learner AJAX endpoints (assessment submission) verify nonces and that the user is a member of the submitting subsite
- Prompt files loaded from plugin directory, never from user input

---

## 11. Generation Pipeline (Server-Side)

### 11.1 Pipeline Architecture

The pipeline must work entirely within WordPress's execution model. A course with 3 objectives and 2 lessons per objective requires 20+ Anthropic API calls, each taking seconds. This far exceeds PHP's `max_execution_time` on any standard host. The pipeline uses **WordPress's background processing pattern**: each step is a discrete unit of work dispatched via `wp_schedule_single_event()` (WP-Cron) or a loopback `wp_remote_post()` to a non-blocking AJAX handler that spawns the next step.

**Execution model:**

1. Super admin clicks "Generate Course" → AJAX handler validates input, creates the course term, stores generation state in term meta, and schedules the first step.
2. Each step runs as its own PHP request: call one agent, validate output, save to database, schedule the next step.
3. Progress is stored in a transient after each step. The client polls for updates (Section 11.7).
4. If a step fails, the generation state records which step failed. The super admin can retry from the failed step.

This follows the same pattern used by WordPress core updates, WooCommerce background processing, and other plugins that handle long-running work. No single PHP request runs more than one agent call.

```
AJAX Request → Validate → Create Course Term → Schedule Phase 0
  ↓
Phase 0 (own request): Course Describer → save → schedule Per-Objective
  ↓
Per-Objective (own request each): Planner → save → schedule Per-Lesson
  ↓
Per-Lesson (own request each): Writer → save → schedule Activity
  ↓
Activity (own request each): Creator → Reviewer → save → schedule next
  ↓
Phase Final (own request): Assessment Creator → save → mark complete
```

**Why not a single long-running AJAX request?** PHP's `max_execution_time` (typically 30–60 seconds on shared hosts) would kill the process mid-pipeline. Even with `set_time_limit(0)`, many hosts enforce hard limits at the web server level. The step-per-request model works on every WordPress host, including shared hosting, and is the standard WordPress pattern for background work.

**AJAX Endpoints:**
- `1111_generate_course` — validates input, creates course term, kicks off pipeline
- `1111_generation_step` — executes one pipeline step (called by WP-Cron or loopback)
- `1111_generation_status` — returns current progress (polled by client)

### 11.2 Phase 0: Course Description

1. Call Course Describer agent with title, description, objectives
2. Validate output (narrative_description + work_product + lessons array)
3. Store `_1111_narrative_description`, `_1111_lesson_titles`, `_1111_work_product`, `_1111_work_product_type`, `_1111_work_product_description` on the course term
4. Send progress update: lesson titles and work product now visible in the stepper UI

### 11.3 Per-Objective Loop (Phase 1+)

For each objective (index 0 to N-1):

1. **Check for existing content** — if lesson group already has content (retry scenario), skip
2. **Lesson Planner** — call with objective, narrative description, all objectives (for scope control), preset title from Phase 0, target lesson count, work product context, and any feedback
3. Validate plan output. Retry once on failure.
4. **Create `lesson_group` tag** — store the full lesson plan on the tag's term meta

The Lesson Planner may produce 1–4 lesson outlines per objective. Each lesson outline triggers its own Lesson Writer → Activity Creator → Activity Reviewer cycle:

5. **For each lesson in the plan** (1 to lesson_count):
   a. **Lesson Writer** — call with the specific lesson outline, mastery criteria, and course description. Each lesson in the plan gets its own Writer call — a plan with 3 lessons means 3 separate Writer invocations.
   b. Validate content output. Retry once on failure.
   c. **Activity Creator** — call with activity seed, objective, mastery criteria, activity type, work product context, and course position from the plan
   d. Validate activity output. Retry once on failure.
   e. **Activity Reviewer** — call with the generated activity, mastery criteria, and work product context
   f. Validate review output. If `revision_needed`, send suggestions back to Activity Creator for one revision attempt.
   g. **Create WordPress post** — `learn` CPT, authored by 1111 Agent user, assigned to `course` taxonomy term and `lesson_group` tag, with all meta (including activity, gamification, portfolio data)
   h. **Commit and report progress** — save post, update stepper

A course with 3 objectives and `lessons_per_objective` = 2 produces: 3 Planner calls → 6 Writer calls → 6 Activity Creator calls → 6 Activity Reviewer calls → 6 lesson posts + 1 assessment post.

### 11.4 Phase Final: Assessment

After all objectives have been processed:

1. **Assessment Creator** — call with course title, narrative description, work product, all objectives, all mastery criteria, summary of all generated activities and their portfolio contributions, and any feedback
2. Validate assessment output. Retry once on failure.
3. **Create assessment post** — `learn` CPT with `_1111_activity_type` = `final`, authored by 1111 Agent user, assigned to the course taxonomy term
4. Store assessment spec in post meta and `_1111_assessment` on the course term
5. Calculate `_1111_total_xp` for the course (sum of all activity XP + assessment XP) and store on course term
6. Send progress update: "Assessment created — course generation complete"

### 11.4 Post Creation

| Post field | Value |
|------------|-------|
| `post_type` | `learn` |
| `post_author` | 1111 Agent user ID (Persona 3.4) |
| `post_title` | `lesson_title` from Lesson Writer |
| `post_content` | `lesson_body` converted from Markdown to WordPress block markup |
| `post_excerpt` | First sentence of `lesson_body`, or `lesson_summary` from Course Describer |
| `post_status` | `draft` (super admin reviews before publishing) |
| `menu_order` | Objective index (for ordering) |
| `tax_input` | Assigned to the `course` taxonomy term and `lesson_group` tag |

Posts are created as **drafts** authored by the 1111 Agent user (Persona 3.4). The super admin reviews generated content and provides feedback to trigger regeneration — they do not directly edit post content.

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

Progress is reported via **transient + polling** — standard WordPress patterns, no WebSockets or server-sent events:

- Each pipeline step updates a transient (`_1111_generation_progress_{term_id}`) before scheduling the next step
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
| `prompts/course-describer.md` | Course Describer | fast | Narrative arc + lesson titles + work product definition |
| `prompts/lesson-planner.md` | Lesson Planner | fast | Backward design lesson plan + activity type assignment |
| `prompts/lesson-writer.md` | Lesson Writer | default | Full lesson content from plan |
| `prompts/activity-creator.md` | Activity Creator | fast | Portfolio activity + gamification from activity seed |
| `prompts/activity-reviewer.md` | Activity Reviewer | fast | Quality gate: rubric alignment, difficulty, portfolio check |
| `prompts/assessment-creator.md` | Assessment Creator | default | Summative portfolio assessment across all objectives |
| `prompts/activity-assessment.md` | Activity Assessment | default | Evaluates learner WordPress content against rubric |

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

1. **Capability checks:** Learn admin panel and generation/feedback AJAX handlers on the main site require `manage_network` (super admin). Learner-facing endpoints (assessment submission, course selection, learner feedback) require the user to be a member of the relevant subsite. Content modification is restricted to the 1111 Agent user via the `wp_insert_post_data` filter.
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
- Detect which generated fields consistently receive feedback (signaling the agent underperformed)
- Spot course topics or objective structures that cause disproportionate failures
- Automatically propose prompt changes as PRs that agents can build, test, and submit

### 15.2 Consent and Opt-In

- **Default:** Telemetry is OFF. No data is collected or transmitted until the super admin explicitly enables it.
- **Toggle:** "Share anonymous usage data with 1111" checkbox on the Settings page.
- **Consent dialog:** On first enable, a modal explains exactly what is collected, what is never collected, how data is stored, and how to withdraw consent. The super admin must confirm before telemetry activates.
- **Withdrawal:** Disabling the toggle immediately stops all data collection and transmission. No previously sent data is retroactively deleted (auto-expires per retention policy).

### 15.3 Events Collected

All events are recorded server-side during course generation and batched for transmission.

| Event Type | When | Data Collected |
|------------|------|----------------|
| `course_started` | Super admin clicks Generate Course | objectiveCount, pluginVersion, wpVersion, phpVersion |
| `agent_request` | Before each agent call | agentName, model, promptFileHash (not contents), inputTokenEstimate |
| `agent_response` | After each agent call | agentName, model, outputTokens, latencyMs, responseJson |
| `validation_failure` | Agent output fails schema validation | agentName, validationErrors, failedResponseJson, retried (bool) |
| `retry_outcome` | After automatic retry | agentName, succeeded (bool), originalErrors, retryErrors |
| `course_completed` | All lessons generated successfully | objectiveCount, totalLatencyMs, totalTokens, lessonCount |
| `course_failed` | Pipeline fails fatally | failedAgent, failedObjectiveIndex, errorType, errorMessage |
| `feedback_submitted` | Super admin submits feedback for regeneration | feedbackLevel (course/plan/lesson/activity), agentsToRerun (list) |
| `content_regenerated` | Regeneration completes from feedback | feedbackLevel, agentsRerun, succeeded (bool), lessonCount |
| `submission_assessed` | Activity Assessment Agent completes | activityType, score, recommendation, attemptNumber, rubricCriteriaCount, rubricCriteriaMet |
| `learner_registered` | Learner signs up via registration form | pluginVersion (no PII) |
| `course_enrolled` | Learner selects a course | courseObjectiveCount, lessonCount |
| `learner_feedback_submitted` | Learner submits feedback on their copy | feedbackLevel (course/plan/lesson/activity), agentsToRerun (list) |
| `learner_content_regenerated` | Learner's copy regenerated from feedback | feedbackLevel, agentsRerun, succeeded (bool) |

### 15.4 What Is Never Collected

Following the extension's `stripBinaries` pattern, these are explicitly excluded:

- **API keys** — never logged, never transmitted
- **Course content** — lesson bodies, activity text, and learner-facing content are never sent. Only agent response JSON structure is captured for schema analysis.
- **Personal information** — no usernames, emails, site URLs, or IP addresses
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
- No correlation to site URL, user identity, or Anthropic API key
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

The diagram above shows the full cycle. The only human step is PR review — collection, analysis, proposal, and measurement are fully automated. Merged prompt changes take effect immediately for all installations (no plugin update required), and subsequent telemetry measures whether the change helped, closing the loop.

### 15.9 Feedback Tracking

Since no one can directly edit generated content, the telemetry signal is "what feedback was given, and at what level?" This captures *intent*, not just *diff*, which is a stronger signal for prompt improvement:

- If 80% of feedback targets lesson plans, the Lesson Planner prompt needs improvement.
- If most feedback at the lesson level requests "more examples," the Lesson Writer prompt should emphasize worked examples.
- If activity feedback frequently says "too vague," the Activity Creator prompt needs tighter rubric generation instructions.
- If course-level feedback is rare, the Course Describer is performing well.
- If the same lesson is frequently regenerated multiple times, the agent is not incorporating feedback effectively.

Tracked via `feedback_submitted` and `content_regenerated` telemetry events. Feedback text itself is **never** collected — only the level (course/plan/lesson/activity), the number of regeneration cycles, and which agents were re-run.

### 15.10 Privacy Documentation

Any change that adds, removes, or modifies collected data must update:

1. The consent dialog text on the Settings page
2. The plugin's privacy policy section (README)
3. The data-stripping logic that excludes sensitive fields
4. The learn-service validation and documentation

This mirrors [Rule #10 from the extension's CLAUDE.md](https://github.com/1111philo/learn-extension/blob/main/CLAUDE.md) — privacy changes are never a single-file edit.

---

## 16. Non-Goals (Explicitly Out of Scope)

> **Note:** Telemetry, assessments, learner-submitted assessment grading, learner self-registration, enrollment, content distribution, learner feedback, publish immutability, and multi-site are no longer non-goals — see Sections 4.7, 5.5–5.7, 8.2.2, 8.7, and 15.

These are intentionally excluded from Learn:

1. **Learner profiles** — No tracking of individual learner preferences or adaptive personalization. The plugin tracks submissions and assessment results per learner, but does not build a learner profile with strengths, weaknesses, or pacing data.
2. **Certificates or badges** — No completion rewards beyond the generated `completion_message` and portfolio framing.
3. **LMS integration** — No direct integration with LearnDash, LifterLMS, etc. (but generated posts are compatible).
4. **Internationalization** — English only for v1 (all strings use `__()` / `_e()` for future translation readiness).
5. **On-demand generation** — Unlike School, all lessons are generated upfront (no need for on-demand since there's no learner progression to gate on).
6. **Cumulative XP tracking** — The plugin tracks XP per enrollment but does not maintain a cross-course cumulative XP total, leaderboards, or streak mechanics.

---

## 17. Future: Learn Administrator (Companion Plugin)

With enrollment, learner self-registration, learner assessment grading, WordPress-native portfolio creation, content copy distribution, learner feedback, and Multisite all part of the Learn plugin, the Administrator companion plugin focuses on the **advanced learner experience layer**:

- **Learner profiles** with adaptive content (following the learn-extension's Learner Profile Agent pattern: monotonically growing profile with strengths, weaknesses, pacing, preferences)
- **Cumulative XP tracking** — cross-course XP totals, milestone celebrations, and leaderboards
- **Portfolio presentation** — learners view their accumulated WordPress content with a contribution timeline (following the learn-extension's Work Detail "build timeline" view). Since portfolio items are WordPress pages/posts on the learner's subsite, the Administrator plugin provides a curated portfolio view across all courses.
- **Advanced analytics dashboard** — admin-facing analytics: completion rates, average assessment scores, time-to-completion, common feedback patterns, learner regeneration patterns
- **Certificates** — generate completion certificates based on course completion and assessment scores

The Learn plugin is designed so the Administrator plugin can build on top of its data structures without modifications. The Learn plugin stores everything the Administrator needs: `_1111_mastery_criteria`, `_1111_activity` (including `scoring_rubric`, `portfolio_contribution`, `xp_value`, `milestone`), `_1111_key_takeaways`, `_1111_assessment` (including `portfolio_rubric`, `scoring_guide`), `_1111_work_product`, the `_1111_learn_submissions` table with per-learner assessment results, and the `_1111_learn_enrollments` table — all structured for the Administrator to consume for progress tracking, XP accumulation, portfolio display, and learner profiles.

---

## 18. Development Guidelines

1. **No build step.** Vanilla PHP, JS, CSS. No Webpack, Sass, or npm. Exception: the block editor sidebar panel (`editor-sidebar.js`) uses `wp.plugins.registerPlugin` and `wp.editPost.PluginSidebar` from the bundled `@wordpress/edit-post` and `@wordpress/plugins` packages — no npm install required, these ship with WordPress.
2. **WordPress coding standards.** Follow WordPress PHP and JavaScript coding standards.
3. **Minimum requirements:** WordPress 6.7+, PHP 8.0+, **WordPress Multisite** network. The plugin targets the **latest WordPress version** and relies on block editor APIs (block locking, PluginSidebar, SlotFill) that are stable in 6.7+. The plugin must be network activated on a Multisite installation. Course creation is restricted to super admins on the main site. Learner subsites show the learner panel, not the admin course creation UI. Do not add fallbacks for the classic editor — the block editor is required.
4. **Block editor first.** All post-editor UI (feedback panels, content locking, activity meta box) is built for the block editor using the `@wordpress/` JS packages bundled with WordPress. Taxonomy term editors use standard WordPress admin UI enhanced with custom meta boxes.
5. **Prefix everything.** `_1111_learn_` for meta/options, `Learn_` for classes.
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
   - `1111_learn_feedback_submitted` — action when super admin submits feedback at any level
   - `1111_learn_before_regenerate` — filter feedback + inputs before regeneration pipeline
   - `1111_learn_content_regenerated` — action after regeneration completes
   - `1111_learn_before_assess` — filter learner content before Activity Assessment Agent
   - `1111_learn_submission_assessed` — action after learner submission is assessed
   - `1111_learn_learner_registered` — action after learner registers and subsite is provisioned
   - `1111_learn_course_enrolled` — action after learner selects a course and content is copied
   - `1111_learn_course_published` — action after super admin publishes a course (content becomes immutable)
   - `1111_learn_learner_feedback_submitted` — action when a learner submits feedback on their copy
8. **Prompts are data, not code.** `prompts/*.md` loaded at runtime. Editable without touching PHP.
9. **Agent user owns all content.** All generated posts are authored by the 1111 Agent user. Human users interact with content through feedback, not direct editing.

---

## 19. Implementation Phases

### Phase 1: Foundation
- [ ] Plugin bootstrap file with proper headers, ABSPATH checks, and **Multisite network activation check** (bail with admin notice if not on Multisite)
- [ ] Register `learn` custom post type with REST support
- [ ] Register `course` taxonomy
- [ ] Register `lesson_group` tag taxonomy
- [ ] Create 1111 Agent user and `1111_learn_agent` role on activation (network-wide)
- [ ] Create `_1111_learn_submissions` custom table on activation (see Section 9.3)
- [ ] Create `_1111_learn_enrollments` custom table on activation (see Section 9.4)
- [ ] Clean up agent user, role, and custom tables on uninstall
- [ ] Settings page with encrypted API key storage and data sharing opt-in (main site only)
- [ ] Restrict Learn admin panel to main site + super admin only (`is_main_site()` + `is_super_admin()`)
- [ ] `CLAUDE.md` for the new repo
- [ ] `README.md` with install instructions (including Multisite setup)

### Phase 2: Agents and Orchestrator
- [ ] Anthropic API HTTP client class (`wp_remote_post`, error handling, retries)
- [ ] Prompt file loader (reads `prompts/*.md`)
- [ ] JSON parser (handles markdown fencing, extracts JSON from response)
- [ ] Validation functions for each agent's output schema
- [ ] Orchestrator class wiring the seven-agent pipeline
- [ ] Write all seven prompt files:
  - [ ] `prompts/course-describer.md` (narrative + work product as WordPress content type)
  - [ ] `prompts/lesson-planner.md` (backward design + activity types)
  - [ ] `prompts/lesson-writer.md` (lesson content)
  - [ ] `prompts/activity-creator.md` (portfolio activity directing learner to create WordPress content)
  - [ ] `prompts/activity-reviewer.md` (quality gate)
  - [ ] `prompts/assessment-creator.md` (summative portfolio assessment)
  - [ ] `prompts/activity-assessment.md` (evaluates learner WordPress content against rubric)

### Phase 3: Admin Dashboard
- [ ] Dashboard page registration and menu setup (main site, super admin only)
- [ ] Course creation form (title, description, objectives repeater)
- [ ] Client-side validation with accessible error handling
- [ ] AJAX handler for course generation
- [ ] Polling endpoint for generation progress
- [ ] Vertical stepper progress UI
- [ ] Success / error / retry states
- [ ] **Course review panel** — consolidated view of all generated material (lessons, plans, activities, assessment)
- [ ] **Publish flow** — publish button, confirmation dialog, immutability enforcement
- [ ] **Learner progress dashboard** — view all learners, per-course and per-learner progress

### Phase 4: Content Generation Pipeline
- [ ] Wire dashboard form → orchestrator → agents
- [ ] Phase 0: Course Describer → create taxonomy term with narrative + titles + work product
- [ ] Per-objective loop: Planner → Writer → Activity Creator → Activity Reviewer → create draft posts
- [ ] Activity Reviewer loop: if `revision_needed`, send suggestions to Activity Creator for one retry
- [ ] Create `lesson_group` tag per objective, assign all lessons from same plan
- [ ] Set post author to 1111 Agent user on all generated posts
- [ ] Markdown-to-block conversion for `post_content`
- [ ] Store all structured meta (mastery criteria, activity, gamification, portfolio contribution, takeaways)
- [ ] Store lesson plan on `lesson_group` term meta (not post meta)
- [ ] Support multi-lesson plans (lessons_per_objective > 1)
- [ ] Phase Final: Assessment Creator → create assessment draft post with portfolio rubric
- [ ] Calculate and store total course XP on course term meta
- [ ] Incremental recovery: skip already-generated objectives on retry
- [ ] Progress transients and polling responses

### Phase 5: Block Editor Integration
- [ ] Content locking: lock all blocks in agent-authored posts (move + remove)
- [ ] Server-side guard: `wp_insert_post_data` filter rejects content changes from non-agent users
- [ ] Block editor notice: "This lesson was generated by Learn. Use the feedback panel..."
- [ ] Sidebar panel via `registerPlugin` / `PluginSidebar`: lesson feedback textarea + regenerate button
- [ ] Sidebar panel for assessment post: assessment display + feedback textarea + regenerate button
- [ ] Activity meta box below content: activity display with type badge, XP, milestone, portfolio contribution, reviewer verdict, feedback textarea + regenerate button
- [ ] Version indicators for lesson, activity, and assessment regeneration counts
- [ ] **Publish immutability**: remove all feedback UI from published course content on main site

### Phase 6: Feedback and Regeneration
- [ ] Course taxonomy term edit screen: narrative display + feedback textarea + regenerate button
- [ ] Lesson group tag edit screen: plan display + feedback textarea + regenerate button
- [ ] AJAX handlers for feedback submission at all five levels (course, plan, lesson, activity, assessment)
- [ ] Regeneration pipeline: re-run appropriate agents with original inputs + feedback
- [ ] Cascading regeneration: course feedback re-runs all agents, plan feedback re-runs planner + downstream
- [ ] Reuse progress stepper UI for regeneration progress
- [ ] WordPress revisions capture before/after diffs on regeneration
- [ ] Clear feedback field after successful regeneration
- [ ] **Learner feedback**: same feedback UI on learner subsites, regenerating their copy only

### Phase 7: Learner Registration and Content Distribution
- [ ] Frontend registration form (shortcode `[learn_register]` or block)
- [ ] Subsite provisioning on registration (`wpmu_create_blog()`)
- [ ] Content copy engine: duplicate published course content from main site to learner subsite
- [ ] Copy all `learn` posts with full meta, `course` and `lesson_group` taxonomy terms
- [ ] Track source linkage via `_1111_source_post_id` and `_1111_source_blog_id` meta
- [ ] Create enrollment record in `_1111_learn_enrollments` table
- [ ] **Learner panel** on learner subsites (replaces course creation UI):
  - [ ] Course catalog: browse published courses from main site
  - [ ] Course selection: copy course content on enrollment
  - [ ] Progress tracking: lessons completed, XP earned, milestones, assessment scores
  - [ ] **Course navigation** (frontend):
    - [ ] Course landing page (shortcode `[learn_course]` or block): narrative overview, lesson list with status
    - [ ] Lesson page: previous/next links, breadcrumb, progress indicator, activity status badge
    - [ ] Lesson group transition headers (objective + narrative connection)
    - [ ] Activity submission UI at bottom of lesson page (Submit for Assessment button)
    - [ ] Inline assessment results display (score, strengths, improvements, recommendation)
    - [ ] Semantic HTML with CSS classes for theme overriding; minimal default styling
- [ ] Prevent learners from accessing course creation UI on their subsite

### Phase 8: Telemetry
- [ ] Telemetry class: event collection, buffering, batch flush
- [ ] learn-service anonymous registration (`/v1/auth/register`)
- [ ] Event transmission (`/v1/events`) with fire-and-forget error handling
- [ ] Opt-in toggle on Settings page with consent dialog
- [ ] Data stripping: ensure API keys, content, and PII are never included
- [ ] Feedback tracking: `feedback_submitted` and `content_regenerated` events (level + agents, never feedback text)
- [ ] Wire telemetry events into orchestrator pipeline (agent_request, agent_response, validation_failure, etc.)
- [ ] Track learner feedback events (regeneration on learner subsites)

### Phase 9: Learner Assessment Pipeline
- [ ] AJAX endpoint for learner submission: accepts `(lesson_post_id, blog_id, post_id)`
- [ ] Content extraction: `switch_to_blog()` → read learner's page/post content → `restore_current_blog()`
- [ ] Wire extracted content + activity rubric + mastery criteria → Activity Assessment Agent
- [ ] Validate assessment output (score, recommendation, rubric_results)
- [ ] Store submission and assessment result in `_1111_learn_submissions` table
- [ ] Content snapshot: save the learner's content at submission time
- [ ] Support resubmission: increment `attempt_number`, run assessment again
- [ ] Learner-facing assessment result display: score, strengths, improvements, rubric results
- [ ] Update enrollment progress (lessons_completed, current_xp) on submission
- [ ] Admin view: see all submissions and assessment results per course/lesson (Learner Progress dashboard)
- [ ] Telemetry: `submission_assessed` event (score, recommendation, attempt_number — never learner content)

### Phase 10: Polish and Quality
- [ ] Accessibility audit: focus management, ARIA, keyboard, contrast
- [ ] Security audit: nonces, capabilities, sanitization, escaping
- [ ] Uninstall cleanup (`uninstall.php` — remove options, term meta, post meta, custom tables, learner subsites optional)
- [ ] Test with real API key across diverse course topics
- [ ] Test full flow: super admin creates course → publishes → learner signs up → selects course → completes activities → gets assessed
- [ ] Evaluate: Do lessons teach? Are activities aligned to mastery criteria? Does the narrative thread hold?
- [ ] Iterate prompts until output quality is consistently good

---

## 20. Success Criteria

1. A **super admin** can generate a complete course (1–4 lessons per objective, 1–8 objectives, plus final assessment) from title + description + objectives in under 5 minutes.
2. Generated lessons follow a visible narrative arc — they read as chapters in the same course, not disconnected topics.
3. Each lesson's content clearly prepares the learner for the associated activity. Backward design is evident.
4. Activities have specific, checkable rubric criteria — not vague "practice what you learned."
5. Every activity contributes to a single portfolio work product built within WordPress. A learner completing the course has a tangible WordPress artifact with a shareable URL.
6. The Activity Reviewer catches rubric-mastery misalignment before content reaches the super admin — measurably reducing the need for feedback on activities.
7. The plugin installs on a WordPress Multisite network with zero configuration beyond entering an API key.
8. All admin and learner UI passes WCAG 2.1 AA.
9. A failed generation can be retried without losing already-generated lessons.
10. Feedback-driven regeneration visibly improves output with each cycle — for both admin (pre-publish) and learners (on their copy).
11. Prompt quality measurably improves over time via the telemetry → PR pipeline: validation failure rates, retry rates, and feedback rates all decrease.
12. The Activity Assessment Agent returns calibrated scores (0.85+ for strong submissions, below 0.5 for weak ones) with actionable, specific feedback referencing actual learner content.
13. A learner can sign up, receive their own WordPress subsite, select a course, and begin learning within 2 minutes of registration.
14. Published courses are immutable — no super admin or agent action can modify published content on the main site. The super admin must create a new course.
15. Learner feedback on their copy triggers regeneration that personalizes their content without affecting the source or other learners.
