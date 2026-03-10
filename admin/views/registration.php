<?php
/**
 * Learner registration form template.
 *
 * @package Learn
 *
 * @var WP_Error $errors Registration errors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$username_value = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
$email_value    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
?>
<div class="1111-learn-registration">
	<h2><?php esc_html_e( 'Create Your Learning Site', '1111-learn' ); ?></h2>

	<?php if ( $errors->has_errors() ) : ?>
		<div class="1111-learn-errors" role="alert" aria-live="assertive">
			<ul>
				<?php foreach ( $errors->get_error_messages() as $message ) : ?>
					<li><?php echo esc_html( $message ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<form method="post" action="" novalidate>
		<?php wp_nonce_field( '1111_learn_register', '1111_learn_register_nonce' ); ?>

		<div class="1111-learn-form-field">
			<label for="1111-learn-username">
				<?php esc_html_e( 'Username', '1111-learn' ); ?>
				<span aria-hidden="true">*</span>
			</label>
			<input
				type="text"
				id="1111-learn-username"
				name="username"
				value="<?php echo esc_attr( $username_value ); ?>"
				required
				minlength="3"
				maxlength="60"
				autocomplete="username"
				aria-required="true"
				aria-describedby="1111-learn-username-desc<?php echo $errors->get_error_message( 'username' ) ? ' 1111-learn-username-error' : ''; ?>"
			/>
			<p class="1111-learn-field-desc" id="1111-learn-username-desc">
				<?php esc_html_e( 'Between 3 and 60 characters. This will also be your site address.', '1111-learn' ); ?>
			</p>
			<?php if ( $errors->get_error_message( 'username' ) ) : ?>
				<p class="1111-learn-field-error" id="1111-learn-username-error" role="alert">
					<?php echo esc_html( $errors->get_error_message( 'username' ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="1111-learn-form-field">
			<label for="1111-learn-email">
				<?php esc_html_e( 'Email Address', '1111-learn' ); ?>
				<span aria-hidden="true">*</span>
			</label>
			<input
				type="email"
				id="1111-learn-email"
				name="email"
				value="<?php echo esc_attr( $email_value ); ?>"
				required
				autocomplete="email"
				aria-required="true"
				aria-describedby="1111-learn-email-desc<?php echo $errors->get_error_message( 'email' ) ? ' 1111-learn-email-error' : ''; ?>"
			/>
			<p class="1111-learn-field-desc" id="1111-learn-email-desc">
				<?php esc_html_e( 'A valid email address.', '1111-learn' ); ?>
			</p>
			<?php if ( $errors->get_error_message( 'email' ) ) : ?>
				<p class="1111-learn-field-error" id="1111-learn-email-error" role="alert">
					<?php echo esc_html( $errors->get_error_message( 'email' ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="1111-learn-form-field">
			<label for="1111-learn-password">
				<?php esc_html_e( 'Password', '1111-learn' ); ?>
				<span aria-hidden="true">*</span>
			</label>
			<input
				type="password"
				id="1111-learn-password"
				name="password"
				required
				minlength="8"
				autocomplete="new-password"
				aria-required="true"
				aria-describedby="1111-learn-password-desc<?php echo $errors->get_error_message( 'password' ) ? ' 1111-learn-password-error' : ''; ?>"
			/>
			<p class="1111-learn-field-desc" id="1111-learn-password-desc">
				<?php esc_html_e( 'At least 8 characters.', '1111-learn' ); ?>
			</p>
			<?php if ( $errors->get_error_message( 'password' ) ) : ?>
				<p class="1111-learn-field-error" id="1111-learn-password-error" role="alert">
					<?php echo esc_html( $errors->get_error_message( 'password' ) ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="1111-learn-form-submit">
			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Create My Learning Site', '1111-learn' ); ?>
			</button>
		</div>
	</form>
</div>
