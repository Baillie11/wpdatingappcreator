<?php
/**
 * The core plugin class.
 *
 * @package DatingSiteBuilder
 */

class DSB_Core {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var DSB_Loader
	 */
	protected $loader;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		// Core classes
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-loader.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-profile-fields.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-matching.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-messaging.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-likes.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-group-chat.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-stats.php';

		// Admin classes
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-admin.php';
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-activator.php';

		// Public classes
		require_once DSB_PLUGIN_DIR . 'includes/class-dsb-frontend.php';

		$this->loader = new DSB_Loader();
	}

	/**
	 * Register all hooks related to admin area.
	 */
	private function define_admin_hooks() {
		$plugin_admin = new DSB_Admin();

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		
		// AJAX handlers for admin
		$this->loader->add_action( 'wp_ajax_dsb_save_wizard_step', $plugin_admin, 'save_wizard_step' );
		$this->loader->add_action( 'wp_ajax_dsb_approve_profile', $plugin_admin, 'approve_profile' );
		$this->loader->add_action( 'wp_ajax_dsb_ban_user', $plugin_admin, 'ban_user' );
		$this->loader->add_action( 'wp_ajax_dsb_resolve_report', $plugin_admin, 'resolve_report' );

		// Manage dating profile photos from the standard WP user screens.
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'render_user_profile_photos' );
		$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'render_user_profile_photos' );
		$this->loader->add_action( 'personal_options_update', $plugin_admin, 'save_user_profile_photos' );
		$this->loader->add_action( 'edit_user_profile_update', $plugin_admin, 'save_user_profile_photos' );

		// Edit / moderate every dating profile field from the WP user screens.
		$this->loader->add_action( 'show_user_profile', $plugin_admin, 'render_user_profile_fields' );
		$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'render_user_profile_fields' );
		$this->loader->add_action( 'personal_options_update', $plugin_admin, 'save_user_profile_fields' );
		$this->loader->add_action( 'edit_user_profile_update', $plugin_admin, 'save_user_profile_fields' );

		// Remove the built-in Website field to discourage off-site advertising.
		$this->loader->add_action( 'admin_head-profile.php', $plugin_admin, 'hide_user_website_field' );
		$this->loader->add_action( 'admin_head-user-edit.php', $plugin_admin, 'hide_user_website_field' );
		$this->loader->add_action( 'admin_head-user-new.php', $plugin_admin, 'hide_user_website_field' );
	}

	/**
	 * Register all hooks related to public-facing functionality.
	 */
	private function define_public_hooks() {
		$plugin_public = new DSB_Frontend();

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Run any pending data migrations for existing installs that were
		// upgraded by simply replacing plugin files (no re-activation).
		$this->loader->add_action( 'plugins_loaded', 'DSB_Activator', 'run_migrations' );

		// Hide the Login page from front-end menus when the user is
		// already logged in. Two filters cover both the theme's page menu
		// fallback and any custom nav menu the site owner may build.
		$this->loader->add_filter( 'get_pages', $plugin_public, 'filter_pages_hide_login' );
		$this->loader->add_filter( 'wp_get_nav_menu_items', $plugin_public, 'filter_nav_menu_items_hide_login' );

		// Suppress the theme's own page/nav menu output on pages rendered
		// by this plugin's shortcodes. The plugin ships its own top
		// navigation bar so the theme's menu would just duplicate it.
		$this->loader->add_filter( 'wp_page_menu', $plugin_public, 'filter_hide_theme_page_menu' );
		$this->loader->add_filter( 'pre_wp_nav_menu', $plugin_public, 'filter_hide_theme_nav_menu' );

		// Tag plugin pages with a body class and suppress the theme's
		// page title so the plugin banner sits flush at the top.
		$this->loader->add_filter( 'body_class', $plugin_public, 'filter_body_class_dsb_page' );
		$this->loader->add_filter( 'the_title', $plugin_public, 'filter_remove_page_title', 10, 2 );

		// Register shortcodes
		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
		
		// AJAX handlers for public
		$this->loader->add_action( 'wp_ajax_dsb_register_user', $plugin_public, 'ajax_register_user' );
		$this->loader->add_action( 'wp_ajax_nopriv_dsb_register_user', $plugin_public, 'ajax_register_user' );
		
		$this->loader->add_action( 'wp_ajax_dsb_login_user', $plugin_public, 'ajax_login_user' );
		$this->loader->add_action( 'wp_ajax_nopriv_dsb_login_user', $plugin_public, 'ajax_login_user' );
		
		$this->loader->add_action( 'wp_ajax_dsb_forgot_password', $plugin_public, 'ajax_forgot_password' );
		$this->loader->add_action( 'wp_ajax_nopriv_dsb_forgot_password', $plugin_public, 'ajax_forgot_password' );
		
		$this->loader->add_action( 'wp_ajax_dsb_update_profile', $plugin_public, 'ajax_update_profile' );
		$this->loader->add_action( 'wp_ajax_dsb_upload_photo', $plugin_public, 'ajax_upload_photo' );
		$this->loader->add_action( 'wp_ajax_dsb_delete_photo', $plugin_public, 'ajax_delete_photo' );
		$this->loader->add_action( 'wp_ajax_dsb_set_main_photo', $plugin_public, 'ajax_set_main_photo' );
		
		// Messaging AJAX
		$messaging = new DSB_Messaging();
		$this->loader->add_action( 'wp_ajax_dsb_send_message', $messaging, 'ajax_send_message' );
		$this->loader->add_action( 'wp_ajax_dsb_get_messages', $messaging, 'ajax_get_messages' );
		$this->loader->add_action( 'wp_ajax_dsb_mark_read', $messaging, 'ajax_mark_read' );
		$this->loader->add_action( 'wp_ajax_dsb_block_user', $messaging, 'ajax_block_user' );
		$this->loader->add_action( 'wp_ajax_dsb_report_user', $messaging, 'ajax_report_user' );
		
		// Likes AJAX
		$likes = new DSB_Likes();
		$this->loader->add_action( 'wp_ajax_dsb_toggle_like', $likes, 'ajax_toggle_like' );
		$this->loader->add_action( 'wp_ajax_dsb_get_likes', $likes, 'ajax_get_likes' );
		
		// Group Chat AJAX
		$group_chat = new DSB_Group_Chat();
		$this->loader->add_action( 'wp_ajax_dsb_group_chat_send', $group_chat, 'ajax_send_message' );
		$this->loader->add_action( 'wp_ajax_dsb_group_chat_get', $group_chat, 'ajax_get_messages' );
		$this->loader->add_action( 'wp_ajax_dsb_group_chat_online', $group_chat, 'ajax_get_online_users' );
	}

	/**
	 * Run the loader to execute all hooks.
	 */
	public function run() {
		$this->loader->run();
	}
}
