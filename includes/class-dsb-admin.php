<?php
/**
 * Admin functionality including setup wizard.
 *
 * @package DatingSiteBuilder
 */

class DSB_Admin {

	/**
	 * Add admin menu pages.
	 */
	public function add_admin_menu() {
		// Get custom site name or use default
		$site_name = get_option( 'dsb_site_name', '' );
		$menu_name = ! empty( $site_name ) ? $site_name : __( 'Dating Builder', 'dating-site-builder' );
		$menu_title = ! empty( $site_name ) ? $site_name . ' Settings' : __( 'Dating Builder', 'dating-site-builder' );

		add_menu_page(
			$menu_title,
			$menu_name,
			'manage_dating_site',
			'dsb-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-heart',
			30
		);

		add_submenu_page(
			'dsb-dashboard',
			__( 'Setup Wizard', 'dating-site-builder' ),
			__( 'Setup Wizard', 'dating-site-builder' ),
			'manage_dating_site',
			'dsb-wizard',
			array( $this, 'render_wizard' )
		);

		add_submenu_page(
			'dsb-dashboard',
			__( 'Members', 'dating-site-builder' ),
			__( 'Members', 'dating-site-builder' ),
			'moderate_dating_profiles',
			'dsb-members',
			array( $this, 'render_members' )
		);

		add_submenu_page(
			'dsb-dashboard',
			__( 'Reports', 'dating-site-builder' ),
			__( 'Reports', 'dating-site-builder' ),
			'view_dating_reports',
			'dsb-reports',
			array( $this, 'render_reports' )
		);

		add_submenu_page(
			'dsb-dashboard',
			__( 'Settings', 'dating-site-builder' ),
			__( 'Settings', 'dating-site-builder' ),
			'manage_dating_site',
			'dsb-settings',
			array( $this, 'render_settings' )
		);

		// Check for activation redirect
		if ( get_transient( 'dsb_activation_redirect' ) ) {
			delete_transient( 'dsb_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=dsb-wizard' ) );
				exit;
			}
		}
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'dsb_settings', 'dsb_site_type' );
		register_setting( 'dsb_settings', 'dsb_minimum_age' );
		register_setting( 'dsb_settings', 'dsb_require_email_verification' );
		register_setting( 'dsb_settings', 'dsb_require_profile_approval' );
		register_setting( 'dsb_settings', 'dsb_enabled_field_groups' );
		register_setting( 'dsb_settings', 'dsb_allow_custom_gender' );
		register_setting( 'dsb_settings', 'dsb_allow_multiple_interests' );
		register_setting( 'dsb_settings', 'dsb_accessibility_fields' );
		register_setting( 'dsb_settings', 'dsb_matching_mode' );
		register_setting( 'dsb_settings', 'dsb_enable_messaging' );
		register_setting( 'dsb_settings', 'dsb_enable_likes' );
		register_setting( 'dsb_settings', 'dsb_require_mutual_like' );
		register_setting( 'dsb_settings', 'dsb_enable_blocking' );
		register_setting( 'dsb_settings', 'dsb_enable_reporting' );
		register_setting( 'dsb_settings', 'dsb_membership_enabled' );
		register_setting( 'dsb_settings', 'dsb_photo_privacy_mode' );
		register_setting( 'dsb_settings', 'dsb_enable_private_photos' );
		register_setting( 'dsb_settings', 'dsb_suspend_reason_options' );
		register_setting( 'dsb_settings', 'dsb_cancel_reason_options' );
		register_setting( 'dsb_settings', 'dsb_adult_age_gate_mode' );
		register_setting( 'dsb_settings', 'dsb_max_photos' );
		register_setting( 'dsb_settings', 'dsb_setup_complete' );
	}

	/**
	 * Enqueue admin styles.
	 */
	public function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'dsb-' ) === false && $hook !== 'toplevel_page_dsb-dashboard' ) {
			return;
		}

		wp_enqueue_style(
			'dsb-admin',
			DSB_PLUGIN_URL . 'admin/css/dsb-admin.css',
			array(),
			DSB_VERSION
		);
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'dsb-' ) === false && $hook !== 'toplevel_page_dsb-dashboard' ) {
			return;
		}

		// The wizard and settings pages use the WP media library
		// uploader for the Site Logo field.
		wp_enqueue_media();

		wp_enqueue_script(
			'dsb-admin',
			DSB_PLUGIN_URL . 'admin/js/dsb-admin.js',
			array( 'jquery' ),
			DSB_VERSION,
			true
		);

		wp_localize_script(
			'dsb-admin',
			'dsbAdmin',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'dsb_admin_nonce' ),
				'logo_modal_title' => __( 'Choose Site Logo', 'dating-site-builder' ),
				'logo_modal_btn'   => __( 'Use this logo', 'dating-site-builder' ),
			)
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * Card-based layout grouped into Live Activity, Members,
	 * Engagement, and Moderation. Each card links to the relevant
	 * admin page so the dashboard doubles as a quick-jump hub.
	 */
	public function render_dashboard() {
		global $wpdb;

		// --- Member counts --------------------------------------------------
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

		// --- Table names ----------------------------------------------------
		$messages_table   = $wpdb->prefix . 'dsb_messages';
		$likes_table      = $wpdb->prefix . 'dsb_likes';
		$reports_table    = $wpdb->prefix . 'dsb_reports';
		$views_table      = $wpdb->prefix . 'dsb_profile_views';
		$group_chat_table = $wpdb->prefix . 'dsb_group_chat';

		// --- All-time totals ------------------------------------------------
		$total_messages  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table" );
		$total_likes     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $likes_table" );
		$pending_reports = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'" );
		$profile_views   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $views_table" );

		// Mutual matches: pairs where A likes B AND B likes A. The self-join
		// counts each pair twice, so divide by 2 for the true match count.
		$mutual_matches = (int) $wpdb->get_var(
			"SELECT COUNT(*) / 2 FROM $likes_table l1
			 INNER JOIN $likes_table l2
			   ON l1.user_id = l2.target_id
			  AND l1.target_id = l2.user_id"
		);

		// --- Live activity --------------------------------------------------
		$five_min_ago    = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( 5 * MINUTE_IN_SECONDS ) );
		$fifteen_min_ago = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - ( 15 * MINUTE_IN_SECONDS ) );

		// dsb_last_activity is stored via current_time('mysql') (site time),
		// so compare in site time too.
		$five_min_ago_local = date( 'Y-m-d H:i:s', strtotime( '-5 minutes' ) );
		$online_now = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
			 WHERE meta_key = 'dsb_last_activity' AND meta_value >= %s",
			$five_min_ago_local
		) );

		$fifteen_min_ago_local = date( 'Y-m-d H:i:s', strtotime( '-15 minutes' ) );
		$in_chat_room = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM $group_chat_table WHERE created_at >= %s",
			$fifteen_min_ago_local
		) );

		$today_start = date( 'Y-m-d 00:00:00' );
		$messages_today = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $messages_table WHERE created_at >= %s",
			$today_start
		) );

		// --- Pipeline / moderation ----------------------------------------
		$pending_approvals = count( get_users( array(
			'role__in'   => array( 'dating_member', 'dating_premium' ),
			'fields'     => 'ID',
			'meta_query' => array(
				array( 'key' => 'dsb_profile_approved', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'dsb_banned',           'compare' => 'NOT EXISTS' ),
			),
		) ) );

		$week_ago_local = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		$new_this_week  = count( get_users( array(
			'role__in'    => array( 'dating_member', 'dating_premium' ),
			'fields'      => 'ID',
			'date_query'  => array( array( 'after' => '7 days ago' ) ),
		) ) );

		// Render helper for cards.
		$render_card = function ( $args ) {
			$args = wp_parse_args( $args, array(
				'icon'     => '✨',
				'value'    => 0,
				'label'    => '',
				'sub'      => '',
				'href'     => '',
				'tone'     => 'default',
				'badge'    => '',
			) );

			$tone_class = 'dsb-dash-card-tone-' . sanitize_html_class( $args['tone'] );
			$tag        = $args['href'] ? 'a' : 'div';
			$href_attr  = $args['href'] ? ' href="' . esc_url( $args['href'] ) . '"' : '';
			?>
			<<?php echo $tag; ?> class="dsb-dash-card <?php echo esc_attr( $tone_class ); ?>"<?php echo $href_attr; ?>>
				<div class="dsb-dash-card-icon" aria-hidden="true"><?php echo esc_html( $args['icon'] ); ?></div>
				<div class="dsb-dash-card-body">
					<div class="dsb-dash-card-value">
						<?php echo esc_html( number_format_i18n( (int) $args['value'] ) ); ?>
						<?php if ( $args['badge'] ) : ?>
							<span class="dsb-dash-card-badge"><?php echo esc_html( $args['badge'] ); ?></span>
						<?php endif; ?>
					</div>
					<div class="dsb-dash-card-label"><?php echo esc_html( $args['label'] ); ?></div>
					<?php if ( $args['sub'] ) : ?>
						<div class="dsb-dash-card-sub"><?php echo esc_html( $args['sub'] ); ?></div>
					<?php endif; ?>
				</div>
			</<?php echo $tag; ?>>
			<?php
		};

		?>
		<div class="wrap dsb-dashboard">
			<h1><?php esc_html_e( 'Dating Site Builder Dashboard', 'dating-site-builder' ); ?></h1>

			<?php if ( ! get_option( 'dsb_setup_complete' ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Setup not complete!', 'dating-site-builder' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-wizard' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Run Setup Wizard', 'dating-site-builder' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<h2 class="dsb-dash-section-title"><?php esc_html_e( 'Live Activity', 'dating-site-builder' ); ?></h2>
			<div class="dsb-dash-grid">
				<?php
				$render_card( array(
					'icon'  => '🟢',
					'value' => $online_now,
					'label' => __( 'Online Now', 'dating-site-builder' ),
					'sub'   => __( 'Active in the last 5 minutes', 'dating-site-builder' ),
					'href'  => admin_url( 'admin.php?page=dsb-members' ),
					'tone'  => 'live',
				) );
				$render_card( array(
					'icon'  => '💬',
					'value' => $in_chat_room,
					'label' => __( 'In Chat Room', 'dating-site-builder' ),
					'sub'   => __( 'Posted a chat message in the last 15 minutes', 'dating-site-builder' ),
					'href'  => get_permalink( get_option( 'dsb_group_chat_page' ) ) ?: '',
					'tone'  => 'chat',
				) );
				$render_card( array(
					'icon'  => '✉️',
					'value' => $messages_today,
					'label' => __( 'Messages Today', 'dating-site-builder' ),
					'sub'   => __( 'Direct messages sent since midnight', 'dating-site-builder' ),
					'tone'  => 'messages',
				) );
				?>
			</div>

			<h2 class="dsb-dash-section-title"><?php esc_html_e( 'Members', 'dating-site-builder' ); ?></h2>
			<div class="dsb-dash-grid">
				<?php
				$render_card( array(
					'icon'  => '👥',
					'value' => $member_count,
					'label' => __( 'Total Members', 'dating-site-builder' ),
					'sub'   => __( 'All approved & pending dating accounts', 'dating-site-builder' ),
					'href'  => admin_url( 'admin.php?page=dsb-members' ),
					'tone'  => 'members',
				) );
				$render_card( array(
					'icon'  => '⭐',
					'value' => $premium_count,
					'label' => __( 'Premium Members', 'dating-site-builder' ),
					'sub'   => __( 'Users on the dating_premium role', 'dating-site-builder' ),
					'tone'  => 'premium',
				) );
				$render_card( array(
					'icon'  => '🆕',
					'value' => $new_this_week,
					'label' => __( 'New This Week', 'dating-site-builder' ),
					'sub'   => __( 'Members registered in the last 7 days', 'dating-site-builder' ),
					'tone'  => 'new',
				) );
				?>
			</div>

			<h2 class="dsb-dash-section-title"><?php esc_html_e( 'Engagement', 'dating-site-builder' ); ?></h2>
			<div class="dsb-dash-grid">
				<?php
				$render_card( array(
					'icon'  => '❤️',
					'value' => $total_likes,
					'label' => __( 'Total Likes', 'dating-site-builder' ),
					'sub'   => __( 'Lifetime number of profile likes', 'dating-site-builder' ),
					'tone'  => 'likes',
				) );
				$render_card( array(
					'icon'  => '💕',
					'value' => $mutual_matches,
					'label' => __( 'Mutual Matches', 'dating-site-builder' ),
					'sub'   => __( 'Pairs where both members liked each other', 'dating-site-builder' ),
					'tone'  => 'matches',
				) );
				$render_card( array(
					'icon'  => '👀',
					'value' => $profile_views,
					'label' => __( 'Profile Views', 'dating-site-builder' ),
					'sub'   => __( 'Lifetime profile page views', 'dating-site-builder' ),
					'tone'  => 'views',
				) );
				?>
			</div>

			<h2 class="dsb-dash-section-title"><?php esc_html_e( 'Moderation', 'dating-site-builder' ); ?></h2>
			<div class="dsb-dash-grid">
				<?php
				$render_card( array(
					'icon'  => '⏳',
					'value' => $pending_approvals,
					'label' => __( 'Pending Approvals', 'dating-site-builder' ),
					'sub'   => __( 'Members awaiting admin approval', 'dating-site-builder' ),
					'href'  => admin_url( 'admin.php?page=dsb-members&filter=pending' ),
					'tone'  => 'warning',
				) );
				$render_card( array(
					'icon'  => '🚩',
					'value' => $pending_reports,
					'label' => __( 'Pending Reports', 'dating-site-builder' ),
					'sub'   => __( 'User-submitted reports awaiting review', 'dating-site-builder' ),
					'href'  => admin_url( 'admin.php?page=dsb-reports' ),
					'tone'  => 'danger',
				) );
				$render_card( array(
					'icon'  => '💌',
					'value' => $total_messages,
					'label' => __( 'Messages Sent', 'dating-site-builder' ),
					'sub'   => __( 'Lifetime direct-message volume', 'dating-site-builder' ),
					'tone'  => 'messages',
				) );
				?>
			</div>

			<div class="dsb-dashboard-actions">
				<h2><?php esc_html_e( 'Quick Actions', 'dating-site-builder' ); ?></h2>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-wizard' ) ); ?>" class="button">
						<?php esc_html_e( 'Setup Wizard', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-members' ) ); ?>" class="button">
						<?php esc_html_e( 'Manage Members', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-reports' ) ); ?>" class="button">
						<?php esc_html_e( 'View Reports', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-settings' ) ); ?>" class="button">
						<?php esc_html_e( 'Settings', 'dating-site-builder' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a compact grid of clickable admin filter cards.
	 *
	 * @param array $cards List of card definitions.
	 */
	private function render_admin_filter_cards( $cards ) {
		if ( empty( $cards ) || ! is_array( $cards ) ) {
			return;
		}
		?>
		<div class="dsb-admin-filter-cards">
			<?php foreach ( $cards as $card ) : ?>
				<?php
				$card = wp_parse_args(
					$card,
					array(
						'href'    => '',
						'icon'    => '📊',
						'value'   => 0,
						'label'   => '',
						'tone'    => 'default',
						'current' => false,
					)
				);

				$classes = array(
					'dsb-admin-filter-card',
					'dsb-admin-filter-card-tone-' . sanitize_html_class( $card['tone'] ),
				);

				if ( ! empty( $card['current'] ) ) {
					$classes[] = 'is-current';
				}
				?>
				<a href="<?php echo esc_url( $card['href'] ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
					<span class="dsb-admin-filter-card-icon" aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></span>
					<span class="dsb-admin-filter-card-body">
						<span class="dsb-admin-filter-card-value"><?php echo esc_html( number_format_i18n( (int) $card['value'] ) ); ?></span>
						<span class="dsb-admin-filter-card-label"><?php echo esc_html( $card['label'] ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render setup wizard.
	 */
	public function render_wizard() {
		$step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;

		?>
		<div class="wrap dsb-wizard">
			<h1><?php esc_html_e( 'Dating Site Setup Wizard', 'dating-site-builder' ); ?></h1>
			
			<div class="dsb-wizard-progress">
				<div class="dsb-wizard-steps">
					<?php for ( $i = 1; $i <= 8; $i++ ) : ?>
						<span class="dsb-step <?php echo $i === $step ? 'active' : ''; ?> <?php echo $i < $step ? 'completed' : ''; ?>">
							<?php echo esc_html( $i ); ?>
						</span>
					<?php endfor; ?>
				</div>
			</div>

			<form method="post" id="dsb-wizard-form" class="dsb-wizard-form">
				<?php wp_nonce_field( 'dsb_wizard', 'dsb_wizard_nonce' ); ?>
				<input type="hidden" name="current_step" value="<?php echo esc_attr( $step ); ?>">

				<?php
				switch ( $step ) {
					case 1:
						$this->render_wizard_step_1();
						break;
					case 2:
						$this->render_wizard_step_2();
						break;
					case 3:
						$this->render_wizard_step_3();
						break;
					case 4:
						$this->render_wizard_step_4();
						break;
					case 5:
						$this->render_wizard_step_5();
						break;
					case 6:
						$this->render_wizard_step_6();
						break;
					case 7:
						$this->render_wizard_step_7();
						break;
					case 8:
						$this->render_wizard_step_8();
						break;
				}
				?>

				<div class="dsb-wizard-actions">
					<?php if ( $step > 1 ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-wizard&step=' . ( $step - 1 ) ) ); ?>" class="button">
							<?php esc_html_e( 'Back', 'dating-site-builder' ); ?>
						</a>
					<?php endif; ?>

					<?php if ( $step < 8 ) : ?>
						<button type="submit" name="action" value="next" class="button button-primary">
							<?php esc_html_e( 'Next', 'dating-site-builder' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="action" value="finish" class="button button-primary">
							<?php esc_html_e( 'Finish Setup', 'dating-site-builder' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Wizard Step 1: Site Type.
	 */
	private function render_wizard_step_1() {
		$site_name = get_option( 'dsb_site_name', '' );
		$site_type = get_option( 'dsb_site_type', 'standard' );
		$color_theme = get_option( 'dsb_color_theme', 'romantic_red' );
		$template_style = get_option( 'dsb_template_style', 'modern' );
		$site_logo_id = (int) get_option( 'dsb_site_logo', 0 );
		$site_logo_url = $site_logo_id ? wp_get_attachment_image_url( $site_logo_id, 'medium' ) : '';
		?>
		<h2><?php esc_html_e( 'Step 1: Site Identity & Theme', 'dating-site-builder' ); ?></h2>
		<p><?php esc_html_e( 'Give your dating site a name and choose a design template.', 'dating-site-builder' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="dsb_site_name"><?php esc_html_e( 'Site Name', 'dating-site-builder' ); ?></label>
				</th>
				<td>
					<input type="text" id="dsb_site_name" name="dsb_site_name" value="<?php echo esc_attr( $site_name ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Cupid, LoveMatch, DateNight', 'dating-site-builder' ); ?>">
					<p class="description"><?php esc_html_e( 'This name will be used in the admin menu (e.g., "Cupid Settings") and can be displayed to your users.', 'dating-site-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dsb_site_logo"><?php esc_html_e( 'Site Logo', 'dating-site-builder' ); ?></label>
				</th>
				<td>
					<div class="dsb-logo-uploader">
						<input type="hidden" id="dsb_site_logo" name="dsb_site_logo" value="<?php echo esc_attr( $site_logo_id ); ?>">
						<div class="dsb-logo-preview" id="dsb-logo-preview" style="<?php echo $site_logo_url ? '' : 'display:none;'; ?>">
							<img src="<?php echo esc_url( $site_logo_url ); ?>" alt="<?php esc_attr_e( 'Site logo preview', 'dating-site-builder' ); ?>" style="max-width:200px;max-height:200px;display:block;margin-bottom:8px;">
						</div>
						<button type="button" class="button" id="dsb-logo-upload-btn">
							<?php echo $site_logo_id ? esc_html__( 'Change Logo', 'dating-site-builder' ) : esc_html__( 'Choose / Upload Logo', 'dating-site-builder' ); ?>
						</button>
						<button type="button" class="button-link dsb-logo-remove-btn" id="dsb-logo-remove-btn" style="<?php echo $site_logo_id ? '' : 'display:none;'; ?>;color:#b32d2e;margin-left:8px;">
							<?php esc_html_e( 'Remove', 'dating-site-builder' ); ?>
						</button>
					</div>
					<p class="description">
						<?php esc_html_e( 'Used in the plugin\'s top navigation bar and as the Site Icon / favicon (browser tab, bookmarks, mobile shortcut). For best results upload a square image at least 512 × 512 pixels.', 'dating-site-builder' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Color Theme', 'dating-site-builder' ); ?></th>
				<td>
					<div class="dsb-theme-selector">
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="romantic_red" <?php checked( $color_theme, 'romantic_red' ); ?>>
							<span class="dsb-theme-preview dsb-theme-romantic-red">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Romantic Red', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="ocean_blue" <?php checked( $color_theme, 'ocean_blue' ); ?>>
							<span class="dsb-theme-preview dsb-theme-ocean-blue">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Ocean Blue', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="forest_green" <?php checked( $color_theme, 'forest_green' ); ?>>
							<span class="dsb-theme-preview dsb-theme-forest-green">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Forest Green', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="royal_purple" <?php checked( $color_theme, 'royal_purple' ); ?>>
							<span class="dsb-theme-preview dsb-theme-royal-purple">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Royal Purple', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="sunset_orange" <?php checked( $color_theme, 'sunset_orange' ); ?>>
							<span class="dsb-theme-preview dsb-theme-sunset-orange">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Sunset Orange', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-theme-option">
							<input type="radio" name="dsb_color_theme" value="midnight_dark" <?php checked( $color_theme, 'midnight_dark' ); ?>>
							<span class="dsb-theme-preview dsb-theme-midnight-dark">
								<span class="dsb-theme-gradient"></span>
								<span class="dsb-theme-name"><?php esc_html_e( 'Midnight Dark', 'dating-site-builder' ); ?></span>
							</span>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Choose a color scheme for your dating site. This affects buttons, gradients, and accent colors.', 'dating-site-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Template Style', 'dating-site-builder' ); ?></th>
				<td>
					<div class="dsb-template-selector">
						<label class="dsb-template-option">
							<input type="radio" name="dsb_template_style" value="modern" <?php checked( $template_style, 'modern' ); ?>>
							<span class="dsb-template-preview dsb-template-modern">
								<span class="dsb-template-icon">✨</span>
								<span class="dsb-template-name"><?php esc_html_e( 'Modern', 'dating-site-builder' ); ?></span>
								<span class="dsb-template-desc"><?php esc_html_e( 'Classic dating app style with gradients and cards', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-template-option">
							<input type="radio" name="dsb_template_style" value="glassmorphism" <?php checked( $template_style, 'glassmorphism' ); ?>>
							<span class="dsb-template-preview dsb-template-glassmorphism">
								<span class="dsb-template-icon">🔮</span>
								<span class="dsb-template-name"><?php esc_html_e( 'Glassmorphism', 'dating-site-builder' ); ?></span>
								<span class="dsb-template-desc"><?php esc_html_e( 'Frosted glass effects with blur and transparency', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-template-option">
							<input type="radio" name="dsb_template_style" value="minimalist" <?php checked( $template_style, 'minimalist' ); ?>>
							<span class="dsb-template-preview dsb-template-minimalist">
								<span class="dsb-template-icon">◻️</span>
								<span class="dsb-template-name"><?php esc_html_e( 'Minimalist', 'dating-site-builder' ); ?></span>
								<span class="dsb-template-desc"><?php esc_html_e( 'Clean, flat design with lots of whitespace', 'dating-site-builder' ); ?></span>
							</span>
						</label>
						<label class="dsb-template-option">
							<input type="radio" name="dsb_template_style" value="bold_dark" <?php checked( $template_style, 'bold_dark' ); ?>>
							<span class="dsb-template-preview dsb-template-bold-dark">
								<span class="dsb-template-icon">🌙</span>
								<span class="dsb-template-name"><?php esc_html_e( 'Bold Dark', 'dating-site-builder' ); ?></span>
								<span class="dsb-template-desc"><?php esc_html_e( 'Dark mode with vibrant accents, Tinder-inspired', 'dating-site-builder' ); ?></span>
							</span>
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Choose an overall design template. This changes the layout structure, card styles, and visual effects.', 'dating-site-builder' ); ?></p>
				</td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Site Type', 'dating-site-builder' ); ?></h3>
		<p><?php esc_html_e( 'What type of dating site are you setting up?', 'dating-site-builder' ); ?></p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Site Type', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="dsb_site_type" value="standard" <?php checked( $site_type, 'standard' ); ?>>
							<strong><?php esc_html_e( 'Standard Dating Site', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'General audience dating with standard profile fields', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_site_type" value="adult" <?php checked( $site_type, 'adult' ); ?>>
							<strong><?php esc_html_e( 'Adult Dating Site (18+ only)', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Adult-focused dating with enhanced privacy settings', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_site_type" value="swingers" <?php checked( $site_type, 'swingers' ); ?>>
							<strong><?php esc_html_e( 'Swingers / Alternative Lifestyle', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'For couples and singles in the lifestyle with profile type options', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_site_type" value="ndis" <?php checked( $site_type, 'ndis' ); ?>>
							<strong><?php esc_html_e( 'All Abilities / NDIS-Focused', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Inclusive dating with accessibility and support needs fields', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_site_type" value="custom" <?php checked( $site_type, 'custom' ); ?>>
							<strong><?php esc_html_e( 'Custom', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Manually configure all settings', 'dating-site-builder' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wizard Step 2: Basic Settings.
	 */
	private function render_wizard_step_2() {
		$min_age = get_option( 'dsb_minimum_age', 18 );
		$require_verification = get_option( 'dsb_require_email_verification', false );
		$require_approval = get_option( 'dsb_require_profile_approval', false );
		$default_country = get_option( 'dsb_default_country', '' );
		?>
		<h2><?php esc_html_e( 'Step 2: Basic Settings', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="dsb_minimum_age"><?php esc_html_e( 'Minimum Age', 'dating-site-builder' ); ?></label>
				</th>
				<td>
					<input type="number" id="dsb_minimum_age" name="dsb_minimum_age" value="<?php echo esc_attr( $min_age ); ?>" min="13" max="25" class="small-text">
					<p class="description"><?php esc_html_e( 'Minimum age required to register (default: 18)', 'dating-site-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="dsb_default_country"><?php esc_html_e( 'Default Country/Region', 'dating-site-builder' ); ?></label>
				</th>
				<td>
					<input type="text" id="dsb_default_country" name="dsb_default_country" value="<?php echo esc_attr( $default_country ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Default country to show in forms (optional)', 'dating-site-builder' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Email Verification', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_require_email_verification" value="1" <?php checked( $require_verification, true ); ?>>
						<?php esc_html_e( 'Require email verification for new users', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Profile Approval', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_require_profile_approval" value="1" <?php checked( $require_approval, true ); ?>>
						<?php esc_html_e( 'Require admin approval before profiles go public', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wizard Step 3: Profile Fields.
	 */
	private function render_wizard_step_3() {
		$enabled_groups = get_option( 'dsb_enabled_field_groups', array( 'basics', 'about', 'lifestyle' ) );
		$allow_custom_gender = get_option( 'dsb_allow_custom_gender', true );
		$allow_multiple_interests = get_option( 'dsb_allow_multiple_interests', true );
		$max_photos = get_option( 'dsb_max_photos', 10 );
		$accessibility_fields = get_option( 'dsb_accessibility_fields', array() );
		?>
		<h2><?php esc_html_e( 'Step 3: Profile Fields', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Field Groups', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="basics" <?php checked( in_array( 'basics', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Basics (gender, age, orientation, location, relationship status)', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="about" <?php checked( in_array( 'about', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Vibe & Interests (vibe, interaction style, interests, intent)', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="lifestyle" <?php checked( in_array( 'lifestyle', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Optional Details (occupation, education, smoking, drinking)', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="accessibility" <?php checked( in_array( 'accessibility', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Accessibility & Support Needs (recommended for All Abilities mode)', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="adult_preferences" <?php checked( in_array( 'adult_preferences', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Adult Preferences (for Adult/Swingers sites)', 'dating-site-builder' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Gender Options', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_allow_custom_gender" value="1" <?php checked( $allow_custom_gender, true ); ?>>
						<?php esc_html_e( 'Allow users to specify gender beyond male/female (non-binary, custom, prefer not to say)', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Gender Interests', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_allow_multiple_interests" value="1" <?php checked( $allow_multiple_interests, true ); ?>>
						<?php esc_html_e( 'Allow users to select multiple gender interests (who they are interested in)', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr id="dsb_accessibility_fields_section" style="display: none;">
				<th scope="row"><?php esc_html_e( 'Accessibility Fields', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="dsb_accessibility_fields[]" value="communication_preference" <?php checked( in_array( 'communication_preference', $accessibility_fields ) ); ?>>
							<?php esc_html_e( 'Communication Preferences', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_accessibility_fields[]" value="mobility_info" <?php checked( in_array( 'mobility_info', $accessibility_fields ) ); ?>>
							<?php esc_html_e( 'Mobility Information', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_accessibility_fields[]" value="sensory_preferences" <?php checked( in_array( 'sensory_preferences', $accessibility_fields ) ); ?>>
							<?php esc_html_e( 'Sensory Preferences', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_accessibility_fields[]" value="support_needs" <?php checked( in_array( 'support_needs', $accessibility_fields ) ); ?>>
							<?php esc_html_e( 'Support Needs', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_accessibility_fields[]" value="ndis_participant" <?php checked( in_array( 'ndis_participant', $accessibility_fields ) ); ?>>
							<?php esc_html_e( 'NDIS Participant', 'dating-site-builder' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="dsb_max_photos"><?php esc_html_e( 'Maximum Photos Per User', 'dating-site-builder' ); ?></label>
				</th>
				<td>
					<input type="number" id="dsb_max_photos" name="dsb_max_photos" value="<?php echo esc_attr( $max_photos ); ?>" min="1" max="50" class="small-text">
				</td>
			</tr>
		</table>

		<script>
		jQuery(document).ready(function($) {
			function toggleAccessibilityFields() {
				if ($('input[name="dsb_enabled_field_groups[]"][value="accessibility"]').is(':checked')) {
					$('#dsb_accessibility_fields_section').show();
				} else {
					$('#dsb_accessibility_fields_section').hide();
				}
			}
			toggleAccessibilityFields();
			$('input[name="dsb_enabled_field_groups[]"]').change(toggleAccessibilityFields);
		});
		</script>
		<?php
	}

	/**
	 * Wizard Step 4: Photo Privacy & Adult Options.
	 */
	private function render_wizard_step_4() {
		$photo_privacy = get_option( 'dsb_photo_privacy_mode', 'public' );
		$enable_private_photos = get_option( 'dsb_enable_private_photos', false );
		$age_gate_mode = get_option( 'dsb_adult_age_gate_mode', 'checkbox' );
		?>
		<h2><?php esc_html_e( 'Step 4: Photo Privacy & Age Verification', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Photo Privacy Mode', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="dsb_photo_privacy_mode" value="public" <?php checked( $photo_privacy, 'public' ); ?>>
							<strong><?php esc_html_e( 'Public Photos', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'All profile photos are visible to all members', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_photo_privacy_mode" value="blur_until_match" <?php checked( $photo_privacy, 'blur_until_match' ); ?>>
							<strong><?php esc_html_e( 'Blur Until Match', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Photos are blurred until users match (recommended for adult sites)', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_photo_privacy_mode" value="private" <?php checked( $photo_privacy, 'private' ); ?>>
							<strong><?php esc_html_e( 'Members Only', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Photos only visible to logged-in members', 'dating-site-builder' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Public & Private Photo Albums', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_enable_private_photos" value="1" <?php checked( $enable_private_photos, true ); ?>>
						<?php esc_html_e( 'Allow users to have both public and private photo sections', 'dating-site-builder' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Private photos can only be accessed by users granted permission', 'dating-site-builder' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Age Verification (for Adult/Swingers sites)', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="dsb_adult_age_gate_mode" value="none" <?php checked( $age_gate_mode, 'none' ); ?>>
							<strong><?php esc_html_e( 'None', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'No special age verification (standard sites)', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_adult_age_gate_mode" value="checkbox" <?php checked( $age_gate_mode, 'checkbox' ); ?>>
							<strong><?php esc_html_e( 'Checkbox on Registration', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Users must check "I am 18+" during registration', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_adult_age_gate_mode" value="warning_page" <?php checked( $age_gate_mode, 'warning_page' ); ?>>
							<strong><?php esc_html_e( 'Warning Page', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Show "Adults only" warning page before accessing any dating pages', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_adult_age_gate_mode" value="both" <?php checked( $age_gate_mode, 'both' ); ?>>
							<strong><?php esc_html_e( 'Both', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Warning page + checkbox on registration (maximum protection)', 'dating-site-builder' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	// Continued in next message due to length...
	/**
	 * Wizard Step 5: Matching & Discovery.
	 */
	private function render_wizard_step_5() {
		$matching_mode = get_option( 'dsb_matching_mode', 'hybrid' );
		?>
		<h2><?php esc_html_e( 'Step 5: Matching & Discovery', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Matching Algorithm', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="dsb_matching_mode" value="simple" <?php checked( $matching_mode, 'simple' ); ?>>
							<strong><?php esc_html_e( 'Simple Preferences', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Match based on gender, age range, and location preferences only', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_matching_mode" value="interests" <?php checked( $matching_mode, 'interests' ); ?>>
							<strong><?php esc_html_e( 'Interests-Based', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Score matches based on shared interests and hobbies', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_matching_mode" value="hybrid" <?php checked( $matching_mode, 'hybrid' ); ?>>
							<strong><?php esc_html_e( 'Hybrid (Recommended)', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Combine preferences, interests, and activity for best matches', 'dating-site-builder' ); ?></span>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wizard Step 6: Messaging & Interaction.
	 */
	private function render_wizard_step_6() {
		$enable_messaging = get_option( 'dsb_enable_messaging', true );
		$enable_likes = get_option( 'dsb_enable_likes', true );
		$require_mutual_like = get_option( 'dsb_require_mutual_like', false );
		$enable_blocking = get_option( 'dsb_enable_blocking', true );
		$enable_reporting = get_option( 'dsb_enable_reporting', true );
		?>
		<h2><?php esc_html_e( 'Step 6: Messaging & Interaction', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Private Messaging', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_enable_messaging" value="1" <?php checked( $enable_messaging, true ); ?>>
						<?php esc_html_e( 'Enable private 1-to-1 messaging', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Like System', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_enable_likes" value="1" <?php checked( $enable_likes, true ); ?>>
						<?php esc_html_e( 'Enable like/favorite system', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Messaging Rules', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_require_mutual_like" value="1" <?php checked( $require_mutual_like, true ); ?>>
						<?php esc_html_e( 'Require mutual like/match before messaging (recommended for quality control)', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Block User', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_enable_blocking" value="1" <?php checked( $enable_blocking, true ); ?>>
						<?php esc_html_e( 'Allow users to block other users', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Report System', 'dating-site-builder' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dsb_enable_reporting" value="1" <?php checked( $enable_reporting, true ); ?>>
						<?php esc_html_e( 'Enable report user/message system', 'dating-site-builder' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wizard Step 7: Membership Tiers.
	 */
	private function render_wizard_step_7() {
		$membership_enabled = get_option( 'dsb_membership_enabled', false );
		?>
		<h2><?php esc_html_e( 'Step 7: Monetization (Optional)', 'dating-site-builder' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Membership Tiers', 'dating-site-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="dsb_membership_enabled" value="0" <?php checked( $membership_enabled, false ); ?>>
							<strong><?php esc_html_e( 'Free Only', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'All features available to all users', 'dating-site-builder' ); ?></span>
						</label><br><br>

						<label>
							<input type="radio" name="dsb_membership_enabled" value="1" <?php checked( $membership_enabled, true ); ?>>
							<strong><?php esc_html_e( 'Free + Paid Tiers', 'dating-site-builder' ); ?></strong><br>
							<span class="description"><?php esc_html_e( 'Set up free and premium membership roles (payment gateway integration required)', 'dating-site-builder' ); ?></span>
						</label>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Note: Payment gateway integration (Stripe, PayPal) must be added separately using the plugin hooks.', 'dating-site-builder' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Wizard Step 8: Final Confirmation.
	 */
	private function render_wizard_step_8() {
		?>
		<h2><?php esc_html_e( 'Step 8: Review & Complete Setup', 'dating-site-builder' ); ?></h2>
		
		<p><?php esc_html_e( 'Review your settings below. Click "Finish Setup" to complete the installation.', 'dating-site-builder' ); ?></p>

		<div class="dsb-wizard-summary">
			<h3><?php esc_html_e( 'Configuration Summary', 'dating-site-builder' ); ?></h3>
			<table class="widefat">
				<tr>
					<td><strong><?php esc_html_e( 'Site Name:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( get_option( 'dsb_site_name', '' ) ?: __( '(Not set)', 'dating-site-builder' ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Site Logo:', 'dating-site-builder' ); ?></strong></td>
					<td>
						<?php
						$summary_logo_id  = (int) get_option( 'dsb_site_logo', 0 );
						$summary_logo_url = $summary_logo_id ? wp_get_attachment_image_url( $summary_logo_id, 'thumbnail' ) : '';
						if ( $summary_logo_url ) :
						?>
							<img src="<?php echo esc_url( $summary_logo_url ); ?>" alt="" style="max-width:80px;max-height:80px;vertical-align:middle;border:1px solid #ddd;border-radius:4px;padding:2px;background:#fff;">
							<span style="margin-left:8px;color:#666;"><?php esc_html_e( 'Will also be used as the Site Icon / favicon.', 'dating-site-builder' ); ?></span>
						<?php else : ?>
							<em><?php esc_html_e( '(No logo uploaded — the existing Site Icon will be left unchanged)', 'dating-site-builder' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Color Theme:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', get_option( 'dsb_color_theme', 'romantic_red' ) ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Template Style:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', get_option( 'dsb_template_style', 'modern' ) ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Site Type:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( ucfirst( get_option( 'dsb_site_type', 'standard' ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Minimum Age:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( get_option( 'dsb_minimum_age', 18 ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Email Verification:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo get_option( 'dsb_require_email_verification' ) ? esc_html__( 'Required', 'dating-site-builder' ) : esc_html__( 'Optional', 'dating-site-builder' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Profile Approval:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo get_option( 'dsb_require_profile_approval' ) ? esc_html__( 'Required', 'dating-site-builder' ) : esc_html__( 'Not Required', 'dating-site-builder' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Matching Mode:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( ucfirst( get_option( 'dsb_matching_mode', 'hybrid' ) ) ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Messaging:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo get_option( 'dsb_enable_messaging', true ) ? esc_html__( 'Enabled', 'dating-site-builder' ) : esc_html__( 'Disabled', 'dating-site-builder' ); ?></td>
				</tr>
				<tr>
					<td><strong><?php esc_html_e( 'Likes:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo get_option( 'dsb_enable_likes', true ) ? esc_html__( 'Enabled', 'dating-site-builder' ) : esc_html__( 'Disabled', 'dating-site-builder' ); ?></td>
				</tr>
			</table>

			<p class="description">
				<?php esc_html_e( 'After setup completes, the plugin will automatically create the necessary pages with shortcodes.', 'dating-site-builder' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save wizard step.
	 */
	public function save_wizard_step() {
		check_ajax_referer( 'dsb_wizard', 'dsb_wizard_nonce' );

		if ( ! current_user_can( 'manage_dating_site' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'dating-site-builder' ) );
		}

		$step = isset( $_POST['current_step'] ) ? intval( $_POST['current_step'] ) : 1;
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : 'next';

		// Save fields based on step
		$this->save_wizard_data( $step );

		if ( $action === 'finish' ) {
			// Complete setup
			update_option( 'dsb_setup_complete', true );

			// Create pages with shortcodes
			$this->create_dating_pages();

			// Apply the chosen logo as the WordPress Site Icon
			// (favicon, browser tab icon, mobile bookmark icon).
			$this->apply_site_logo_as_site_icon();

			wp_send_json_success( array(
				'redirect' => admin_url( 'admin.php?page=dsb-dashboard' ),
			) );
		} else {
			$next_step = $step + 1;
			wp_send_json_success( array(
				'redirect' => admin_url( 'admin.php?page=dsb-wizard&step=' . $next_step ),
			) );
		}
	}

	/**
	 * Save wizard data.
	 */
	private function save_wizard_data( $step ) {
		// Sanitize and save all posted options
		$options_map = array(
			'dsb_site_name'                   => 'sanitize_text_field',
			'dsb_site_logo'                   => 'intval',
			'dsb_color_theme'                 => 'sanitize_text_field',
			'dsb_template_style'              => 'sanitize_text_field',
			'dsb_site_type'                   => 'sanitize_text_field',
			'dsb_minimum_age'                 => 'intval',
			'dsb_default_country'             => 'sanitize_text_field',
			'dsb_require_email_verification'  => 'rest_sanitize_boolean',
			'dsb_require_profile_approval'    => 'rest_sanitize_boolean',
			'dsb_enabled_field_groups'        => 'array',
			'dsb_allow_custom_gender'         => 'rest_sanitize_boolean',
			'dsb_allow_multiple_interests'    => 'rest_sanitize_boolean',
			'dsb_accessibility_fields'        => 'array',
			'dsb_max_photos'                  => 'intval',
			'dsb_photo_privacy_mode'          => 'sanitize_text_field',
			'dsb_enable_private_photos'       => 'rest_sanitize_boolean',
			'dsb_adult_age_gate_mode'         => 'sanitize_text_field',
			'dsb_matching_mode'               => 'sanitize_text_field',
			'dsb_enable_messaging'            => 'rest_sanitize_boolean',
			'dsb_enable_likes'                => 'rest_sanitize_boolean',
			'dsb_require_mutual_like'         => 'rest_sanitize_boolean',
			'dsb_enable_blocking'             => 'rest_sanitize_boolean',
			'dsb_enable_reporting'            => 'rest_sanitize_boolean',
			'dsb_membership_enabled'          => 'rest_sanitize_boolean',
		);

		foreach ( $options_map as $option_name => $sanitize_func ) {
			if ( isset( $_POST[ $option_name ] ) ) {
				$value = $_POST[ $option_name ];
				
				if ( $sanitize_func === 'array' ) {
					$value = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
				} else {
					$value = call_user_func( $sanitize_func, $value );
				}
				
				update_option( $option_name, $value );
			}
		}
	}

	/**
	 * Create dating pages with shortcodes.
	 */
	private function create_dating_pages() {
		// Pages are split into two passes: top-level pages first (so the
		// "profile" page ID exists before its children are created), then
		// child pages which reference their parent via the 'parent' key.
		$pages = array(
			'register' => array(
				'title'   => 'Register',
				'content' => '[dsb_register]',
				'option'  => 'dsb_register_page',
			),
			'login' => array(
				'title'   => 'Login',
				'content' => '[dsb_login]',
				'option'  => 'dsb_login_page',
			),
			'profile' => array(
				'title'   => 'My Profile',
				'content' => '[dsb_profile_view]',
				'option'  => 'dsb_profile_view_page',
			),
			'forgot-password' => array(
				'title'   => 'Forgot Password',
				'content' => '[dsb_forgot_password]',
				'option'  => 'dsb_forgot_password_page',
				'parent'  => 'dsb_profile_view_page',
			),
			'profile-edit' => array(
				'title'   => 'Edit Profile',
				'content' => '[dsb_profile_edit]',
				'option'  => 'dsb_profile_edit_page',
				'parent'  => 'dsb_profile_view_page',
			),
			'members' => array(
				'title'   => 'Browse Members',
				'content' => '[dsb_member_directory]',
				'option'  => 'dsb_member_directory_page',
			),
			'matches' => array(
				'title'   => 'Your Matches',
				'content' => '[dsb_matches]',
				'option'  => 'dsb_matches_page',
			),
			'messages' => array(
				'title'   => 'Messages',
				'content' => '[dsb_messages]',
				'option'  => 'dsb_messages_page',
			),
			'likes' => array(
				'title'   => 'Likes & Favorites',
				'content' => '[dsb_likes]',
				'option'  => 'dsb_likes_page',
				'parent'  => 'dsb_profile_view_page',
			),
			'chat' => array(
				'title'   => 'Community Chat',
				'content' => '[dsb_group_chat]',
				'option'  => 'dsb_group_chat_page',
			),
		);

		// First pass: create / fetch every page and save its option so the
		// parent option IDs are available for the second pass.
		foreach ( $pages as $slug => $page_data ) {
			$existing_page = get_page_by_path( $slug );

			if ( $existing_page ) {
				$page_id = $existing_page->ID;
			} else {
				$page_id = wp_insert_post( array(
					'post_title'   => $page_data['title'],
					'post_content' => $page_data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_name'    => $slug,
				) );
			}

			if ( $page_id && ! is_wp_error( $page_id ) && ! empty( $page_data['option'] ) ) {
				update_option( $page_data['option'], $page_id );
			}
		}

		// Second pass: enforce parent/child relationships so that Edit
		// Profile, Forgot Password and Likes & Favorites appear as a
		// submenu under My Profile in the theme's page menu.
		foreach ( $pages as $slug => $page_data ) {
			if ( empty( $page_data['parent'] ) || empty( $page_data['option'] ) ) {
				continue;
			}

			$child_id  = (int) get_option( $page_data['option'] );
			$parent_id = (int) get_option( $page_data['parent'] );

			if ( $child_id && $parent_id ) {
				$current = get_post( $child_id );
				if ( $current && (int) $current->post_parent !== $parent_id ) {
					wp_update_post( array(
						'ID'          => $child_id,
						'post_parent' => $parent_id,
					) );
				}
			}
		}

		// Set the Login page as the homepage (front page)
		$login_page_id = get_option( 'dsb_login_page' );
		if ( $login_page_id ) {
			// Tell WordPress to use a static page as the front page
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $login_page_id );
		}
	}

	/**
	 * Apply the chosen Site Logo attachment as WordPress's official
	 * Site Icon (which generates the favicon, apple-touch-icon and
	 * the browser-tab icon automatically).
	 *
	 * Only updates `site_icon` when the user has actually picked a
	 * logo and the attachment exists. Leaves any existing site_icon
	 * alone if the wizard finishes without a logo selection.
	 */
	private function apply_site_logo_as_site_icon() {
		$logo_id = (int) get_option( 'dsb_site_logo', 0 );
		if ( ! $logo_id ) {
			return;
		}

		$attachment = get_post( $logo_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		update_option( 'site_icon', $logo_id );
	}

	/**
	 * Render members page.
	 */
	public function render_members() {
		// Handle single-row POST actions with reason fields.
		if ( isset( $_POST['dsb_single_action'], $_POST['user_id'] ) ) {
			$user_id = intval( $_POST['user_id'] );
			check_admin_referer( 'dsb_member_action_' . $user_id, 'dsb_member_nonce' );

			$action = sanitize_key( wp_unslash( $_POST['dsb_single_action'] ) );
			$reason = isset( $_POST['dsb_action_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dsb_action_reason'] ) ) : '';

			if ( $user_id > 0 ) {
				switch ( $action ) {
					case 'approve':
						update_user_meta( $user_id, 'dsb_profile_approved', '1' );
						break;
					case 'suspend':
						update_user_meta( $user_id, 'dsb_suspended', '1' );
						update_user_meta( $user_id, 'dsb_suspended_at', current_time( 'mysql' ) );
						if ( '' !== trim( $reason ) ) {
							update_user_meta( $user_id, 'dsb_suspended_reason', $reason );
						}
						break;
					case 'unsuspend':
						delete_user_meta( $user_id, 'dsb_suspended' );
						delete_user_meta( $user_id, 'dsb_suspended_reason' );
						break;
					case 'ban':
						update_user_meta( $user_id, 'dsb_banned', '1' );
						break;
					case 'unban':
						delete_user_meta( $user_id, 'dsb_banned' );
						break;
					case 'delete':
						$member = get_userdata( $user_id );
						if ( $member ) {
							$cancellation_log = get_option( 'dsb_account_cancellation_log', array() );
							if ( ! is_array( $cancellation_log ) ) {
								$cancellation_log = array();
							}
							$cancellation_log[] = array(
								'user_id'      => $user_id,
								'user_login'   => $member->user_login,
								'user_email'   => $member->user_email,
								'display_name' => $member->display_name,
								'reason'       => $reason,
								'source'       => 'admin',
								'admin_id'     => get_current_user_id(),
								'created_at'   => current_time( 'mysql' ),
							);
							if ( count( $cancellation_log ) > 200 ) {
								$cancellation_log = array_slice( $cancellation_log, -200 );
							}
							update_option( 'dsb_account_cancellation_log', $cancellation_log, false );
						}
						require_once ABSPATH . 'wp-admin/includes/user.php';
						wp_delete_user( $user_id );
						break;
				}
			}

			$redirect_url = remove_query_arg( array( 'action', 'user_id', '_wpnonce' ) );
			wp_safe_redirect( add_query_arg( 'dsb_notice', 'member-updated', $redirect_url ) );
			exit;
		}

		// Handle single-row actions
		if ( isset( $_GET['action'], $_GET['user_id'] ) && in_array( $_GET['action'], array( 'approve', 'ban', 'unban' ), true ) ) {
			check_admin_referer( 'dsb_member_action' );

			$action = sanitize_key( wp_unslash( $_GET['action'] ) );
			$user_id = intval( $_GET['user_id'] );

			if ( $user_id > 0 ) {
				switch ( $action ) {
					case 'approve':
						update_user_meta( $user_id, 'dsb_profile_approved', '1' );
						break;
					case 'ban':
						update_user_meta( $user_id, 'dsb_banned', '1' );
						break;
					case 'unban':
						delete_user_meta( $user_id, 'dsb_banned' );
						break;
				}
			}

			$redirect_url = remove_query_arg( array( 'action', 'user_id', '_wpnonce' ) );
			wp_safe_redirect( add_query_arg( 'dsb_notice', 'member-updated', $redirect_url ) );
			exit;
		}

		// Handle bulk actions
		if ( isset( $_POST['dsb_bulk_action'] ) && isset( $_POST['member_ids'] ) ) {
			check_admin_referer( 'dsb_members_bulk', 'dsb_members_nonce' );
			$action = sanitize_text_field( $_POST['dsb_bulk_action'] );
			$member_ids = array_map( 'intval', $_POST['member_ids'] );
			
			foreach ( $member_ids as $user_id ) {
				switch ( $action ) {
					case 'approve':
						update_user_meta( $user_id, 'dsb_profile_approved', '1' );
						break;
					case 'ban':
						update_user_meta( $user_id, 'dsb_banned', '1' );
						break;
					case 'unban':
						delete_user_meta( $user_id, 'dsb_banned' );
						break;
				}
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Members updated successfully.', 'dating-site-builder' ) . '</p></div>';
		}

		if ( isset( $_GET['dsb_notice'] ) && 'member-updated' === sanitize_key( wp_unslash( $_GET['dsb_notice'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Member updated successfully.', 'dating-site-builder' ) . '</p></div>';
		}

		// Get filter
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 20;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'registered';
		$order   = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';

		$sortable_columns = array( 'display_name', 'email', 'registered', 'status' );
		if ( ! in_array( $orderby, $sortable_columns, true ) ) {
			$orderby = 'registered';
		}
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Build user query args
		$args = array(
			'role__in'    => array( 'dating_member', 'dating_premium' ),
		);

		if ( 'status' === $orderby ) {
			$args['number'] = -1;
		} else {
			$args['number']  = $per_page;
			$args['offset']  = ( $paged - 1 ) * $per_page;
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		if ( $search ) {
			$args['search'] = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		if ( $filter === 'pending' ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'dsb_profile_approved',
					'compare' => 'NOT EXISTS',
				),
			);
		} elseif ( $filter === 'suspended' ) {
			$args['meta_key'] = 'dsb_suspended';
			$args['meta_value'] = '1';
		} elseif ( $filter === 'banned' ) {
			$args['meta_key'] = 'dsb_banned';
			$args['meta_value'] = '1';
		}

		$user_query = new WP_User_Query( $args );
		$members = $user_query->get_results();
		$total_members = $user_query->get_total();
		$total_pages = ceil( $total_members / $per_page );

		if ( 'status' === $orderby ) {
			usort(
				$members,
				function ( $left, $right ) use ( $order ) {
					$left_rank  = $this->get_member_status_rank( $left );
					$right_rank = $this->get_member_status_rank( $right );

					if ( $left_rank === $right_rank ) {
						$left_name  = isset( $left->display_name ) ? (string) $left->display_name : '';
						$right_name = isset( $right->display_name ) ? (string) $right->display_name : '';
						$comparison = strcasecmp( $left_name, $right_name );

						if ( 0 === $comparison ) {
							return (int) $left->ID <=> (int) $right->ID;
						}

						return $comparison;
					}

					return $left_rank <=> $right_rank;
				}
			);

			if ( 'DESC' === $order ) {
				$members = array_reverse( $members );
			}

			$members = array_slice( $members, ( $paged - 1 ) * $per_page, $per_page );
		}

		// Count stats
		$all_count = count( get_users( array( 'role__in' => array( 'dating_member', 'dating_premium' ), 'fields' => 'ID' ) ) );
		$pending_count = count( get_users( array(
			'role__in' => array( 'dating_member', 'dating_premium' ),
			'fields' => 'ID',
			'meta_query' => array(
				array( 'key' => 'dsb_profile_approved', 'compare' => 'NOT EXISTS' ),
			),
		) ) );
		$suspended_count = count( get_users( array(
			'role__in' => array( 'dating_member', 'dating_premium' ),
			'fields' => 'ID',
			'meta_key' => 'dsb_suspended',
			'meta_value' => '1',
		) ) );
		$banned_count = count( get_users( array(
			'role__in' => array( 'dating_member', 'dating_premium' ),
			'fields' => 'ID',
			'meta_key' => 'dsb_banned',
			'meta_value' => '1',
		) ) );

		$member_filter_cards = array(
			array(
				'href'    => admin_url( 'admin.php?page=dsb-members' ),
				'icon'    => '👥',
				'value'   => $all_count,
				'label'   => __( 'All Members', 'dating-site-builder' ),
				'tone'    => 'members',
				'current' => 'all' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-members&filter=pending' ),
				'icon'    => '⏳',
				'value'   => $pending_count,
				'label'   => __( 'Pending Approval', 'dating-site-builder' ),
				'tone'    => 'warning',
				'current' => 'pending' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-members&filter=suspended' ),
				'icon'    => '⏸️',
				'value'   => $suspended_count,
				'label'   => __( 'Suspended', 'dating-site-builder' ),
				'tone'    => 'muted',
				'current' => 'suspended' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-members&filter=banned' ),
				'icon'    => '🚫',
				'value'   => $banned_count,
				'label'   => __( 'Banned', 'dating-site-builder' ),
				'tone'    => 'danger',
				'current' => 'banned' === $filter,
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Members', 'dating-site-builder' ); ?></h1>
			<?php $this->render_admin_filter_cards( $member_filter_cards ); ?>

			<form method="get" class="search-form dsb-members-search-form">
				<input type="hidden" name="page" value="dsb-members">
				<?php if ( $filter !== 'all' ) : ?>
					<input type="hidden" name="filter" value="<?php echo esc_attr( $filter ); ?>">
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="member-search-input"><?php esc_html_e( 'Search Members', 'dating-site-builder' ); ?></label>
					<input type="search" id="member-search-input" name="s" value="<?php echo esc_attr( $search ); ?>">
					<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Search Members', 'dating-site-builder' ); ?>">
				</p>
			</form>

			<form method="post">
				<?php wp_nonce_field( 'dsb_members_bulk', 'dsb_members_nonce' ); ?>
				
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<select name="dsb_bulk_action">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'dating-site-builder' ); ?></option>
							<option value="approve"><?php esc_html_e( 'Approve', 'dating-site-builder' ); ?></option>
							<option value="ban"><?php esc_html_e( 'Ban', 'dating-site-builder' ); ?></option>
							<option value="unban"><?php esc_html_e( 'Unban', 'dating-site-builder' ); ?></option>
						</select>
						<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'dating-site-builder' ); ?>">
					</div>
					<div class="tablenav-pages">
						<span class="displaying-num"><?php printf( esc_html__( '%s items', 'dating-site-builder' ), number_format_i18n( $total_members ) ); ?></span>
					</div>
				</div>

				<table class="wp-list-table widefat fixed striped users">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
							<th scope="col"><?php esc_html_e( 'Photo', 'dating-site-builder' ); ?></th>
							<?php $this->render_member_sortable_th( __( 'Username', 'dating-site-builder' ), 'display_name', $orderby, $order, $filter, $search ); ?>
							<?php $this->render_member_sortable_th( __( 'Email', 'dating-site-builder' ), 'email', $orderby, $order, $filter, $search ); ?>
							<?php $this->render_member_sortable_th( __( 'Registered', 'dating-site-builder' ), 'registered', $orderby, $order, $filter, $search ); ?>
							<?php $this->render_member_sortable_th( __( 'Status', 'dating-site-builder' ), 'status', $orderby, $order, $filter, $search ); ?>
							<th scope="col"><?php esc_html_e( 'Actions', 'dating-site-builder' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $members ) ) : ?>
							<tr>
								<td colspan="7"><?php esc_html_e( 'No members found.', 'dating-site-builder' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $members as $member ) : 
								$is_approved = get_user_meta( $member->ID, 'dsb_profile_approved', true );
								$is_suspended = get_user_meta( $member->ID, 'dsb_suspended', true );
								$is_banned = get_user_meta( $member->ID, 'dsb_banned', true );
								$norm_photos = DSB_Frontend::normalize_photos( get_user_meta( $member->ID, 'dsb_photos', true ) );
								$main_photo = ! empty( $norm_photos ) ? $norm_photos[0]['url'] : '';
							?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="member_ids[]" value="<?php echo esc_attr( $member->ID ); ?>">
									</th>
									<td>
										<?php if ( $main_photo ) : ?>
											<img src="<?php echo esc_url( $main_photo ); ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
										<?php else : ?>
											<?php echo get_avatar( $member->ID, 40 ); ?>
										<?php endif; ?>
									</td>
									<td>
										<strong><?php echo esc_html( $member->display_name ); ?></strong>
										<br><small><?php echo esc_html( $member->user_login ); ?></small>
									</td>
									<td><?php echo esc_html( $member->user_email ); ?></td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $member->user_registered ) ) ); ?></td>
									<td>
										<?php if ( $is_banned ) : ?>
											<span class="dsb-status dsb-status-banned"><?php esc_html_e( 'Banned', 'dating-site-builder' ); ?></span>
										<?php elseif ( $is_suspended ) : ?>
											<span class="dsb-status dsb-status-suspended"><?php esc_html_e( 'Suspended', 'dating-site-builder' ); ?></span>
										<?php elseif ( ! $is_approved ) : ?>
											<span class="dsb-status dsb-status-pending"><?php esc_html_e( 'Pending', 'dating-site-builder' ); ?></span>
										<?php else : ?>
											<span class="dsb-status dsb-status-active"><?php esc_html_e( 'Active', 'dating-site-builder' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $member->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'dating-site-builder' ); ?></a>
										<?php if ( $is_banned ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-members&action=unban&user_id=' . $member->ID ), 'dsb_member_action' ) ); ?>" class="button button-small"><?php esc_html_e( 'Unban', 'dating-site-builder' ); ?></a>
										<?php else : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-members&action=ban&user_id=' . $member->ID ), 'dsb_member_action' ) ); ?>" class="button button-small" style="color:#dc3545;"><?php esc_html_e( 'Ban', 'dating-site-builder' ); ?></a>
										<?php endif; ?>
										<?php if ( ! $is_approved ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-members&action=approve&user_id=' . $member->ID ), 'dsb_member_action' ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Approve', 'dating-site-builder' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							$page_links = paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							) );
							echo $page_links;
							?>
						</div>
					</div>
				<?php endif; ?>
			</form>
		</div>

		<style>
			.dsb-status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
			.dsb-status-active { background: #d4edda; color: #155724; }
			.dsb-status-pending { background: #fff3cd; color: #856404; }
			.dsb-status-suspended { background: #ffeaa7; color: #856404; }
			.dsb-status-banned { background: #f8d7da; color: #721c24; }
			.dsb-members-search-form { float: right; clear: both; margin-bottom: 10px; }
		</style>
		<?php
	}

	/**
	 * Render a sortable members table header.
	 *
	 * @param string $label Column label.
	 * @param string $column Sort column key.
	 * @param string $current_orderby Current active orderby key.
	 * @param string $current_order Current active order direction.
	 * @param string $filter Current filter value.
	 * @param string $search Current search term.
	 */
	private function render_member_sortable_th( $label, $column, $current_orderby, $current_order, $filter, $search ) {
		$is_sorted  = ( $current_orderby === $column );
		$next_order = $is_sorted && 'ASC' === $current_order ? 'DESC' : 'ASC';

		$args = array(
			'page'    => 'dsb-members',
			'orderby' => $column,
			'order'   => $next_order,
			'paged'   => 1,
		);

		if ( 'all' !== $filter ) {
			$args['filter'] = $filter;
		}

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$sort_url    = add_query_arg( $args, admin_url( 'admin.php' ) );
		$sort_class  = $is_sorted ? 'sorted ' . strtolower( $current_order ) : 'sortable';
		$aria_sort   = $is_sorted ? strtolower( $current_order ) : 'none';
		$indicator   = $is_sorted ? ( 'ASC' === $current_order ? '▲' : '▼' ) : '↕';
		?>
		<th scope="col" class="manage-column column-<?php echo esc_attr( $column ); ?> <?php echo esc_attr( $sort_class ); ?>" aria-sort="<?php echo esc_attr( $aria_sort ); ?>">
			<a href="<?php echo esc_url( $sort_url ); ?>" class="dsb-member-sort-link">
				<span><?php echo esc_html( $label ); ?></span>
				<span class="dsb-sort-indicator" aria-hidden="true"><?php echo esc_html( $indicator ); ?></span>
			</a>
		</th>
		<?php
	}

	/**
	 * Convert a member object into a sortable status rank.
	 *
	 * @param WP_User $member Member object.
	 * @return int
	 */
	private function get_member_status_rank( $member ) {
		$is_banned = get_user_meta( $member->ID, 'dsb_banned', true );
		if ( $is_banned ) {
			return 3;
		}

		$is_suspended = get_user_meta( $member->ID, 'dsb_suspended', true );
		if ( $is_suspended ) {
			return 2;
		}

		$is_approved = get_user_meta( $member->ID, 'dsb_profile_approved', true );
		if ( ! $is_approved ) {
			return 1;
		}

		return 0;
	}

	/**
	 * Render reports page.
	 */
	public function render_reports() {
		global $wpdb;
		$reports_table = $wpdb->prefix . 'dsb_reports';

		// Handle resolve action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'resolve' && isset( $_GET['report_id'] ) ) {
			check_admin_referer( 'dsb_report_action' );
			$report_id = intval( $_GET['report_id'] );
			$wpdb->update(
				$reports_table,
				array(
					'status'      => 'resolved',
					'resolved_at' => current_time( 'mysql' ),
					'resolved_by' => get_current_user_id(),
				),
				array( 'id' => $report_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Report resolved.', 'dating-site-builder' ) . '</p></div>';
		}

		// Handle dismiss action
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'dismiss' && isset( $_GET['report_id'] ) ) {
			check_admin_referer( 'dsb_report_action' );
			$report_id = intval( $_GET['report_id'] );
			$wpdb->update(
				$reports_table,
				array(
					'status'      => 'dismissed',
					'resolved_at' => current_time( 'mysql' ),
					'resolved_by' => get_current_user_id(),
				),
				array( 'id' => $report_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Report dismissed.', 'dating-site-builder' ) . '</p></div>';
		}

		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'pending';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 20;
		$offset = ( $paged - 1 ) * $per_page;

		// Get counts
		$pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'" );
		$resolved_count = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'resolved'" );
		$dismissed_count = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'dismissed'" );
		$all_count = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table" );

		// Build query
		$where = '';
		if ( $filter !== 'all' ) {
			$where = $wpdb->prepare( " WHERE status = %s", $filter );
		}

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table $where" );
		$total_pages = ceil( $total / $per_page );

		$reports = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $reports_table $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		$report_filter_cards = array(
			array(
				'href'    => admin_url( 'admin.php?page=dsb-reports&filter=pending' ),
				'icon'    => '🚩',
				'value'   => $pending_count ?: 0,
				'label'   => __( 'Pending', 'dating-site-builder' ),
				'tone'    => 'warning',
				'current' => 'pending' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-reports&filter=resolved' ),
				'icon'    => '✅',
				'value'   => $resolved_count ?: 0,
				'label'   => __( 'Resolved', 'dating-site-builder' ),
				'tone'    => 'success',
				'current' => 'resolved' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-reports&filter=dismissed' ),
				'icon'    => '🗂️',
				'value'   => $dismissed_count ?: 0,
				'label'   => __( 'Dismissed', 'dating-site-builder' ),
				'tone'    => 'muted',
				'current' => 'dismissed' === $filter,
			),
			array(
				'href'    => admin_url( 'admin.php?page=dsb-reports&filter=all' ),
				'icon'    => '📋',
				'value'   => $all_count ?: 0,
				'label'   => __( 'All Reports', 'dating-site-builder' ),
				'tone'    => 'default',
				'current' => 'all' === $filter,
			),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'User Reports', 'dating-site-builder' ); ?></h1>
			<?php $this->render_admin_filter_cards( $report_filter_cards ); ?>

			<table class="wp-list-table widefat fixed striped dsb-admin-report-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Reporter', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reported User', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reason', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Details', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'dating-site-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'dating-site-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $reports ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No reports found.', 'dating-site-builder' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $reports as $report ) : 
							$reporter = get_userdata( $report->reporter_id );
							$reported = get_userdata( $report->reported_id );
						?>
							<tr>
								<td>
									<?php if ( $reporter ) : ?>
										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $reporter->ID ) ); ?>">
											<?php echo esc_html( $reporter->display_name ); ?>
										</a>
									<?php else : ?>
										<?php esc_html_e( 'Deleted User', 'dating-site-builder' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $reported ) : ?>
										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $reported->ID ) ); ?>">
											<strong><?php echo esc_html( $reported->display_name ); ?></strong>
										</a>
									<?php else : ?>
										<?php esc_html_e( 'Deleted User', 'dating-site-builder' ); ?>
									<?php endif; ?>
								</td>
								<td><span class="dsb-reason-badge"><?php echo esc_html( ucwords( str_replace( '_', ' ', $report->reason ) ) ); ?></span></td>
								<td><?php echo esc_html( wp_trim_words( $report->details, 15 ) ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->created_at ) ) ); ?></td>
								<td>
									<?php if ( $report->status === 'pending' ) : ?>
										<span class="dsb-status dsb-status-pending"><?php esc_html_e( 'Pending', 'dating-site-builder' ); ?></span>
									<?php elseif ( $report->status === 'resolved' ) : ?>
										<span class="dsb-status dsb-status-active"><?php esc_html_e( 'Resolved', 'dating-site-builder' ); ?></span>
									<?php else : ?>
										<span class="dsb-status dsb-status-dismissed"><?php esc_html_e( 'Dismissed', 'dating-site-builder' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $report->status === 'pending' ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-reports&action=resolve&report_id=' . $report->id ), 'dsb_report_action' ) ); ?>" class="button button-small button-primary"><?php esc_html_e( 'Resolve', 'dating-site-builder' ); ?></a>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-reports&action=dismiss&report_id=' . $report->id ), 'dsb_report_action' ) ); ?>" class="button button-small"><?php esc_html_e( 'Dismiss', 'dating-site-builder' ); ?></a>
										<?php if ( $reported ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsb-members&action=ban&user_id=' . $reported->ID ), 'dsb_member_action' ) ); ?>" class="button button-small" style="color:#dc3545;"><?php esc_html_e( 'Ban User', 'dating-site-builder' ); ?></a>
										<?php endif; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $total_pages,
							'current'   => $paged,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.dsb-status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
			.dsb-status-active { background: #d4edda; color: #155724; }
			.dsb-status-pending { background: #fff3cd; color: #856404; }
			.dsb-status-dismissed { background: #e2e3e5; color: #383d41; }
			.dsb-reason-badge { background: #e9ecef; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
		</style>
		<?php
	}

	/**
	 * Persist all settings posted from the Settings page.
	 *
	 * Mirrors the wizard's save_wizard_data() coverage so an admin
	 * can change every wizard option here without ever needing to
	 * re-run the wizard. Unlike the wizard (which only updates
	 * fields present in $_POST so step navigation does not wipe
	 * skipped fields), this routine treats unchecked checkboxes as
	 * false and missing array fields as empty arrays so the
	 * Settings UI is the single source of truth.
	 */
	private function save_settings_data() {
		// Plain text / int / string options.
		$text_options = array(
			'dsb_site_name'           => 'sanitize_text_field',
			'dsb_color_theme'         => 'sanitize_text_field',
			'dsb_template_style'      => 'sanitize_text_field',
			'dsb_site_type'           => 'sanitize_text_field',
			'dsb_default_country'     => 'sanitize_text_field',
			'dsb_photo_privacy_mode'  => 'sanitize_text_field',
			'dsb_adult_age_gate_mode' => 'sanitize_text_field',
			'dsb_matching_mode'       => 'sanitize_text_field',
			'dsb_header_logo_size'    => 'sanitize_text_field',
			'dsb_suspend_reason_options' => 'sanitize_textarea_field',
			'dsb_cancel_reason_options'  => 'sanitize_textarea_field',
		);
		foreach ( $text_options as $opt => $sanitizer ) {
			if ( isset( $_POST[ $opt ] ) ) {
				update_option( $opt, call_user_func( $sanitizer, wp_unslash( $_POST[ $opt ] ) ) );
			}
		}

		$int_options = array(
			'dsb_minimum_age' => 18,
			'dsb_max_photos'  => 10,
			'dsb_site_logo'   => 0,
		);
		foreach ( $int_options as $opt => $default ) {
			if ( isset( $_POST[ $opt ] ) ) {
				update_option( $opt, intval( $_POST[ $opt ] ) );
			}
		}

		// Checkbox options - unchecked boxes are not posted, so we
		// explicitly write false to keep them in sync.
		$bool_options = array(
			'dsb_require_email_verification',
			'dsb_require_profile_approval',
			'dsb_allow_custom_gender',
			'dsb_allow_multiple_interests',
			'dsb_enable_private_photos',
			'dsb_enable_messaging',
			'dsb_enable_likes',
			'dsb_require_mutual_like',
			'dsb_enable_blocking',
			'dsb_enable_reporting',
			'dsb_membership_enabled',
		);
		foreach ( $bool_options as $opt ) {
			update_option( $opt, ! empty( $_POST[ $opt ] ) );
		}

		// Multi-value (checkbox group) options.
		$array_options = array(
			'dsb_enabled_field_groups',
			'dsb_accessibility_fields',
			'dsb_public_stats_enabled',
		);
		foreach ( $array_options as $opt ) {
			$value = isset( $_POST[ $opt ] ) && is_array( $_POST[ $opt ] )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $opt ] ) )
				: array();
			update_option( $opt, $value );
		}

		// Bust the public stats transient so the new selection is
		// visible immediately on the front end.
		delete_transient( DSB_Stats::CACHE_KEY );

		// Apply the chosen Site Logo as the WordPress Site Icon, the
		// same way the wizard does on completion. No-op when the
		// admin has not picked a logo.
		$this->apply_site_logo_as_site_icon();
	}

	/**
	 * Render settings page.
	 *
	 * Exposes every option configured by the Setup Wizard so admins
	 * can edit them without re-running the wizard.
	 */
	public function render_settings() {
		// Save settings
		if ( isset( $_POST['dsb_save_settings'] ) ) {
			check_admin_referer( 'dsb_settings', 'dsb_settings_nonce' );
			$this->save_settings_data();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'dating-site-builder' ) . '</p></div>';
		}

		// Get current values - defaults match the wizard step renderers.
		$site_name              = get_option( 'dsb_site_name', '' );
		$site_logo_id           = (int) get_option( 'dsb_site_logo', 0 );
		$site_logo_url          = $site_logo_id ? wp_get_attachment_image_url( $site_logo_id, 'medium' ) : '';
		$header_logo_size       = get_option( 'dsb_header_logo_size', 'full' );
		$valid_logo_sizes       = array( 'small', 'medium', 'large', 'full' );
		if ( ! in_array( $header_logo_size, $valid_logo_sizes, true ) ) {
			$header_logo_size = 'full';
		}
		$color_theme            = get_option( 'dsb_color_theme', 'romantic_red' );
		$template_style         = get_option( 'dsb_template_style', 'modern' );
		$site_type              = get_option( 'dsb_site_type', 'standard' );
		$minimum_age            = get_option( 'dsb_minimum_age', 18 );
		$default_country        = get_option( 'dsb_default_country', '' );
		$require_email          = get_option( 'dsb_require_email_verification', false );
		$require_approval       = get_option( 'dsb_require_profile_approval', false );
		$enabled_groups         = get_option( 'dsb_enabled_field_groups', array( 'basics', 'about', 'lifestyle' ) );
		if ( ! is_array( $enabled_groups ) ) {
			$enabled_groups = array();
		}
		$allow_custom_gender    = get_option( 'dsb_allow_custom_gender', true );
		$allow_multi_interests  = get_option( 'dsb_allow_multiple_interests', true );
		$accessibility_fields   = get_option( 'dsb_accessibility_fields', array() );
		if ( ! is_array( $accessibility_fields ) ) {
			$accessibility_fields = array();
		}
		$max_photos             = get_option( 'dsb_max_photos', 10 );
		$photo_privacy          = get_option( 'dsb_photo_privacy_mode', 'public' );
		$enable_private         = get_option( 'dsb_enable_private_photos', false );
		$age_gate_mode          = get_option( 'dsb_adult_age_gate_mode', 'checkbox' );
		$matching_mode          = get_option( 'dsb_matching_mode', 'hybrid' );
		$enable_messaging       = get_option( 'dsb_enable_messaging', true );
		$enable_likes           = get_option( 'dsb_enable_likes', true );
		$require_mutual         = get_option( 'dsb_require_mutual_like', false );
		$enable_blocking        = get_option( 'dsb_enable_blocking', true );
		$enable_reporting       = get_option( 'dsb_enable_reporting', true );
		$membership_enabled     = (bool) get_option( 'dsb_membership_enabled', false );
		$suspend_reason_options = (string) get_option( 'dsb_suspend_reason_options', implode( "\n", DSB_Profile_Fields::get_default_account_reason_lines( 'suspend' ) ) );
		$cancel_reason_options  = (string) get_option( 'dsb_cancel_reason_options', implode( "\n", DSB_Profile_Fields::get_default_account_reason_lines( 'delete' ) ) );
		$enabled_public_stats   = DSB_Stats::get_enabled_public_keys();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dating Site Settings', 'dating-site-builder' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Every option configured by the Setup Wizard is also available here, so you can fine-tune your dating site without re-running the wizard.', 'dating-site-builder' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'dsb_settings', 'dsb_settings_nonce' ); ?>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Site Identity & Theme', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsb_site_name"><?php esc_html_e( 'Site Name', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="text" name="dsb_site_name" id="dsb_site_name" value="<?php echo esc_attr( $site_name ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Cupid, LoveMatch, DateNight', 'dating-site-builder' ); ?>">
								<p class="description"><?php esc_html_e( 'Used in the admin menu (e.g., "Cupid Settings") and on the front-end branding.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_site_logo"><?php esc_html_e( 'Site Logo', 'dating-site-builder' ); ?></label></th>
							<td>
								<div class="dsb-logo-uploader">
									<input type="hidden" id="dsb_site_logo" name="dsb_site_logo" value="<?php echo esc_attr( $site_logo_id ); ?>">
									<div class="dsb-logo-preview" id="dsb-logo-preview" style="<?php echo $site_logo_url ? '' : 'display:none;'; ?>">
										<?php if ( $site_logo_url ) : ?>
											<img src="<?php echo esc_url( $site_logo_url ); ?>" alt="<?php esc_attr_e( 'Site logo preview', 'dating-site-builder' ); ?>" style="max-width:200px;max-height:200px;display:block;margin-bottom:8px;">
										<?php endif; ?>
									</div>
									<button type="button" class="button" id="dsb-logo-upload-btn">
										<?php echo $site_logo_id ? esc_html__( 'Change Logo', 'dating-site-builder' ) : esc_html__( 'Choose / Upload Logo', 'dating-site-builder' ); ?>
									</button>
									<button type="button" class="button-link dsb-logo-remove-btn" id="dsb-logo-remove-btn" style="<?php echo $site_logo_id ? '' : 'display:none;'; ?>;color:#b32d2e;margin-left:8px;">
										<?php esc_html_e( 'Remove', 'dating-site-builder' ); ?>
									</button>
								</div>
								<p class="description"><?php esc_html_e( 'Used in the plugin\'s top navigation bar and as the WordPress Site Icon (browser tab favicon, mobile bookmarks, etc.). Saving these settings will also update the Site Icon.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_header_logo_size"><?php esc_html_e( 'Header Logo Size', 'dating-site-builder' ); ?></label></th>
							<td>
								<select name="dsb_header_logo_size" id="dsb_header_logo_size">
									<option value="small" <?php selected( $header_logo_size, 'small' ); ?>><?php esc_html_e( 'Small (40px)', 'dating-site-builder' ); ?></option>
									<option value="medium" <?php selected( $header_logo_size, 'medium' ); ?>><?php esc_html_e( 'Medium (64px)', 'dating-site-builder' ); ?></option>
									<option value="large" <?php selected( $header_logo_size, 'large' ); ?>><?php esc_html_e( 'Large (96px)', 'dating-site-builder' ); ?></option>
									<option value="full" <?php selected( $header_logo_size, 'full' ); ?>><?php esc_html_e( 'Full (140px - native aspect ratio)', 'dating-site-builder' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Controls how tall the logo image renders in the member-area top bar. "Full" uses the largest size that still fits the header.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_color_theme"><?php esc_html_e( 'Color Theme', 'dating-site-builder' ); ?></label></th>
							<td>
								<select name="dsb_color_theme" id="dsb_color_theme">
									<option value="romantic_red" <?php selected( $color_theme, 'romantic_red' ); ?>><?php esc_html_e( 'Romantic Red', 'dating-site-builder' ); ?></option>
									<option value="ocean_blue" <?php selected( $color_theme, 'ocean_blue' ); ?>><?php esc_html_e( 'Ocean Blue', 'dating-site-builder' ); ?></option>
									<option value="forest_green" <?php selected( $color_theme, 'forest_green' ); ?>><?php esc_html_e( 'Forest Green', 'dating-site-builder' ); ?></option>
									<option value="royal_purple" <?php selected( $color_theme, 'royal_purple' ); ?>><?php esc_html_e( 'Royal Purple', 'dating-site-builder' ); ?></option>
									<option value="sunset_orange" <?php selected( $color_theme, 'sunset_orange' ); ?>><?php esc_html_e( 'Sunset Orange', 'dating-site-builder' ); ?></option>
									<option value="midnight_dark" <?php selected( $color_theme, 'midnight_dark' ); ?>><?php esc_html_e( 'Midnight Dark', 'dating-site-builder' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_template_style"><?php esc_html_e( 'Template Style', 'dating-site-builder' ); ?></label></th>
							<td>
								<select name="dsb_template_style" id="dsb_template_style">
									<option value="modern" <?php selected( $template_style, 'modern' ); ?>><?php esc_html_e( 'Modern - gradients & cards', 'dating-site-builder' ); ?></option>
									<option value="glassmorphism" <?php selected( $template_style, 'glassmorphism' ); ?>><?php esc_html_e( 'Glassmorphism - frosted glass', 'dating-site-builder' ); ?></option>
									<option value="minimalist" <?php selected( $template_style, 'minimalist' ); ?>><?php esc_html_e( 'Minimalist - clean & flat', 'dating-site-builder' ); ?></option>
									<option value="bold_dark" <?php selected( $template_style, 'bold_dark' ); ?>><?php esc_html_e( 'Bold Dark - dark mode', 'dating-site-builder' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Overall layout / visual treatment for member-facing pages.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_site_type"><?php esc_html_e( 'Site Type', 'dating-site-builder' ); ?></label></th>
							<td>
								<select name="dsb_site_type" id="dsb_site_type">
									<option value="standard" <?php selected( $site_type, 'standard' ); ?>><?php esc_html_e( 'Standard Dating Site', 'dating-site-builder' ); ?></option>
									<option value="adult" <?php selected( $site_type, 'adult' ); ?>><?php esc_html_e( 'Adult Dating Site (18+ only)', 'dating-site-builder' ); ?></option>
									<option value="swingers" <?php selected( $site_type, 'swingers' ); ?>><?php esc_html_e( 'Swingers / Alternative Lifestyle', 'dating-site-builder' ); ?></option>
									<option value="ndis" <?php selected( $site_type, 'ndis' ); ?>><?php esc_html_e( 'All Abilities / NDIS-Focused', 'dating-site-builder' ); ?></option>
									<option value="custom" <?php selected( $site_type, 'custom' ); ?>><?php esc_html_e( 'Custom', 'dating-site-builder' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Basic Settings', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsb_minimum_age"><?php esc_html_e( 'Minimum Age', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="number" name="dsb_minimum_age" id="dsb_minimum_age" value="<?php echo esc_attr( $minimum_age ); ?>" min="13" max="99" class="small-text">
								<p class="description"><?php esc_html_e( 'Minimum age required to register (default: 18).', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_default_country"><?php esc_html_e( 'Default Country', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="text" name="dsb_default_country" id="dsb_default_country" value="<?php echo esc_attr( $default_country ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Australia', 'dating-site-builder' ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Verification', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_require_email_verification" value="1" <?php checked( $require_email, true ); ?>>
									<?php esc_html_e( 'Require email verification for new users', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Profile Approval', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_require_profile_approval" value="1" <?php checked( $require_approval, true ); ?>>
									<?php esc_html_e( 'Require admin approval before profiles go public', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Profile Fields', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Field Groups', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="dsb_enabled_field_groups[]" value="basics" <?php checked( in_array( 'basics', $enabled_groups, true ) ); ?>>
										<?php esc_html_e( 'Basics (gender, age, orientation, location, relationship status)', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_enabled_field_groups[]" value="about" <?php checked( in_array( 'about', $enabled_groups, true ) ); ?>>
										<?php esc_html_e( 'Vibe & Interests (vibe, interaction style, interests, intent)', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_enabled_field_groups[]" value="lifestyle" <?php checked( in_array( 'lifestyle', $enabled_groups, true ) ); ?>>
										<?php esc_html_e( 'Optional Details (occupation, education, smoking, drinking)', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_enabled_field_groups[]" value="accessibility" <?php checked( in_array( 'accessibility', $enabled_groups, true ) ); ?>>
										<?php esc_html_e( 'Accessibility & Support Needs (recommended for All Abilities mode)', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_enabled_field_groups[]" value="adult_preferences" <?php checked( in_array( 'adult_preferences', $enabled_groups, true ) ); ?>>
										<?php esc_html_e( 'Adult Preferences (for Adult/Swingers sites)', 'dating-site-builder' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gender Options', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_allow_custom_gender" value="1" <?php checked( $allow_custom_gender, true ); ?>>
									<?php esc_html_e( 'Allow users to specify gender beyond male/female (non-binary, custom, prefer not to say)', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Gender Interests', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_allow_multiple_interests" value="1" <?php checked( $allow_multi_interests, true ); ?>>
									<?php esc_html_e( 'Allow users to select multiple gender interests (who they are interested in)', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr id="dsb_settings_accessibility_fields" style="<?php echo in_array( 'accessibility', $enabled_groups, true ) ? '' : 'display:none;'; ?>">
							<th scope="row"><?php esc_html_e( 'Accessibility Fields', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="dsb_accessibility_fields[]" value="communication_preference" <?php checked( in_array( 'communication_preference', $accessibility_fields, true ) ); ?>>
										<?php esc_html_e( 'Communication Preferences', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_accessibility_fields[]" value="mobility_info" <?php checked( in_array( 'mobility_info', $accessibility_fields, true ) ); ?>>
										<?php esc_html_e( 'Mobility Information', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_accessibility_fields[]" value="sensory_preferences" <?php checked( in_array( 'sensory_preferences', $accessibility_fields, true ) ); ?>>
										<?php esc_html_e( 'Sensory Preferences', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_accessibility_fields[]" value="support_needs" <?php checked( in_array( 'support_needs', $accessibility_fields, true ) ); ?>>
										<?php esc_html_e( 'Support Needs', 'dating-site-builder' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="dsb_accessibility_fields[]" value="ndis_participant" <?php checked( in_array( 'ndis_participant', $accessibility_fields, true ) ); ?>>
										<?php esc_html_e( 'NDIS Participant', 'dating-site-builder' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_max_photos"><?php esc_html_e( 'Maximum Photos', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="number" name="dsb_max_photos" id="dsb_max_photos" value="<?php echo esc_attr( $max_photos ); ?>" min="1" max="50" class="small-text">
								<p class="description"><?php esc_html_e( 'Maximum number of photos per member profile.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
					</table>
					<script>
					jQuery(document).ready(function($){
						function toggleSettingsAccessibility(){
							var checked = $('input[name="dsb_enabled_field_groups[]"][value="accessibility"]').is(':checked');
							$('#dsb_settings_accessibility_fields').toggle(checked);
						}
						toggleSettingsAccessibility();
						$('input[name="dsb_enabled_field_groups[]"]').on('change', toggleSettingsAccessibility);
					});
					</script>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Photo Privacy & Age Verification', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Photo Privacy Mode', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="dsb_photo_privacy_mode" value="public" <?php checked( $photo_privacy, 'public' ); ?>>
										<strong><?php esc_html_e( 'Public Photos', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'All profile photos are visible to all members.', 'dating-site-builder' ); ?></span>
									</label><br>
									<label>
										<input type="radio" name="dsb_photo_privacy_mode" value="blur_until_match" <?php checked( $photo_privacy, 'blur_until_match' ); ?>>
										<strong><?php esc_html_e( 'Blur Until Match', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Photos are blurred until users match (recommended for adult sites).', 'dating-site-builder' ); ?></span>
									</label><br>
									<label>
										<input type="radio" name="dsb_photo_privacy_mode" value="private" <?php checked( $photo_privacy, 'private' ); ?>>
										<strong><?php esc_html_e( 'Members Only', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Photos only visible to logged-in members.', 'dating-site-builder' ); ?></span>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Public & Private Albums', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_private_photos" value="1" <?php checked( $enable_private, true ); ?>>
									<?php esc_html_e( 'Allow members to mark photos as private (requires approval to view)', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Age Verification', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="dsb_adult_age_gate_mode" value="none" <?php checked( $age_gate_mode, 'none' ); ?>>
										<strong><?php esc_html_e( 'None', 'dating-site-builder' ); ?></strong>
									</label><br>
									<label>
										<input type="radio" name="dsb_adult_age_gate_mode" value="checkbox" <?php checked( $age_gate_mode, 'checkbox' ); ?>>
										<strong><?php esc_html_e( 'Checkbox on Registration', 'dating-site-builder' ); ?></strong>
									</label><br>
									<label>
										<input type="radio" name="dsb_adult_age_gate_mode" value="warning_page" <?php checked( $age_gate_mode, 'warning_page' ); ?>>
										<strong><?php esc_html_e( 'Warning Page', 'dating-site-builder' ); ?></strong>
									</label><br>
									<label>
										<input type="radio" name="dsb_adult_age_gate_mode" value="both" <?php checked( $age_gate_mode, 'both' ); ?>>
										<strong><?php esc_html_e( 'Both (warning page + checkbox)', 'dating-site-builder' ); ?></strong>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Matching & Discovery', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Matching Algorithm', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="dsb_matching_mode" value="simple" <?php checked( $matching_mode, 'simple' ); ?>>
										<strong><?php esc_html_e( 'Simple Preferences', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Match based on gender, age range, and location only.', 'dating-site-builder' ); ?></span>
									</label><br>
									<label>
										<input type="radio" name="dsb_matching_mode" value="interests" <?php checked( $matching_mode, 'interests' ); ?>>
										<strong><?php esc_html_e( 'Interests-Based', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Score matches based on shared interests and hobbies.', 'dating-site-builder' ); ?></span>
									</label><br>
									<label>
										<input type="radio" name="dsb_matching_mode" value="hybrid" <?php checked( $matching_mode, 'hybrid' ); ?>>
										<strong><?php esc_html_e( 'Hybrid (Recommended)', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Combine preferences, interests, and activity for best matches.', 'dating-site-builder' ); ?></span>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Messaging & Interaction', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Private Messaging', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_messaging" value="1" <?php checked( $enable_messaging, true ); ?>>
									<?php esc_html_e( 'Enable private 1-to-1 messaging', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Likes System', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_likes" value="1" <?php checked( $enable_likes, true ); ?>>
									<?php esc_html_e( 'Enable like/favorite system', 'dating-site-builder' ); ?>
								</label>
								<br><br>
								<label>
									<input type="checkbox" name="dsb_require_mutual_like" value="1" <?php checked( $require_mutual, true ); ?>>
									<?php esc_html_e( 'Require mutual like before messaging (recommended for quality control)', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Safety Features', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_blocking" value="1" <?php checked( $enable_blocking, true ); ?>>
									<?php esc_html_e( 'Allow users to block other users', 'dating-site-builder' ); ?>
								</label>
								<br><br>
								<label>
									<input type="checkbox" name="dsb_enable_reporting" value="1" <?php checked( $enable_reporting, true ); ?>>
									<?php esc_html_e( 'Enable user/message reporting system', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Monetization', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Membership Tiers', 'dating-site-builder' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="radio" name="dsb_membership_enabled" value="0" <?php checked( $membership_enabled, false ); ?>>
										<strong><?php esc_html_e( 'Free Only', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'All features available to all users.', 'dating-site-builder' ); ?></span>
									</label><br>
									<label>
										<input type="radio" name="dsb_membership_enabled" value="1" <?php checked( $membership_enabled, true ); ?>>
										<strong><?php esc_html_e( 'Free + Paid Tiers', 'dating-site-builder' ); ?></strong> &mdash;
										<span class="description"><?php esc_html_e( 'Set up free and premium membership roles (payment gateway integration required).', 'dating-site-builder' ); ?></span>
									</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Note: Payment gateway integration (Stripe, PayPal) must be added separately using the plugin hooks.', 'dating-site-builder' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Account Moderation Reasons', 'dating-site-builder' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Edit the dropdown reason lists used on the member profile page and in the Edit User screen. One reason per line. Use "Category | Reason" to group items.', 'dating-site-builder' ); ?></p>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsb_suspend_reason_options"><?php esc_html_e( 'Suspend Reasons', 'dating-site-builder' ); ?></label></th>
							<td>
								<textarea name="dsb_suspend_reason_options" id="dsb_suspend_reason_options" class="large-text code" rows="12"><?php echo esc_textarea( $suspend_reason_options ); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_cancel_reason_options"><?php esc_html_e( 'Cancel Reasons', 'dating-site-builder' ); ?></label></th>
							<td>
								<textarea name="dsb_cancel_reason_options" id="dsb_cancel_reason_options" class="large-text code" rows="18"><?php echo esc_textarea( $cancel_reason_options ); ?></textarea>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Public Stats Display', 'dating-site-builder' ); ?></h2>
					<p class="description">
						<?php
						printf(
							/* translators: %s: shortcode example */
							esc_html__( 'Choose which metrics appear in the slim banner above the Browse Members directory and inside the %s shortcode. Moderation counters (pending approvals, pending reports) are admin-only and never shown publicly.', 'dating-site-builder' ),
							'<code>[dsb_site_stats]</code>'
						);
						?>
					</p>
					<table class="form-table">
						<?php
						foreach ( DSB_Stats::get_definitions() as $stat_key => $stat_def ) :
							if ( ! empty( $stat_def['admin_only'] ) ) {
								continue;
							}
							$is_on = in_array( $stat_key, $enabled_public_stats, true );
							?>
							<tr>
								<th scope="row">
									<span aria-hidden="true" style="font-size:1.1em;margin-right:4px;"><?php echo esc_html( $stat_def['icon'] ); ?></span>
									<?php echo esc_html( $stat_def['label'] ); ?>
								</th>
								<td>
									<label>
										<input type="checkbox" name="dsb_public_stats_enabled[]" value="<?php echo esc_attr( $stat_key ); ?>" <?php checked( $is_on ); ?>>
										<?php esc_html_e( 'Show on public pages', 'dating-site-builder' ); ?>
									</label>
									<?php if ( ! empty( $stat_def['sub'] ) ) : ?>
										<p class="description"><?php echo esc_html( $stat_def['sub'] ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>

				<p class="submit">
					<input type="submit" name="dsb_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'dating-site-builder' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-wizard' ) ); ?>" class="button">
						<?php esc_html_e( 'Re-run Setup Wizard', 'dating-site-builder' ); ?>
					</a>
				</p>
			</form>
		</div>

		<style>
			.dsb-settings-section { background: #fff; padding: 1px 20px 20px; margin: 20px 0; border: 1px solid #ccd0d4; }
			.dsb-settings-section h2 { border-bottom: 1px solid #eee; padding-bottom: 10px; }
		</style>
		<?php
	}

	/**
	 * AJAX handlers for admin actions.
	 */
	public function approve_profile() {
		check_ajax_referer( 'dsb_admin_nonce', 'nonce' );
		
		if ( ! current_user_can( 'moderate_dating_profiles' ) ) {
			wp_send_json_error();
		}

		$user_id = intval( $_POST['user_id'] );
		update_user_meta( $user_id, 'dsb_profile_approved', '1' );
		
		wp_send_json_success();
	}

	public function ban_user() {
		check_ajax_referer( 'dsb_admin_nonce', 'nonce' );
		
		if ( ! current_user_can( 'moderate_dating_profiles' ) ) {
			wp_send_json_error();
		}

		$user_id = intval( $_POST['user_id'] );
		update_user_meta( $user_id, 'dsb_banned', '1' );
		
		wp_send_json_success();
	}

	public function resolve_report() {
		check_ajax_referer( 'dsb_admin_nonce', 'nonce' );
		
		if ( ! current_user_can( 'view_dating_reports' ) ) {
			wp_send_json_error();
		}

		$report_id = intval( $_POST['report_id'] );
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_reports';
		
		$wpdb->update(
			$table,
			array(
				'status'      => 'resolved',
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			),
			array( 'id' => $report_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);
		
		wp_send_json_success();
	}

	/**
	 * Determine whether the given WP_User belongs to the dating site.
	 *
	 * The photo manager only makes sense for members; we still allow
	 * administrators to see/manage it on their own profile so they can
	 * test, but plain subscribers are skipped.
	 */
	private function is_dating_user( $user ) {
		if ( ! ( $user instanceof WP_User ) ) {
			return false;
		}
		$dating_roles = array( 'dating_member', 'dating_premium', 'administrator' );
		foreach ( $dating_roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}
		// Always allow when the user has dating photos already stored.
		$photos = get_user_meta( $user->ID, 'dsb_photos', true );
		return ! empty( $photos );
	}

	/**
	 * Render the dating profile photo manager on user-edit / profile screens.
	 *
	 * Hooked into 'show_user_profile' and 'edit_user_profile' so admins can
	 * add, reorder, and remove a member's profile photos directly from the
	 * standard WP user edit screen.
	 */
	public function render_user_profile_photos( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		if ( ! $this->is_dating_user( $user ) ) {
			return;
		}

		// Make sure wp.media is available for the "Add Photo(s)" picker.
		wp_enqueue_media();

		$photos = DSB_Frontend::normalize_photos( get_user_meta( $user->ID, 'dsb_photos', true ) );
		$private_enabled = (bool) get_option( 'dsb_enable_private_photos', false );
		$max_photos = (int) get_option( 'dsb_max_photos', 10 );
		if ( $max_photos < 1 ) {
			$max_photos = 10;
		}
		?>
		<h2><?php esc_html_e( 'Profile Photos', 'dating-site-builder' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Manage this member\'s profile photos. The first photo is shown as their main photo.', 'dating-site-builder' ); ?>
		</p>
		<?php wp_nonce_field( 'dsb_user_photos_' . $user->ID, 'dsb_user_photos_nonce' ); ?>
		<input type="hidden" name="dsb_user_photos_form" value="1">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Profile Photos', 'dating-site-builder' ); ?></th>
				<td>
					<div id="dsb-user-photos" class="dsb-user-photos" data-max="<?php echo esc_attr( $max_photos ); ?>">
						<?php if ( empty( $photos ) ) : ?>
							<p class="dsb-user-photos-empty description">
								<?php esc_html_e( 'No profile photos yet. Use the button below to add photos from the media library.', 'dating-site-builder' ); ?>
							</p>
				<?php else : ?>
							<?php foreach ( $photos as $index => $photo ) : ?>
								<div class="dsb-user-photo-item">
									<img src="<?php echo esc_url( $photo['url'] ); ?>" alt="">
									<input type="hidden" name="dsb_photos[]" value="<?php echo esc_attr( $photo['url'] ); ?>">
									<input type="hidden" name="dsb_photo_privacy[]" value="<?php echo esc_attr( $photo['privacy'] ); ?>">
									<div class="dsb-user-photo-actions">
										<?php if ( 0 === $index ) : ?>
											<span class="dsb-user-photo-main-badge"><?php esc_html_e( 'Main', 'dating-site-builder' ); ?></span>
										<?php else : ?>
											<button type="button" class="button button-small dsb-user-photo-make-main"><?php esc_html_e( 'Set Main', 'dating-site-builder' ); ?></button>
										<?php endif; ?>
										<?php if ( $private_enabled ) : ?>
											<button type="button" class="button button-small dsb-user-photo-toggle-privacy">
												<?php echo 'private' === $photo['privacy'] ? esc_html__( 'Private', 'dating-site-builder' ) : esc_html__( 'Public', 'dating-site-builder' ); ?>
											</button>
										<?php endif; ?>
										<button type="button" class="button button-small dsb-user-photo-remove"><?php esc_html_e( 'Remove', 'dating-site-builder' ); ?></button>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<p>
						<button type="button" class="button button-primary" id="dsb-user-photo-add">
							<?php esc_html_e( 'Add Photo(s) from Media Library', 'dating-site-builder' ); ?>
						</button>
					</p>
					<p class="description">
						<?php
						printf(
							/* translators: %d: maximum number of profile photos. */
							esc_html__( 'Up to %d photos per member. Click "Update User" to save changes.', 'dating-site-builder' ),
							(int) $max_photos
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<style>
			.dsb-user-photos { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
			.dsb-user-photos-empty { width: 100%; margin: 0; }
			.dsb-user-photo-item { position: relative; width: 150px; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden; background: #fff; padding: 6px; box-sizing: border-box; }
			.dsb-user-photo-item img { display: block; width: 100%; height: 140px; object-fit: cover; border-radius: 2px; }
			.dsb-user-photo-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; align-items: center; justify-content: space-between; }
			.dsb-user-photo-main-badge { background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
		</style>
		<script>
		jQuery(function($){
			var mainLabel   = <?php echo wp_json_encode( __( 'Main', 'dating-site-builder' ) ); ?>;
			var setMainText = <?php echo wp_json_encode( __( 'Set Main', 'dating-site-builder' ) ); ?>;
			var removeText  = <?php echo wp_json_encode( __( 'Remove', 'dating-site-builder' ) ); ?>;
			var maxText     = <?php echo wp_json_encode( __( 'You have reached the maximum number of photos.', 'dating-site-builder' ) ); ?>;
			var pickerTitle = <?php echo wp_json_encode( __( 'Choose Profile Photo(s)', 'dating-site-builder' ) ); ?>;
			var pickerBtn   = <?php echo wp_json_encode( __( 'Add to profile', 'dating-site-builder' ) ); ?>;
			var emptyText   = <?php echo wp_json_encode( __( 'No profile photos yet. Use the button below to add photos from the media library.', 'dating-site-builder' ) ); ?>;

			var $list = $('#dsb-user-photos');
			var maxPhotos = parseInt( $list.data('max'), 10 ) || 10;

			function count(){
				return $list.find('.dsb-user-photo-item').length;
			}

			function refreshLayout(){
				$list.find('.dsb-user-photos-empty').remove();
				if ( 0 === count() ) {
					$list.append(
						$('<p class="dsb-user-photos-empty description"></p>').text( emptyText )
					);
					return;
				}
				$list.find('.dsb-user-photo-item').each(function(idx){
					var $actions = $(this).find('.dsb-user-photo-actions');
					$actions.empty();
					if ( 0 === idx ) {
						$('<span class="dsb-user-photo-main-badge"></span>')
							.text( mainLabel )
							.appendTo( $actions );
					} else {
						$('<button type="button" class="button button-small dsb-user-photo-make-main"></button>')
							.text( setMainText )
							.appendTo( $actions );
					}
					$('<button type="button" class="button button-small dsb-user-photo-remove"></button>')
						.text( removeText )
						.appendTo( $actions );
				});
			}

		function buildItem( url ){
				var $item = $('<div class="dsb-user-photo-item"></div>');
				$('<img alt="">').attr('src', url).appendTo($item);
				$('<input type="hidden" name="dsb_photos[]">').val(url).appendTo($item);
				$('<input type="hidden" name="dsb_photo_privacy[]">').val('public').appendTo($item);
				$('<div class="dsb-user-photo-actions"></div>').appendTo($item);
				return $item;
			}

			$list.on('click', '.dsb-user-photo-toggle-privacy', function(e){
				e.preventDefault();
				var $btn = $(this);
				var $item = $btn.closest('.dsb-user-photo-item');
				var $input = $item.find('input[name="dsb_photo_privacy[]"]');
				if ( $input.val() === 'private' ) {
					$input.val('public');
					$btn.text('Public');
				} else {
					$input.val('private');
					$btn.text('Private');
				}
			});

			$list.on('click', '.dsb-user-photo-remove', function(e){
				e.preventDefault();
				$(this).closest('.dsb-user-photo-item').remove();
				refreshLayout();
			});

			$list.on('click', '.dsb-user-photo-make-main', function(e){
				e.preventDefault();
				var $item = $(this).closest('.dsb-user-photo-item');
				$list.prepend($item);
				refreshLayout();
			});

			var frame;
			$('#dsb-user-photo-add').on('click', function(e){
				e.preventDefault();
				if ( count() >= maxPhotos ) {
					window.alert( maxText );
					return;
				}
				if ( ! frame ) {
					frame = wp.media({
						title: pickerTitle,
						button: { text: pickerBtn },
						multiple: true,
						library: { type: 'image' }
					});
					frame.on('select', function(){
						var attachments = frame.state().get('selection').toJSON();
						for ( var i = 0; i < attachments.length; i++ ) {
							if ( count() >= maxPhotos ) {
								window.alert( maxText );
								break;
							}
							var url = attachments[i].url;
							if ( url ) {
								$list.append( buildItem( url ) );
							}
						}
						refreshLayout();
					});
				}
				frame.open();
			});
		});
		</script>
		<?php
	}

	/**
	 * Persist the dating profile photo list when a user record is saved.
	 *
	 * Hooked into 'personal_options_update' (own profile) and
	 * 'edit_user_profile_update' (admins editing other users).
	 */
	public function save_user_profile_photos( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['dsb_user_photos_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsb_user_photos_nonce'] ) ), 'dsb_user_photos_' . $user_id ) ) {
			return;
		}
		// Refuse to touch dsb_photos meta unless the photo manager UI
		// was actually rendered as part of this form submission. This
		// prevents the existing photo list from being silently wiped
		// when another plugin or partial form posts the user-edit page
		// without the dating profile section visible.
		if ( empty( $_POST['dsb_user_photos_form'] ) ) {
			return;
		}

		$max_photos = (int) get_option( 'dsb_max_photos', 10 );
		if ( $max_photos < 1 ) {
			$max_photos = 10;
		}

		$submitted_urls = ( isset( $_POST['dsb_photos'] ) && is_array( $_POST['dsb_photos'] ) )
			? wp_unslash( $_POST['dsb_photos'] )
			: array();
		$submitted_privacy = ( isset( $_POST['dsb_photo_privacy'] ) && is_array( $_POST['dsb_photo_privacy'] ) )
			? wp_unslash( $_POST['dsb_photo_privacy'] )
			: array();

		$clean = array();
		foreach ( $submitted_urls as $i => $url ) {
			$url = esc_url_raw( $url );
			if ( '' === $url ) {
				continue;
			}
			$privacy = isset( $submitted_privacy[ $i ] ) && 'private' === $submitted_privacy[ $i ] ? 'private' : 'public';
			$clean[] = array( 'url' => $url, 'privacy' => $privacy );
			if ( count( $clean ) >= $max_photos ) {
				break;
			}
		}

		// Extra safety net: if the submitted form somehow has zero
		// valid photo URLs but the user already has photos stored,
		// keep the existing photos rather than wiping them.
		if ( empty( $clean ) ) {
			$existing = get_user_meta( $user_id, 'dsb_photos', true );
			if ( ! empty( $existing ) && is_array( $existing ) ) {
				return;
			}
		}

		update_user_meta( $user_id, 'dsb_photos', $clean );
	}

	/**
	 * Render every dating profile field on the standard WP user-edit
	 * screen so admins can review and (importantly) moderate any
	 * inappropriate content a member may have posted.
	 *
	 * Mirrors the front-end profile-edit form's input types so the
	 * admin gets exactly the same controls a member would see.
	 *
	 * Hooked into 'show_user_profile' and 'edit_user_profile'.
	 */
	public function render_user_profile_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		if ( ! $this->is_dating_user( $user ) ) {
			return;
		}

		global $wpdb;

		$fields = DSB_Profile_Fields::get_edit_fields();
		if ( empty( $fields ) ) {
			return;
		}
		$profile_kind_value = (string) get_user_meta( $user->ID, 'dsb_profile_kind', true );
		$is_couple_profile  = 0 === strpos( $profile_kind_value, 'couple_' );
		?>
		<h2><?php esc_html_e( 'Dating Profile Fields', 'dating-site-builder' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Edit any of this member\'s dating profile fields below. Useful for moderating inappropriate content — changes save when you click "Update User" / "Update Profile".', 'dating-site-builder' ); ?>
		</p>
		<?php wp_nonce_field( 'dsb_user_fields_' . $user->ID, 'dsb_user_fields_nonce' ); ?>
		<input type="hidden" name="dsb_user_fields_form" value="1">
		<table class="form-table" role="presentation">
			<?php foreach ( $fields as $field_key => $field ) :
				$value     = get_user_meta( $user->ID, 'dsb_' . $field_key, true );
				$field_id  = 'dsb_field_' . $field_key;
				$type      = isset( $field['type'] ) ? $field['type'] : 'text';
				$maxlen    = ! empty( $field['maxlength'] ) ? (int) $field['maxlength'] : 0;
				$row_attrs = '';
				if ( ! empty( $field['requires_couple'] ) ) {
					$row_attrs = ' data-requires-couple="1"';
					if ( ! $is_couple_profile ) {
						$row_attrs .= ' style="display:none;"';
					}
				}
				?>
				<tr<?php echo $row_attrs; ?>>
					<th scope="row">
						<label for="<?php echo esc_attr( $field_id ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
						</label>
					</th>
					<td>
						<?php
						switch ( $type ) {
							case 'date':
								?>
								<input type="date"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="dsb_field[<?php echo esc_attr( $field_key ); ?>]"
									value="<?php echo esc_attr( is_array( $value ) ? '' : $value ); ?>">
								<?php
								break;

							case 'textarea':
								?>
								<textarea id="<?php echo esc_attr( $field_id ); ?>"
									name="dsb_field[<?php echo esc_attr( $field_key ); ?>]"
									rows="4"
									class="large-text"
									<?php echo $maxlen ? 'maxlength="' . esc_attr( $maxlen ) . '"' : ''; ?>><?php echo esc_textarea( is_array( $value ) ? '' : $value ); ?></textarea>
								<?php
								break;

							case 'select':
								?>
								<select id="<?php echo esc_attr( $field_id ); ?>" name="dsb_field[<?php echo esc_attr( $field_key ); ?>]">
									<option value=""><?php esc_html_e( '— Select —', 'dating-site-builder' ); ?></option>
									<?php
									$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
									foreach ( $options as $opt_key => $opt_label ) :
										if ( '' === $opt_key ) {
											continue;
										}
										?>
										<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $value, $opt_key ); ?>>
											<?php echo esc_html( $opt_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php
								break;

							case 'checkbox':
								$selected_values = is_array( $value ) ? $value : array();
								$options         = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
								?>
								<fieldset>
									<?php foreach ( $options as $opt_key => $opt_label ) : ?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox"
												name="dsb_field[<?php echo esc_attr( $field_key ); ?>][]"
												value="<?php echo esc_attr( $opt_key ); ?>"
												<?php checked( in_array( (string) $opt_key, array_map( 'strval', $selected_values ), true ) ); ?>>
											<?php echo esc_html( $opt_label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<?php
								break;

							case 'text':
							default:
								?>
								<input type="text"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="dsb_field[<?php echo esc_attr( $field_key ); ?>]"
									value="<?php echo esc_attr( is_array( $value ) ? '' : $value ); ?>"
									class="regular-text"
									<?php echo $maxlen ? 'maxlength="' . esc_attr( $maxlen ) . '"' : ''; ?>>
								<?php
								break;
						}
						?>
						<?php if ( ! empty( $field['description'] ) ) : ?>
							<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>

		<?php
		$suspend_groups = DSB_Profile_Fields::get_account_reason_groups( 'suspend' );
		$cancel_groups  = DSB_Profile_Fields::get_account_reason_groups( 'delete' );
		$current_suspend_reason = trim( (string) get_user_meta( $user->ID, 'dsb_suspended_reason', true ) );
		$current_suspend_note   = trim( (string) get_user_meta( $user->ID, 'dsb_suspended_reason_note', true ) );
		$current_suspend_key    = trim( (string) get_user_meta( $user->ID, 'dsb_suspended_reason_key', true ) );
		$is_suspended           = (bool) get_user_meta( $user->ID, 'dsb_suspended', true );
		$is_banned              = (bool) get_user_meta( $user->ID, 'dsb_banned', true );
		$account_actions_table   = $wpdb->prefix . 'dsb_account_actions';
		$latest_action           = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $account_actions_table WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT 1",
			$user->ID
		) );
		?>
		<h2><?php esc_html_e( 'Account Moderation', 'dating-site-builder' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Suspend or cancel this member account from the user edit screen. Reasons are stored in the account actions table and suspension details are also saved against the profile while the account remains active.', 'dating-site-builder' ); ?></p>
		<?php if ( $latest_action ) : ?>
			<p class="description">
				<?php printf( esc_html__( 'Latest action: %1$s on %2$s', 'dating-site-builder' ), esc_html( ucfirst( $latest_action->action_type ) ), esc_html( $latest_action->created_at ) ); ?>
				<?php if ( ! empty( $latest_action->reason_label ) ) : ?>
					<br><?php printf( esc_html__( 'Reason: %s', 'dating-site-builder' ), esc_html( $latest_action->reason_label ) ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( $is_suspended && $current_suspend_reason ) : ?>
			<p class="description"><strong><?php esc_html_e( 'Current suspension reason:', 'dating-site-builder' ); ?></strong> <?php echo esc_html( $current_suspend_reason ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dsb-admin-account-action-form">
			<input type="hidden" name="action" value="dsb_admin_account_action">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
			<?php wp_nonce_field( 'dsb_admin_member_action_' . $user->ID, 'dsb_admin_member_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="dsb-admin-account-action"><?php esc_html_e( 'Action', 'dating-site-builder' ); ?></label></th>
					<td>
						<select name="dsb_admin_account_action" id="dsb-admin-account-action" class="regular-text">
							<option value="suspend"><?php esc_html_e( 'Suspend Account', 'dating-site-builder' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Cancel Account (Delete)', 'dating-site-builder' ); ?></option>
						</select>
					</td>
				</tr>
				<tr class="dsb-admin-account-reason dsb-admin-account-reason-suspend">
					<th scope="row"><label for="dsb-admin-suspend-reason"><?php esc_html_e( 'Suspend Reason', 'dating-site-builder' ); ?></label></th>
					<td>
						<select name="dsb_admin_suspend_reason" id="dsb-admin-suspend-reason" class="regular-text">
							<?php foreach ( $suspend_groups as $group_label => $reasons ) : ?>
								<optgroup label="<?php echo esc_attr( $group_label ); ?>">
									<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
										<option value="<?php echo esc_attr( $reason_key ); ?>" <?php selected( $current_suspend_key, $reason_key ); ?>><?php echo esc_html( $reason_label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr class="dsb-admin-account-reason dsb-admin-account-reason-delete" style="display:none;">
					<th scope="row"><label for="dsb-admin-cancel-reason"><?php esc_html_e( 'Cancel Reason', 'dating-site-builder' ); ?></label></th>
					<td>
						<select name="dsb_admin_cancel_reason" id="dsb-admin-cancel-reason" class="regular-text">
							<?php foreach ( $cancel_groups as $group_label => $reasons ) : ?>
								<optgroup label="<?php echo esc_attr( $group_label ); ?>">
									<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
										<option value="<?php echo esc_attr( $reason_key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="dsb-admin-account-note"><?php esc_html_e( 'Reason Note', 'dating-site-builder' ); ?></label></th>
					<td>
						<textarea name="dsb_admin_account_note" id="dsb-admin-account-note" class="large-text" rows="3"><?php echo esc_textarea( $current_suspend_note ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional internal note or extra context to save with the action.', 'dating-site-builder' ); ?></p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Apply this account action now?', 'dating-site-builder' ) ); ?>');"><?php esc_html_e( 'Apply Account Action', 'dating-site-builder' ); ?></button>
			</p>
		</form>
		<script>
		jQuery(function($){
			function dsbToggleAdminCoupleFields() {
				var value = $('#dsb_field_profile_kind').val() || '';
				var couple = value.indexOf('couple_') === 0;
				$('tr[data-requires-couple="1"]').toggle(couple);
			}

			function dsbToggleAdminAccountReasons() {
				var action = $('#dsb-admin-account-action').val();
				$('.dsb-admin-account-reason-suspend').toggle(action === 'suspend');
				$('.dsb-admin-account-reason-delete').toggle(action === 'delete');
			}

			dsbToggleAdminCoupleFields();
			dsbToggleAdminAccountReasons();
			$(document).on('change', '#dsb_field_profile_kind', dsbToggleAdminCoupleFields);
			$(document).on('change', '#dsb-admin-account-action', dsbToggleAdminAccountReasons);
		});
		</script>
		<?php
	}

	/**
	 * Persist edited dating profile fields when an admin (or the user)
	 * saves a WP user record. Uses DSB_Profile_Fields::sanitize_field()
	 * so option-backed fields stay within their allowed values.
	 *
	 * Hooked into 'personal_options_update' and 'edit_user_profile_update'.
	 */
	public function save_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		if ( ! isset( $_POST['dsb_user_fields_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsb_user_fields_nonce'] ) ), 'dsb_user_fields_' . $user_id ) ) {
			return;
		}
		// Refuse to touch any dating profile meta unless the dating
		// fields UI was actually rendered as part of this submission.
		// Avoids silently clearing checkbox arrays when another plugin
		// posts the user-edit form without our section visible.
		if ( empty( $_POST['dsb_user_fields_form'] ) ) {
			return;
		}

		$fields    = DSB_Profile_Fields::get_all_fields();
		$submitted = ( isset( $_POST['dsb_field'] ) && is_array( $_POST['dsb_field'] ) )
			? wp_unslash( $_POST['dsb_field'] )
			: array();

		foreach ( $fields as $field_key => $field ) {
			$meta_key = 'dsb_' . $field_key;
			$type     = isset( $field['type'] ) ? $field['type'] : 'text';

			if ( 'checkbox' === $type ) {
				// Unchecked checkbox groups are not posted at all, so we
				// treat the absence as "clear all selections" rather than
				// leaving the previous value untouched.
				$raw   = isset( $submitted[ $field_key ] ) ? $submitted[ $field_key ] : array();
				$clean = DSB_Profile_Fields::sanitize_field( $field_key, $raw );
				update_user_meta( $user_id, $meta_key, $clean );
				continue;
			}

			if ( ! array_key_exists( $field_key, $submitted ) ) {
				continue;
			}

			$clean = DSB_Profile_Fields::sanitize_field( $field_key, $submitted[ $field_key ] );
			update_user_meta( $user_id, $meta_key, $clean );
		}
	}

	/**
	 * Handle account moderation from the Edit User screen.
	 */
	public function handle_admin_account_action() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'dating-site-builder' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_die( esc_html__( 'Invalid member.', 'dating-site-builder' ) );
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dsb_admin_member_nonce'] ?? '' ) ), 'dsb_admin_member_action_' . $user_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'dating-site-builder' ) );
		}

		$action = isset( $_POST['dsb_admin_account_action'] ) ? sanitize_key( wp_unslash( $_POST['dsb_admin_account_action'] ) ) : '';
		$note   = isset( $_POST['dsb_admin_account_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dsb_admin_account_note'] ) ) : '';

		$reason_key = '';
		$reason_label = '';
		$reason_groups = 'delete' === $action
			? DSB_Profile_Fields::get_account_reason_groups( 'delete' )
			: DSB_Profile_Fields::get_account_reason_groups( 'suspend' );
		$reason_field = 'delete' === $action ? 'dsb_admin_cancel_reason' : 'dsb_admin_suspend_reason';
		$submitted_key = isset( $_POST[ $reason_field ] ) ? sanitize_key( wp_unslash( $_POST[ $reason_field ] ) ) : '';

		foreach ( $reason_groups as $group_label => $reasons ) {
			if ( isset( $reasons[ $submitted_key ] ) ) {
				$reason_key   = $submitted_key;
				$reason_label = $reasons[ $submitted_key ];
				break;
			}
		}

		if ( '' === $reason_key && '' !== $submitted_key ) {
			$reason_key   = $submitted_key;
			$reason_label = $submitted_key;
		}

		global $wpdb;
		$audits_table = $wpdb->prefix . 'dsb_account_actions';

		if ( 'suspend' === $action ) {
			update_user_meta( $user_id, 'dsb_suspended', '1' );
			update_user_meta( $user_id, 'dsb_suspended_at', current_time( 'mysql' ) );
			if ( '' !== trim( $reason_label ) ) {
				update_user_meta( $user_id, 'dsb_suspended_reason', $reason_label );
				update_user_meta( $user_id, 'dsb_suspended_reason_key', $reason_key );
			} else {
				delete_user_meta( $user_id, 'dsb_suspended_reason' );
				delete_user_meta( $user_id, 'dsb_suspended_reason_key' );
			}
			update_user_meta( $user_id, 'dsb_suspended_reason_note', $note );
			$wpdb->insert(
				$audits_table,
				array(
					'user_id'      => $user_id,
					'action_type'  => 'suspend',
					'reason_key'   => $reason_key,
					'reason_label' => $reason_label,
					'reason_note'  => $note,
					'source'       => 'admin',
					'performed_by' => get_current_user_id(),
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		} elseif ( 'delete' === $action ) {
			$wpdb->insert(
				$audits_table,
				array(
					'user_id'      => $user_id,
					'action_type'  => 'delete',
					'reason_key'   => $reason_key,
					'reason_label' => $reason_label,
					'reason_note'  => $note,
					'source'       => 'admin',
					'performed_by' => get_current_user_id(),
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
		}

		$redirect_url = add_query_arg(
			array( 'user_id' => $user_id, 'dsb_notice' => 'member-updated' ),
			admin_url( 'user-edit.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Hide the built-in WordPress "Website" (user_url) field from the
	 * profile / user-edit screens so members cannot use the site to
	 * advertise an external business.
	 *
	 * Hooked into 'admin_head-profile.php', 'admin_head-user-edit.php',
	 * and 'admin_head-user-new.php'.
	 */
	public function hide_user_website_field() {
		?>
		<style>
			/* Dating Site Builder: hide the built-in "Website" user field. */
			tr.user-url-wrap,
			#your-profile tr.user-url-wrap,
			#createuser tr.form-field:has(#url),
			tr.form-field:has(input#url) { display: none !important; }

			/* Dating Site Builder: hide the default WordPress "Profile Picture"
			   (Gravatar) section — the plugin's own Dating Profile Photos
			   section replaces it. Targets both the heading and the table row. */
			tr.user-profile-picture,
			#your-profile tr.user-profile-picture,
			.user-profile-picture { display: none !important; }

			/* Dating Site Builder: hide the standard WordPress Personal Options
			   rows on profile / user-edit screens to keep the moderation UI
			   focused on dating-site fields only. */
			tr.user-admin-color-wrap,
			#your-profile tr.user-admin-color-wrap,
			tr.user-comment-shortcuts-wrap,
			#your-profile tr.user-comment-shortcuts-wrap,
			tr.show-admin-bar,
			#your-profile tr.show-admin-bar,
			tr.user-language-wrap,
			#your-profile tr.user-language-wrap { display: none !important; }
		</style>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var headings = document.querySelectorAll('#your-profile h2, #your-profile h3');
				headings.forEach(function(heading) {
					if (heading.textContent && heading.textContent.trim() === 'Personal Options') {
						heading.style.display = 'none';
					}
				});
			});
		</script>
		<?php
	}
}
