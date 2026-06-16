-- Dating Site Builder initial safe migration baseline.
-- Run only after taking a database backup.
-- Replace __WP_PREFIX__ with the site's WordPress table prefix, for example wp_.
-- This migration is additive only and must not delete existing data.

CREATE TABLE IF NOT EXISTS `__WP_PREFIX__dsb_schema_migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `__WP_PREFIX__dsb_schema_migrations` (`migration`)
VALUES ('2026-06-14-initial-safe-baseline.sql');
