<?php
/**
 * Frontend functionality and shortcodes.
 *
 * @package DatingSiteBuilder
 */

class DSB_Frontend {

	/**
	 * Remove the Login page from `wp_list_pages()` / `wp_page_menu()`
	 * output when the visitor is already logged in.
	 *
	 * @param array $pages Array of WP_Post objects as returned by get_pages().
	 * @return array
	 */
	public function filter_pages_hide_login( $pages ) {
		if ( is_admin() || ! is_user_logged_in() || empty( $pages ) ) {
			return $pages;
		}

		$login_page_id = (int) get_option( 'dsb_login_page' );
		if ( ! $login_page_id ) {
			return $pages;
		}

		foreach ( $pages as $i => $page ) {
			if ( isset( $page->ID ) && (int) $page->ID === $login_page_id ) {
				unset( $pages[ $i ] );
			}
		}

		return array_values( $pages );
	}

	/**
	 * Remove the Login page from custom WP nav menus when the visitor
	 * is already logged in.
	 *
	 * @param array $items Array of nav menu items.
	 * @return array
	 */
	public function filter_nav_menu_items_hide_login( $items ) {
		if ( is_admin() || ! is_user_logged_in() || empty( $items ) ) {
			return $items;
		}

		$login_page_id = (int) get_option( 'dsb_login_page' );
		if ( ! $login_page_id ) {
			return $items;
		}

		foreach ( $items as $i => $item ) {
			if ( isset( $item->object, $item->object_id )
				&& 'page' === $item->object
				&& (int) $item->object_id === $login_page_id ) {
				unset( $items[ $i ] );
			}
		}

		return array_values( $items );
	}

	/**
	 * Is the current front-end request a page that contains one of the
	 * plugin's shortcodes? Used to suppress the theme's own page/nav
	 * menu on dating pages since the plugin renders its own top nav.
	 *
	 * @return bool
	 */
	private function is_dsb_page() {
		if ( is_admin() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof WP_Post || empty( $post->post_content ) ) {
			return false;
		}

		$shortcodes = array(
			'dsb_register',
			'dsb_login',
			'dsb_forgot_password',
			'dsb_profile_edit',
			'dsb_profile_view',
			'dsb_member_directory',
			'dsb_matches',
			'dsb_messages',
			'dsb_likes',
			'dsb_group_chat',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Suppress the theme's fallback `wp_page_menu()` output on pages
	 * that use a plugin shortcode, so only the plugin's own top nav is
	 * visible.
	 *
	 * @param string $menu HTML produced by `wp_page_menu()`.
	 * @return string
	 */
	public function filter_hide_theme_page_menu( $menu ) {
		return $this->is_dsb_page() ? '' : $menu;
	}

	/**
	 * Short-circuit `wp_nav_menu()` on pages that use a plugin
	 * shortcode so the theme's registered menu location renders
	 * nothing. Returning an empty string replaces the menu output.
	 *
	 * @param string|null $output Existing short-circuit value (null by default).
	 * @return string|null
	 */
	public function filter_hide_theme_nav_menu( $output ) {
		return $this->is_dsb_page() ? '' : $output;
	}

	/**
	 * Add a body class on plugin pages so the accompanying CSS can
	 * hide the theme's page title and make the plugin banner full
	 * width.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function filter_body_class_dsb_page( $classes ) {
		if ( $this->is_dsb_page() ) {
			$classes[] = 'dsb-plugin-page';
		}
		return $classes;
	}

	/**
	 * Suppress the page title ("Browse Members", "My Profile", ...) on
	 * plugin pages. Only affects the main query so menus, widgets and
	 * admin listings are untouched.
	 *
	 * @param string $title Current page title.
	 * @param int    $id    Post ID (optional).
	 * @return string
	 */
	public function filter_remove_page_title( $title, $id = 0 ) {
		if ( is_admin() ) {
			return $title;
		}

		if ( ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}

		if ( $id && (int) $id !== (int) get_queried_object_id() ) {
			return $title;
		}

		return $this->is_dsb_page() ? '' : $title;
	}

	/**
	 * CSS that hides the theme's page-title area and lets the plugin's
	 * banner span the full viewport. Applied only when the body
	 * carries the `dsb-plugin-page` class.
	 *
	 * @return string
	 */
	private function get_full_width_css() {
		return '
		/* Dating Site Builder – full-width layout on plugin pages */

		/* Hide the theme\'s header/footer and title wrappers entirely
		   so the plugin banner can sit flush to the top. */
		body.dsb-plugin-page .site-header,
		body.dsb-plugin-page header.site-header,
		body.dsb-plugin-page #masthead,
		body.dsb-plugin-page .wp-block-template-part.site-header,
		body.dsb-plugin-page .wp-block-template-part[data-area="header"],
		body.dsb-plugin-page .wp-block-template-part:has(nav),
		body.dsb-plugin-page .site-branding,
		body.dsb-plugin-page .entry-header,
		body.dsb-plugin-page .page-header,
		body.dsb-plugin-page .entry-title,
		body.dsb-plugin-page .page-title,
		body.dsb-plugin-page header.entry-header,
		body.dsb-plugin-page .wp-block-post-title,
		body.dsb-plugin-page .site-content > header,
		body.dsb-plugin-page main > header,
		body.dsb-plugin-page article > header,
		body.dsb-plugin-page .site-footer,
		body.dsb-plugin-page footer#colophon,
		body.dsb-plugin-page .wp-block-template-part[data-area="footer"] { display: none !important; }

		/* Zero out any margin/padding between <body> and the first
		   visible element so the banner is truly flush to the top.
		   The WP admin bar adds its own body margin-top via an inline
		   style, which we leave alone. */
		body.dsb-plugin-page {
			margin: 0 !important;
			padding: 0 !important;
		}

		body.dsb-plugin-page #page,
		body.dsb-plugin-page #content,
		body.dsb-plugin-page #primary,
		body.dsb-plugin-page .site,
		body.dsb-plugin-page .site-content,
		body.dsb-plugin-page .content-area,
		body.dsb-plugin-page .site-main,
		body.dsb-plugin-page main,
		body.dsb-plugin-page article,
		body.dsb-plugin-page .entry-content,
		body.dsb-plugin-page .wp-site-blocks,
		body.dsb-plugin-page .wp-block-group,
		body.dsb-plugin-page .wp-block-post-content,
		body.dsb-plugin-page .wp-block-post-template {
			margin: 0 !important;
			padding: 0 !important;
			max-width: none !important;
			width: 100% !important;
			border: 0 !important;
		}

		/* Remove any horizontal rule a theme places between header and
		   content. */
		body.dsb-plugin-page .site-content hr,
		body.dsb-plugin-page .entry-content > hr:first-child,
		body.dsb-plugin-page main > hr:first-child,
		body.dsb-plugin-page .wp-site-blocks > hr { display: none !important; }

		/* Break the plugin banner out of any centered wrapper so it
		   spans the full viewport, flush to the top. */
		body.dsb-plugin-page .dsb-app-header,
		body.dsb-plugin-page .dsb-member-nav,
		body.dsb-plugin-page .dsb-public-nav {
			position: relative;
			width: 100vw;
			max-width: 100vw;
			margin-left: calc(50% - 50vw);
			margin-right: calc(50% - 50vw);
			margin-top: 0 !important;
		}

		/* Fix: first select in the members directory (e.g. the gender
		   filter) was clipping its text because the theme forces a
		   small line-height. Give the filter selects enough vertical
		   breathing room. */
		body.dsb-plugin-page .dsb-directory-filters .dsb-filter,
		body.dsb-plugin-page select.dsb-filter {
			min-height: 44px !important;
			height: auto !important;
			line-height: 1.4 !important;
			padding: 0.625rem 2rem 0.625rem 1rem !important;
			box-sizing: border-box !important;
			appearance: auto !important;
			-webkit-appearance: auto !important;
			-moz-appearance: auto !important;
		}
		';
	}

	/**
	 * Enqueue frontend styles.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'dsb-public', DSB_PLUGIN_URL . 'public/css/dsb-public.css', array(), DSB_VERSION, 'all' );

		// Get the selected color theme and output theme-specific CSS variables
		$theme_css = $this->get_theme_css_variables();

		// Get template style CSS
		$template_css = $this->get_template_css();

		// Full-width / hide-theme-title CSS (scoped via body.dsb-plugin-page
		// so it only affects dating pages).
		$full_width_css = $this->get_full_width_css();

		// Header logo size variable (Settings > Header Logo Size).
		$logo_size_css = $this->get_header_logo_size_css();

		wp_add_inline_style( 'dsb-public', $theme_css . $template_css . $full_width_css . $logo_size_css );
	}

	/**
	 * Translate the dsb_header_logo_size option into a CSS variable
	 * the front-end stylesheet can consume.
	 */
	private function get_header_logo_size_css() {
		$size = get_option( 'dsb_header_logo_size', 'full' );

		$map = array(
			'small'  => '40px',
			'medium' => '64px',
			'large'  => '96px',
			'full'   => '140px',
		);

		$value = isset( $map[ $size ] ) ? $map[ $size ] : $map['full'];

		return ':root { --dsb-app-logo-height: ' . $value . '; }';
	}

	/**
	 * Get theme-specific CSS variables based on selected theme.
	 */
	private function get_theme_css_variables() {
		$theme = get_option( 'dsb_color_theme', 'romantic_red' );
		
		// Define theme color palettes
		$themes = array(
			'romantic_red' => array(
				'primary'          => '#ff4458',
				'primary_dark'     => '#e63946',
				'primary_light'    => '#ff6b7a',
				'secondary'        => '#7c3aed',
				'accent'           => '#ec4899',
				'gradient_primary' => 'linear-gradient(135deg, #ff4458 0%, #ff6b7a 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #ff4458, #ff6b7a, #ec4899, #7c3aed)',
			),
			'ocean_blue' => array(
				'primary'          => '#3b82f6',
				'primary_dark'     => '#2563eb',
				'primary_light'    => '#60a5fa',
				'secondary'        => '#06b6d4',
				'accent'           => '#8b5cf6',
				'gradient_primary' => 'linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #3b82f6, #06b6d4, #8b5cf6, #0ea5e9)',
			),
			'forest_green' => array(
				'primary'          => '#10b981',
				'primary_dark'     => '#059669',
				'primary_light'    => '#34d399',
				'secondary'        => '#14b8a6',
				'accent'           => '#84cc16',
				'gradient_primary' => 'linear-gradient(135deg, #10b981 0%, #34d399 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #10b981, #34d399, #14b8a6, #059669)',
			),
			'royal_purple' => array(
				'primary'          => '#8b5cf6',
				'primary_dark'     => '#7c3aed',
				'primary_light'    => '#a78bfa',
				'secondary'        => '#ec4899',
				'accent'           => '#f472b6',
				'gradient_primary' => 'linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #8b5cf6, #a855f7, #ec4899, #7c3aed)',
			),
			'sunset_orange' => array(
				'primary'          => '#f97316',
				'primary_dark'     => '#ea580c',
				'primary_light'    => '#fb923c',
				'secondary'        => '#eab308',
				'accent'           => '#facc15',
				'gradient_primary' => 'linear-gradient(135deg, #f97316 0%, #facc15 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #f97316, #fb923c, #facc15, #ea580c)',
			),
			'midnight_dark' => array(
				'primary'          => '#6366f1',
				'primary_dark'     => '#4f46e5',
				'primary_light'    => '#818cf8',
				'secondary'        => '#8b5cf6',
				'accent'           => '#06b6d4',
				'gradient_primary' => 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%)',
				'gradient_bg'      => 'linear-gradient(-45deg, #1e293b, #334155, #475569, #1e293b)',
				'bg_main'          => '#0f172a',
				'bg_card'          => '#1e293b',
				'text_primary'     => '#f1f5f9',
				'text_secondary'   => '#94a3b8',
				'border'           => '#334155',
			),
		);
		
		// Default to romantic_red if theme not found
		$colors = isset( $themes[ $theme ] ) ? $themes[ $theme ] : $themes['romantic_red'];
		
		// Build CSS
		$css = ':root {';
		$css .= '--dsb-primary: ' . $colors['primary'] . ';';
		$css .= '--dsb-primary-dark: ' . $colors['primary_dark'] . ';';
		$css .= '--dsb-primary-light: ' . $colors['primary_light'] . ';';
		$css .= '--dsb-secondary: ' . $colors['secondary'] . ';';
		$css .= '--dsb-accent: ' . $colors['accent'] . ';';
		$css .= '--dsb-gradient-primary: ' . $colors['gradient_primary'] . ';';
		$css .= '--dsb-gradient-bg: ' . $colors['gradient_bg'] . ';';
		
		// Dark theme overrides
		if ( isset( $colors['bg_main'] ) ) {
			$css .= '--dsb-bg-main: ' . $colors['bg_main'] . ';';
			$css .= '--dsb-bg-card: ' . $colors['bg_card'] . ';';
			$css .= '--dsb-text-primary: ' . $colors['text_primary'] . ';';
			$css .= '--dsb-text-secondary: ' . $colors['text_secondary'] . ';';
			$css .= '--dsb-border: ' . $colors['border'] . ';';
		}
		
		$css .= '}';
		
		return $css;
	}

	/**
	 * Get template-specific CSS based on selected template style.
	 */
	private function get_template_css() {
		$template = get_option( 'dsb_template_style', 'modern' );
		
		// Modern is the default - no additional CSS needed
		if ( $template === 'modern' ) {
			return '';
		}
		
		$css = '';
		
		switch ( $template ) {
			case 'glassmorphism':
				$css = $this->get_glassmorphism_css();
				break;
			case 'minimalist':
				$css = $this->get_minimalist_css();
				break;
			case 'bold_dark':
				$css = $this->get_bold_dark_css();
				break;
		}
		
		return $css;
	}

	/**
	 * Glassmorphism template CSS - frosted glass effects.
	 */
	private function get_glassmorphism_css() {
		return '
		/* Glassmorphism Template */
		:root {
			--dsb-glass-bg: rgba(255, 255, 255, 0.25);
			--dsb-glass-border: rgba(255, 255, 255, 0.18);
			--dsb-glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
		}
		
		/* Glass background for main content */
		.dsb-app-content {
			background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%) !important;
			position: relative;
		}
		
		.dsb-app-content::before {
			content: "";
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: 
				radial-gradient(ellipse at 20% 20%, rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.15) 0%, transparent 50%),
				radial-gradient(ellipse at 80% 80%, rgba(var(--dsb-secondary-rgb, 139, 92, 246), 0.15) 0%, transparent 50%),
				radial-gradient(ellipse at 50% 50%, rgba(var(--dsb-accent-rgb, 236, 72, 153), 0.1) 0%, transparent 60%);
			pointer-events: none;
			z-index: 0;
		}
		
