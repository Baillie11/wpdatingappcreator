# Dating Site Builder - Installation Guide

## Quick Installation

### Step 1: Upload Plugin
1. Copy the entire `datingplugin` folder to your WordPress plugins directory:
   `wp-content/plugins/dating-site-builder/`

### Step 2: Activate Plugin
1. Log in to your WordPress admin dashboard
2. Go to **Plugins > Installed Plugins**
3. Find "Dating Site Builder"
4. Click **Activate**

### Step 3: Complete Setup Wizard
1. After activation, go to **Dating Builder** in the WordPress admin menu
2. Complete the 8-step setup wizard:
   - **Step 1:** Choose your site type (Standard, Adult, Swingers, NDIS, or Custom)
   - **Step 2:** Configure basic settings (age limits, verification, approval)
   - **Step 3:** Select profile field groups
   - **Step 4:** Set photo privacy and age gate options
   - **Step 5:** Configure matching algorithm
   - **Step 6:** Set messaging and interaction rules
   - **Step 7:** Configure monetization (optional)
   - **Step 8:** Review and complete

The wizard will automatically create the necessary pages with shortcodes.

## Pages Created Automatically

The plugin creates these pages during setup:

- **Register** - User registration form `[dsb_register]`
- **Login** - User login form `[dsb_login]`
- **Edit Profile** - Profile editing interface `[dsb_profile_edit]`
- **View Profile** - Display user profiles `[dsb_profile_view]`
- **Browse Members** - Member directory `[dsb_member_directory]`
- **My Matches** - Recommended matches `[dsb_matches]`
- **Messages** - Messaging inbox `[dsb_messages]`
- **My Likes** - Liked profiles `[dsb_likes]`

## Manual Shortcode Usage

You can add these shortcodes to any page or post:

```
[dsb_register]
[dsb_login]
[dsb_profile_edit]
[dsb_profile_view]
[dsb_member_directory]
[dsb_matches]
[dsb_messages]
[dsb_likes]
```

## Customization

### Theme Colors
Edit `public/css/dsb-public.css` and modify the CSS variables at the top:

```css
:root {
    --dsb-primary: #ff4458;       /* Main brand color */
    --dsb-secondary: #7c3aed;     /* Secondary color */
    --dsb-accent: #ec4899;        /* Accent color */
}
```

### Profile Fields
Use the `dsb_profile_fields` filter to add custom fields:

```php
add_filter('dsb_profile_fields', function($fields) {
    $fields['custom_field'] = array(
        'label'    => 'Custom Field',
        'type'     => 'text',
        'required' => false,
    );
    return $fields;
});
```

### Matching Algorithm
Customize the matching algorithm:

```php
add_filter('dsb_match_score', function($score, $user_id, $match_id) {
    // Add your custom matching logic
    return $score;
}, 10, 3);
```

## Testing the Plugin

1. **Create test users:**
   - Go to the registration page
   - Create 2-3 test accounts
   - Fill out their profiles with different information

2. **Test matching:**
   - Log in as one user
   - Go to "My Matches" page
   - Verify matches appear based on preferences

3. **Test messaging:**
   - Send a message to another user
   - Log in as that user
   - Check that the message appears in their inbox
   - Reply to test two-way communication

4. **Test likes:**
   - Like another user's profile
   - Have that user like you back
   - Verify "mutual match" notification appears

## Troubleshooting

### Database Connection Error
If you see "Error establishing a database connection":
- Check your `wp-config.php` database credentials
- Verify MySQL/MariaDB is running
- Ensure the database user has proper permissions

### Plugin Won't Activate
- Check PHP version (requires 7.4+)
- Check WordPress version (requires 6.0+)
- Look for errors in `wp-content/debug.log` if debugging is enabled

### Shortcodes Not Working
- Make sure the plugin is activated
- Complete the setup wizard
- Check that you're using the correct shortcode syntax

### Styles Not Loading
- Clear your browser cache
- Clear WordPress cache if using a caching plugin
- Check that `/public/css/dsb-public.css` exists
- Verify file permissions

### AJAX Not Working
- Check browser console for JavaScript errors
- Verify jQuery is loaded
- Ensure `/public/js/dsb-public.js` exists
- Check that nonces are being generated correctly

## Security Best Practices

1. **Keep WordPress Updated**
   - Regularly update WordPress core, themes, and plugins

2. **Use Strong Passwords**
   - Enforce strong passwords for user accounts
   - Consider using a security plugin

3. **Regular Backups**
   - Backup your database regularly
   - Store backups off-site

4. **SSL Certificate**
   - Use HTTPS for all dating site functionality
   - Dating sites handle sensitive user data

5. **Moderate Content**
   - Review user profiles regularly
   - Respond to reports promptly
   - Ban abusive users

## Performance Optimization

1. **Use Caching**
   - Install a caching plugin (WP Rocket, W3 Total Cache)
   - Enable object caching for better database performance

2. **Optimize Images**
   - Limit photo upload sizes
   - Use image optimization plugins

3. **Database Maintenance**
   - Regularly optimize database tables
   - Archive old messages and data

4. **CDN**
   - Use a CDN for static assets
   - Improves load times globally

## Getting Help

- Check the README.md for feature documentation
- Review the code comments for implementation details
- Search WordPress.org forums for similar issues
- Contact the plugin author for support

## Uninstalling

To completely remove the plugin:

1. Deactivate the plugin from WordPress admin
2. Delete the plugin files
3. On non-production environments, the `uninstall.php` script will
   automatically:
   - Drop all custom database tables
   - Remove all plugin options
   - Clean up user metadata

**Production safety:** On production/live-looking environments, destructive
uninstall cleanup is blocked unless `DSB_ALLOW_PRODUCTION_DATA_DELETION` is
explicitly set to `true` in `wp-config.php`. Take a verified database backup
before enabling that constant.

**Note:** Uninstalling with destructive cleanup enabled will delete all dating
profiles, messages, and likes permanently!
