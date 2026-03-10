<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Learn_Activity_Assessment_Agent extends Learn_Agent_Base {
	protected function prompt_slug() { return 'activity-assessment'; }
	protected function validator_slug() { return 'activity-assessment'; }
}
