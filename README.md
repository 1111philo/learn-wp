# Learn — WordPress Plugin

AI-powered course creation, portfolio building, and assessment for WordPress Multisite.

## Overview

Learn is a WordPress Multisite plugin that uses a seven-agent AI pipeline to generate complete courses. Super admins create courses on the main site; learners sign up, get their own subsite, and learn by building real WordPress content.

## Key Features

- **Seven-Agent Pipeline**: Narrative-driven course generation (powered by Anthropic Claude).
- **Portfolio-First**: Learners build real WordPress content (pages, posts) as their portfolio.
- **Activity Assessment**: AI-powered grading of learner-created WordPress content.
- **Multisite Native**: Automatic subsite provisioning and content distribution.
- **Feedback-Driven**: Content is refined through AI feedback loops rather than direct editing.
- **Privacy & Telemetry**: Opt-in anonymous telemetry for continuous prompt improvement.

## Requirements

- WordPress 6.7+
- WordPress Multisite enabled
- PHP 8.0+
- Anthropic API Key

## Installation

1. Upload the `1111-learn` folder to `/wp-content/plugins/`.
2. Network Activate the plugin.
3. Go to **Learn > Settings** on the main site and enter your Anthropic API Key.

## Development

- **No Build Step**: Use vanilla PHP, JS, and CSS.
- **Prompts**: Editable in `prompts/*.md`.
- **Multisite**: Always test in a Multisite environment.

## License

GPL v2 or later.
