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
					case 'suspend':
						update_user_meta( $user_id, 'dsb_suspended', '1' );
						break;
					case 'unsuspend':
						delete_user_meta( $user_id, 'dsb_suspended' );
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

		// Get filter
		$filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$per_page = 20;

		// Build user query args
		$args = array(
			'role__in'    => array( 'dating_member', 'dating_premium' ),
			'number'      => $per_page,
			'offset'      => ( $paged - 1 ) * $per_page,
			'orderby'     => 'registered',
			'order'       => 'DESC',
		);

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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Manage Members', 'dating-site-builder' ); ?></h1>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-members' ) ); ?>" class="<?php echo $filter === 'all' ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $all_count ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-members&filter=pending' ) ); ?>" class="<?php echo $filter === 'pending' ? 'current' : ''; ?>"><?php esc_html_e( 'Pending Approval', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $pending_count ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-members&filter=suspended' ) ); ?>" class="<?php echo $filter === 'suspended' ? 'current' : ''; ?>"><?php esc_html_e( 'Suspended', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $suspended_count ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-members&filter=banned' ) ); ?>" class="<?php echo $filter === 'banned' ? 'current' : ''; ?>"><?php esc_html_e( 'Banned', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $banned_count ); ?>)</span></a></li>
			</ul>

			<form method="get" class="search-form">
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
							<option value="suspend"><?php esc_html_e( 'Suspend', 'dating-site-builder' ); ?></option>
							<option value="unsuspend"><?php esc_html_e( 'Unsuspend', 'dating-site-builder' ); ?></option>
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
							<th scope="col"><?php esc_html_e( 'Username', 'dating-site-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'dating-site-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Registered', 'dating-site-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'dating-site-builder' ); ?></th>
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
								$photos = get_user_meta( $member->ID, 'dsb_photos', true );
								$main_photo = ! empty( $photos ) && is_array( $photos ) ? $photos[0] : '';
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
			.search-form { float: right; margin-bottom: 10px; }
			.subsubsub { margin-bottom: 40px; }
		</style>
		<?php
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'User Reports', 'dating-site-builder' ); ?></h1>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-reports&filter=pending' ) ); ?>" class="<?php echo $filter === 'pending' ? 'current' : ''; ?>"><?php esc_html_e( 'Pending', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $pending_count ?: 0 ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-reports&filter=resolved' ) ); ?>" class="<?php echo $filter === 'resolved' ? 'current' : ''; ?>"><?php esc_html_e( 'Resolved', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $resolved_count ?: 0 ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-reports&filter=dismissed' ) ); ?>" class="<?php echo $filter === 'dismissed' ? 'current' : ''; ?>"><?php esc_html_e( 'Dismissed', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $dismissed_count ?: 0 ); ?>)</span></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=dsb-reports&filter=all' ) ); ?>" class="<?php echo $filter === 'all' ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'dating-site-builder' ); ?> <span class="count">(<?php echo esc_html( $all_count ?: 0 ); ?>)</span></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped" style="margin-top:40px;">
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
	 * Render settings page.
	 */
	public function render_settings() {
		// Save settings
		if ( isset( $_POST['dsb_save_settings'] ) ) {
			check_admin_referer( 'dsb_settings', 'dsb_settings_nonce' );

			// General settings
			update_option( 'dsb_site_name', sanitize_text_field( $_POST['dsb_site_name'] ?? '' ) );
			update_option( 'dsb_color_theme', sanitize_text_field( $_POST['dsb_color_theme'] ?? 'romantic_red' ) );
			update_option( 'dsb_minimum_age', intval( $_POST['dsb_minimum_age'] ?? 18 ) );
			update_option( 'dsb_default_country', sanitize_text_field( $_POST['dsb_default_country'] ?? '' ) );

			// Registration settings
			update_option( 'dsb_require_email_verification', isset( $_POST['dsb_require_email_verification'] ) );
			update_option( 'dsb_require_profile_approval', isset( $_POST['dsb_require_profile_approval'] ) );

			// Features
			update_option( 'dsb_enable_messaging', isset( $_POST['dsb_enable_messaging'] ) );
			update_option( 'dsb_enable_likes', isset( $_POST['dsb_enable_likes'] ) );
			update_option( 'dsb_require_mutual_like', isset( $_POST['dsb_require_mutual_like'] ) );
			update_option( 'dsb_enable_blocking', isset( $_POST['dsb_enable_blocking'] ) );
			update_option( 'dsb_enable_reporting', isset( $_POST['dsb_enable_reporting'] ) );

			// Photo settings
			update_option( 'dsb_max_photos', intval( $_POST['dsb_max_photos'] ?? 6 ) );
			update_option( 'dsb_enable_private_photos', isset( $_POST['dsb_enable_private_photos'] ) );

			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'dating-site-builder' ) . '</p></div>';
		}

		// Get current values
		$site_name = get_option( 'dsb_site_name', '' );
		$color_theme = get_option( 'dsb_color_theme', 'romantic_red' );
		$minimum_age = get_option( 'dsb_minimum_age', 18 );
		$default_country = get_option( 'dsb_default_country', '' );
		$require_email = get_option( 'dsb_require_email_verification', false );
		$require_approval = get_option( 'dsb_require_profile_approval', false );
		$enable_messaging = get_option( 'dsb_enable_messaging', true );
		$enable_likes = get_option( 'dsb_enable_likes', true );
		$require_mutual = get_option( 'dsb_require_mutual_like', false );
		$enable_blocking = get_option( 'dsb_enable_blocking', true );
		$enable_reporting = get_option( 'dsb_enable_reporting', true );
		$max_photos = get_option( 'dsb_max_photos', 6 );
		$enable_private = get_option( 'dsb_enable_private_photos', false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dating Site Settings', 'dating-site-builder' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'dsb_settings', 'dsb_settings_nonce' ); ?>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'General Settings', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsb_site_name"><?php esc_html_e( 'Site Name', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="text" name="dsb_site_name" id="dsb_site_name" value="<?php echo esc_attr( $site_name ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'The name of your dating site.', 'dating-site-builder' ); ?></p>
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
							<th scope="row"><label for="dsb_minimum_age"><?php esc_html_e( 'Minimum Age', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="number" name="dsb_minimum_age" id="dsb_minimum_age" value="<?php echo esc_attr( $minimum_age ); ?>" min="18" max="99" class="small-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="dsb_default_country"><?php esc_html_e( 'Default Country', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="text" name="dsb_default_country" id="dsb_default_country" value="<?php echo esc_attr( $default_country ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Australia', 'dating-site-builder' ); ?>">
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Registration & Moderation', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Email Verification', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_require_email_verification" <?php checked( $require_email ); ?>>
									<?php esc_html_e( 'Require email verification before users can browse members', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Profile Approval', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_require_profile_approval" <?php checked( $require_approval ); ?>>
									<?php esc_html_e( 'Require admin approval before profiles are visible to other members', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Features', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Messaging', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_messaging" <?php checked( $enable_messaging ); ?>>
									<?php esc_html_e( 'Enable private messaging between members', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Likes System', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_likes" <?php checked( $enable_likes ); ?>>
									<?php esc_html_e( 'Enable likes and favorites', 'dating-site-builder' ); ?>
								</label>
								<br><br>
								<label>
									<input type="checkbox" name="dsb_require_mutual_like" <?php checked( $require_mutual ); ?>>
									<?php esc_html_e( 'Require mutual like before messaging', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Safety Features', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_blocking" <?php checked( $enable_blocking ); ?>>
									<?php esc_html_e( 'Allow members to block other members', 'dating-site-builder' ); ?>
								</label>
								<br><br>
								<label>
									<input type="checkbox" name="dsb_enable_reporting" <?php checked( $enable_reporting ); ?>>
									<?php esc_html_e( 'Allow members to report inappropriate profiles', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div class="dsb-settings-section">
					<h2><?php esc_html_e( 'Photo Settings', 'dating-site-builder' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><label for="dsb_max_photos"><?php esc_html_e( 'Maximum Photos', 'dating-site-builder' ); ?></label></th>
							<td>
								<input type="number" name="dsb_max_photos" id="dsb_max_photos" value="<?php echo esc_attr( $max_photos ); ?>" min="1" max="20" class="small-text">
								<p class="description"><?php esc_html_e( 'Maximum number of photos per member profile.', 'dating-site-builder' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Private Photos', 'dating-site-builder' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="dsb_enable_private_photos" <?php checked( $enable_private ); ?>>
									<?php esc_html_e( 'Allow members to mark photos as private (requires approval to view)', 'dating-site-builder' ); ?>
								</label>
							</td>
						</tr>
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
}
