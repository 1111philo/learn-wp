<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Learn_Lesson_Planner extends Learn_Agent_Base {
	protected function prompt_slug() { return 'lesson-planner'; }
	protected function validator_slug() { return 'lesson-planner'; }
	protected function model() { return 'claude-haiku-4-5-20251001'; }
}
