<?php
/**
 * Shared statistics helper.
 *
 * Provides one source of truth for the metrics shown on the admin
 * Dashboard, the [dsb_site_stats] shortcode, and the slim banner
 * above the Browse Members directory.
 *
 * @package DatingSiteBuilder
 */

class DSB_Stats {

	/**
	 * Transient key used to cache computed values for a short window
	 * so high-traffic public pages do not hammer the database.
	 */
	const CACHE_KEY = 'dsb_public_stats_cache';

	/**
	 * Cache lifetime in seconds. Short on purpose so live-activity
	 * counters (Online Now, In Chat Room) stay reasonably fresh.
	 */
	const CACHE_TTL = 60;

	/**
	 * Definitions for every stat the plugin can surface.
	 *
	 * Each entry has:
	 *   - label          The human-readable card label.
	 *   - icon           Emoji shown in the card.
	 *   - tone           CSS modifier slug used by both the admin
	 *                    dashboard and the public stats grid.
	 *   - sub            Short descriptive sub-label.
	 *   - public_default Whether the stat is enabled for public
	 *                    display on a fresh install.
	 *   - admin_only     Whether the stat must never be shown
	 *                    publicly (moderation counters).
	 *
	 * @return array
	 */
	public static function get_definitions() {
		return array(
			'online_now' => array(
				'label'          => __( 'Online Now', 'dating-site-builder' ),
				'icon'           => '🟢',
				'tone'           => 'live',
				'sub'            => __( 'Active in the last 5 minutes', 'dating-site-builder' ),
				'public_default' => true,
			),
			'in_chat_room' => array(
				'label'          => __( 'In Chat Room', 'dating-site-builder' ),
				'icon'           => '💬',
				'tone'           => 'chat',
				'sub'            => __( 'Posted a chat message in the last 15 minutes', 'dating-site-builder' ),
				'public_default' => true,
			),
			'messages_today' => array(
				'label'          => __( 'Messages Today', 'dating-site-builder' ),
				'icon'           => '✉️',
				'tone'           => 'messages',
				'sub'            => __( 'Direct messages sent since midnight', 'dating-site-builder' ),
				'public_default' => false,
			),
			'total_members' => array(
				'label'          => __( 'Total Members', 'dating-site-builder' ),
				'icon'           => '👥',
				'tone'           => 'members',
				'sub'            => __( 'All approved & pending dating accounts', 'dating-site-builder' ),
				'public_default' => true,
			),
			'premium_members' => array(
				'label'          => __( 'Premium Members', 'dating-site-builder' ),
				'icon'           => '⭐',
				'tone'           => 'premium',
				'sub'            => __( 'Users on the dating_premium role', 'dating-site-builder' ),
				'public_default' => false,
			),
			'new_this_week' => array(
				'label'          => __( 'New This Week', 'dating-site-builder' ),
				'icon'           => '🆕',
				'tone'           => 'new',
				'sub'            => __( 'Members registered in the last 7 days', 'dating-site-builder' ),
				'public_default' => true,
			),
			'total_likes' => array(
				'label'          => __( 'Total Likes', 'dating-site-builder' ),
				'icon'           => '❤️',
				'tone'           => 'likes',
				'sub'            => __( 'Lifetime number of profile likes', 'dating-site-builder' ),
				'public_default' => false,
			),
			'mutual_matches' => array(
				'label'          => __( 'Mutual Matches', 'dating-site-builder' ),
				'icon'           => '💕',
				'tone'           => 'matches',
				'sub'            => __( 'Pairs where both members liked each other', 'dating-site-builder' ),
				'public_default' => true,
			),
			'profile_views' => array(
				'label'          => __( 'Profile Views', 'dating-site-builder' ),
				'icon'           => '👀',
				'tone'           => 'views',
				'sub'            => __( 'Lifetime profile page views', 'dating-site-builder' ),
				'public_default' => false,
			),
			'pending_approvals' => array(
				'label'          => __( 'Pending Approvals', 'dating-site-builder' ),
				'icon'           => '⏳',
				'tone'           => 'warning',
				'sub'            => __( 'Members awaiting admin approval', 'dating-site-builder' ),
				'public_default' => false,
				'admin_only'     => true,
			),
			'pending_reports' => array(
				'label'          => __( 'Pending Reports', 'dating-site-builder' ),
				'icon'           => '🚩',
				'tone'           => 'danger',
				'sub'            => __( 'User-submitted reports awaiting review', 'dating-site-builder' ),
				'public_default' => false,
				'admin_only'     => true,
			),
			'total_messages' => array(
				'label'          => __( 'Messages Sent', 'dating-site-builder' ),
				'icon'           => '💌',
				'tone'           => 'messages',
				'sub'            => __( 'Lifetime direct-message volume', 'dating-site-builder' ),
				'public_default' => false,
			),
		);
	}

