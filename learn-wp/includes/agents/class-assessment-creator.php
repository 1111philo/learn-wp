<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Learn_Assessment_Creator extends Learn_Agent_Base {
	protected function prompt_slug() { return 'assessment-creator'; }
	protected function validator_slug() { return 'assessment-creator'; }
}