		/* Glassmorphic cards */
		.dsb-member-card {
			background: rgba(255, 255, 255, 0.7) !important;
			backdrop-filter: blur(20px) !important;
			-webkit-backdrop-filter: blur(20px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
			box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1) !important;
			border-radius: 24px !important;
		}
		
		.dsb-member-card:hover {
			box-shadow: 0 16px 48px rgba(31, 38, 135, 0.2) !important;
			transform: translateY(-10px) scale(1.02) !important;
		}
		
		.dsb-member-card-photo {
			border-radius: 20px 20px 0 0 !important;
		}
		
		/* Glass forms */
		.dsb-form {
			background: rgba(255, 255, 255, 0.6) !important;
			backdrop-filter: blur(20px) !important;
			-webkit-backdrop-filter: blur(20px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
			border-radius: 24px !important;
		}
		
		/* Glass inputs */
		.dsb-input {
			background: rgba(255, 255, 255, 0.5) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
			border-radius: 12px !important;
			backdrop-filter: blur(10px) !important;
		}
		
		.dsb-input:focus {
			background: rgba(255, 255, 255, 0.7) !important;
			border-color: var(--dsb-primary) !important;
			box-shadow: 0 0 0 4px rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.15) !important;
		}
		
		/* Glass header */
		.dsb-app-header {
			background: rgba(255, 255, 255, 0.1) !important;
			backdrop-filter: blur(20px) !important;
			-webkit-backdrop-filter: blur(20px) !important;
			border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
		}
		
		/* Glass buttons */
		.dsb-btn-secondary {
			background: rgba(255, 255, 255, 0.5) !important;
			backdrop-filter: blur(10px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
		}
		
		/* Auth pages */
		.dsb-auth-container {
			background: rgba(255, 255, 255, 0.75) !important;
			backdrop-filter: blur(25px) !important;
			-webkit-backdrop-filter: blur(25px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
			border-radius: 32px !important;
		}
		
		/* Glass message bubbles */
		.dsb-message-bubble {
			background: rgba(255, 255, 255, 0.7) !important;
			backdrop-filter: blur(10px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
		}
		
		.dsb-message.sent .dsb-message-bubble {
			background: var(--dsb-gradient-primary) !important;
			border: none !important;
		}
		
		/* Profile view */
		.dsb-profile-view-wrapper {
			background: rgba(255, 255, 255, 0.6) !important;
			backdrop-filter: blur(20px) !important;
			border-radius: 24px !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
		}
		
		/* Directory wrapper */
		.dsb-member-directory-wrapper,
		.dsb-matches-wrapper,
		.dsb-likes-wrapper {
			position: relative;
			z-index: 1;
		}
		
		/* Chat room glass */
		.dsb-group-chat-wrapper {
			background: rgba(255, 255, 255, 0.6) !important;
			backdrop-filter: blur(20px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
			border-radius: 24px !important;
		}
		
		.dsb-chat-message-bubble {
			background: rgba(255, 255, 255, 0.8) !important;
			backdrop-filter: blur(10px) !important;
			border: 1px solid rgba(255, 255, 255, 0.3) !important;
		}
		';
	}

	/**
	 * Minimalist template CSS - clean, flat design.
	 */
	private function get_minimalist_css() {
		return '
		/* Minimalist Template */
		:root {
			--dsb-radius: 4px !important;
			--dsb-radius-lg: 8px !important;
			--dsb-radius-xl: 12px !important;
			--dsb-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
			--dsb-shadow-lg: 0 2px 6px rgba(0, 0, 0, 0.1);
			--dsb-bg-main: #ffffff;
		}
		
		/* Clean white background */
		.dsb-app-content {
			background: #ffffff !important;
		}
		
		/* Flat header with thin border */
		.dsb-app-header {
			background: #ffffff !important;
			box-shadow: none !important;
			border-bottom: 1px solid #e5e5e5 !important;
		}
		
		.dsb-app-header-inner {
			background: #ffffff !important;
		}
		
		.dsb-app-logo span {
			color: #111111 !important;
		}
		
		.dsb-app-nav-link {
			color: #666666 !important;
			background: transparent !important;
			font-weight: 500 !important;
		}
		
		.dsb-app-nav-link:hover,
		.dsb-app-nav-link.active {
			color: #111111 !important;
			background: transparent !important;
			text-decoration: underline !important;
			text-underline-offset: 4px !important;
		}
		
		.dsb-app-user-link {
			color: #333333 !important;
		}
		
		.dsb-app-logout {
			color: #666666 !important;
		}
		
		/* Flat cards with borders */
		.dsb-member-card {
			border-radius: 8px !important;
			box-shadow: none !important;
			border: 1px solid #e5e5e5 !important;
			overflow: hidden;
		}
		
		.dsb-member-card:hover {
			transform: none !important;
			box-shadow: none !important;
			border-color: var(--dsb-primary) !important;
		}
		
		.dsb-member-card-photo {
			border-radius: 0 !important;
		}
		
		/* Clean flat buttons */
		.dsb-btn-primary {
			background: var(--dsb-primary) !important;
			background-image: none !important;
			box-shadow: none !important;
			border-radius: 4px !important;
			font-weight: 500 !important;
			text-transform: uppercase !important;
			letter-spacing: 0.5px !important;
			font-size: 0.8rem !important;
		}
		
		.dsb-btn-primary:hover {
			transform: none !important;
			box-shadow: none !important;
			filter: brightness(0.95) !important;
		}
		
		.dsb-btn-secondary {
			border-radius: 4px !important;
			border-width: 1px !important;
			box-shadow: none !important;
		}
		
		/* Flat forms */
		.dsb-form {
			border-radius: 8px !important;
			box-shadow: none !important;
			border: 1px solid #e5e5e5 !important;
		}
		
		.dsb-input {
			border-radius: 4px !important;
			border: 1px solid #d1d5db !important;
			box-shadow: none !important;
		}
		
		.dsb-input:focus {
			border-color: var(--dsb-primary) !important;
			box-shadow: none !important;
			outline: 2px solid var(--dsb-primary) !important;
			outline-offset: 1px !important;
		}
		
		/* Auth pages - clean card */
		.dsb-fullscreen-bg {
			background: #f9fafb !important;
			animation: none !important;
			background-size: auto !important;
		}
		
		.dsb-fullscreen-bg::before,
		.dsb-fullscreen-bg::after {
			display: none !important;
		}
		
		.dsb-bg-overlay {
			background: transparent !important;
		}
		
		.dsb-auth-container {
			background: #ffffff !important;
			border-radius: 8px !important;
			box-shadow: none !important;
			border: 1px solid #e5e5e5 !important;
			backdrop-filter: none !important;
		}
		
		.dsb-logo {
			animation: none !important;
		}
		
		/* Messages - clean bubbles */
		.dsb-message-bubble {
			border-radius: 4px !important;
			box-shadow: none !important;
			border: 1px solid #e5e5e5 !important;
		}
		
		.dsb-message.sent .dsb-message-bubble {
			background: var(--dsb-primary) !important;
			border: none !important;
		}
		
		/* Match score - flat pill */
		.dsb-match-score {
			background: var(--dsb-primary) !important;
			background-image: none !important;
			border-radius: 4px !important;
		}
		
		/* Profile sections */
		.dsb-profile-view-wrapper {
			box-shadow: none !important;
			border: 1px solid #e5e5e5 !important;
			border-radius: 8px !important;
		}
		
		/* Chat room */
		.dsb-group-chat-wrapper {
			border-radius: 8px !important;
			border: 1px solid #e5e5e5 !important;
			box-shadow: none !important;
		}
		
		.dsb-chat-message-bubble {
			border-radius: 4px !important;
		}
		';
	}

	/**
	 * Bold Dark template CSS - dark mode with vibrant accents.
	 */
	private function get_bold_dark_css() {
		return '
		/* Bold Dark Template */
		:root {
			--dsb-bg-main: #0a0a0a !important;
			--dsb-bg-card: #161616 !important;
			--dsb-text-primary: #ffffff !important;
			--dsb-text-secondary: #a1a1aa !important;
			--dsb-border: #27272a !important;
			--dsb-radius: 16px;
			--dsb-radius-lg: 24px;
			--dsb-radius-xl: 32px;
		}
		
		/* Dark background */
		.dsb-app-content {
			background: #0a0a0a !important;
		}
		
		/* Dark header with gradient border */
		.dsb-app-header {
			background: linear-gradient(180deg, #161616 0%, #0a0a0a 100%) !important;
			border-bottom: 1px solid #27272a !important;
		}
		
		.dsb-app-header-inner {
			background: transparent !important;
		}
		
		/* Bold card style */
		.dsb-member-card {
			background: #161616 !important;
			border-radius: 24px !important;
			border: 1px solid #27272a !important;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
			overflow: hidden;
		}
		
		.dsb-member-card:hover {
			transform: translateY(-8px) scale(1.02) !important;
			box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6), 0 0 0 1px var(--dsb-primary) !important;
		}
		
		.dsb-member-card-photo {
			border-radius: 20px 20px 0 0 !important;
		}
		
		.dsb-member-card-info {
			background: #161616 !important;
		}
		
		.dsb-member-card h3 {
			color: #ffffff !important;
		}
		
		.dsb-member-card-meta {
			color: #a1a1aa !important;
		}
		
		/* Vibrant gradient buttons */
		.dsb-btn-primary {
			background: var(--dsb-gradient-primary) !important;
			box-shadow: 0 4px 15px rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.4) !important;
			border-radius: 12px !important;
			font-weight: 700 !important;
		}
		
		.dsb-btn-primary:hover {
			box-shadow: 0 6px 25px rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.6) !important;
			transform: translateY(-3px) !important;
		}
		
		.dsb-btn-secondary {
			background: #27272a !important;
			border: 1px solid #3f3f46 !important;
			color: #ffffff !important;
			border-radius: 12px !important;
		}
		
		.dsb-btn-secondary:hover {
			background: #3f3f46 !important;
			border-color: var(--dsb-primary) !important;
		}
		
		/* Dark forms */
		.dsb-form {
			background: #161616 !important;
			border: 1px solid #27272a !important;
			border-radius: 24px !important;
		}
		
		.dsb-form h2,
		.dsb-form label {
			color: #ffffff !important;
		}
		
		.dsb-input {
			background: #0a0a0a !important;
			border: 1px solid #27272a !important;
			color: #ffffff !important;
			border-radius: 12px !important;
		}
		
		.dsb-input::placeholder {
			color: #71717a !important;
		}
		
		.dsb-input:focus {
			border-color: var(--dsb-primary) !important;
			box-shadow: 0 0 0 3px rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.3) !important;
		}
		
		/* Auth pages - dark mode */
		.dsb-fullscreen-bg {
			background: radial-gradient(ellipse at center, #1a1a2e 0%, #0a0a0a 100%) !important;
			background-size: 100% 100% !important;
			animation: none !important;
		}
		
		.dsb-fullscreen-bg::before,
		.dsb-fullscreen-bg::after {
			background: rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.1) !important;
		}
		
		.dsb-bg-overlay {
			background: 
				radial-gradient(circle at 20% 80%, rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.15) 0%, transparent 40%),
				radial-gradient(circle at 80% 20%, rgba(var(--dsb-secondary-rgb, 139, 92, 246), 0.15) 0%, transparent 40%) !important;
		}
		
