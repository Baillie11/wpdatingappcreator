<?php
/**
 * Fired during plugin activation.
 *
 * @package DatingSiteBuilder
 */

class DSB_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Create database tables, add custom roles, and set up initial options.
	 */
	public static function activate() {
		self::create_database_tables();
		self::add_custom_roles();
		self::set_default_options();
		self::schedule_events();
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_database_tables() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		// Messages table
		$table_messages = $wpdb->prefix . 'dsb_messages';
		$sql_messages = "CREATE TABLE IF NOT EXISTS $table_messages (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sender_id bigint(20) UNSIGNED NOT NULL,
			receiver_id bigint(20) UNSIGNED NOT NULL,
			message_text longtext NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			read_at datetime DEFAULT NULL,
			deleted_by_sender tinyint(1) NOT NULL DEFAULT 0,
			deleted_by_receiver tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY sender_id (sender_id),
			KEY receiver_id (receiver_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Likes table
		$table_likes = $wpdb->prefix . 'dsb_likes';
		$sql_likes = "CREATE TABLE IF NOT EXISTS $table_likes (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			target_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_target (user_id, target_id),
			KEY target_id (target_id)
		) $charset_collate;";

		// Blocks table
		$table_blocks = $wpdb->prefix . 'dsb_blocks';
		$sql_blocks = "CREATE TABLE IF NOT EXISTS $table_blocks (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			blocked_user_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_blocked (user_id, blocked_user_id),
			KEY blocked_user_id (blocked_user_id)
		) $charset_collate;";

		// Reports table
		$table_reports = $wpdb->prefix . 'dsb_reports';
		$sql_reports = "CREATE TABLE IF NOT EXISTS $table_reports (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reporter_id bigint(20) UNSIGNED NOT NULL,
			reported_user_id bigint(20) UNSIGNED NOT NULL,
			report_type varchar(50) NOT NULL DEFAULT 'user',
			report_reason text NOT NULL,
			related_id bigint(20) UNSIGNED DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			resolved_at datetime DEFAULT NULL,
			resolved_by bigint(20) UNSIGNED DEFAULT NULL,
			admin_notes text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY reporter_id (reporter_id),
			KEY reported_user_id (reported_user_id),
			KEY status (status)
		) $charset_collate;";

		// Profile views tracking (optional, for future analytics)
		$table_views = $wpdb->prefix . 'dsb_profile_views';
		$sql_views = "CREATE TABLE IF NOT EXISTS $table_views (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			viewer_id bigint(20) UNSIGNED NOT NULL,
			viewed_user_id bigint(20) UNSIGNED NOT NULL,
			viewed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY viewer_id (viewer_id),
			KEY viewed_user_id (viewed_user_id),
			KEY viewed_at (viewed_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_messages );
		dbDelta( $sql_likes );
		dbDelta( $sql_blocks );
		dbDelta( $sql_reports );
		dbDelta( $sql_views );

		// Store database version
		update_option( 'dsb_db_version', '1.0' );
	}

	/**
	 * Add custom user roles.
	 */
	private static function add_custom_roles() {
		// Dating member role (free)
		add_role(
			'dating_member',
			__( 'Dating Member', 'dating-site-builder' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);

		// Premium member role
		add_role(
			'dating_premium',
			__( 'Premium Member', 'dating-site-builder' ),
			array(
				'read'         => true,
				'upload_files' => true,
			)
		);

		// Add capabilities to administrator
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_dating_site' );
			$admin_role->add_cap( 'moderate_dating_profiles' );
			$admin_role->add_cap( 'view_dating_reports' );
		}
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		// Only set if not already configured
		if ( ! get_option( 'dsb_setup_complete' ) ) {
			update_option( 'dsb_setup_complete', false );
			update_option( 'dsb_setup_wizard_step', 1 );
			
			// Redirect to setup wizard on first activation
			set_transient( 'dsb_activation_redirect', true, 30 );
		}
	}

	/**
	 * Schedule cron events.
	 */
	private static function schedule_events() {
		// Daily cleanup of old messages (if needed in future)
		if ( ! wp_next_scheduled( 'dsb_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'dsb_daily_cleanup' );
		}
	}
}
