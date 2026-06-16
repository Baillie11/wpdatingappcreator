# Dating Site Builder — Changelog

All notable changes to the Dating Site Builder plugin are documented in this
file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/):

- **MAJOR** — incompatible API or data-model changes
- **MINOR** — new functionality in a backwards-compatible way
- **PATCH** — backwards-compatible bug fixes

The plugin version is defined in two places that must be kept in sync:

- The `Version:` header in `dating-site-builder.php`
- The `DSB_VERSION` constant in `dating-site-builder.php`

When making any user-visible change, add a new entry at the top of this file
under an `## [Unreleased]` heading and bump both version locations when the
change is shipped.

---

## [Unreleased]

### Added
- Safe deployment/database workflow documentation for VentraIP staging-to-live
  releases, including the rule that production data remains the source of
  truth and only code files should be promoted during normal deployments.
- `database-migrations/` with an additive initial baseline migration and
  migration rules for production-safe schema updates.
- Production-aware environment helper and uninstall guard that blocks
  destructive plugin cleanup on production/live-looking databases unless an
  explicit backup-era override constant is set.

---

## [1.7.0] — 2026-05-04

### Added
- **Public site stats** — a new `DSB_Stats` helper class
  (`includes/class-dsb-stats.php`) holds the single source of truth for
  every metric the plugin can surface, with a 60-second transient cache so
  high-traffic public pages don't hammer the database.
- **`[dsb_site_stats]` shortcode** (`includes/class-dsb-frontend.php`):
  drop it on any page or widget to render a responsive grid of the metrics
  the admin has enabled. Accepts a `show_sub` attribute (default `true`)
  to hide the descriptive sub-labels.
- **Site-pulse banner above the Browse Members directory**: the
  `[dsb_member_directory]` shortcode now renders the same enabled stats in
  a compact strip above the heading, so logged-in members see live
  activity at a glance.
- **Per-stat toggles in Settings** (`includes/class-dsb-admin.php`): a new
  *Public Stats Display* section exposes a checkbox for every public stat
  (Online Now, In Chat Room, Messages Today, Total Members, Premium
  Members, New This Week, Total Likes, Mutual Matches, Profile Views,
  Messages Sent). Defaults are conservative — Online Now, In Chat Room,
  Total Members, New This Week, and Mutual Matches are enabled out of the
  box. Saving the settings page busts the stats transient so changes are
  visible immediately on the front end.
- Public stats CSS in `public/css/dsb-public.css` with tone-coloured
  left-border accents matching the admin Dashboard cards.

### Notes
- Moderation counters (Pending Approvals, Pending Reports) are flagged
  `admin_only` in the helper and cannot be exposed publicly even if the
  option is hand-edited.

---

## [1.6.1] — 2026-05-04

### Fixed
- Logged-in members landing on the Login page (which doubles as the front
  page) saw a dead-end "You are already logged in. Continue or log out"
  message whose **Continue** link reloaded the same page, doing nothing
  visible. `shortcode_login()` in `includes/class-dsb-frontend.php` now
  resolves the destination via `get_dsb_page_url()` (so the URL falls back
  to a real `/members/` slug even if the option is missing or trashed),
  normalises both URLs before comparing, and — if the directory still
  resolves to the current page — sends the visitor to their profile editor
  instead. The bare "log out" prompt is kept only as a last-resort fallback.

---

## [1.6.0] — 2026-05-03

A focused release that streamlines the member profile UX, modernises matching,
adds a few admin / branding niceties, and protects member data from being
clobbered during plugin updates.

### Added
- **Filter cards on admin Members & Reports** pages
  (`includes/class-dsb-admin.php`, `admin/css/dsb-admin.css`): the inline
  subsubsub link strips were replaced with the same card grid used on the
  Dashboard, with tone-coloured accents and current-filter highlighting.
- **Click-to-spin animation for the header logo**
  (`public/css/dsb-public.css`, `public/js/dsb-public.js`). Clicking the logo
  plays a 360° spin and then follows the existing home/browse link.
  Modifier-clicks (Cmd/Ctrl/Shift/Alt or middle-click) bypass the animation
  so members can still open the home page in a new tab.
- **Streamlined profile structure** (`includes/class-dsb-profile-fields.php`):
  - New "How would you describe yourself?" multi-select **Vibe** field
    (Adventurous, Laid-back, Flirty, Discreet, Confident, Curious, Dominant,
    Submissive, Playful, Open-minded).
  - New "How do you like to connect?" multi-select **Interaction Style** field
    (Straight to the point, Chat first, Meet quickly, Take it slow, Love a bit
    of banter).
  - New "Tonight I'm…" single-select **Intent** field (Just browsing, Open to
    chat, Looking to meet, Ready for something spontaneous).
  - New checkbox-grid **Interests** field (Travel, Beach, Fitness, Nightlife,
    Food & drinks, Scavenger hunts, Music, Movies, Outdoors, Events).
- **Member-data self-heal migration** (`includes/class-dsb-activator.php`):
  - New `migrate_restore_member_approval()` rebuilds `dsb_profile_approved`
    for any dating member that has lost it, unless the user is banned or is a
    fresh signup on a site that requires admin approval.
  - Runs as part of the versioned migration ladder (db_version 1.4) and again
    as a throttled hourly self-heal on `plugins_loaded`, so future regressions
    auto-correct.