		.dsb-auth-container {
			background: rgba(22, 22, 22, 0.95) !important;
			border: 1px solid #27272a !important;
			box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6) !important;
			border-radius: 32px !important;
		}
		
		.dsb-auth-branding h1 {
			color: #ffffff !important;
		}
		
		.dsb-auth-branding p {
			color: #a1a1aa !important;
		}
		
		.dsb-auth-form .dsb-input {
			background: #0a0a0a !important;
			border: 1px solid #27272a !important;
			color: #ffffff !important;
		}
		
		.dsb-auth-footer,
		.dsb-auth-footer a {
			color: #a1a1aa !important;
		}
		
		.dsb-auth-footer a:hover {
			color: var(--dsb-primary) !important;
		}
		
		/* Match score with glow */
		.dsb-match-score {
			box-shadow: 0 0 20px rgba(var(--dsb-primary-rgb, 255, 68, 88), 0.4) !important;
			border-radius: 12px !important;
		}
		
		/* Dark messages */
		.dsb-messages-wrapper {
			background: #0a0a0a !important;
		}
		
		.dsb-message-bubble {
			background: #27272a !important;
			color: #ffffff !important;
			border-radius: 16px !important;
		}
		
		.dsb-message.sent .dsb-message-bubble {
			background: var(--dsb-gradient-primary) !important;
		}
		
		/* Profile page dark */
		.dsb-profile-view-wrapper {
			background: #161616 !important;
			border: 1px solid #27272a !important;
		}
		
		.dsb-profile-view-wrapper h2,
		.dsb-profile-view-wrapper h3 {
			color: #ffffff !important;
		}
		
		.dsb-profile-view-wrapper p {
			color: #a1a1aa !important;
		}
		
		/* Directory headers */
		.dsb-directory-header h2,
		.dsb-matches-header h2 {
			color: #ffffff !important;
		}
		
		.dsb-directory-header p {
			color: #a1a1aa !important;
		}
		
		/* Filters dark */
		.dsb-filter {
			background: #161616 !important;
			border: 1px solid #27272a !important;
			color: #ffffff !important;
		}
		
		/* Chat room dark */
		.dsb-group-chat-wrapper {
			background: #161616 !important;
			border: 1px solid #27272a !important;
		}
		
		.dsb-chat-header {
			background: #0a0a0a !important;
			border-bottom: 1px solid #27272a !important;
			color: #ffffff !important;
		}
		
		.dsb-chat-messages {
			background: #0a0a0a !important;
		}
		
		.dsb-chat-message-bubble {
			background: #27272a !important;
			color: #ffffff !important;
		}
		
		.dsb-chat-message.own .dsb-chat-message-bubble {
			background: var(--dsb-gradient-primary) !important;
		}
		
		.dsb-chat-message-username a {
			color: #ffffff !important;
		}
		
		.dsb-chat-message-time {
			color: #71717a !important;
		}
		
		.dsb-chat-input-area {
			background: #161616 !important;
			border-top: 1px solid #27272a !important;
		}
		
		.dsb-online-sidebar {
			background: #161616 !important;
			border-left: 1px solid #27272a !important;
		}
		
		.dsb-online-header {
			color: #ffffff !important;
		}
		
		.dsb-online-user-name {
			color: #ffffff !important;
		}
		';
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'dsb-public', DSB_PLUGIN_URL . 'public/js/dsb-public.js', array( 'jquery' ), DSB_VERSION, true );
		
		wp_localize_script( 'dsb-public', 'dsbPublic', array(
			'ajaxurl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'dsb_public_nonce' ),
			'messaging_nonce'   => wp_create_nonce( 'dsb_messaging_nonce' ),
			'group_chat_nonce'  => wp_create_nonce( 'dsb_group_chat_nonce' ),
			'current_user_id'   => get_current_user_id(),
			'strings'           => array(
				'confirm_delete' => __( 'Are you sure you want to delete this photo?', 'dating-site-builder' ),
				'confirm_block'  => __( 'Are you sure you want to block this user?', 'dating-site-builder' ),
			),
		) );
	}

	/**
	 * Register all shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'dsb_register', array( $this, 'shortcode_register' ) );
		add_shortcode( 'dsb_login', array( $this, 'shortcode_login' ) );
		add_shortcode( 'dsb_forgot_password', array( $this, 'shortcode_forgot_password' ) );
		add_shortcode( 'dsb_profile_edit', array( $this, 'shortcode_profile_edit' ) );
		add_shortcode( 'dsb_profile_view', array( $this, 'shortcode_profile_view' ) );
		add_shortcode( 'dsb_member_directory', array( $this, 'shortcode_member_directory' ) );
		add_shortcode( 'dsb_matches', array( $this, 'shortcode_matches' ) );
		add_shortcode( 'dsb_messages', array( $this, 'shortcode_messages' ) );
		add_shortcode( 'dsb_likes', array( $this, 'shortcode_likes' ) );
		add_shortcode( 'dsb_logout', array( $this, 'shortcode_logout' ) );
		add_shortcode( 'dsb_member_nav', array( $this, 'shortcode_member_nav' ) );
		add_shortcode( 'dsb_group_chat', array( $this, 'shortcode_group_chat' ) );
	}

	/**
	 * Logout shortcode.
	 */
	public function shortcode_logout( $atts ) {
		$atts = shortcode_atts( array(
			'text' => __( 'Logout', 'dating-site-builder' ),
			'redirect' => home_url(),
		), $atts );

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$logout_url = wp_logout_url( $atts['redirect'] );
		return '<a href="' . esc_url( $logout_url ) . '" class="dsb-btn dsb-btn-secondary dsb-logout-btn">' . esc_html( $atts['text'] ) . '</a>';
	}

	/**
	 * Member navigation bar shortcode.
	 */
	public function shortcode_member_nav( $atts ) {
		if ( ! is_user_logged_in() ) {
			// Show login/register links for guests
			ob_start();
			?>
			<nav class="dsb-member-nav dsb-guest-nav">
				<div class="dsb-nav-links">
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_login_page' ) ) ); ?>" class="dsb-nav-link">
						<?php _e( 'Login', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_register_page' ) ) ); ?>" class="dsb-btn dsb-btn-primary">
						<?php _e( 'Join Free', 'dating-site-builder' ); ?>
					</a>
				</div>
			</nav>
			<?php
			return ob_get_clean();
		}

		$user = wp_get_current_user();
		$unread_messages = $this->get_unread_message_count( get_current_user_id() );

		ob_start();
		?>
		<nav class="dsb-member-nav">
			<div class="dsb-nav-links">
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_member_directory_page' ) ) ); ?>" class="dsb-nav-link">
					<?php _e( 'Browse', 'dating-site-builder' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_matches_page' ) ) ); ?>" class="dsb-nav-link">
					<?php _e( 'Matches', 'dating-site-builder' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_likes_page' ) ) ); ?>" class="dsb-nav-link">
					<?php _e( 'Likes', 'dating-site-builder' ); ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_messages_page' ) ) ); ?>" class="dsb-nav-link dsb-nav-messages">
					<?php _e( 'Messages', 'dating-site-builder' ); ?>
					<?php if ( $unread_messages > 0 ) : ?>
						<span class="dsb-nav-badge"><?php echo esc_html( $unread_messages ); ?></span>
					<?php endif; ?>
				</a>
			</div>
			<div class="dsb-nav-user">
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_profile_edit_page' ) ) ); ?>" class="dsb-nav-profile">
					<?php echo get_avatar( get_current_user_id(), 32 ); ?>
					<span><?php echo esc_html( $user->display_name ); ?></span>
				</a>
				<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="dsb-nav-link dsb-nav-logout">
					<?php _e( 'Logout', 'dating-site-builder' ); ?>
				</a>
			</div>
		</nav>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get unread message count for a user.
	 */
	private function get_unread_message_count( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_messages';
		
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE receiver_id = %d AND read_at IS NULL",
			$user_id
		) );
	}

	/**
	 * Get the dating site name.
	 */
	private function get_site_name() {
		$site_name = get_option( 'dsb_site_name', '' );
		return ! empty( $site_name ) ? $site_name : get_bloginfo( 'name' );
	}

	/**
	 * Render the app header for member pages.
	 */
	private function render_app_header( $active_page = '' ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user = wp_get_current_user();
		$unread_messages = $this->get_unread_message_count( get_current_user_id() );

		ob_start();
		?>
		<header class="dsb-app-header">
			<div class="dsb-app-header-inner">
				<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_member_directory_page' ) ) ); ?>" class="dsb-app-logo">
					<?php
					$dsb_logo_id  = (int) get_option( 'dsb_site_logo', 0 );
					$dsb_logo_url = $dsb_logo_id ? wp_get_attachment_image_url( $dsb_logo_id, 'medium' ) : '';
					if ( $dsb_logo_url ) :
					?>
						<img class="dsb-app-logo-img" src="<?php echo esc_url( $dsb_logo_url ); ?>" alt="<?php echo esc_attr( $this->get_site_name() ); ?>">
					<?php else : ?>
						<span class="dsb-app-logo-icon">💕</span>
						<span class="dsb-app-logo-text"><?php echo esc_html( $this->get_site_name() ); ?></span>
					<?php endif; ?>
				</a>
				<nav class="dsb-app-nav">
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_member_directory_page' ) ) ); ?>" class="dsb-app-nav-link <?php echo $active_page === 'browse' ? 'active' : ''; ?>">
						<?php _e( 'Browse', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_group_chat_page' ) ) ); ?>" class="dsb-app-nav-link <?php echo $active_page === 'chat' ? 'active' : ''; ?>">
						<?php _e( 'Chat', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_matches_page' ) ) ); ?>" class="dsb-app-nav-link <?php echo $active_page === 'matches' ? 'active' : ''; ?>">
						<?php _e( 'Matches', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_likes_page' ) ) ); ?>" class="dsb-app-nav-link <?php echo $active_page === 'likes' ? 'active' : ''; ?>">
						<?php _e( 'Likes', 'dating-site-builder' ); ?>
					</a>
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_messages_page' ) ) ); ?>" class="dsb-app-nav-link <?php echo $active_page === 'messages' ? 'active' : ''; ?>">
						<?php _e( 'Messages', 'dating-site-builder' ); ?>
						<?php if ( $unread_messages > 0 ) : ?>
							<span class="dsb-badge"><?php echo esc_html( $unread_messages ); ?></span>
						<?php endif; ?>
					</a>
				</nav>
				<div class="dsb-app-user">
					<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_profile_edit_page' ) ) ); ?>" class="dsb-app-user-link">
						<?php echo get_avatar( get_current_user_id(), 32 ); ?>
						<span class="dsb-app-user-name"><?php echo esc_html( $user->display_name ); ?></span>
					</a>
					<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" class="dsb-app-logout">
						<?php _e( 'Logout', 'dating-site-builder' ); ?>
					</a>
				</div>
			</div>
		</header>
		<?php
		return ob_get_clean();
	}

	/**
	 * Add body class for member pages.
	 */
	private function add_member_page_class() {
		add_filter( 'body_class', function( $classes ) {
			$classes[] = 'dsb-member-page';
			return $classes;
		});
	}

	/**
	 * Forgot password form shortcode.
	 */
	public function shortcode_forgot_password( $atts ) {
		if ( is_user_logged_in() ) {
			$dashboard_url = get_permalink( get_option( 'dsb_member_directory_page' ) );
			return '<p>' . sprintf( __( 'You are already logged in. <a href="%s">Browse members</a>', 'dating-site-builder' ), esc_url( $dashboard_url ) ) . '</p>';
		}

		// Add fullscreen body class
		add_filter( 'body_class', function( $classes ) {
			$classes[] = 'dsb-fullscreen-page';
			return $classes;
		});

		ob_start();
		?>
		<div class="dsb-fullscreen-wrapper">
			<div class="dsb-fullscreen-bg">
				<div class="dsb-bg-overlay"></div>
			</div>
			<div class="dsb-fullscreen-content">
				<div class="dsb-auth-container">
					<div class="dsb-auth-branding">
						<?php echo $this->render_auth_logo( '🔐' ); ?>
						<h1><?php echo esc_html( $this->get_site_name() ); ?></h1>
						<p><?php _e( 'Reset your password', 'dating-site-builder' ); ?></p>
					</div>
					<form id="dsb-forgot-password-form" class="dsb-auth-form">
						<?php wp_nonce_field( 'dsb_forgot_password', 'dsb_forgot_password_nonce' ); ?>
						
						<h2><?php _e( 'Forgot Password?', 'dating-site-builder' ); ?></h2>
						<p class="dsb-auth-subtitle"><?php _e( 'Enter your email and we\'ll send you a reset link', 'dating-site-builder' ); ?></p>

						<div class="dsb-form-group">
							<label for="forgot_email"><?php _e( 'Email Address', 'dating-site-builder' ); ?></label>
							<input type="email" id="forgot_email" name="email" placeholder="<?php _e( 'Enter your email address', 'dating-site-builder' ); ?>" required />
						</div>

						<div class="dsb-form-message"></div>

						<button type="submit" class="dsb-btn dsb-btn-primary dsb-btn-large">
							<?php _e( 'Send Reset Link', 'dating-site-builder' ); ?>
						</button>

						<div class="dsb-auth-divider">
							<span><?php _e( 'Remember your password?', 'dating-site-builder' ); ?></span>
						</div>

						<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_login_page' ) ) ); ?>" class="dsb-btn dsb-btn-outline dsb-btn-large">
							<?php _e( 'Back to Sign In', 'dating-site-builder' ); ?>
						</a>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Registration form shortcode.
	 */
	public function shortcode_register( $atts ) {
		if ( is_user_logged_in() ) {
			return '<p>' . __( 'You are already registered and logged in.', 'dating-site-builder' ) . '</p>';
		}

		$min_age = get_option( 'dsb_minimum_age', 18 );
		$require_verification = get_option( 'dsb_require_email_verification', false );

		// Add fullscreen body class
		add_filter( 'body_class', function( $classes ) {
			$classes[] = 'dsb-fullscreen-page';
			return $classes;
		});
		
		ob_start();
		?>
		<div class="dsb-fullscreen-wrapper">
			<div class="dsb-fullscreen-bg">
				<div class="dsb-bg-overlay"></div>
			</div>
			<div class="dsb-fullscreen-content">
				<div class="dsb-auth-container dsb-register-container">
					<div class="dsb-auth-branding">
						<?php echo $this->render_auth_logo( '💕' ); ?>
						<h1><?php echo esc_html( $this->get_site_name() ); ?></h1>
						<p><?php _e( 'Start your love story today', 'dating-site-builder' ); ?></p>
					</div>
					<form id="dsb-register-form" class="dsb-auth-form">
						<?php wp_nonce_field( 'dsb_register', 'dsb_register_nonce' ); ?>
						
						<h2><?php _e( 'Create Account', 'dating-site-builder' ); ?></h2>
						<p class="dsb-auth-subtitle"><?php printf( __( 'Join free. Must be %d or older.', 'dating-site-builder' ), $min_age ); ?></p>

						<div class="dsb-form-row">
							<div class="dsb-form-group">
								<label for="reg_username"><?php _e( 'Username', 'dating-site-builder' ); ?></label>
								<input type="text" id="reg_username" name="username" placeholder="<?php _e( 'Choose a username', 'dating-site-builder' ); ?>" required />
							</div>
							<div class="dsb-form-group">
								<label for="reg_email"><?php _e( 'Email', 'dating-site-builder' ); ?></label>
								<input type="email" id="reg_email" name="email" placeholder="<?php _e( 'Your email address', 'dating-site-builder' ); ?>" required />
							</div>
						</div>

						<div class="dsb-form-group">
							<label for="reg_display_name"><?php _e( 'Display Name', 'dating-site-builder' ); ?></label>
							<input type="text" id="reg_display_name" name="display_name" placeholder="<?php _e( 'How others will see you', 'dating-site-builder' ); ?>" required />
						</div>

						<div class="dsb-form-row">
							<div class="dsb-form-group">
								<label for="reg_password"><?php _e( 'Password', 'dating-site-builder' ); ?></label>
								<input type="password" id="reg_password" name="password" placeholder="<?php _e( 'Create a password', 'dating-site-builder' ); ?>" required />
							</div>
							<div class="dsb-form-group">
								<label for="reg_password_confirm"><?php _e( 'Confirm Password', 'dating-site-builder' ); ?></label>
								<input type="password" id="reg_password_confirm" name="password_confirm" placeholder="<?php _e( 'Confirm password', 'dating-site-builder' ); ?>" required />
							</div>
						</div>

						<div class="dsb-form-group">
							<label class="dsb-checkbox-label">
								<input type="checkbox" name="agree_terms" required />
								<span><?php _e( 'I agree to the Terms of Service and Privacy Policy', 'dating-site-builder' ); ?></span>
							</label>
						</div>

						<?php if ( $require_verification ) : ?>
						<div class="dsb-alert dsb-alert-info">
							<?php _e( 'You will need to verify your email address.', 'dating-site-builder' ); ?>
						</div>
						<?php endif; ?>

						<div class="dsb-form-message"></div>

						<button type="submit" class="dsb-btn dsb-btn-primary dsb-btn-large">
							<?php _e( 'Create Free Account', 'dating-site-builder' ); ?>
						</button>

						<div class="dsb-auth-divider">
							<span><?php _e( 'Already a member?', 'dating-site-builder' ); ?></span>
						</div>

						<a href="<?php echo esc_url( $this->get_dsb_page_url( 'dsb_login_page', 'login' ) ); ?>" class="dsb-btn dsb-btn-outline dsb-btn-large">
							<?php _e( 'Sign In', 'dating-site-builder' ); ?>
						</a>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Resolve a plugin page URL safely.
	 *
	 * Falls back to `get_page_by_path( $slug )` when the option is
	 * empty or points at a missing post, and finally to the site
	 * home URL. Prevents the old behaviour where `get_permalink(0)`
	 * silently returned the current page URL and produced links that
	 * looped back to themselves.
	 *
	 * @param string $option_name dsb_*_page option to read.
	 * @param string $slug        Page slug to look up as a fallback.
	 * @return string
	 */
	public function get_dsb_page_url( $option_name, $slug ) {
		$page_id = (int) get_option( $option_name );
		if ( $page_id ) {
			$post = get_post( $page_id );
			if ( $post && 'trash' !== $post->post_status ) {
				$url = get_permalink( $page_id );
				if ( $url ) {
					return (string) $url;
				}
			}
		}

		$fallback = get_page_by_path( $slug );
		if ( $fallback && 'trash' !== $fallback->post_status ) {
			$url = get_permalink( $fallback->ID );
			if ( $url ) {
				return (string) $url;
			}
		}

		return home_url( '/' . ltrim( $slug, '/' ) . '/' );
	}

	/**
	 * Render the branding logo for the full-screen auth pages
	 * (login, register, forgot-password).
	 *
	 * If the admin has uploaded a Site Logo via the wizard, that
	 * image is shown. Otherwise we fall back to the page-specific
	 * emoji that was previously hard-coded.
	 *
	 * @param string $emoji_fallback Emoji or text shown when no logo.
	 * @return string HTML.
	 */
	private function render_auth_logo( $emoji_fallback = '💕' ) {
		$logo_id  = (int) get_option( 'dsb_site_logo', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		if ( $logo_url ) {
			return '<div class="dsb-logo dsb-logo-has-image">'
				. '<img class="dsb-logo-img" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $this->get_site_name() ) . '">'
				. '</div>';
		}

		return '<div class="dsb-logo">' . $emoji_fallback . '</div>';
	}

	/**
	 * Login form shortcode.
	 */
	public function shortcode_login( $atts ) {
		if ( is_user_logged_in() ) {
			// Redirect logged-in users to Browse Members, but never to
			// ourselves – that would create an infinite redirect loop if
			// the option is missing or mis-configured.
			$directory_id   = (int) get_option( 'dsb_member_directory_page' );
			$login_page_id  = (int) get_option( 'dsb_login_page' );
			$current_id     = (int) get_queried_object_id();
			$dashboard_url  = $directory_id ? get_permalink( $directory_id ) : '';

			$is_same_page = (
				! $directory_id
				|| $directory_id === $login_page_id
				|| $directory_id === $current_id
				|| empty( $dashboard_url )
				|| trailingslashit( $dashboard_url ) === trailingslashit( ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] )
			);

			if ( ! $is_same_page ) {
				if ( ! headers_sent() ) {
					wp_safe_redirect( $dashboard_url );
					exit;
				}
				return '<script>window.location.href = "' . esc_url( $dashboard_url ) . '";</script>
					<p>' . sprintf( __( 'Redirecting... <a href="%s">Click here</a> if not redirected.', 'dating-site-builder' ), esc_url( $dashboard_url ) ) . '</p>';
			}

			// Same-page fallback: don't redirect, show a friendly link
			// so the user can always escape this page.
			$fallback_url = $dashboard_url ? $dashboard_url : home_url( '/' );
			return '<p>' . sprintf(
				__( 'You are already logged in. <a href="%s">Continue</a> or <a href="%s">log out</a>.', 'dating-site-builder' ),
				esc_url( $fallback_url ),
				esc_url( wp_logout_url( home_url() ) )
			) . '</p>';
		}

		// Add fullscreen body class
		add_filter( 'body_class', function( $classes ) {
			$classes[] = 'dsb-fullscreen-page';
			return $classes;
		});

		ob_start();
		?>
		<div class="dsb-fullscreen-wrapper">
			<div class="dsb-fullscreen-bg">
				<div class="dsb-bg-overlay"></div>
			</div>
			<div class="dsb-fullscreen-content">
				<div class="dsb-auth-container">
					<div class="dsb-auth-branding">
						<?php echo $this->render_auth_logo( '💕' ); ?>
						<h1><?php echo esc_html( $this->get_site_name() ); ?></h1>
						<p><?php _e( 'Find your perfect match today', 'dating-site-builder' ); ?></p>
					</div>
					<form id="dsb-login-form" class="dsb-auth-form">
						<?php wp_nonce_field( 'dsb_login', 'dsb_login_nonce' ); ?>
						
						<h2><?php _e( 'Welcome Back', 'dating-site-builder' ); ?></h2>
						<p class="dsb-auth-subtitle"><?php _e( 'Login to continue your journey', 'dating-site-builder' ); ?></p>

						<div class="dsb-form-group">
							<label for="login_username"><?php _e( 'Username or Email', 'dating-site-builder' ); ?></label>
							<input type="text" id="login_username" name="username" placeholder="<?php _e( 'Enter your username or email', 'dating-site-builder' ); ?>" required />
						</div>

						<div class="dsb-form-group">
							<label for="login_password"><?php _e( 'Password', 'dating-site-builder' ); ?></label>
							<input type="password" id="login_password" name="password" placeholder="<?php _e( 'Enter your password', 'dating-site-builder' ); ?>" required />
						</div>

						<div class="dsb-form-row-split">
							<label class="dsb-checkbox-label">
								<input type="checkbox" name="remember" value="1" />
								<span><?php _e( 'Remember Me', 'dating-site-builder' ); ?></span>
							</label>
							<a href="<?php echo esc_url( get_permalink( get_option( 'dsb_forgot_password_page' ) ) ); ?>" class="dsb-forgot-link"><?php _e( 'Forgot Password?', 'dating-site-builder' ); ?></a>
						</div>

						<div class="dsb-form-message"></div>

						<button type="submit" class="dsb-btn dsb-btn-primary dsb-btn-large">
							<?php _e( 'Sign In', 'dating-site-builder' ); ?>
						</button>

						<div class="dsb-auth-divider">
							<span><?php _e( 'New here?', 'dating-site-builder' ); ?></span>
						</div>

						<a href="<?php echo esc_url( $this->get_dsb_page_url( 'dsb_register_page', 'register' ) ); ?>" class="dsb-btn dsb-btn-outline dsb-btn-large">
							<?php _e( 'Create Free Account', 'dating-site-builder' ); ?>
						</a>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Profile edit shortcode.
	 */
	public function shortcode_profile_edit( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to edit your profile.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		$user_id = get_current_user_id();
		$user = get_userdata( $user_id );
		$fields = DSB_Profile_Fields::get_all_fields();

		ob_start();
		echo $this->render_app_header( 'profile' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-profile-edit-wrapper">
			<form id="dsb-profile-edit-form" class="dsb-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'dsb_update_profile', 'dsb_profile_nonce' ); ?>
				
				<div class="dsb-profile-header">
					<h2><?php _e( 'Edit Your Profile', 'dating-site-builder' ); ?></h2>
				</div>

				<!-- Photo Upload Section -->
				<div class="dsb-photo-section">
					<h3><?php _e( 'Your Photos', 'dating-site-builder' ); ?></h3>
					<div class="dsb-photo-grid" id="dsb-photo-grid">
						<?php echo $this->render_user_photos( $user_id, true ); ?>
					</div>
					<div class="dsb-photo-upload">
						<input type="file" id="dsb-photo-upload" accept="image/*" multiple />
						<label for="dsb-photo-upload" class="dsb-btn dsb-btn-secondary">
							<?php _e( 'Add Photos', 'dating-site-builder' ); ?>
						</label>
						<small><?php _e( 'Upload up to 10 photos. First photo will be your main photo.', 'dating-site-builder' ); ?></small>
					</div>
				</div>

				<!-- Profile Fields -->
				<div class="dsb-profile-fields">
					<?php foreach ( $fields as $field_key => $field ) : 
						$value = get_user_meta( $user_id, 'dsb_' . $field_key, true );
						$couple_attr = ! empty( $field['requires_couple'] ) ? ' data-requires-couple="1"' : '';
						?>
						<div class="dsb-form-group"<?php echo $couple_attr; ?>>
							<label for="field_<?php echo esc_attr( $field_key ); ?>">
								<?php echo esc_html( $field['label'] ); ?>
								<?php if ( ! empty( $field['required'] ) ) : ?>
									<span class="required">*</span>
								<?php endif; ?>
							</label>

							<?php
							switch ( $field['type'] ) {
								case 'text':
									?>
									<input 
										type="text" 
										id="field_<?php echo esc_attr( $field_key ); ?>" 
										name="<?php echo esc_attr( $field_key ); ?>" 
										value="<?php echo esc_attr( $value ); ?>"
										<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
										<?php echo ! empty( $field['maxlength'] ) ? 'maxlength="' . $field['maxlength'] . '"' : ''; ?>
									/>
									<?php
									break;

								case 'date':
									?>
									<input 
										type="date" 
										id="field_<?php echo esc_attr( $field_key ); ?>" 
										name="<?php echo esc_attr( $field_key ); ?>" 
										value="<?php echo esc_attr( $value ); ?>"
										<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
									/>
									<?php
									break;

								case 'textarea':
									?>
									<textarea 
										id="field_<?php echo esc_attr( $field_key ); ?>" 
										name="<?php echo esc_attr( $field_key ); ?>"
										<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
										<?php echo ! empty( $field['maxlength'] ) ? 'maxlength="' . $field['maxlength'] . '"' : ''; ?>
										rows="4"
									><?php echo esc_textarea( $value ); ?></textarea>
									<?php
									break;

								case 'select':
									?>
									<select 
										id="field_<?php echo esc_attr( $field_key ); ?>" 
										name="<?php echo esc_attr( $field_key ); ?>"
										<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
									>
										<option value=""><?php _e( 'Select...', 'dating-site-builder' ); ?></option>
										<?php foreach ( $field['options'] as $opt_key => $opt_label ) : ?>
											<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $value, $opt_key ); ?>>
												<?php echo esc_html( $opt_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<?php
									break;

								case 'checkbox':
									$selected_values = is_array( $value ) ? $value : array();
									foreach ( $field['options'] as $opt_key => $opt_label ) :
									?>
										<label class="dsb-checkbox">
											<input 
												type="checkbox" 
												name="<?php echo esc_attr( $field_key ); ?>[]" 
												value="<?php echo esc_attr( $opt_key ); ?>"
												<?php checked( in_array( $opt_key, $selected_values ) ); ?>
											/>
											<?php echo esc_html( $opt_label ); ?>
										</label>
									<?php endforeach;
									break;
							}
							?>

							<?php if ( ! empty( $field['description'] ) ) : ?>
								<small><?php echo esc_html( $field['description'] ); ?></small>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="dsb-form-message"></div>

				<button type="submit" class="dsb-btn dsb-btn-primary dsb-btn-large">
					<?php _e( 'Save Profile', 'dating-site-builder' ); ?>
				</button>
			</form>
		</div>
		</div><!-- .dsb-app-content -->
		<script>
		jQuery(function($){
			// Show / hide partner-only fields based on the "I am / We are"
			// select. Partner fields are flagged with data-requires-couple="1".
			function dsbToggleCoupleFields(){
				var $kind  = $('#field_profile_kind');
				var value  = $kind.length ? ( $kind.val() || '' ) : '';
				var couple = value.indexOf('couple_') === 0;
				$('.dsb-form-group[data-requires-couple]').toggle( couple );
			}
			dsbToggleCoupleFields();
			$(document).on('change', '#field_profile_kind', dsbToggleCoupleFields);
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Profile view shortcode.
	 */
	public function shortcode_profile_view( $atts ) {
		$atts = shortcode_atts( array(
			'user_id' => get_current_user_id(),
		), $atts );

		// Check for profile_user query param
		if ( isset( $_GET['profile_user'] ) ) {
			$atts['user_id'] = intval( $_GET['profile_user'] );
		}

		$user_id = intval( $atts['user_id'] );
		$current_user_id = get_current_user_id();

		if ( ! $user_id || ! $current_user_id ) {
			return '<p>' . __( 'Invalid profile.', 'dating-site-builder' ) . '</p>';
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '<p>' . __( 'User not found.', 'dating-site-builder' ); '</p>';
		}

		// Check if blocked
		if ( $user_id !== $current_user_id ) {
			global $wpdb;
			$blocks_table = $wpdb->prefix . 'dsb_blocks';
			$is_blocked = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $blocks_table WHERE blocker_id = %d AND blocked_id = %d",
				$current_user_id, $user_id
			) );

			if ( $is_blocked ) {
				return '<p>' . __( 'This profile is not available.', 'dating-site-builder' ); '</p>';
			}
		}

		// Track profile view
		if ( $user_id !== $current_user_id ) {
			$this->track_profile_view( $user_id, $current_user_id );
		}

		$fields            = DSB_Profile_Fields::get_all_fields();
		$age               = $this->calculate_age( get_user_meta( $user_id, 'dsb_date_of_birth', true ) );
		$gender            = get_user_meta( $user_id, 'dsb_gender', true );
		$city              = get_user_meta( $user_id, 'dsb_city', true );
		$state             = get_user_meta( $user_id, 'dsb_state', true );
		$country           = get_user_meta( $user_id, 'dsb_country', true );
		$headline          = get_user_meta( $user_id, 'dsb_headline', true );
		$about_me          = get_user_meta( $user_id, 'dsb_about_me', true );
		$looking_for_text  = get_user_meta( $user_id, 'dsb_looking_for_text', true );

		// I am / We are + partner / dual-profile data.
		$profile_kind        = get_user_meta( $user_id, 'dsb_profile_kind', true );
		$profile_kind_label  = '';
		if ( $profile_kind && isset( $fields['profile_kind']['options'][ $profile_kind ] ) ) {
			$profile_kind_label = $fields['profile_kind']['options'][ $profile_kind ];
		}
		$is_couple   = $profile_kind && strpos( $profile_kind, 'couple_' ) === 0;
		$kind_prefix = $is_couple ? __( 'We are', 'dating-site-builder' ) : __( 'I am', 'dating-site-builder' );

		$partner_name        = get_user_meta( $user_id, 'dsb_partner_display_name', true );
		$partner_dob         = get_user_meta( $user_id, 'dsb_partner_date_of_birth', true );
		$partner_age         = $partner_dob ? $this->calculate_age( $partner_dob ) : '';
		$partner_headline    = get_user_meta( $user_id, 'dsb_partner_headline', true );
		$partner_about       = get_user_meta( $user_id, 'dsb_partner_about', true );
		$partner_looking_for = get_user_meta( $user_id, 'dsb_partner_looking_for', true );
		$has_partner_info    = $is_couple && (
			$partner_name || $partner_age || $partner_headline || $partner_about || $partner_looking_for
		);

		// Build the location string from whatever pieces the member has
		// supplied (city, state/region, country) so visitors can see
		// roughly where they are without leaking the full address.
		$location_parts = array_filter( array( $city, $state, $country ), 'strlen' );
		$location       = implode( ', ', $location_parts );

		// Check if liked
		$is_liked = false;
		if ( $user_id !== $current_user_id ) {
			$is_liked = DSB_Likes::has_liked( $current_user_id, $user_id );
		}

		// Fields that we render in dedicated sections above the generic
		// details list — skip them when iterating $fields below so they
		// don't appear twice.
		$handled_keys = array(
			'date_of_birth',
			'headline',
			'about_me',
			'looking_for_text',
			'city',
			'state',
			'country',
			// Surfaced explicitly above / in the partner section below.
			'profile_kind',
			'partner_display_name',
			'partner_date_of_birth',
			'partner_headline',
			'partner_about',
			'partner_looking_for',
		);

		// Pre-compute the remaining detail rows so we can decide whether
		// to render the "Profile Details" heading at all.
		$detail_rows = array();
		foreach ( $fields as $field_key => $field ) {
			if ( in_array( $field_key, $handled_keys, true ) ) {
				continue;
			}
			$value = get_user_meta( $user_id, 'dsb_' . $field_key, true );
			if ( '' === $value || null === $value || array() === $value ) {
				continue;
			}

			// Translate stored option keys back to their human label
			// for select / checkbox fields.
			$display_value = $value;
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				if ( is_array( $value ) ) {
					$labels = array();
					foreach ( $value as $v ) {
						$labels[] = isset( $field['options'][ $v ] ) ? $field['options'][ $v ] : $v;
					}
					$display_value = implode( ', ', $labels );
				} else {
					$display_value = isset( $field['options'][ $value ] ) ? $field['options'][ $value ] : $value;
				}
			} elseif ( is_array( $value ) ) {
				$display_value = implode( ', ', $value );
			}

			$detail_rows[] = array(
				'label' => $field['label'],
				'value' => $display_value,
			);
		}

		$has_any_text = ( $headline || $about_me || $looking_for_text || ! empty( $detail_rows ) || $location || $profile_kind_label || $has_partner_info );

		$this->add_member_page_class();

		ob_start();
		echo $this->render_app_header( '' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-profile-view-wrapper dsb-profile-view-narrow">
			<div class="dsb-profile-card dsb-profile-card-stacked">
				<div class="dsb-profile-photos">
					<?php echo $this->render_user_photos( $user_id, false ); ?>
				</div>

				<div class="dsb-profile-info">
					<div class="dsb-profile-header">
						<h2>
							<?php echo esc_html( $user->display_name ); ?><?php echo $age ? ', ' . esc_html( $age ) : ''; ?>
						</h2>
						<?php if ( $profile_kind_label ) : ?>
							<p class="dsb-profile-kind">
								<span class="dsb-profile-kind-prefix"><?php echo esc_html( $kind_prefix ); ?>:</span>
								<span class="dsb-profile-kind-value"><?php echo esc_html( $profile_kind_label ); ?></span>
							</p>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<p class="dsb-profile-location"><?php echo esc_html( $location ); ?></p>
						<?php endif; ?>
						<?php if ( $headline ) : ?>
							<p class="dsb-profile-headline">“<?php echo esc_html( $headline ); ?>”</p>
						<?php endif; ?>
					</div>

					<?php if ( $user_id !== $current_user_id ) : ?>
					<div class="dsb-profile-actions">
						<button class="dsb-btn dsb-btn-secondary dsb-like-btn <?php echo $is_liked ? 'liked' : ''; ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>">
							<span class="dsb-icon-heart" aria-hidden="true"></span>
							<span class="dsb-btn-label"><?php echo $is_liked ? esc_html__( 'Liked', 'dating-site-builder' ) : esc_html__( 'Like', 'dating-site-builder' ); ?></span>
						</button>
						<a href="<?php echo esc_url( add_query_arg( 'conversation', $user_id, get_permalink( get_option( 'dsb_messages_page' ) ) ) ); ?>" class="dsb-btn dsb-btn-primary dsb-message-btn" data-user-id="<?php echo esc_attr( $user_id ); ?>">
							<span class="dsb-icon-message" aria-hidden="true"></span>
							<span class="dsb-btn-label"><?php esc_html_e( 'Message', 'dating-site-builder' ); ?></span>
						</a>
						<button class="dsb-btn dsb-btn-text dsb-report-btn" data-user-id="<?php echo esc_attr( $user_id ); ?>">
							<?php esc_html_e( 'Report', 'dating-site-builder' ); ?>
						</button>
					</div>
					<?php endif; ?>

					<?php if ( $about_me ) : ?>
						<section class="dsb-profile-section">
							<h3><?php esc_html_e( 'About Me', 'dating-site-builder' ); ?></h3>
							<p class="dsb-profile-bio"><?php echo nl2br( esc_html( $about_me ) ); ?></p>
						</section>
					<?php endif; ?>

					<?php if ( $looking_for_text ) : ?>
						<section class="dsb-profile-section">
							<h3><?php esc_html_e( 'What I\'m Looking For', 'dating-site-builder' ); ?></h3>
							<p class="dsb-profile-bio"><?php echo nl2br( esc_html( $looking_for_text ) ); ?></p>
						</section>
					<?php endif; ?>

					<?php if ( $has_partner_info ) : ?>
						<section class="dsb-profile-section dsb-profile-partner">
							<h3>
								<?php
								if ( $partner_name ) {
									printf(
										/* translators: %s: partner display name and (optional) age. */
										esc_html__( 'About %s', 'dating-site-builder' ),
										esc_html( $partner_name . ( $partner_age ? ', ' . $partner_age : '' ) )
									);
								} else {
									esc_html_e( 'About Partner', 'dating-site-builder' );
								}
								?>
							</h3>
							<?php if ( $partner_headline ) : ?>
								<p class="dsb-profile-headline">“<?php echo esc_html( $partner_headline ); ?>”</p>
							<?php endif; ?>
							<?php if ( $partner_about ) : ?>
								<p class="dsb-profile-bio"><?php echo nl2br( esc_html( $partner_about ) ); ?></p>
							<?php endif; ?>
							<?php if ( $partner_looking_for ) : ?>
								<p class="dsb-profile-bio">
									<strong><?php esc_html_e( 'What partner is looking for:', 'dating-site-builder' ); ?></strong><br>
									<?php echo nl2br( esc_html( $partner_looking_for ) ); ?>
								</p>
							<?php endif; ?>
						</section>
					<?php endif; ?>

					<?php if ( ! empty( $detail_rows ) ) : ?>
						<section class="dsb-profile-section">
							<h3><?php esc_html_e( 'Profile Details', 'dating-site-builder' ); ?></h3>
							<div class="dsb-profile-details">
								<?php foreach ( $detail_rows as $row ) : ?>
									<div class="dsb-profile-field">
										<strong><?php echo esc_html( $row['label'] ); ?>:</strong>
										<span><?php echo esc_html( $row['value'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<?php if ( ! $has_any_text ) : ?>
						<p class="dsb-profile-empty">
							<?php esc_html_e( 'This member hasn\'t added any profile details yet.', 'dating-site-builder' ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Member directory shortcode.
	 */
	public function shortcode_member_directory( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to browse members.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		$atts = shortcode_atts( array(
			'per_page' => 12,
		), $atts );

		$current_user_id = get_current_user_id();
		$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

		// Build user query
		$args = array(
			'role__in' => array( 'dating_member', 'dating_premium' ),
			'exclude'  => array( $current_user_id ),
			'number'   => intval( $atts['per_page'] ),
			'paged'    => $paged,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'dsb_profile_approved',
					'value'   => '1',
					'compare' => '=',
				),
				array(
					'key'     => 'dsb_banned',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$user_query = new WP_User_Query( $args );
		$users = $user_query->get_results();
		$total_users = $user_query->get_total();

		ob_start();
		echo $this->render_app_header( 'browse' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-member-directory-wrapper">
			<div class="dsb-directory-header">
				<h2><?php _e( 'Browse Members', 'dating-site-builder' ); ?></h2>
				<div class="dsb-directory-filters">
					<select id="dsb-filter-gender" class="dsb-filter">
						<option value=""><?php _e( 'All Genders', 'dating-site-builder' ); ?></option>
						<option value="male"><?php _e( 'Male', 'dating-site-builder' ); ?></option>
						<option value="female"><?php _e( 'Female', 'dating-site-builder' ); ?></option>
						<option value="non-binary"><?php _e( 'Non-binary', 'dating-site-builder' ); ?></option>
					</select>
					<input type="number" id="dsb-filter-age-min" class="dsb-filter" placeholder="<?php _e( 'Min age', 'dating-site-builder' ); ?>" min="18" max="99" />
					<input type="number" id="dsb-filter-age-max" class="dsb-filter" placeholder="<?php _e( 'Max age', 'dating-site-builder' ); ?>" min="18" max="99" />
					<input type="text" id="dsb-filter-location" class="dsb-filter" placeholder="<?php _e( 'Location', 'dating-site-builder' ); ?>" />
					<button class="dsb-btn dsb-btn-primary" id="dsb-apply-filters"><?php _e( 'Filter', 'dating-site-builder' ); ?></button>
				</div>
			</div>

			<div class="dsb-member-grid">
				<?php if ( $users ) : ?>
					<?php foreach ( $users as $user ) : ?>
						<?php echo $this->render_member_card( $user->ID ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="dsb-no-results"><?php _e( 'No members found.', 'dating-site-builder' ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $total_users > $atts['per_page'] ) : ?>
			<div class="dsb-pagination">
				<?php
				echo paginate_links( array(
					'total'   => ceil( $total_users / $atts['per_page'] ),
					'current' => $paged,
				) );
				?>
			</div>
			<?php endif; ?>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Matches shortcode.
	 */
	public function shortcode_matches( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to see matches.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		$user_id = get_current_user_id();
		$matches = DSB_Matching::get_matches( $user_id, array( 'limit' => 20 ) );

		ob_start();
		echo $this->render_app_header( 'matches' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-matches-wrapper">
			<div class="dsb-matches-header">
				<h2><?php _e( 'Your Matches', 'dating-site-builder' ); ?></h2>
				<p><?php _e( 'Members we think you\'ll love', 'dating-site-builder' ); ?></p>
			</div>

			<div class="dsb-member-grid">
				<?php if ( ! empty( $matches ) ) : ?>
					<?php foreach ( $matches as $match_id => $score ) : ?>
						<?php echo $this->render_member_card( $match_id, $score ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="dsb-no-results">
						<?php _e( 'No matches found yet. Complete your profile to get better matches!', 'dating-site-builder' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Messages shortcode.
	 */
	public function shortcode_messages( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to view messages.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		$user_id = get_current_user_id();
		$inbox = DSB_Messaging::get_inbox( $user_id );
		$active_conversation = isset( $_GET['conversation'] ) ? intval( $_GET['conversation'] ) : 0;

		ob_start();
		echo $this->render_app_header( 'messages' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-messages-wrapper">
			<div class="dsb-messages-sidebar">
				<h3><?php _e( 'Conversations', 'dating-site-builder' ); ?></h3>
				<div class="dsb-conversation-list">
					<?php if ( $inbox ) : ?>
						<?php foreach ( $inbox as $conversation ) : ?>
							<?php
							$other_user_id = ( $conversation['sender_id'] == $user_id ) ? $conversation['receiver_id'] : $conversation['sender_id'];
							$other_user = get_userdata( $other_user_id );
							$unread_class = $conversation['unread_count'] > 0 ? 'unread' : '';
							?>
							<a href="?conversation=<?php echo esc_attr( $other_user_id ); ?>" class="dsb-conversation-item <?php echo $unread_class; ?>" data-user-id="<?php echo esc_attr( $other_user_id ); ?>">
								<div class="dsb-conversation-avatar">
									<?php echo get_avatar( $other_user_id, 48 ); ?>
								</div>
								<div class="dsb-conversation-info">
									<strong><?php echo esc_html( $other_user->display_name ); ?></strong>
									<p><?php echo esc_html( wp_trim_words( $conversation['last_message'], 8 ) ); ?></p>
								</div>
								<?php if ( $conversation['unread_count'] > 0 ) : ?>
									<span class="dsb-unread-badge"><?php echo esc_html( $conversation['unread_count'] ); ?></span>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="dsb-no-conversations"><?php _e( 'No conversations yet', 'dating-site-builder' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="dsb-messages-content">
				<?php if ( $active_conversation ) : ?>
					<?php echo $this->render_conversation( $user_id, $active_conversation ); ?>
				<?php else : ?>
					<div class="dsb-no-conversation-selected">
						<p><?php _e( 'Select a conversation to start messaging', 'dating-site-builder' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Likes shortcode.
	 */
	public function shortcode_likes( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to see likes.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		$user_id = get_current_user_id();
		$liked_users = DSB_Likes::get_liked_users( $user_id );
		$mutual_matches = DSB_Likes::get_mutual_matches( $user_id );

		ob_start();
		echo $this->render_app_header( 'likes' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-likes-wrapper">
			<?php if ( ! empty( $mutual_matches ) ) : ?>
			<div class="dsb-likes-section">
				<h3><?php _e( 'Mutual Matches', 'dating-site-builder' ); ?></h3>
				<p><?php _e( 'You both liked each other!', 'dating-site-builder' ); ?></p>
				<div class="dsb-member-grid">
					<?php foreach ( $mutual_matches as $match_id ) : ?>
						<?php echo $this->render_member_card( $match_id ); ?>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="dsb-likes-section">
				<h3><?php _e( 'People You Liked', 'dating-site-builder' ); ?></h3>
				<div class="dsb-member-grid">
					<?php if ( ! empty( $liked_users ) ) : ?>
						<?php foreach ( $liked_users as $liked_id ) : ?>
							<?php echo $this->render_member_card( $liked_id ); ?>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="dsb-no-results"><?php _e( 'You haven\'t liked anyone yet.', 'dating-site-builder' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Render member card.
	 */
	private function render_member_card( $user_id, $match_score = 0 ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return '';
		}

		$age = $this->calculate_age( get_user_meta( $user_id, 'dsb_date_of_birth', true ) );
		$city = get_user_meta( $user_id, 'dsb_city', true );
		$country = get_user_meta( $user_id, 'dsb_country', true );
		$headline = get_user_meta( $user_id, 'dsb_headline', true );
		$main_photo = $this->get_main_photo( $user_id );
		$is_liked = DSB_Likes::has_liked( get_current_user_id(), $user_id );

		ob_start();
		?>
		<div class="dsb-member-card">
			<?php if ( $match_score > 0 ) : ?>
				<div class="dsb-match-score"><?php echo round( $match_score ); ?>% <?php _e( 'Match', 'dating-site-builder' ); ?></div>
			<?php endif; ?>
			
			<div class="dsb-member-photo" style="background-image: url('<?php echo esc_url( $main_photo ); ?>');">
				<a href="<?php echo esc_url( add_query_arg( 'profile_user', $user_id, get_permalink( get_option( 'dsb_profile_view_page' ) ) ) ); ?>"></a>
			</div>
			
			<div class="dsb-member-info">
				<h3><?php echo esc_html( $user->display_name ); ?>, <?php echo esc_html( $age ); ?></h3>
				<?php if ( $city || $country ) : ?>
					<p class="dsb-member-location"><?php echo esc_html( $city ? $city . ', ' : '' ); ?><?php echo esc_html( $country ); ?></p>
				<?php endif; ?>
				<?php if ( $headline ) : ?>
					<p class="dsb-member-headline"><?php echo esc_html( $headline ); ?></p>
				<?php endif; ?>
			</div>
			
			<?php
			$profile_url = add_query_arg( 'profile_user', $user_id, get_permalink( get_option( 'dsb_profile_view_page' ) ) );
			$message_url = add_query_arg( 'conversation', $user_id, get_permalink( get_option( 'dsb_messages_page' ) ) );
			$like_hint = $is_liked
				? __( 'You\'ve liked this member. Click to remove your like.', 'dating-site-builder' )
				: __( 'Like this member to let them know you\'re interested. If they like you back it\'s a match!', 'dating-site-builder' );
			?>
			<div class="dsb-member-actions">
				<a href="<?php echo esc_url( $profile_url ); ?>" class="dsb-btn dsb-btn-primary dsb-view-profile-btn">
					<?php esc_html_e( 'View Profile', 'dating-site-builder' ); ?>
				</a>
				<div class="dsb-member-actions-row">
					<button type="button" class="dsb-btn dsb-btn-secondary dsb-like-btn <?php echo $is_liked ? 'liked' : ''; ?>" data-user-id="<?php echo esc_attr( $user_id ); ?>" title="<?php echo esc_attr( $like_hint ); ?>" aria-label="<?php echo esc_attr( $like_hint ); ?>">
						<span class="dsb-icon-heart" aria-hidden="true"></span>
						<span class="dsb-btn-label"><?php echo $is_liked ? esc_html__( 'Liked', 'dating-site-builder' ) : esc_html__( 'Like', 'dating-site-builder' ); ?></span>
					</button>
					<a href="<?php echo esc_url( $message_url ); ?>" class="dsb-btn dsb-btn-secondary dsb-message-btn" title="<?php esc_attr_e( 'Send this member a private message', 'dating-site-builder' ); ?>" aria-label="<?php esc_attr_e( 'Send this member a private message', 'dating-site-builder' ); ?>">
						<span class="dsb-icon-message" aria-hidden="true"></span>
						<span class="dsb-btn-label"><?php esc_html_e( 'Message', 'dating-site-builder' ); ?></span>
					</a>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render conversation.
	 */
	private function render_conversation( $user_id, $other_user_id ) {
		$other_user = get_userdata( $other_user_id );
		if ( ! $other_user ) {
			return '<p>' . __( 'User not found.', 'dating-site-builder' ) . '</p>';
		}

		$messages = DSB_Messaging::get_conversation( $user_id, $other_user_id );

		ob_start();
		?>
		<div class="dsb-conversation-wrapper" data-user-id="<?php echo esc_attr( $other_user_id ); ?>">
			<div class="dsb-conversation-header">
				<div class="dsb-conversation-user">
					<?php echo get_avatar( $other_user_id, 40 ); ?>
					<strong><?php echo esc_html( $other_user->display_name ); ?></strong>
				</div>
				<div class="dsb-conversation-actions">
					<button class="dsb-btn dsb-btn-text dsb-block-user-btn" data-user-id="<?php echo esc_attr( $other_user_id ); ?>">
						<?php _e( 'Block', 'dating-site-builder' ); ?>
					</button>
				</div>
			</div>

			<div class="dsb-conversation-messages" id="dsb-conversation-messages">
				<?php if ( $messages ) : ?>
					<?php foreach ( $messages as $message ) : ?>
						<?php
						$is_sender = ( $message->sender_id == $user_id );
						$message_class = $is_sender ? 'sent' : 'received';
						?>
						<div class="dsb-message <?php echo esc_attr( $message_class ); ?>" data-message-id="<?php echo esc_attr( $message->id ); ?>">
							<div class="dsb-message-content">
								<?php echo wp_kses_post( $message->message_text ); ?>
							</div>
							<div class="dsb-message-time">
								<?php echo esc_html( human_time_diff( strtotime( $message->created_at ), current_time( 'timestamp' ) ) . ' ago' ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="dsb-no-messages"><?php _e( 'No messages yet. Start the conversation!', 'dating-site-builder' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="dsb-conversation-input">
				<form id="dsb-send-message-form">
					<textarea id="dsb-message-input" name="message" placeholder="<?php _e( 'Type your message...', 'dating-site-builder' ); ?>" rows="2"></textarea>
					<button type="submit" class="dsb-btn dsb-btn-primary"><?php _e( 'Send', 'dating-site-builder' ); ?></button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render user photos.
	 */
	private function render_user_photos( $user_id, $editable = false ) {
		$photos = get_user_meta( $user_id, 'dsb_photos', true );
		if ( empty( $photos ) || ! is_array( $photos ) ) {
			$photos = array();
		}

		ob_start();
		foreach ( $photos as $index => $photo_url ) :
		?>
			<div class="dsb-photo-item" data-photo-index="<?php echo esc_attr( $index ); ?>">
				<img src="<?php echo esc_url( $photo_url ); ?>" alt="<?php _e( 'User photo', 'dating-site-builder' ); ?>" />
				<?php if ( $editable ) : ?>
					<div class="dsb-photo-actions">
						<?php if ( $index !== 0 ) : ?>
							<button class="dsb-set-main-photo" data-index="<?php echo esc_attr( $index ); ?>"><?php _e( 'Set as main', 'dating-site-builder' ); ?></button>
						<?php endif; ?>
						<button class="dsb-delete-photo" data-index="<?php echo esc_attr( $index ); ?>"><?php _e( 'Delete', 'dating-site-builder' ); ?></button>
					</div>
				<?php endif; ?>
			</div>
		<?php
		endforeach;
		return ob_get_clean();
	}

	/**
	 * Get main photo URL.
	 */
	private function get_main_photo( $user_id ) {
		$photos = get_user_meta( $user_id, 'dsb_photos', true );
		if ( ! empty( $photos ) && is_array( $photos ) ) {
			return $photos[0];
		}
		return get_avatar_url( $user_id, array( 'size' => 400 ) );
	}

	/**
	 * Calculate age from date of birth.
	 */
	private function calculate_age( $dob ) {
		if ( empty( $dob ) ) {
			return '';
		}
		$birthDate = new DateTime( $dob );
		$today = new DateTime( 'today' );
		return $birthDate->diff( $today )->y;
	}

	/**
	 * Track profile view.
	 */
	private function track_profile_view( $viewed_user_id, $viewer_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'dsb_profile_views';
		
		$wpdb->insert(
			$table,
			array(
				'viewer_id'      => $viewer_id,
				'viewed_user_id' => $viewed_user_id,
				'viewed_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s' )
		);
	}

	/**
	 * AJAX: Register user.
	 */
	public function ajax_register_user() {
		check_ajax_referer( 'dsb_register', 'nonce' );

		$username = sanitize_user( $_POST['username'] );
		$email = sanitize_email( $_POST['email'] );
		$password = $_POST['password'];
		$display_name = sanitize_text_field( $_POST['display_name'] );

		// Validate
		if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'dating-site-builder' ) ) );
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'Username already exists.', 'dating-site-builder' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email already exists.', 'dating-site-builder' ) ) );
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Update display name
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $display_name,
		) );

		// Set role
		$user = new WP_User( $user_id );
		$user->set_role( 'dating_member' );

		// Set profile as pending if approval is required
		if ( get_option( 'dsb_require_profile_approval', false ) ) {
			update_user_meta( $user_id, 'dsb_profile_approved', '0' );
		} else {
			update_user_meta( $user_id, 'dsb_profile_approved', '1' );
		}

		// Auto-login if email verification is not required
		if ( ! get_option( 'dsb_require_email_verification', false ) ) {
			wp_set_auth_cookie( $user_id );
			$redirect_url = $this->get_dsb_page_url( 'dsb_profile_edit_page', 'profile-edit' );
		} else {
			$redirect_url = $this->get_dsb_page_url( 'dsb_login_page', 'login' );
		}

		wp_send_json_success( array(
			'message'      => __( 'Registration successful!', 'dating-site-builder' ),
			'redirect_url' => $redirect_url,
		) );
	}

	/**
	 * AJAX: Login user.
	 */
	public function ajax_login_user() {
		check_ajax_referer( 'dsb_login', 'nonce' );

		$username = sanitize_text_field( $_POST['username'] );
		$password = $_POST['password'];
		$remember = ! empty( $_POST['remember'] );

		$creds = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => $remember,
		);

		$user = wp_signon( $creds, false );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid username or password.', 'dating-site-builder' ) ) );
		}

		$redirect_url = $this->get_dsb_page_url( 'dsb_member_directory_page', 'members' );

		wp_send_json_success( array(
			'message'      => __( 'Login successful!', 'dating-site-builder' ),
			'redirect_url' => $redirect_url,
		) );
	}

	/**
	 * AJAX: Forgot password - send reset link.
	 */
	public function ajax_forgot_password() {
		check_ajax_referer( 'dsb_forgot_password', 'nonce' );

		$email = sanitize_email( $_POST['email'] );

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'dating-site-builder' ) ) );
		}

		$user = get_user_by( 'email', $email );

		// Always show success message for security (don't reveal if email exists)
		if ( ! $user ) {
			// Fake success to prevent email enumeration
			wp_send_json_success( array( 
				'message' => __( 'If an account exists with that email, you will receive a password reset link shortly.', 'dating-site-builder' ) 
			) );
		}

		// Generate password reset key
		$reset_key = get_password_reset_key( $user );

		if ( is_wp_error( $reset_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to generate reset link. Please try again.', 'dating-site-builder' ) ) );
		}

		// Build reset URL (uses WordPress default reset page)
		$reset_url = network_site_url( "wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode( $user->user_login ), 'login' );

		// Email content
		$site_name = get_bloginfo( 'name' );
		$subject = sprintf( __( '[%s] Password Reset Request', 'dating-site-builder' ), $site_name );
		
		$message = sprintf( __( 'Hi %s,', 'dating-site-builder' ), $user->display_name ) . "\r\n\r\n";
		$message .= __( 'Someone requested a password reset for your account. If this was you, click the link below to set a new password:', 'dating-site-builder' ) . "\r\n\r\n";
		$message .= $reset_url . "\r\n\r\n";
		$message .= __( 'If you didn\'t request this, you can safely ignore this email.', 'dating-site-builder' ) . "\r\n\r\n";
		$message .= sprintf( __( 'This link will expire in 24 hours.', 'dating-site-builder' ) ) . "\r\n\r\n";
		$message .= sprintf( __( '- The %s Team', 'dating-site-builder' ), $site_name );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Unable to send email. Please try again later.', 'dating-site-builder' ) ) );
		}

		wp_send_json_success( array( 
			'message' => __( 'If an account exists with that email, you will receive a password reset link shortly.', 'dating-site-builder' ) 
		) );
	}

	/**
	 * AJAX: Update profile.
	 */
	public function ajax_update_profile() {
		// Check nonce - try both possible nonce names
		if ( ! wp_verify_nonce( $_POST['dsb_profile_nonce'] ?? '', 'dsb_update_profile' ) &&
		     ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dsb_update_profile' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'dating-site-builder' ) ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to update your profile.', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$fields = DSB_Profile_Fields::get_all_fields();
		$errors = array();

		// Validate required fields first
		foreach ( $fields as $field_key => $field ) {
			if ( ! empty( $field['required'] ) ) {
				$value = isset( $_POST[ $field_key ] ) ? $_POST[ $field_key ] : '';
				
				if ( empty( $value ) || ( is_array( $value ) && count( $value ) === 0 ) ) {
					$errors[] = sprintf( __( '%s is required.', 'dating-site-builder' ), $field['label'] );
				}
			}

			// Validate specific field types
			if ( isset( $_POST[ $field_key ] ) && ! empty( $_POST[ $field_key ] ) ) {
				$value = $_POST[ $field_key ];

				// Date validation
				if ( $field['type'] === 'date' && $field_key === 'date_of_birth' ) {
					$dob = strtotime( $value );
					if ( $dob === false ) {
						$errors[] = __( 'Please enter a valid date of birth.', 'dating-site-builder' );
					} else {
						$age = floor( ( time() - $dob ) / ( 365.25 * 24 * 60 * 60 ) );
						$min_age = get_option( 'dsb_minimum_age', 18 );
						if ( $age < $min_age ) {
							$errors[] = sprintf( __( 'You must be at least %d years old to use this site.', 'dating-site-builder' ), $min_age );
						}
						if ( $age > 120 ) {
							$errors[] = __( 'Please enter a valid date of birth.', 'dating-site-builder' );
						}
					}
				}

				// Maxlength validation
				if ( ! empty( $field['maxlength'] ) && ! is_array( $value ) ) {
					if ( strlen( $value ) > $field['maxlength'] ) {
						$errors[] = sprintf( __( '%s must be %d characters or less.', 'dating-site-builder' ), $field['label'], $field['maxlength'] );
					}
				}

				// Select field validation - must be a valid option
				if ( $field['type'] === 'select' && ! empty( $field['options'] ) ) {
					if ( ! array_key_exists( $value, $field['options'] ) && ! empty( $value ) ) {
						$errors[] = sprintf( __( 'Please select a valid option for %s.', 'dating-site-builder' ), $field['label'] );
					}
				}
			}
		}

		// Return errors if any
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
		}

		// Save all fields
		foreach ( $fields as $field_key => $field ) {
			if ( isset( $_POST[ $field_key ] ) ) {
				$value = $_POST[ $field_key ];

				// Sanitize based on field type
				if ( $field['type'] === 'textarea' ) {
					$value = sanitize_textarea_field( $value );
				} elseif ( is_array( $value ) ) {
					$value = array_map( 'sanitize_text_field', $value );
				} else {
					$value = sanitize_text_field( $value );
				}

				update_user_meta( $user_id, 'dsb_' . $field_key, $value );
			}
		}

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully!', 'dating-site-builder' ) ) );
	}

	/**
	 * AJAX: Upload photo.
	 */
	public function ajax_upload_photo() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'dating-site-builder' ) ) );
		}

		if ( empty( $_FILES['photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'dating-site-builder' ) ) );
		}

		$user_id = get_current_user_id();
		$photos = get_user_meta( $user_id, 'dsb_photos', true );
		if ( ! is_array( $photos ) ) {
			$photos = array();
		}

		if ( count( $photos ) >= 10 ) {
			wp_send_json_error( array( 'message' => __( 'Maximum 10 photos allowed.', 'dating-site-builder' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$photo_url = wp_get_attachment_url( $attachment_id );
		$photos[] = $photo_url;

		update_user_meta( $user_id, 'dsb_photos', $photos );

		wp_send_json_success( array(
			'message'   => __( 'Photo uploaded successfully!', 'dating-site-builder' ),
			'photo_url' => $photo_url,
		) );
	}

	/**
	 * AJAX: Delete photo.
	 */
	public function ajax_delete_photo() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		$index = intval( $_POST['index'] );

		$photos = get_user_meta( $user_id, 'dsb_photos', true );
		if ( is_array( $photos ) && isset( $photos[ $index ] ) ) {
			unset( $photos[ $index ] );
			$photos = array_values( $photos );
			update_user_meta( $user_id, 'dsb_photos', $photos );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Set main photo.
	 */
	public function ajax_set_main_photo() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		$index = intval( $_POST['index'] );

		$photos = get_user_meta( $user_id, 'dsb_photos', true );
		if ( is_array( $photos ) && isset( $photos[ $index ] ) ) {
			$main_photo = $photos[ $index ];
			unset( $photos[ $index ] );
			array_unshift( $photos, $main_photo );
			$photos = array_values( $photos );
			update_user_meta( $user_id, 'dsb_photos', $photos );
		}

		wp_send_json_success();
	}

	/**
	 * Group chat shortcode.
	 */
	public function shortcode_group_chat( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to access the chat room.', 'dating-site-builder' ) . '</p>';
		}

		$this->add_member_page_class();

		// Update user's last activity for online status
		update_user_meta( get_current_user_id(), 'dsb_last_activity', current_time( 'mysql' ) );

		$user = wp_get_current_user();
		$photos = get_user_meta( get_current_user_id(), 'dsb_photos', true );
		$avatar = ! empty( $photos ) && is_array( $photos ) ? $photos[0] : get_avatar_url( get_current_user_id(), array( 'size' => 40 ) );

		ob_start();
		echo $this->render_app_header( 'chat' );
		?>
		<div class="dsb-app-content">
			<div class="dsb-group-chat-wrapper">
				<div class="dsb-chat-container">
					<div class="dsb-chat-header">
						<div class="dsb-chat-title">
							<h2>💬 <?php _e( 'Community Chat', 'dating-site-builder' ); ?></h2>
							<p><?php _e( 'Chat with all members in real-time', 'dating-site-builder' ); ?></p>
						</div>
						<div class="dsb-chat-online">
							<span class="dsb-online-indicator"></span>
							<span id="dsb-online-count">0</span> <?php _e( 'online', 'dating-site-builder' ); ?>
						</div>
					</div>

					<div class="dsb-chat-messages" id="dsb-chat-messages">
						<div class="dsb-chat-loading">
							<div class="dsb-spinner"></div>
							<?php _e( 'Loading messages...', 'dating-site-builder' ); ?>
						</div>
					</div>

					<div class="dsb-chat-input-wrapper">
						<form id="dsb-group-chat-form" class="dsb-chat-form">
							<div class="dsb-chat-user-avatar">
								<img src="<?php echo esc_url( $avatar ); ?>" alt="<?php echo esc_attr( $user->display_name ); ?>">
							</div>
							<div class="dsb-chat-input-container">
								<input type="text" id="dsb-chat-input" name="message" placeholder="<?php esc_attr_e( 'Type your message...', 'dating-site-builder' ); ?>" maxlength="1000" autocomplete="off" />
								<button type="submit" class="dsb-chat-send-btn" id="dsb-chat-send">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
										<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
									</svg>
								</button>
							</div>
						</form>
					</div>
				</div>

				<div class="dsb-chat-sidebar">
					<div class="dsb-chat-sidebar-header">
						<h3><?php _e( 'Online Members', 'dating-site-builder' ); ?></h3>
					</div>
					<div class="dsb-online-users" id="dsb-online-users">
						<!-- Online users will be populated via JS -->
					</div>
				</div>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}
}
