<?php
/**
 * Activation and install tasks.
 *
 * @package Learn_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Learn_Installer {
	/**
	 * Agent username.
	 */
	const AGENT_USERNAME = '1111-learn-agent';

	/**
	 * Activation routine.
	 *
	 * @param bool $network_wide Network-wide activation.
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( ! is_multisite() || ! $network_wide ) {
			return;
		}

		self::create_role();
		self::create_agent_user();
		self::create_tables();
		update_site_option( '_1111_learn_installed_version', LEARN_WP_VERSION );
	}

	/**
	 * Create role used by agent user.
	 *
	 * @return void
	 */
	private static function create_role() {
		add_role(
			'1111_learn_agent',
			'1111 Learn Agent',
			array(
				'read'                        => true,
				'edit_learn_posts'            => true,
				'edit_published_learn_posts'  => true,
				'publish_learn_posts'         => true,
				'delete_learn_posts'          => true,
			)
		);
	}

	/**
	 * Create or update system agent user.
	 *
	 * @return void
	 */
	private static function create_agent_user() {
		$user = get_user_by( 'login', self::AGENT_USERNAME );
		if ( $user ) {
			$user->set_role( '1111_learn_agent' );
			update_site_option( '_1111_learn_agent_user_id', $user->ID );
			return;
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::AGENT_USERNAME,
				'user_pass'    => wp_generate_password( 48, true, true ),
				'user_email'   => 'agent@1111-learn.local',
				'display_name' => '1111',
				'role'         => '1111_learn_agent',
			)
		);

		if ( ! is_wp_error( $user_id ) ) {
			update_site_option( '_1111_learn_agent_user_id', $user_id );
		}
	}

	/**
	 * Create custom tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = $wpdb->base_prefix . '1111_learn_submissions';
		$enrollments     = $wpdb->base_prefix . '1111_learn_enrollments';

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			lesson_post_id bigint(20) unsigned NOT NULL,
			submission_post_id bigint(20) unsigned NOT NULL,
			attempt_number int(11) unsigned NOT NULL DEFAULT 1,
			score decimal(5,4) NOT NULL DEFAULT 0,
			recommendation varchar(20) NOT NULL DEFAULT 'revise',
			assessment_json longtext NOT NULL,
			content_snapshot longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY blog_user (blog_id, user_id),
			KEY lesson_post (lesson_post_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_enrollments = "CREATE TABLE {$enrollments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			source_course_term_id bigint(20) unsigned NOT NULL,
			source_blog_id bigint(20) unsigned NOT NULL,
			lessons_completed int(11) unsigned NOT NULL DEFAULT 0,
			current_xp int(11) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY blog_user_course (blog_id, user_id, source_course_term_id),
			KEY source_course (source_blog_id, source_course_term_id)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_enrollments );
	}
}