- **Form-present sentinels** in the WP user-edit save handlers
  (`includes/class-dsb-admin.php`):
  - `dsb_user_photos_form` and `dsb_user_fields_form` hidden inputs are now
    rendered alongside their respective sections.
  - `save_user_profile_photos()` / `save_user_profile_fields()` refuse to
    touch any meta unless the matching sentinel is present, so unrelated
    user-edit submissions can no longer wipe photos or checkbox fields.

### Changed
- **"Looking For" is now a checkbox of relationship types**, not a gender
  filter. Options: Casual fun, Ongoing connection, Friends first, Couples,
  Group experiences, Online chat only, Open to anything. Required on the
  edit form.
- **Mandatory basic identity** trimmed to `profile_kind`, `gender`,
  `date_of_birth`, and `city / postcode`. `country` is now optional.
- **Matching algorithm** (`includes/class-dsb-matching.php`):
  - Removed the legacy gender-based meta filter that excluded everyone once
    `looking_for` switched semantics.
  - `check_orientation_compatibility()` now matches on shared `looking_for`
    selections, with `open_to_anything` acting as a wildcard, and gives the
    benefit of the doubt to brand-new members who haven't picked anything yet.
  - `calculate_interests_score()` accepts both the new array-based
    `dsb_interests` data and legacy comma-separated strings.
- **Wizard / settings labels** updated:
  - "About Me" group renamed to **Vibe & Interests**.
  - "Lifestyle & Interests" group renamed to **Optional Details**.
  - Default `dsb_enabled_field_groups` now seeds with `basics`, `about`,
    `lifestyle` so new installs get the optional details out of the box.
- **Activator** no longer resets `dsb_db_version` to `1.0` on every
  reactivation; the version is only seeded on a fresh install via
  `add_option`, preserving applied migrations across updates.

### Removed
- Free-text profile fields **Profile Headline**, **About Me**, and **What I'm
  Looking For**. Their content is now derived from the structured Vibe,
  Interaction Style, Interests, and Intent selections.
- Display of the old `dsb_headline` quote and the "About Me" / "What I'm
  Looking For" sections from `shortcode_profile_view`.
- Headline rendering from member cards in `render_member_card()`.

### Fixed
- **Critical error on Messages page** (`includes/class-dsb-frontend.php`):
  inbox loop was treating `stdClass` rows from `DSB_Messaging::get_inbox()`
  as arrays, throwing `Cannot use object of type stdClass as array` whenever
  the user had any conversations. The loop now uses object property access
  and reads the correct `message_text` column for the snippet.
- **Members reverting to "Pending" after plugin updates**: see the new
  approval self-heal migration described under *Added*.
- **Member photos disappearing after admin user-edit submissions**: the
  photo save handler now requires the form sentinel and refuses to overwrite
  a populated `dsb_photos` array with an empty one.

---

## [1.5.1] — 2026-04-28

### Added
- "Married" and "Polyamorous" options to the Relationship Status field.

---

## [1.5.0] — 2026-04-28

### Added
- Card-based admin Dashboard grouped into Live Activity, Members, Engagement,
  and Moderation, with twelve metrics (Online Now, In Chat Room, Messages
  Today, Total Members, Premium Members, New This Week, Total Likes, Mutual
  Matches, Profile Views, Pending Approvals, Pending Reports, Messages Sent).

---

## [1.4.0] — 2026-04-28

### Added
- "I am / We are" (`profile_kind`) selector with single, couple (M/F, F/F,
  M/M), group, and gender-diverse options.
- Partner profile fields (Partner Name, DOB, Headline, About, What partner is
  looking for) that auto-show when a couple option is selected on both the
  front-end profile editor and the WP admin user-edit screen.

---

## [1.3.0] — 2026-04-28

### Added
- Admin moderation toolset: bulk approve / suspend / ban actions on the
  Members screen and resolve / dismiss actions on Reports.
- Dating profile photo manager and dating profile field editor on the
  standard WP user-edit / profile screens.
- Front-end profile view + member card UX refresh.

---

## [1.2.0] — 2026-04-28

### Added
- Group / community chat shortcode (`[dsb_group_chat]`) with online-members
  sidebar and live activity tracking.
- Admin and public CSS / JS asset reorganisation.

### Changed
- Documentation directory restructured (this file lives under `Documentation/`).

---

## [1.0.0] — 2026-04-25

Initial public release.

### Added
- Six color themes (Romantic Red, Ocean Blue, Forest Green, Royal Purple,
  Sunset Orange, Midnight Dark) and four template styles (Modern,
  Glassmorphism, Minimalist, Bold Dark).
- Member registration, login, forgot-password flows.
- Profile editor, profile viewer, member directory.
- Matching engine with Simple / Interests / Hybrid modes.
- Likes & mutual-match detection.
- Private 1-to-1 messaging with block/report.
- Eight-step Setup Wizard and a parallel Settings screen.

---

[Unreleased]: #unreleased
[1.7.0]: #170--2026-05-04
[1.6.1]: #161--2026-05-04
[1.6.0]: #160--2026-05-03
[1.5.1]: #151--2026-04-28
[1.5.0]: #150--2026-04-28
[1.4.0]: #140--2026-04-28
[1.3.0]: #130--2026-04-28
[1.2.0]: #120--2026-04-28
[1.0.0]: #100--2026-04-25
