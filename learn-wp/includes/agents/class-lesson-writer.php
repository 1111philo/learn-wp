<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class Learn_Lesson_Writer extends Learn_Agent_Base {
	protected function prompt_slug() { return 'lesson-writer'; }
	protected function validator_slug() { return 'lesson-writer'; }
}
