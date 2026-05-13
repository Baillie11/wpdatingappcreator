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
		self::run_migrations();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Run any pending data migrations.
	 *
	 * Executed on activation and also on every request via a
	 * `plugins_loaded` hook so existing installs upgrade automatically
	 * when the plugin files are updated without re-activation.
	 */
	public static function run_migrations() {
		$current = get_option( 'dsb_db_version', '1.0' );

		if ( version_compare( $current, '1.1', '<' ) ) {
			self::migrate_reparent_profile_children();
			update_option( 'dsb_db_version', '1.1' );
		}

		if ( version_compare( $current, '1.2', '<' ) ) {
			self::migrate_ensure_plugin_pages();
			update_option( 'dsb_db_version', '1.2' );
		}

		if ( version_compare( $current, '1.3', '<' ) ) {
			self::migrate_dedupe_plugin_shortcodes();
			update_option( 'dsb_db_version', '1.3' );
		}

		if ( version_compare( $current, '1.4', '<' ) ) {
			self::migrate_restore_member_approval();
			update_option( 'dsb_db_version', '1.4' );
		}

		if ( version_compare( $current, '1.5', '<' ) ) {
			self::migrate_create_photo_access_table();
			update_option( 'dsb_db_version', '1.5' );
		}

		// Self-heal sweep: re-approve any dating member that is
		// somehow missing the approval flag. Throttled to once per
		// hour to keep front-end requests cheap.
		if ( false === get_transient( 'dsb_member_approval_sweep' ) ) {
			self::migrate_restore_member_approval();
			set_transient( 'dsb_member_approval_sweep', 1, HOUR_IN_SECONDS );
		}

		// Self-heal sweep: ensure every dsb_*_page option points at
		// a valid, published page. Catches situations where the
		// options were accidentally cleared (e.g. import, DB reset,
		// option cleanup plugin). Throttled to once per hour.
		if ( false === get_transient( 'dsb_page_options_sweep' ) ) {
			self::migrate_ensure_plugin_pages();
			set_transient( 'dsb_page_options_sweep', 1, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Migration / self-heal: ensure every dating member has a
	 * `dsb_profile_approved` meta value.
	 *
	 * Members that were previously approved but somehow lost the
	 * flag (e.g. external user-meta cleanup, partial user-edit save
	 * before the form sentinel was added) get auto-approved again
	 * unless they are currently banned. New members that registered
	 * while admin approval was required keep their pending state
	 * because they already have the meta set to '0'.
	 */
	public static function migrate_restore_member_approval() {
		$members = get_users( array(
			'role__in' => array( 'dating_member', 'dating_premium' ),
			'fields'   => 'ID',
			'number'   => -1,
		) );

		if ( empty( $members ) ) {
			return;
		}

		$require_approval = (bool) get_option( 'dsb_require_profile_approval', false );

		foreach ( $members as $user_id ) {
			$user_id = (int) $user_id;
			if ( ! $user_id ) {
				continue;
			}
			// Skip banned users - they should remain blocked.
			if ( get_user_meta( $user_id, 'dsb_banned', true ) ) {
				continue;
			}
			$existing = get_user_meta( $user_id, 'dsb_profile_approved', true );
			if ( '' !== $existing && null !== $existing ) {
				// Meta already set (either '1' or '0'). Leave it alone.
				continue;
			}
			// Missing flag: auto-approve unless the site requires
			// admin approval AND the user has not yet completed any
			// profile data (treat them as a fresh signup).
			if ( $require_approval ) {
				$has_profile_data = get_user_meta( $user_id, 'dsb_photos', true )
					|| get_user_meta( $user_id, 'dsb_date_of_birth', true )
					|| get_user_meta( $user_id, 'dsb_gender', true );
				if ( ! $has_profile_data ) {
					update_user_meta( $user_id, 'dsb_profile_approved', '0' );
					continue;
				}
			}
			update_user_meta( $user_id, 'dsb_profile_approved', '1' );
		}
	}

	/**
	 * Migration: make sure every page the plugin relies on exists,
	 * carries its expected shortcode content, and has its option ID
	 * pointing at the correct post. Self-heals situations where:
	 *   - the dsb_*_page option was cleared or deleted
	 *   - the option points at a post that was trashed
	 *   - the page content was edited and no longer contains the
	 *     required shortcode (so links kept bouncing back to Login).
	 */
	public static function migrate_ensure_plugin_pages() {
		$pages = array(
			'register'        => array( 'title' => 'Register',          'shortcode' => 'dsb_register',        'option' => 'dsb_register_page' ),
			'login'           => array( 'title' => 'Login',             'shortcode' => 'dsb_login',           'option' => 'dsb_login_page' ),
			'profile'         => array( 'title' => 'My Profile',        'shortcode' => 'dsb_profile_view',    'option' => 'dsb_profile_view_page' ),
			'forgot-password' => array( 'title' => 'Forgot Password',   'shortcode' => 'dsb_forgot_password', 'option' => 'dsb_forgot_password_page', 'parent' => 'dsb_profile_view_page' ),
			'profile-edit'    => array( 'title' => 'Edit Profile',      'shortcode' => 'dsb_profile_edit',    'option' => 'dsb_profile_edit_page',    'parent' => 'dsb_profile_view_page' ),
			'members'         => array( 'title' => 'Browse Members',    'shortcode' => 'dsb_member_directory','option' => 'dsb_member_directory_page' ),
			'matches'         => array( 'title' => 'Your Matches',      'shortcode' => 'dsb_matches',         'option' => 'dsb_matches_page' ),
			'messages'        => array( 'title' => 'Messages',          'shortcode' => 'dsb_messages',        'option' => 'dsb_messages_page' ),
			'likes'           => array( 'title' => 'Likes & Favorites', 'shortcode' => 'dsb_likes',           'option' => 'dsb_likes_page',           'parent' => 'dsb_profile_view_page' ),
			'chat'            => array( 'title' => 'Community Chat',    'shortcode' => 'dsb_group_chat',      'option' => 'dsb_group_chat_page' ),
		);

		// First pass: ensure each page exists and its option is set to
		// a published post whose content contains the expected shortcode.
		foreach ( $pages as $slug => $spec ) {
			$content  = '[' . $spec['shortcode'] . ']';
			$option   = $spec['option'];
			$page_id  = (int) get_option( $option );
			$existing = $page_id ? get_post( $page_id ) : null;

			// If the stored ID is missing or the post was trashed /
			// deleted, look up by slug as a fallback.
			if ( ! $existing || 'page' !== $existing->post_type || 'trash' === $existing->post_status ) {
				$found = get_page_by_path( $slug );
				if ( $found && 'page' === $found->post_type && 'trash' !== $found->post_status ) {
					$existing = $found;
					$page_id  = $existing->ID;
				}
			}

			if ( ! $existing ) {
				// Recreate the missing page.
				$page_id = wp_insert_post( array(
					'post_title'   => $spec['title'],
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $slug,
				) );
				if ( is_wp_error( $page_id ) || ! $page_id ) {
					continue;
				}
			} else {
				// Ensure the page is published and still contains its
				// required shortcode.
				//
				// NOTE: we deliberately use a regex string check rather
				// than has_shortcode(). has_shortcode() returns false
				// when the shortcode tag isn't registered yet, and this
				// migration runs on plugins_loaded which fires before
				// `init` (where dsb_* shortcodes are registered). Using
				// has_shortcode() here previously caused the plugin to
				// double-up shortcodes on every page.
				$updates = array();
				if ( 'publish' !== $existing->post_status ) {
					$updates['post_status'] = 'publish';
				}
				if ( ! self::content_contains_shortcode( (string) $existing->post_content, $spec['shortcode'] ) ) {
					$updates['post_content'] = trim( $existing->post_content . "\n\n" . $content );
				}
				if ( ! empty( $updates ) ) {
					$updates['ID'] = $existing->ID;
					wp_update_post( $updates );
				}
			}

			update_option( $option, (int) $page_id );
		}

		// Second pass: re-assert parent/child relationships (in case
		// the earlier 1.1 migration ran before the page existed).
		foreach ( $pages as $slug => $spec ) {
			if ( empty( $spec['parent'] ) ) {
				continue;
			}
			$child_id  = (int) get_option( $spec['option'] );
			$parent_id = (int) get_option( $spec['parent'] );
			if ( ! $child_id || ! $parent_id ) {
				continue;
			}
			$child = get_post( $child_id );
			if ( $child && (int) $child->post_parent !== $parent_id ) {
				wp_update_post( array(
					'ID'          => $child_id,
					'post_parent' => $parent_id,
				) );
			}
		}
	}

	/**
	 * Detect a shortcode in raw post content using a regex match.
	 *
	 * Safe to call on hooks earlier than `init` (unlike WordPress's
	 * own has_shortcode() which depends on the shortcode being
	 * registered).
	 */
	private static function content_contains_shortcode( $content, $shortcode ) {
		if ( '' === $content || false === strpos( $content, '[' ) ) {
			return false;
		}
		return (bool) preg_match(
			'/\[' . preg_quote( $shortcode, '/' ) . '(?:[\s\]\/])/',
			$content
		);
	}

	/**
	 * Migration: collapse duplicate dsb_* shortcodes that the broken
	 * 1.2 migration created. For each plugin shortcode, if it appears
	 * more than once on its target page, keep only the first
	 * occurrence (preserving any user-specified attributes) and strip
	 * the rest.
	 */
	public static function migrate_dedupe_plugin_shortcodes() {
		$shortcode_options = array(
			'dsb_register'         => 'dsb_register_page',
			'dsb_login'            => 'dsb_login_page',
			'dsb_profile_view'     => 'dsb_profile_view_page',
			'dsb_forgot_password'  => 'dsb_forgot_password_page',
			'dsb_profile_edit'     => 'dsb_profile_edit_page',
			'dsb_member_directory' => 'dsb_member_directory_page',
			'dsb_matches'          => 'dsb_matches_page',
			'dsb_messages'         => 'dsb_messages_page',
			'dsb_likes'            => 'dsb_likes_page',
			'dsb_group_chat'       => 'dsb_group_chat_page',
		);

		foreach ( $shortcode_options as $shortcode => $option_name ) {
			$page_id = (int) get_option( $option_name );
			if ( ! $page_id ) {
				continue;
			}

			$post = get_post( $page_id );
			if ( ! $post || 'page' !== $post->post_type ) {
				continue;
			}

			$pattern = '/\[' . preg_quote( $shortcode, '/' ) . '(?:\s+[^\]]*)?\]/';
			if ( ! preg_match_all( $pattern, $post->post_content, $matches ) ) {
				continue;
			}
			if ( count( $matches[0] ) < 2 ) {
				continue;
			}

			$first_occurrence = $matches[0][0];
			$cleaned          = preg_replace( $pattern, '', $post->post_content );
			$cleaned          = preg_replace( "/\n{3,}/", "\n\n", (string) $cleaned );
			$cleaned          = trim( (string) $cleaned );

			$new_content = '' === $cleaned
				? $first_occurrence
				: $cleaned . "\n\n" . $first_occurrence;

			if ( $new_content !== $post->post_content ) {
				wp_update_post( array(
					'ID'           => $page_id,
					'post_content' => $new_content,
				) );
			}
		}
	}

	/**
	 * Migration: create the dsb_photo_access table for private photo
	 * album access requests. Also runs on activation via
	 * create_database_tables() but the migration ensures existing
	 * installs that upgrade without re-activating still get the table.
	 */
	private static function migrate_create_photo_access_table() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table = $wpdb->prefix . 'dsb_photo_access';
		$sql = "CREATE TABLE IF NOT EXISTS $table (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			requester_id bigint(20) UNSIGNED NOT NULL,
			owner_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY requester_owner (requester_id, owner_id),
			KEY owner_id (owner_id),
			KEY status (status)
		) $charset_collate;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Migration: make Edit Profile, Forgot Password and Likes &
	 * Favorites children of the My Profile page so the theme's page
	 * menu collapses them into a submenu.
	 */
	private static function migrate_reparent_profile_children() {
		$parent_id = (int) get_option( 'dsb_profile_view_page' );

		if ( ! $parent_id || ! get_post( $parent_id ) ) {
			return;
		}

		$child_options = array(
			'dsb_profile_edit_page',
			'dsb_forgot_password_page',
			'dsb_likes_page',
		);

		foreach ( $child_options as $option_name ) {
			$child_id = (int) get_option( $option_name );
			if ( ! $child_id ) {
				continue;
			}

			$child = get_post( $child_id );
			if ( ! $child || (int) $child->post_parent === $parent_id ) {
				continue;
			}

			wp_update_post( array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			) );
		}
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

		// Group chat messages table
		$table_group_chat = $wpdb->prefix . 'dsb_group_chat';
		$sql_group_chat = "CREATE TABLE IF NOT EXISTS $table_group_chat (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			message_text text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Photo access requests table (private photo albums)
		$table_photo_access = $wpdb->prefix . 'dsb_photo_access';
		$sql_photo_access = "CREATE TABLE IF NOT EXISTS $table_photo_access (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			requester_id bigint(20) UNSIGNED NOT NULL,
			owner_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY requester_owner (requester_id, owner_id),
			KEY owner_id (owner_id),
			KEY status (status)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_messages );
		dbDelta( $sql_likes );
		dbDelta( $sql_blocks );
		dbDelta( $sql_reports );
		dbDelta( $sql_views );
		dbDelta( $sql_group_chat );
		dbDelta( $sql_photo_access );

		// Store database version - only seed on fresh installs so
		// reactivating the plugin does not roll already-applied
		// migrations back to 1.0 and re-run them.
		if ( false === get_option( 'dsb_db_version' ) ) {
			add_option( 'dsb_db_version', '1.0' );
		}
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
