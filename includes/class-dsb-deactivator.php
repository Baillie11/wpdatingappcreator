<?php
/**
 * Fired during plugin deactivation.
 *
 * @package DatingSiteBuilder
 */

class DSB_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clear scheduled events and flush rewrite rules.
	 */
	public static function deactivate() {
		// Clear scheduled events
		$timestamp = wp_next_scheduled( 'dsb_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'dsb_daily_cleanup' );
		}

		// Flush rewrite rules
		flush_rewrite_rules();
	}
}
