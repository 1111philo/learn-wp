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
	<h1><?php esc_html_e( 'Learn Settings', '1111-learn' ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( Learn_Settings::SETTINGS_GROUP );
		do_settings_sections( Learn_Settings::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>

<script>
(function() {
	var field = document.getElementById( '1111_learn_api_key' );
	if ( ! field ) {
		return;
	}

	var toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.className = 'button button-secondary';
	toggle.style.marginLeft = '8px';
	toggle.textContent = '<?php echo esc_js( __( 'Show', '1111-learn' ) ); ?>';
	toggle.setAttribute( 'aria-label', '<?php echo esc_js( __( 'Toggle API key visibility', '1111-learn' ) ); ?>' );

	toggle.addEventListener( 'click', function() {
		if ( field.type === 'password' ) {
			field.type = 'text';
			toggle.textContent = '<?php echo esc_js( __( 'Hide', '1111-learn' ) ); ?>';
		} else {
			field.type = 'password';
			toggle.textContent = '<?php echo esc_js( __( 'Show', '1111-learn' ) ); ?>';
		}
	});

	field.parentNode.insertBefore( toggle, field.nextSibling );
})();
</script>
