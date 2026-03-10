<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Learn_Activity_Reviewer extends Learn_Agent_Base {
	protected function prompt_slug() { return 'activity-reviewer'; }
	protected function validator_slug() { return 'activity-reviewer'; }
	protected function model() { return 'claude-haiku-4-5-20251001'; }
}
