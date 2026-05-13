<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package DatingSiteBuilder
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables
$tables = array(
	$wpdb->prefix . 'dsb_messages',
	$wpdb->prefix . 'dsb_likes',
	$wpdb->prefix . 'dsb_blocks',
	$wpdb->prefix . 'dsb_reports',
	$wpdb->prefix . 'dsb_profile_views',
	$wpdb->prefix . 'dsb_photo_access',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Remove plugin options
$options = array(
	'dsb_site_type',
	'dsb_minimum_age',
	'dsb_maximum_age',
	'dsb_require_email_verification',
	'dsb_require_profile_approval',
	'dsb_enabled_field_groups',
	'dsb_allow_custom_gender',
	'dsb_allow_multiple_interests',
	'dsb_photo_privacy_mode',
	'dsb_enable_private_photos',
	'dsb_age_gate_enabled',
	'dsb_matching_mode',
	'dsb_require_mutual_like',
	'dsb_register_page',
	'dsb_login_page',
	'dsb_profile_edit_page',
	'dsb_profile_view_page',
	'dsb_member_directory_page',
	'dsb_matches_page',
	'dsb_messages_page',
	'dsb_likes_page',
	'dsb_wizard_completed',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up user meta (optional - only if you want to remove all dating profile data)
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'dsb_%'" );

// Remove custom user roles (optional - commented out by default)
// remove_role( 'dating_member' );
// remove_role( 'dating_premium' );
