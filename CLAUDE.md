# CLAUDE.md — Learn WordPress Plugin

## Project Overview
- **Goal**: AI-powered course creation and portfolio building on WordPress Multisite.
- **Stack**: Vanilla PHP 8.0+, WordPress 6.7+ (Multisite), Vanilla JS/CSS (no NPM/Webpack).
- **Architecture**: Seven-agent pipeline using Anthropic Messages API.

## Code Style
- **Naming**: `Learn_` prefix for classes, `1111_learn_` for options/meta/hooks.
- **Standards**: Follow WordPress PHP and JS Coding Standards.
- **File Structure**: 
  - `1111-learn.php`: Bootstrap.
  - `includes/`: Core logic (classes).
  - `admin/`: Admin UI files (CSS/JS/Views).
  - `prompts/`: Agent system prompts (Markdown).
  - `assets/`: Logos and static assets.

## Agent Guidelines
1. **Prompts are Data**: Never hardcode prompts in PHP. Load from `prompts/*.md`.
2. **Deterministic Output**: Always validate agent JSON responses against schemas.
3. **Multisite Logic**: Use `is_main_site()` and `switch_to_blog()` appropriately.
4. **Agent User**: All generated content must be authored by the `1111-learn-agent` user.
5. **Content Locking**: Generated content is locked; humans use feedback for changes.
6. **Telemetry**: No PII, API keys, or full course content in telemetry.

## Useful Commands
- `wp post list --post_type=learn`: List all lessons.
- `wp user list --role=1111_learn_agent`: Find agent user.
- `wp db query "SELECT * FROM wp_1111_learn_enrollments"`: View enrollments.
