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
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'dsb_admin_nonce' ),
			)
		);
	}

	/**
	 * Render dashboard page.
	 */
	public function render_dashboard() {
		// Get statistics
		$user_count = count_users();
		$member_count = 0;
		if ( isset( $user_count['avail_roles']['dating_member'] ) ) {
			$member_count += $user_count['avail_roles']['dating_member'];
		}
		if ( isset( $user_count['avail_roles']['dating_premium'] ) ) {
			$member_count += $user_count['avail_roles']['dating_premium'];
		}

		global $wpdb;
		$messages_table = $wpdb->prefix . 'dsb_messages';
		$likes_table = $wpdb->prefix . 'dsb_likes';
		$reports_table = $wpdb->prefix . 'dsb_reports';

		$total_messages = $wpdb->get_var( "SELECT COUNT(*) FROM $messages_table" );
		$total_likes = $wpdb->get_var( "SELECT COUNT(*) FROM $likes_table" );
		$pending_reports = $wpdb->get_var( "SELECT COUNT(*) FROM $reports_table WHERE status = 'pending'" );

		?>
		<div class="wrap">
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

			<div class="dsb-dashboard-stats">
				<div class="dsb-stat-box">
					<h3><?php echo esc_html( number_format( $member_count ) ); ?></h3>
					<p><?php esc_html_e( 'Total Members', 'dating-site-builder' ); ?></p>
				</div>
				<div class="dsb-stat-box">
					<h3><?php echo esc_html( number_format( $total_messages ) ); ?></h3>
					<p><?php esc_html_e( 'Messages Sent', 'dating-site-builder' ); ?></p>
				</div>
				<div class="dsb-stat-box">
					<h3><?php echo esc_html( number_format( $total_likes ) ); ?></h3>
					<p><?php esc_html_e( 'Total Likes', 'dating-site-builder' ); ?></p>
				</div>
				<div class="dsb-stat-box">
					<h3><?php echo esc_html( number_format( $pending_reports ) ); ?></h3>
					<p><?php esc_html_e( 'Pending Reports', 'dating-site-builder' ); ?></p>
				</div>
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
		?>
		<h2><?php esc_html_e( 'Step 1: Site Identity & Theme', 'dating-site-builder' ); ?></h2>
		<p><?php esc_html_e( 'Give your dating site a name and choose a color theme.', 'dating-site-builder' ); ?></p>

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
		$enabled_groups = get_option( 'dsb_enabled_field_groups', array( 'basics', 'about' ) );
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
							<?php esc_html_e( 'About Me (headline, bio, what I\'m looking for)', 'dating-site-builder' ); ?>
						</label><br>

						<label>
							<input type="checkbox" name="dsb_enabled_field_groups[]" value="lifestyle" <?php checked( in_array( 'lifestyle', $enabled_groups ) ); ?>>
							<?php esc_html_e( 'Lifestyle & Interests (hobbies, occupation, education, smoking, drinking)', 'dating-site-builder' ); ?>
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
					<td><strong><?php esc_html_e( 'Color Theme:', 'dating-site-builder' ); ?></strong></td>
					<td><?php echo esc_html( ucwords( str_replace( '_', ' ', get_option( 'dsb_color_theme', 'romantic_red' ) ) ) ); ?></td>
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
			'dsb_color_theme'                 => 'sanitize_text_field',
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
			'forgot-password' => array(
				'title'   => 'Forgot Password',
				'content' => '[dsb_forgot_password]',
				'option'  => 'dsb_forgot_password_page',
			),
			'profile-edit' => array(
				'title'   => 'Edit Profile',
				'content' => '[dsb_profile_edit]',
				'option'  => 'dsb_profile_edit_page',
			),
			'profile' => array(
				'title'   => 'My Profile',
				'content' => '[dsb_profile_view]',
				'option'  => 'dsb_profile_view_page',
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
			),
		);

		foreach ( $pages as $slug => $page_data ) {
			// Check if page already exists
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

			// Save page ID to options for shortcode links
			if ( $page_id && ! is_wp_error( $page_id ) && ! empty( $page_data['option'] ) ) {
				update_option( $page_data['option'], $page_id );
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
	 * Render members page.
	 */
	public function render_members() {
		// Implementation for member management
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Members', 'dating-site-builder' ); ?></h1>
			<p><?php esc_html_e( 'Member management interface - view, approve, suspend, or ban members.', 'dating-site-builder' ); ?></p>
			<!-- Member list table would go here -->
		</div>
		<?php
	}

	/**
	 * Render reports page.
	 */
	public function render_reports() {
		// Implementation for reports management
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'User Reports', 'dating-site-builder' ); ?></h1>
			<p><?php esc_html_e( 'View and manage user reports.', 'dating-site-builder' ); ?></p>
			<!-- Reports table would go here -->
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dating Site Settings', 'dating-site-builder' ); ?></h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-wizard' ) ); ?>" class="button">
					<?php esc_html_e( 'Re-run Setup Wizard', 'dating-site-builder' ); ?>
				</a>
			</p>
			<!-- Settings form would go here -->
		</div>
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
}