	/**
	 * Compute every stat value in one pass.
	 *
	 * Cached via a short-lived transient so the front-end can render
	 * the directory banner / shortcode on every page hit without
	 * re-running the SQL each time.
	 *
	 * @param bool $force_refresh Bypass and rebuild the cache.
	 * @return array key => integer value
	 */
	public static function compute_all( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;

		$messages_table   = $wpdb->prefix . 'dsb_messages';
		$likes_table      = $wpdb->prefix . 'dsb_likes';
		$reports_table    = $wpdb->prefix . 'dsb_reports';
		$views_table      = $wpdb->prefix . 'dsb_profile_views';
		$group_chat_table = $wpdb->prefix . 'dsb_group_chat';

		// Member counts via WordPress' role aggregation.
		$user_count    = count_users();
		$member_count  = 0;
		$premium_count = 0;
		if ( isset( $user_count['avail_roles']['dating_member'] ) ) {
			$member_count += (int) $user_count['avail_roles']['dating_member'];
		}
		if ( isset( $user_count['avail_roles']['dating_premium'] ) ) {
			$member_count  += (int) $user_count['avail_roles']['dating_premium'];
			$premium_count = (int) $user_count['avail_roles']['dating_premium'];
		}

		// Live activity windows are stored via current_time('mysql')
		// (site time), so compare in site time too.
		$five_min_ago_local = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );
		$online_now         = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
			 WHERE meta_key = 'dsb_last_activity' AND meta_value >= %s",
			$five_min_ago_local
		) );

		$fifteen_min_ago_local = date( 'Y-m-d H:i:s', strtotime( '-15 minutes' ) );
		$in_chat_room          = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM $group_chat_table WHERE created_at >= %s",
			$fifteen_min_ago_local
		) );

		$today_start    = date( 'Y-m-d 00:00:00' );
		$messages_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $messages_table WHERE created_at >= %s",
			$today_start
		) );

		$total_messages  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table" );
		$total_likes     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $likes_table" );
		$pending_reports = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'" );
		$profile_views   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $views_table" );

		// Mutual matches: pairs where A likes B AND B likes A. The self
		// join counts each pair twice, so divide by 2.
		$mutual_matches = (int) $wpdb->get_var(
			"SELECT COUNT(*) / 2 FROM $likes_table l1
			 INNER JOIN $likes_table l2
			   ON l1.user_id = l2.target_id
			  AND l1.target_id = l2.user_id"
		);

		$pending_approvals = count( get_users( array(
			'role__in'   => array( 'dating_member', 'dating_premium' ),
			'fields'     => 'ID',
			'meta_query' => array(
				array( 'key' => 'dsb_profile_approved', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'dsb_banned',           'compare' => 'NOT EXISTS' ),
			),
		) ) );

		$new_this_week = count( get_users( array(
			'role__in'   => array( 'dating_member', 'dating_premium' ),
			'fields'     => 'ID',
			'date_query' => array( array( 'after' => '7 days ago' ) ),
		) ) );

		$values = array(
			'online_now'        => $online_now,
			'in_chat_room'      => $in_chat_room,
			'messages_today'    => $messages_today,
			'total_members'     => $member_count,
			'premium_members'   => $premium_count,
			'new_this_week'     => $new_this_week,
			'total_likes'       => $total_likes,
			'mutual_matches'    => $mutual_matches,
			'profile_views'     => $profile_views,
			'pending_approvals' => $pending_approvals,
			'pending_reports'   => $pending_reports,
			'total_messages'    => $total_messages,
		);

		set_transient( self::CACHE_KEY, $values, self::CACHE_TTL );

		return $values;
	}

	/**
	 * Default set of public stat keys (where `public_default` is true
	 * and `admin_only` is unset).
	 *
	 * @return string[]
	 */
	public static function get_default_public_keys() {
		$keys = array();
		foreach ( self::get_definitions() as $key => $def ) {
			if ( ! empty( $def['public_default'] ) && empty( $def['admin_only'] ) ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Public stat keys the admin currently has enabled. Falls back to
	 * the default set when the option has never been saved.
	 *
	 * @return string[]
	 */
	public static function get_enabled_public_keys() {
		$stored = get_option( 'dsb_public_stats_enabled', null );

		if ( null === $stored ) {
			return self::get_default_public_keys();
		}

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$defs    = self::get_definitions();
		$enabled = array();
		foreach ( $stored as $key ) {
			$key = (string) $key;
			if ( isset( $defs[ $key ] ) && empty( $defs[ $key ]['admin_only'] ) ) {
				$enabled[] = $key;
			}
		}
		return $enabled;
	}

	/**
	 * Render the public stats grid.
	 *
	 * Used by both the [dsb_site_stats] shortcode and the slim banner
	 * above the Browse Members filters. Returns an empty string when
	 * no public stats are enabled.
	 *
	 * @param array $args Optional overrides:
	 *                    - wrapper_class string  CSS classes for the outer div.
	 *                    - show_sub      bool    Whether to show sub-labels.
	 * @return string HTML
	 */
	public static function render_public_grid( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'wrapper_class' => 'dsb-public-stats',
			'show_sub'      => true,
		) );

		$enabled = self::get_enabled_public_keys();
		if ( empty( $enabled ) ) {
			return '';
		}

		$defs   = self::get_definitions();
		$values = self::compute_all();

		ob_start();
		?>
		<div class="<?php echo esc_attr( $args['wrapper_class'] ); ?>">
			<?php
			foreach ( $enabled as $key ) :
				if ( ! isset( $defs[ $key ] ) ) {
					continue;
				}
				$def        = $defs[ $key ];
				$value      = isset( $values[ $key ] ) ? (int) $values[ $key ] : 0;
				$tone_class = 'dsb-public-stat-tone-' . sanitize_html_class( $def['tone'] );
				?>
				<div class="dsb-public-stat <?php echo esc_attr( $tone_class ); ?>">
					<span class="dsb-public-stat-icon" aria-hidden="true"><?php echo esc_html( $def['icon'] ); ?></span>
					<span class="dsb-public-stat-body">
						<span class="dsb-public-stat-value"><?php echo esc_html( number_format_i18n( $value ) ); ?></span>
						<span class="dsb-public-stat-label"><?php echo esc_html( $def['label'] ); ?></span>
						<?php if ( ! empty( $args['show_sub'] ) && ! empty( $def['sub'] ) ) : ?>
							<span class="dsb-public-stat-sub"><?php echo esc_html( $def['sub'] ); ?></span>
						<?php endif; ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
