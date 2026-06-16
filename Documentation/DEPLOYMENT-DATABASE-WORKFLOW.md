# Safe Deployment and Database Workflow

This plugin must be deployed with production/live data treated as the source of
truth.

## Current Risk

If the VentraIP staging-to-live action copies the staging database to live, any
live users or signups created after staging was last refreshed will be lost. Do
not use any cPanel, WordPress staging, or phpMyAdmin action that says it will
push, clone, replace, restore, import, or sync the database from staging to
production.

## Correct Workflow

1. Refresh staging from live only when you intentionally want staging to mirror
   production.
2. Test code changes on staging.
3. Promote code/plugin files from staging to live.
4. Leave the live database untouched during normal deployment.
5. Apply schema changes to live with files from `database-migrations/` only.
6. Back up the live database before every production migration.
7. Verify live users, profiles, messages, uploads, payments, bookings,
   subscriptions, and logs after deployment.

## VentraIP/cPanel Manual Steps

- In the staging tool, choose file/code push only if that option exists.
- Do not select options such as "Push database", "Overwrite live database",
  "Sync database", or "Copy staging database to live".
- If VentraIP only offers a full staging push, do not use it for this site.
  Upload the plugin files manually through File Manager/FTP/Git deployment
  instead.
- Use phpMyAdmin to take a live database export before running migrations.
- Use phpMyAdmin to run only reviewed migration SQL against production.

## Environment Configuration

Staging and production must use separate WordPress databases and separate
`wp-config.php` settings.

Production `wp-config.php` should include:

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'DSB_ENVIRONMENT', 'production' );
define( 'DB_NAME', 'your_live_database_name' );
```

Staging `wp-config.php` should include:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'DSB_ENVIRONMENT', 'staging' );
define( 'DB_NAME', 'your_staging_database_name' );
```

Keep database users/passwords separate too. The staging database user should
not have access to the production database.

## Production Safety Guard

`uninstall.php` now blocks destructive cleanup on production/live-looking
databases unless this constant is explicitly set:

```php
define( 'DSB_ALLOW_PRODUCTION_DATA_DELETION', true );
```

Do not set this constant during normal deployment. It exists only for a planned
and backed-up production data removal.

## Migration Rules

- Use `database-migrations/YYYY-MM-DD-description.sql`.
- Prefer additive SQL: `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ADD COLUMN`,
  safe indexes, and idempotent inserts.
- Never put these in production migrations:
  - `DROP DATABASE`
  - `DROP TABLE`
  - `TRUNCATE`
  - broad `DELETE FROM`
  - imports from staging over production
  - seed/reset/sync commands that recreate tables

## Files and Uploads

User-uploaded content lives outside this plugin directory, usually under
`wp-content/uploads`. Do not replace production uploads with staging uploads
unless you are doing a carefully planned media restore.
