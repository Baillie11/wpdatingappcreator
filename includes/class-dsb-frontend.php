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

		.dsb-member-card-info {
			color: #0f172a !important;
		}

		.dsb-member-card h3 {
			color: #0f172a !important;
		}

		.dsb-member-card-meta,
		.dsb-member-location,
		.dsb-member-headline {
			color: #475569 !important;
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

		.dsb-member-card-info {
			color: #111827 !important;
		}

		.dsb-member-card h3 {
			color: #111827 !important;
		}

		.dsb-member-card-meta,
		.dsb-member-location,
		.dsb-member-headline {
			color: #6b7280 !important;
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
		add_shortcode( 'dsb_site_stats', array( $this, 'shortcode_site_stats' ) );
	}

	/**
	 * Public site stats shortcode.
	 *
	 * Renders only the metrics the admin has enabled under
	 * Settings > Public Stats Display. Safe to drop on any page,
	 * widget, or block.
	 *
	 * Attributes:
	 *   show_sub  "true" (default) | "false" - whether each card
	 *             includes its descriptive sub-label.
	 */
	public function shortcode_site_stats( $atts ) {
		$atts = shortcode_atts( array(
			'show_sub' => 'true',
		), $atts, 'dsb_site_stats' );

		return DSB_Stats::render_public_grid( array(
			'wrapper_class' => 'dsb-public-stats dsb-public-stats-shortcode',
			'show_sub'      => filter_var( $atts['show_sub'], FILTER_VALIDATE_BOOLEAN ),
		) );
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
		$account_notice = isset( $_GET['dsb_account_notice'] ) ? sanitize_key( wp_unslash( $_GET['dsb_account_notice'] ) ) : '';
		if ( is_user_logged_in() ) {
			// Resolve where the logged-in member should actually land.
			// Using get_dsb_page_url() guarantees a real URL even when
			// the option is empty / trashed (it falls back to the slug
			// and finally to home_url('/members/')) so the Continue
			// link can never collapse to the current page.
			$dashboard_url = $this->get_dsb_page_url( 'dsb_member_directory_page', 'members' );

			// Build the canonical "current URL" stripped of query string
			// and trailing-slash differences for a clean comparison.
			$normalize = static function ( $url ) {
				$parts = wp_parse_url( (string) $url );
				if ( ! $parts ) {
					return (string) $url;
				}
				$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
				$host   = isset( $parts['host'] )   ? strtolower( $parts['host'] ) : '';
				$path   = isset( $parts['path'] )   ? $parts['path'] : '/';
				return trailingslashit( $scheme . $host . $path );
			};

			$current_url = ( is_ssl() ? 'https://' : 'http://' )
				. ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' )
				. ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );

			$is_same_page = empty( $dashboard_url )
				|| $normalize( $dashboard_url ) === $normalize( $current_url );

			if ( ! $is_same_page ) {
				if ( ! headers_sent() ) {
					wp_safe_redirect( $dashboard_url );
					exit;
				}
				return '<script>window.location.href = "' . esc_url( $dashboard_url ) . '";</script>'
					. '<p>' . sprintf( __( 'Redirecting... <a href="%s">Click here</a> if not redirected.', 'dating-site-builder' ), esc_url( $dashboard_url ) ) . '</p>';
			}

			// Same-page fallback: the resolved directory URL is the
			// page we're already on (e.g. front page is the Login page
			// AND the directory option is missing, so the helper landed
			// on the same slug). Send the member to their profile
			// editor instead - that is always a different URL - and
			// fall back to a bare log-out prompt only if the profile
			// page is also misconfigured.
			$profile_url = $this->get_dsb_page_url( 'dsb_profile_edit_page', 'profile-edit' );
			if ( $profile_url && $normalize( $profile_url ) === $normalize( $current_url ) ) {
				$profile_url = '';
			}

			if ( $profile_url ) {
				return '<p>' . sprintf(
					__( 'You are already logged in. <a href="%s">Go to your profile</a> or <a href="%s">log out</a>.', 'dating-site-builder' ),
					esc_url( $profile_url ),
					esc_url( wp_logout_url( home_url() ) )
				) . '</p>';
			}

			return '<p>' . sprintf(
				__( 'You are already logged in. <a href="%s">Log out</a> to switch accounts.', 'dating-site-builder' ),
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
					<?php if ( 'suspended' === $account_notice ) : ?>
						<div class="dsb-form-message" style="display:block; margin-bottom: 1rem; color:#b45309; background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:0.75rem 1rem;">
							<?php esc_html_e( 'Your account is suspended. Please contact support if you need it reactivated.', 'dating-site-builder' ); ?>
						</div>
					<?php endif; ?>
					<div class="dsb-auth-branding dsb-auth-branding-logo-only">
						<?php echo $this->render_auth_logo( '💕' ); ?>
					</div>
					<form id="dsb-login-form" class="dsb-auth-form">
						<?php wp_nonce_field( 'dsb_login', 'dsb_login_nonce' ); ?>
						
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
		$fields = DSB_Profile_Fields::get_edit_fields();

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

				<?php
				// Photo access requests section (visible when private photos are enabled).
				$private_photos_on = (bool) get_option( 'dsb_enable_private_photos', false );
				if ( $private_photos_on ) :
					global $wpdb;
					$access_table   = $wpdb->prefix . 'dsb_photo_access';
					$pending_reqs   = $wpdb->get_results( $wpdb->prepare(
						"SELECT id, requester_id, created_at FROM $access_table WHERE owner_id = %d AND status = 'pending' ORDER BY created_at DESC",
						$user_id
					) );
					if ( $pending_reqs ) :
				?>
				<div class="dsb-photo-access-requests">
					<h3><?php _e( 'Private Photo Requests', 'dating-site-builder' ); ?></h3>
					<p class="dsb-access-requests-desc"><?php _e( 'These members want to see your private photos.', 'dating-site-builder' ); ?></p>
					<div class="dsb-access-request-list">
						<?php foreach ( $pending_reqs as $req ) :
							$req_user = get_userdata( $req->requester_id );
							if ( ! $req_user ) continue;
						?>
							<div class="dsb-access-request-item" data-request-id="<?php echo esc_attr( $req->id ); ?>">
								<div class="dsb-access-request-user">
									<?php echo get_avatar( $req->requester_id, 40 ); ?>
									<span><?php echo esc_html( $req_user->display_name ); ?></span>
								</div>
								<div class="dsb-access-request-actions">
									<button class="dsb-btn dsb-btn-small dsb-btn-primary dsb-approve-access" data-request-id="<?php echo esc_attr( $req->id ); ?>"><?php _e( 'Approve', 'dating-site-builder' ); ?></button>
									<button class="dsb-btn dsb-btn-small dsb-btn-secondary dsb-deny-access" data-request-id="<?php echo esc_attr( $req->id ); ?>"><?php _e( 'Deny', 'dating-site-builder' ); ?></button>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
					endif;
				endif;
				?>

				<!-- Profile Fields -->
				<div class="dsb-profile-fields">
					<?php $printed_partner_headings = array( '1' => false, '2' => false ); ?>
					<?php foreach ( $fields as $field_key => $field ) : 
						$value = get_user_meta( $user_id, 'dsb_' . $field_key, true );
						if ( ( '' === $value || null === $value ) && 0 === strpos( $field_key, 'partner_2_' ) ) {
							$legacy_field_key = 'partner_' . substr( $field_key, 10 );
							$legacy_value     = get_user_meta( $user_id, 'dsb_' . $legacy_field_key, true );
							if ( '' !== $legacy_value && null !== $legacy_value ) {
								$value = $legacy_value;
							}
						}

						$group_classes = array( 'dsb-form-group' );
						$group_attrs   = array();
						$group_attrs[] = 'data-field-key="' . esc_attr( $field_key ) . '"';
						if ( ! empty( $field['requires_couple'] ) ) {
							$group_classes[] = 'dsb-couple-only-field';
							$group_attrs[]   = 'data-requires-couple="1"';
						}
						if ( ! empty( $field['couple_column'] ) ) {
							$group_classes[] = 'dsb-partner-field';
							$group_attrs[]   = 'data-partner-column="' . esc_attr( (string) $field['couple_column'] ) . '"';
						}

						if ( ! empty( $field['requires_couple'] ) && ! empty( $field['couple_column'] ) ) {
							$column = (string) $field['couple_column'];
							if ( isset( $printed_partner_headings[ $column ] ) && ! $printed_partner_headings[ $column ] ) {
								$printed_partner_headings[ $column ] = true;
								?>
								<div class="dsb-partner-column-heading" data-requires-couple="1" data-partner-column="<?php echo esc_attr( $column ); ?>">
									<?php echo esc_html( '1' === $column ? __( 'Partner 1', 'dating-site-builder' ) : __( 'Partner 2', 'dating-site-builder' ) ); ?>
								</div>
								<?php
							}
						}
						?>
						<div class="<?php echo esc_attr( implode( ' ', $group_classes ) ); ?>" <?php echo implode( ' ', $group_attrs ); ?>>
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
				var $kind        = $('#field_profile_kind');
				var $editWrapper = $('.dsb-profile-edit-wrapper');
				var value        = $kind.length ? ( $kind.val() || '' ) : '';
				var couple       = value.indexOf('couple_') === 0;

				$editWrapper.toggleClass('dsb-couple-layout', couple);
				$('.dsb-profile-fields [data-requires-couple="1"]').toggle( couple );
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
		$account_notice  = isset( $_GET['dsb_account_notice'] ) ? sanitize_key( wp_unslash( $_GET['dsb_account_notice'] ) ) : '';
		$notice_message  = '';
		$notice_class    = 'dsb-notice-success';

		if ( 'updated' === $account_notice ) {
			$notice_message = __( 'Your account has been updated.', 'dating-site-builder' );
		} elseif ( 'error' === $account_notice ) {
			$notice_message = __( 'Unable to update your account right now. Please try again.', 'dating-site-builder' );
			$notice_class   = 'dsb-notice-error';
		}

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

		// Get member number and total count
		global $wpdb;
		$total_members = count_users();
		$total_dating_members = isset( $total_members['avail_roles']['dating_member'] ) ? $total_members['avail_roles']['dating_member'] : 0;
		$total_dating_members += isset( $total_members['avail_roles']['dating_premium'] ) ? $total_members['avail_roles']['dating_premium'] : 0;

		// Get member position (ordinal number based on user_id)
		$member_position = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->users u
			INNER JOIN $wpdb->usermeta um ON u.ID = um.user_id
			WHERE um.meta_key = 'dsb_profile_kind'
			AND u.ID <= %d",
			$user_id
		) );

		$fields            = DSB_Profile_Fields::get_all_fields();
		$age               = $this->calculate_age( get_user_meta( $user_id, 'dsb_date_of_birth', true ) );
		$city              = get_user_meta( $user_id, 'dsb_city', true );
		$state             = get_user_meta( $user_id, 'dsb_state', true );
		$country           = get_user_meta( $user_id, 'dsb_country', true );

		// I am / We are + partner / dual-profile data.
		$profile_kind        = get_user_meta( $user_id, 'dsb_profile_kind', true );
		$profile_kind_label  = '';
		if ( $profile_kind && isset( $fields['profile_kind']['options'][ $profile_kind ] ) ) {
			$profile_kind_label = $fields['profile_kind']['options'][ $profile_kind ];
		}
		$is_couple   = $profile_kind && strpos( $profile_kind, 'couple_' ) === 0;
		$kind_prefix = $is_couple ? __( 'We are', 'dating-site-builder' ) : __( 'I am', 'dating-site-builder' );

		$get_partner_meta = static function ( $meta_key, $legacy_meta_key = '' ) use ( $user_id ) {
			$value = get_user_meta( $user_id, 'dsb_' . $meta_key, true );
			if ( ( '' === $value || null === $value ) && $legacy_meta_key ) {
				$value = get_user_meta( $user_id, 'dsb_' . $legacy_meta_key, true );
			}
			return $value;
		};

		$partner_1 = array(
			'name'        => $get_partner_meta( 'partner_1_display_name' ),
			'dob'         => $get_partner_meta( 'partner_1_date_of_birth' ),
			'headline'    => $get_partner_meta( 'partner_1_headline' ),
			'about'       => $get_partner_meta( 'partner_1_about' ),
			'looking_for' => $get_partner_meta( 'partner_1_looking_for' ),
		);
		$partner_1['age'] = $partner_1['dob'] ? $this->calculate_age( $partner_1['dob'] ) : '';

		$partner_2 = array(
			'name'        => $get_partner_meta( 'partner_2_display_name', 'partner_display_name' ),
			'dob'         => $get_partner_meta( 'partner_2_date_of_birth', 'partner_date_of_birth' ),
			'headline'    => $get_partner_meta( 'partner_2_headline', 'partner_headline' ),
			'about'       => $get_partner_meta( 'partner_2_about', 'partner_about' ),
			'looking_for' => $get_partner_meta( 'partner_2_looking_for', 'partner_looking_for' ),
		);
		$partner_2['age'] = $partner_2['dob'] ? $this->calculate_age( $partner_2['dob'] ) : '';

		$has_partner_1   = ! empty( array_filter( array( $partner_1['name'], $partner_1['age'], $partner_1['headline'], $partner_1['about'], $partner_1['looking_for'] ), 'strlen' ) );
		$has_partner_2   = ! empty( array_filter( array( $partner_2['name'], $partner_2['age'], $partner_2['headline'], $partner_2['about'], $partner_2['looking_for'] ), 'strlen' ) );
		$has_partner_info = $is_couple && ( $has_partner_1 || $has_partner_2 );

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
			'city',
			'state',
			'country',
			// Surfaced explicitly above / in the partner section below.
			'profile_kind',
			'partner_1_display_name',
			'partner_1_date_of_birth',
			'partner_1_headline',
			'partner_1_about',
			'partner_1_looking_for',
			'partner_2_display_name',
			'partner_2_date_of_birth',
			'partner_2_headline',
			'partner_2_about',
			'partner_2_looking_for',
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

		$has_any_text = ( ! empty( $detail_rows ) || $location || $profile_kind_label || $has_partner_info );

		$this->add_member_page_class();

		ob_start();
		echo $this->render_app_header( '' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-profile-view-wrapper dsb-profile-view-narrow">
			<?php if ( $notice_message ) : ?>
				<div class="dsb-notice <?php echo esc_attr( $notice_class ); ?>">
					<?php echo esc_html( $notice_message ); ?>
				</div>
			<?php endif; ?>
			<div class="dsb-profile-card dsb-profile-card-stacked">
				<div class="dsb-profile-photos">
					<?php echo $this->render_user_photos( $user_id, false, $current_user_id ); ?>
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
						<?php if ( $member_position && $total_dating_members ) : ?>
							<p class="dsb-profile-member-number">
								<?php printf( esc_html__( 'Member %d of %d', 'dating-site-builder' ), esc_html( $member_position ), esc_html( $total_dating_members ) ); ?>
							</p>
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

					<?php if ( $has_partner_info ) : ?>
						<?php if ( $has_partner_1 ) : ?>
							<section class="dsb-profile-section dsb-profile-partner">
								<h3>
									<?php
									if ( $partner_1['name'] ) {
										printf(
											/* translators: %s: partner display name and (optional) age. */
											esc_html__( 'Partner 1: %s', 'dating-site-builder' ),
											esc_html( $partner_1['name'] . ( $partner_1['age'] ? ', ' . $partner_1['age'] : '' ) )
										);
									} else {
										esc_html_e( 'Partner 1', 'dating-site-builder' );
									}
									?>
								</h3>
								<?php if ( $partner_1['headline'] ) : ?>
									<p class="dsb-profile-headline">“<?php echo esc_html( $partner_1['headline'] ); ?>”</p>
								<?php endif; ?>
								<?php if ( $partner_1['about'] ) : ?>
									<p class="dsb-profile-bio"><?php echo nl2br( esc_html( $partner_1['about'] ) ); ?></p>
								<?php endif; ?>
								<?php if ( $partner_1['looking_for'] ) : ?>
									<p class="dsb-profile-bio">
										<strong><?php esc_html_e( 'What Partner 1 is looking for:', 'dating-site-builder' ); ?></strong><br>
										<?php echo nl2br( esc_html( $partner_1['looking_for'] ) ); ?>
									</p>
								<?php endif; ?>
							</section>
						<?php endif; ?>

						<?php if ( $has_partner_2 ) : ?>
							<section class="dsb-profile-section dsb-profile-partner">
								<h3>
									<?php
									if ( $partner_2['name'] ) {
										printf(
											/* translators: %s: partner display name and (optional) age. */
											esc_html__( 'Partner 2: %s', 'dating-site-builder' ),
											esc_html( $partner_2['name'] . ( $partner_2['age'] ? ', ' . $partner_2['age'] : '' ) )
										);
									} else {
										esc_html_e( 'Partner 2', 'dating-site-builder' );
									}
									?>
								</h3>
								<?php if ( $partner_2['headline'] ) : ?>
									<p class="dsb-profile-headline">“<?php echo esc_html( $partner_2['headline'] ); ?>”</p>
								<?php endif; ?>
								<?php if ( $partner_2['about'] ) : ?>
									<p class="dsb-profile-bio"><?php echo nl2br( esc_html( $partner_2['about'] ) ); ?></p>
								<?php endif; ?>
								<?php if ( $partner_2['looking_for'] ) : ?>
									<p class="dsb-profile-bio">
										<strong><?php esc_html_e( 'What Partner 2 is looking for:', 'dating-site-builder' ); ?></strong><br>
										<?php echo nl2br( esc_html( $partner_2['looking_for'] ) ); ?>
									</p>
								<?php endif; ?>
							</section>
						<?php endif; ?>
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

					<?php if ( $user_id === $current_user_id ) : ?>
						<section class="dsb-profile-section dsb-account-management">
							<h3><?php esc_html_e( 'Account Management', 'dating-site-builder' ); ?></h3>
							<p class="dsb-profile-bio"><?php esc_html_e( 'Suspend your account to pause it temporarily. Cancel account will permanently delete your account and profile data.', 'dating-site-builder' ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dsb-account-action-form dsb-inline-form">
								<input type="hidden" name="action" value="dsb_account_action">
								<?php wp_nonce_field( 'dsb_account_action_' . $current_user_id, 'dsb_account_nonce' ); ?>
								<label for="dsb-account-action-type" class="dsb-account-reason-label"><?php esc_html_e( 'What would you like to do?', 'dating-site-builder' ); ?></label>
								<select id="dsb-account-action-type" name="dsb_account_action" class="dsb-account-reason-field dsb-account-action-select">
									<option value="suspend"><?php esc_html_e( 'Suspend Account', 'dating-site-builder' ); ?></option>
									<option value="delete"><?php esc_html_e( 'Cancel Account (Delete)', 'dating-site-builder' ); ?></option>
								</select>
								<div class="dsb-account-reason-group dsb-account-reason-group-suspend">
									<label for="dsb-suspend-reason" class="dsb-account-reason-label"><?php esc_html_e( 'Reason for suspension', 'dating-site-builder' ); ?></label>
									<select id="dsb-suspend-reason" name="dsb_account_reason_suspend" class="dsb-account-reason-field">
										<?php foreach ( DSB_Profile_Fields::get_account_reason_groups( 'suspend' ) as $group_label => $reasons ) : ?>
											<optgroup label="<?php echo esc_attr( $group_label ); ?>">
												<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
													<option value="<?php echo esc_attr( $reason_key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="dsb-account-reason-group dsb-account-reason-group-delete" style="display:none;">
									<label for="dsb-cancel-reason" class="dsb-account-reason-label"><?php esc_html_e( 'Reason for cancellation', 'dating-site-builder' ); ?></label>
									<select id="dsb-cancel-reason" name="dsb_account_reason_cancel" class="dsb-account-reason-field">
										<?php foreach ( DSB_Profile_Fields::get_account_reason_groups( 'delete' ) as $group_label => $reasons ) : ?>
											<optgroup label="<?php echo esc_attr( $group_label ); ?>">
												<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
													<option value="<?php echo esc_attr( $reason_key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
								</div>
								<label for="dsb-account-reason-note" class="dsb-account-reason-label"><?php esc_html_e( 'Extra details (optional)', 'dating-site-builder' ); ?></label>
								<textarea id="dsb-account-reason-note" name="dsb_account_reason_note" rows="2" class="dsb-account-reason-field" placeholder="<?php echo esc_attr__( 'Add any extra context...', 'dating-site-builder' ); ?>"></textarea>
								<button type="submit" class="dsb-btn dsb-btn-secondary dsb-account-action-submit" onclick="return confirm('<?php echo esc_js( __( 'Continue with this account action?', 'dating-site-builder' ) ); ?>');">
									<?php esc_html_e( 'Submit', 'dating-site-builder' ); ?>
								</button>
							</form>
							<script>
							jQuery(function($){
								function dsbToggleAccountReasonGroups() {
									var action = $('#dsb-account-action-type').val();
									$('.dsb-account-reason-group-suspend').toggle(action === 'suspend');
									$('.dsb-account-reason-group-delete').toggle(action === 'delete');
								}
								dsbToggleAccountReasonGroups();
								$(document).on('change', '#dsb-account-action-type', dsbToggleAccountReasonGroups);
							});
							</script>
						</section>
					<?php endif; ?>
				</div>
			</div>
		</div>
		</div><!-- .dsb-app-content -->
		<?php
		return ob_get_clean();
	}

	/**
	 * Process suspend/delete account requests submitted by members.
	 */
	public function handle_account_action() {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->get_dsb_page_url( 'dsb_login_page', 'login' ) );
			exit;
		}

		$user_id = get_current_user_id();
		$nonce   = isset( $_POST['dsb_account_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dsb_account_nonce'] ) ) : '';
		$action  = isset( $_POST['dsb_account_action'] ) ? sanitize_key( wp_unslash( $_POST['dsb_account_action'] ) ) : '';
		$reason_note = isset( $_POST['dsb_account_reason_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dsb_account_reason_note'] ) ) : '';
		$reason_key  = '';
		$reason_label = '';

		$reason_groups = 'delete' === $action
			? DSB_Profile_Fields::get_account_reason_groups( 'delete' )
			: DSB_Profile_Fields::get_account_reason_groups( 'suspend' );
		$reason_field  = 'delete' === $action ? 'dsb_account_reason_cancel' : 'dsb_account_reason_suspend';
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

		if ( ! wp_verify_nonce( $nonce, 'dsb_account_action_' . $user_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'dating-site-builder' ) );
		}

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
			update_user_meta( $user_id, 'dsb_suspended_reason_note', $reason_note );
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'dsb_account_actions',
				array(
					'user_id'       => $user_id,
					'action_type'   => 'suspend',
					'reason_key'    => $reason_key,
					'reason_label'  => $reason_label,
					'reason_note'   => $reason_note,
					'source'        => 'member',
					'performed_by'  => $user_id,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			wp_logout();
			wp_safe_redirect( add_query_arg( 'dsb_account_notice', 'suspended', $this->get_dsb_page_url( 'dsb_login_page', 'login' ) ) );
			exit;
		}

		if ( 'delete' === $action ) {
			$current_user = wp_get_current_user();
			global $wpdb;
			$wpdb->insert(
				$wpdb->prefix . 'dsb_account_actions',
				array(
					'user_id'       => $user_id,
					'action_type'   => 'delete',
					'reason_key'    => $reason_key,
					'reason_label'  => $reason_label,
					'reason_note'   => $reason_note,
					'source'        => 'member',
					'performed_by'  => $user_id,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_logout();
			wp_delete_user( $user_id );
			wp_safe_redirect( $this->get_dsb_page_url( 'dsb_login_page', 'login' ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'dsb_account_notice', 'error', $this->get_dsb_page_url( 'dsb_profile_view_page', 'profile' ) ) );
		exit;
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

		// Directory filters (query-string driven).
		$profile_kind = isset( $_GET['profile_kind'] ) ? sanitize_key( wp_unslash( $_GET['profile_kind'] ) ) : '';
		if ( '' === $profile_kind && isset( $_GET['gender'] ) ) {
			// Backward compatibility for old links that used ?gender=.
			$profile_kind = sanitize_key( wp_unslash( $_GET['gender'] ) );
		}
		$age_min_raw = isset( $_GET['age_min'] ) ? intval( $_GET['age_min'] ) : 0;
		$age_max_raw = isset( $_GET['age_max'] ) ? intval( $_GET['age_max'] ) : 0;
		$location    = isset( $_GET['location'] ) ? sanitize_text_field( wp_unslash( $_GET['location'] ) ) : '';

		$age_min = $age_min_raw > 0 ? max( 18, min( 99, $age_min_raw ) ) : 0;
		$age_max = $age_max_raw > 0 ? max( 18, min( 99, $age_max_raw ) ) : 0;
		if ( $age_min && $age_max && $age_min > $age_max ) {
			$temp    = $age_min;
			$age_min = $age_max;
			$age_max = $temp;
		}

		$profile_kind_options = array(
			'male'           => __( 'Male', 'dating-site-builder' ),
			'female'         => __( 'Female', 'dating-site-builder' ),
			'couple_mf'      => __( 'Couples (Male & Female)', 'dating-site-builder' ),
			'couple_ff'      => __( 'Couple (Female & Female)', 'dating-site-builder' ),
			'couple_mm'      => __( 'Couple (Male & Male)', 'dating-site-builder' ),
			'group'          => __( 'Groups', 'dating-site-builder' ),
			'gender_diverse' => __( 'Gender Diverse', 'dating-site-builder' ),
		);

		if ( $profile_kind && ! isset( $profile_kind_options[ $profile_kind ] ) ) {
			$profile_kind = '';
		}

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

		if ( $profile_kind ) {
			$args['meta_query'][] = array(
				'key'     => 'dsb_profile_kind',
				'value'   => $profile_kind,
				'compare' => '=',
			);
		}

		if ( $age_min || $age_max ) {
			$today = gmdate( 'Y-m-d' );
			if ( $age_min && $age_max ) {
				$dob_latest   = gmdate( 'Y-m-d', strtotime( '-' . $age_min . ' years', strtotime( $today ) ) );
				$dob_earliest = gmdate( 'Y-m-d', strtotime( '-' . $age_max . ' years', strtotime( $today ) ) );
				$args['meta_query'][] = array(
					'key'     => 'dsb_date_of_birth',
					'value'   => array( $dob_earliest, $dob_latest ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				);
			} elseif ( $age_min ) {
				$dob_latest = gmdate( 'Y-m-d', strtotime( '-' . $age_min . ' years', strtotime( $today ) ) );
				$args['meta_query'][] = array(
					'key'     => 'dsb_date_of_birth',
					'value'   => $dob_latest,
					'compare' => '<=',
					'type'    => 'DATE',
				);
			} else {
				$dob_earliest = gmdate( 'Y-m-d', strtotime( '-' . $age_max . ' years', strtotime( $today ) ) );
				$args['meta_query'][] = array(
					'key'     => 'dsb_date_of_birth',
					'value'   => $dob_earliest,
					'compare' => '>=',
					'type'    => 'DATE',
				);
			}
		}

		if ( '' !== $location ) {
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'dsb_city',
					'value'   => $location,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'dsb_state',
					'value'   => $location,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'dsb_country',
					'value'   => $location,
					'compare' => 'LIKE',
				),
			);
		}

		$user_query = new WP_User_Query( $args );
		$users = $user_query->get_results();
		$total_users = $user_query->get_total();

		ob_start();
		echo $this->render_app_header( 'browse' );
		?>
		<div class="dsb-app-content">
		<div class="dsb-member-directory-wrapper">
			<?php
			// Slim site-pulse banner above the directory header. Only
			// renders if the admin has enabled at least one public stat
			// under Settings > Public Stats Display.
			echo DSB_Stats::render_public_grid( array(
				'wrapper_class' => 'dsb-public-stats dsb-directory-stats',
				'show_sub'      => false,
			) );
			?>
			<div class="dsb-directory-header">
				<h2><?php _e( 'Browse Members', 'dating-site-builder' ); ?></h2>
				<div class="dsb-directory-filters">
					<select id="dsb-filter-profile-kind" class="dsb-filter">
						<option value=""><?php _e( 'All Member Types', 'dating-site-builder' ); ?></option>
						<?php foreach ( $profile_kind_options as $opt_key => $opt_label ) : ?>
							<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $profile_kind, $opt_key ); ?>>
								<?php echo esc_html( $opt_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="number" id="dsb-filter-age-min" class="dsb-filter" placeholder="<?php _e( 'Min age', 'dating-site-builder' ); ?>" min="18" max="99" value="<?php echo $age_min ? esc_attr( $age_min ) : ''; ?>" />
					<input type="number" id="dsb-filter-age-max" class="dsb-filter" placeholder="<?php _e( 'Max age', 'dating-site-builder' ); ?>" min="18" max="99" value="<?php echo $age_max ? esc_attr( $age_max ) : ''; ?>" />
					<input type="text" id="dsb-filter-location" class="dsb-filter" placeholder="<?php _e( 'Location', 'dating-site-builder' ); ?>" value="<?php echo esc_attr( $location ); ?>" />
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
							// DSB_Messaging::get_inbox() returns stdClass rows from
							// $wpdb->get_results(); access as object properties.
							$other_user_id = isset( $conversation->other_user_id )
								? (int) $conversation->other_user_id
								: ( (int) $conversation->sender_id === (int) $user_id
									? (int) $conversation->receiver_id
									: (int) $conversation->sender_id );
							$other_user = get_userdata( $other_user_id );
							if ( ! $other_user ) {
								continue;
							}
							$unread_count = isset( $conversation->unread_count ) ? (int) $conversation->unread_count : 0;
							$unread_class = $unread_count > 0 ? 'unread' : '';
							$last_message = isset( $conversation->message_text ) ? (string) $conversation->message_text : '';
							?>
							<a href="?conversation=<?php echo esc_attr( $other_user_id ); ?>" class="dsb-conversation-item <?php echo esc_attr( $unread_class ); ?>" data-user-id="<?php echo esc_attr( $other_user_id ); ?>">
								<div class="dsb-conversation-avatar">
									<?php echo get_avatar( $other_user_id, 48 ); ?>
								</div>
								<div class="dsb-conversation-info">
									<strong><?php echo esc_html( $other_user->display_name ); ?></strong>
									<p><?php echo esc_html( wp_trim_words( $last_message, 8 ) ); ?></p>
								</div>
								<?php if ( $unread_count > 0 ) : ?>
									<span class="dsb-unread-badge"><?php echo esc_html( $unread_count ); ?></span>
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
	 * Normalize the dsb_photos user meta value.
	 *
	 * Supports the legacy flat-URL format (`['url1', 'url2']`) and
	 * the new object format (`[{url, privacy}, ...]`). Always returns
	 * an array of associative arrays with 'url' and 'privacy' keys.
	 *
	 * @param mixed $raw Raw value from get_user_meta().
	 * @return array Normalized photo list.
	 */
	public static function normalize_photos( $raw ) {
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return array();
		}
		$normalized = array();
		foreach ( $raw as $entry ) {
			if ( is_array( $entry ) && isset( $entry['url'] ) ) {
				$normalized[] = array(
					'url'     => $entry['url'],
					'privacy' => isset( $entry['privacy'] ) ? $entry['privacy'] : 'public',
				);
			} elseif ( is_string( $entry ) && '' !== $entry ) {
				$normalized[] = array(
					'url'     => $entry,
					'privacy' => 'public',
				);
			}
		}
		return $normalized;
	}

	/**
	 * Render user photos.
	 *
	 * @param int  $user_id    The user whose photos to render.
	 * @param bool $editable   Whether to show edit controls.
	 * @param int  $viewer_id  The user viewing the photos (0 = owner / edit mode).
	 */
	private function render_user_photos( $user_id, $editable = false, $viewer_id = 0 ) {
		$photos = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );
		$private_enabled = (bool) get_option( 'dsb_enable_private_photos', false );

		// Determine viewer's access status for private photos.
		$access_status = '';
		if ( $private_enabled && $viewer_id && $viewer_id !== $user_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'dsb_photo_access';
			$access_status = (string) $wpdb->get_var( $wpdb->prepare(
				"SELECT status FROM $table WHERE requester_id = %d AND owner_id = %d",
				$viewer_id,
				$user_id
			) );
		}

		ob_start();
		foreach ( $photos as $index => $photo ) :
			$is_private  = ( 'private' === $photo['privacy'] );
			$show_locked = ( $is_private && $private_enabled && $viewer_id && $viewer_id !== $user_id && 'approved' !== $access_status );
		?>
			<div class="dsb-photo-item<?php echo $show_locked ? ' dsb-photo-private-locked' : ''; ?>" data-photo-index="<?php echo esc_attr( $index ); ?>">
				<img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php _e( 'User photo', 'dating-site-builder' ); ?>"<?php echo $show_locked ? ' class="dsb-photo-blurred"' : ''; ?> />
				<?php if ( $show_locked ) : ?>
					<div class="dsb-photo-lock-overlay">
						<span class="dsb-lock-icon">&#128274;</span>
						<?php if ( 'pending' === $access_status ) : ?>
							<span class="dsb-photo-access-badge dsb-access-pending"><?php _e( 'Access Requested', 'dating-site-builder' ); ?></span>
						<?php else : ?>
							<button class="dsb-btn dsb-btn-small dsb-request-photo-access" data-owner-id="<?php echo esc_attr( $user_id ); ?>">
								<?php _e( 'Request Access', 'dating-site-builder' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $editable ) : ?>
					<div class="dsb-photo-actions">
						<?php if ( $private_enabled ) : ?>
							<button class="dsb-toggle-photo-privacy <?php echo $is_private ? 'is-private' : ''; ?>" data-index="<?php echo esc_attr( $index ); ?>" title="<?php echo $is_private ? esc_attr__( 'Private - click to make public', 'dating-site-builder' ) : esc_attr__( 'Public - click to make private', 'dating-site-builder' ); ?>">
								<?php echo $is_private ? '&#128274; ' . esc_html__( 'Private', 'dating-site-builder' ) : '&#127760; ' . esc_html__( 'Public', 'dating-site-builder' ); ?>
							</button>
						<?php endif; ?>
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
		$photos = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );
		if ( ! empty( $photos ) ) {
			return $photos[0]['url'];
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

		if ( get_user_meta( $user->ID, 'dsb_suspended', true ) ) {
			wp_logout();
			$suspended_reason = trim( (string) get_user_meta( $user->ID, 'dsb_suspended_reason', true ) );
			$message = __( 'Your account is suspended. Please contact support.', 'dating-site-builder' );
			if ( '' !== $suspended_reason ) {
				/* translators: %s: suspension reason entered by member/admin. */
				$message .= ' ' . sprintf( __( 'Reason: %s', 'dating-site-builder' ), $suspended_reason );
			}
			wp_send_json_error( array( 'message' => $message ) );
		}

		if ( get_user_meta( $user->ID, 'dsb_banned', true ) ) {
			wp_logout();
			wp_send_json_error( array( 'message' => __( 'Your account has been blocked. Please contact support.', 'dating-site-builder' ) ) );
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
		$photos  = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );
		$max     = (int) get_option( 'dsb_max_photos', 10 );

		if ( count( $photos ) >= $max ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Maximum %d photos allowed.', 'dating-site-builder' ), $max ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$photo_url = wp_get_attachment_url( $attachment_id );
		$photos[] = array( 'url' => $photo_url, 'privacy' => 'public' );

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
		$index   = intval( $_POST['index'] );
		$photos  = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );

		if ( isset( $photos[ $index ] ) ) {
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
		$index   = intval( $_POST['index'] );
		$photos  = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );

		if ( isset( $photos[ $index ] ) ) {
			$main = $photos[ $index ];
			unset( $photos[ $index ] );
			array_unshift( $photos, $main );
			$photos = array_values( $photos );
			update_user_meta( $user_id, 'dsb_photos', $photos );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Toggle a photo's privacy between public and private.
	 */
	public function ajax_toggle_photo_privacy() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$user_id = get_current_user_id();
		$index   = intval( $_POST['index'] );
		$photos  = self::normalize_photos( get_user_meta( $user_id, 'dsb_photos', true ) );

		if ( isset( $photos[ $index ] ) ) {
			$photos[ $index ]['privacy'] = ( 'private' === $photos[ $index ]['privacy'] ) ? 'public' : 'private';
			update_user_meta( $user_id, 'dsb_photos', $photos );
			wp_send_json_success( array( 'privacy' => $photos[ $index ]['privacy'] ) );
		}

		wp_send_json_error();
	}

	/**
	 * AJAX: Request access to another user's private photos.
	 */
	public function ajax_request_photo_access() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		global $wpdb;
		$table    = $wpdb->prefix . 'dsb_photo_access';
		$user_id  = get_current_user_id();
		$owner_id = intval( $_POST['owner_id'] );

		if ( $owner_id === $user_id || ! $owner_id ) {
			wp_send_json_error();
		}

		// Upsert: insert or update to pending if previously denied.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM $table WHERE requester_id = %d AND owner_id = %d",
			$user_id,
			$owner_id
		) );

		if ( $existing ) {
			if ( 'pending' === $existing || 'approved' === $existing ) {
				wp_send_json_success( array( 'status' => $existing ) );
			}
			// Re-request after denial.
			$wpdb->update(
				$table,
				array( 'status' => 'pending', 'updated_at' => current_time( 'mysql' ) ),
				array( 'requester_id' => $user_id, 'owner_id' => $owner_id ),
				array( '%s', '%s' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'requester_id' => $user_id,
					'owner_id'     => $owner_id,
					'status'       => 'pending',
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		wp_send_json_success( array( 'status' => 'pending' ) );
	}

	/**
	 * AJAX: Approve or deny a private-photo access request.
	 */
	public function ajax_respond_photo_access() {
		check_ajax_referer( 'dsb_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'dsb_photo_access';
		$owner_id     = get_current_user_id();
		$request_id   = intval( $_POST['request_id'] );
		$decision     = sanitize_text_field( $_POST['decision'] );

		if ( ! in_array( $decision, array( 'approved', 'denied' ), true ) ) {
			wp_send_json_error();
		}

		// Only let the owner respond to requests addressed to them.
		$wpdb->update(
			$table,
			array( 'status' => $decision, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $request_id, 'owner_id' => $owner_id, 'status' => 'pending' ),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		wp_send_json_success( array( 'status' => $decision ) );
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
		$norm_photos = self::normalize_photos( get_user_meta( get_current_user_id(), 'dsb_photos', true ) );
		$avatar = ! empty( $norm_photos ) ? $norm_photos[0]['url'] : get_avatar_url( get_current_user_id(), array( 'size' => 40 ) );

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
