# Dating Site Builder Database Migrations

Production data is the source of truth. Never promote a staging database over
production.

Use this folder for schema-only changes that need to be applied after code is
deployed. Migration filenames should use:

```text
YYYY-MM-DD-description.sql
```

## Rules

- Take a full production database backup before running any production
  migration.
- Run migrations on staging first.
- Production migrations must preserve existing users, profiles, messages,
  payments, bookings, subscriptions, logs, and uploads.
- Do not use `DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, broad `DELETE`, or
  full database imports in production migrations.
- Prefer additive changes: `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ADD
  COLUMN`, and idempotent indexes.
- Replace `__WP_PREFIX__` with the real WordPress table prefix before running
  a SQL file.
- Record each applied migration in `__WP_PREFIX__dsb_schema_migrations`.

## Recommended Production Sequence

1. Back up the live database in VentraIP/cPanel or phpMyAdmin.
2. Deploy plugin code/files only.
3. Open the migration SQL and verify it contains no destructive statements.
4. Replace `__WP_PREFIX__` with the live site's WordPress table prefix.
5. Run the migration in phpMyAdmin against the live database only.
6. Insert the migration filename into `__WP_PREFIX__dsb_schema_migrations` if
   the migration did not already do it.
7. Verify the live site and member data.
