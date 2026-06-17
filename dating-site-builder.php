<?php
/**
 * Plugin Name: Dating Site Builder
 * Plugin URI: https://www.clickecommerce.com.au/dating-site-builder
 * Description: Complete dating site solution for WordPress. Turn any WordPress install into a fully functional dating platform with profiles, matching, messaging, and more.
 * Version: 1.7.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Click eCommerce
 * Author URI: https://www.clickecommerce.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dating-site-builder
 * Domain Path: /languages
 *
 * @package DatingSiteBuilder
 *
 * SHORTCODES PROVIDED:
 * - [dsb_register] - User registration form
 * - [dsb_login] - User login form
 * - [dsb_profile_edit] - Edit current user's profile
 * - [dsb_profile_view] - View a user's profile (current user or specified user)
 * - [dsb_member_directory] - Browse/search members
 * - [dsb_matches] - View recommended matches
 * - [dsb_messages] - Inbox and messaging interface
 * - [dsb_likes] - View people you've liked
 *
 * USAGE:
 * 1. Activate the plugin
 * 2. Navigate to "Dating Builder" in admin menu
 * 3. Complete the setup wizard
 * 4. Pages with shortcodes will be automatically created
 * 5. Customize appearance via WordPress Customizer or additional CSS
 *
 * EXTENSIBILITY:
 * - Payment gateway integration: Hook into 'dsb_process_payment' action
 * - Custom matching logic: Filter 'dsb_match_score' 
 * - Additional profile fields: Filter 'dsb_profile_fields'
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'DSB_VERSION', '1.7.3' );
define( 'DSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DSB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DSB_PLUGIN_DIR . 'includes/class-dsb-environment.php';

/**
 * The code that runs during plugin activation.
 */
function activate_dating_site_builder() {
	require_once DSB_PLUGIN_DIR . 'includes/class-dsb-activator.php';
	DSB_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_dating_site_builder() {
	require_once DSB_PLUGIN_DIR . 'includes/class-dsb-deactivator.php';
	DSB_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_dating_site_builder' );
register_deactivation_hook( __FILE__, 'deactivate_dating_site_builder' );

/**
 * The core plugin class.
 */
require DSB_PLUGIN_DIR . 'includes/class-dsb-core.php';

/**
 * Begins execution of the plugin.
 */
function run_dating_site_builder() {
	$plugin = new DSB_Core();
	$plugin->run();
}
run_dating_site_builder();
