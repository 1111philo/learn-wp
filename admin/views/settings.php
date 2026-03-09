<?php
/**
 * Settings page template.
 *
 * @package Learn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Learn Settings', 'learn' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'learn_settings' );
		do_settings_sections( 'learn-settings' );
		submit_button();
		?>
	</form>
</div>
