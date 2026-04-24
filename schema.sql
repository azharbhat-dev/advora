-- ============================================================
-- Advora MySQL Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+ (Hostinger)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── Users ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`               VARCHAR(32)    NOT NULL,
  `username`         VARCHAR(64)    NOT NULL,
  `password`         VARCHAR(255)   NOT NULL,
  `email`            VARCHAR(191)   DEFAULT '',
  `full_name`        VARCHAR(191)   DEFAULT '',
  `phone`            VARCHAR(64)    DEFAULT '',
  `address`          TEXT           NULL,
  `telegram_id`      VARCHAR(64)    DEFAULT '',
  `business_name`    VARCHAR(191)   DEFAULT '',
  `business_address` VARCHAR(255)   DEFAULT '',
  `doc_verified`     TINYINT(1)     NOT NULL DEFAULT 0,
  `balance`          DECIMAL(14,4)  NOT NULL DEFAULT 0,
  `account_type`     VARCHAR(32)    NOT NULL DEFAULT 'rookie',
  `disabled`         TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`       INT UNSIGNED   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_disabled` (`disabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Campaigns ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `campaigns` (
  `campaign_id`  VARCHAR(32)    NOT NULL,
  `user_id`      VARCHAR(32)    NOT NULL,
  `name`         VARCHAR(191)   NOT NULL,
  `cpv`          DECIMAL(10,4)  NOT NULL DEFAULT 0,
  `cpc`          DECIMAL(10,4)  NOT NULL DEFAULT 0,
  `creative_id`  VARCHAR(32)    DEFAULT NULL,
  `countries`    TEXT           NULL,
  `states`       TEXT           NULL,
  `schedule`     TEXT           NULL,
  `ip_mode`      VARCHAR(16)    NOT NULL DEFAULT 'off',
  `domain_mode`  VARCHAR(16)    NOT NULL DEFAULT 'off',
  `ip_list`      TEXT           NULL,
  `domain_list`  TEXT           NULL,
  `daily_budget` DECIMAL(14,2)  NOT NULL DEFAULT 0,
  `budget`       DECIMAL(14,2)  NOT NULL DEFAULT 0,
  `delivery`     VARCHAR(16)    NOT NULL DEFAULT 'even',
  `sources`      TEXT           NULL,
  `spent`        DECIMAL(14,4)  NOT NULL DEFAULT 0,
  `impressions`  BIGINT         NOT NULL DEFAULT 0,
  `clicks`       BIGINT         NOT NULL DEFAULT 0,
  `good_hits`    BIGINT         NOT NULL DEFAULT 0,
  `views_count`  BIGINT         NOT NULL DEFAULT 0,
  `status`       VARCHAR(16)    NOT NULL DEFAULT 'review',
  `reject_reason` TEXT          NULL,
  `created_at`   INT UNSIGNED   NOT NULL DEFAULT 0,
  `updated_at`   INT UNSIGNED   NOT NULL DEFAULT 0,
  PRIMARY KEY (`campaign_id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Creatives ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `creatives` (
  `id`          VARCHAR(32)   NOT NULL,
  `user_id`     VARCHAR(32)   NOT NULL,
  `name`        VARCHAR(191)  NOT NULL,
  `filename`    VARCHAR(255)  NOT NULL,
  `stored_file` VARCHAR(255)  NOT NULL,
  `file_size`   INT UNSIGNED  NOT NULL DEFAULT 0,
  `track_url`   TINYINT(1)    NOT NULL DEFAULT 0,
  `status`      VARCHAR(16)   NOT NULL DEFAULT 'pending',
  `uploaded_at` INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Topups ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `topups` (
  `id`               VARCHAR(32)   NOT NULL,
  `user_id`          VARCHAR(32)   NOT NULL,
  `username`         VARCHAR(64)   NOT NULL,
  `network`          VARCHAR(32)   NOT NULL,
  `network_label`    VARCHAR(64)   DEFAULT '',
  `address`          VARCHAR(255)  DEFAULT '',
  `amount`           DECIMAL(14,2) NOT NULL DEFAULT 0,
  `fee`              DECIMAL(14,2) NOT NULL DEFAULT 0,
  `amount_after_fee` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `txid`             VARCHAR(255)  NOT NULL,
  `screenshot`       VARCHAR(255)  DEFAULT NULL,
  `status`           VARCHAR(16)   NOT NULL DEFAULT 'pending',
  `created_at`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `approved_at`      INT UNSIGNED  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user`   (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stats (daily aggregates) ────────────────────────────
CREATE TABLE IF NOT EXISTS `stats` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     VARCHAR(32)     NOT NULL,
  `campaign_id` VARCHAR(32)     NOT NULL,
  `date`        DATE            NOT NULL,
  `impressions` BIGINT          NOT NULL DEFAULT 0,
  `clicks`      BIGINT          NOT NULL DEFAULT 0,
  `good_hits`   BIGINT          NOT NULL DEFAULT 0,
  `spent`       DECIMAL(14,4)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_camp_date` (`campaign_id`,`date`),
  KEY `idx_user` (`user_id`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications (user-facing) ─────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         VARCHAR(32)   NOT NULL,
  `user_id`    VARCHAR(32)   NOT NULL,
  `type`       VARCHAR(48)   NOT NULL DEFAULT 'manual',
  `title`      VARCHAR(191)  NOT NULL,
  `message`    TEXT          NULL,
  `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Admin Notifications (user activity log) ─────────────
CREATE TABLE IF NOT EXISTS `admin_notifications` (
  `id`         VARCHAR(32)   NOT NULL,
  `user_id`    VARCHAR(32)   NOT NULL,
  `username`   VARCHAR(64)   NOT NULL,
  `type`       VARCHAR(48)   NOT NULL,
  `title`      VARCHAR(191)  NOT NULL,
  `message`    TEXT          NULL,
  `is_read`    TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user`    (`user_id`),
  KEY `idx_read`    (`is_read`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Insights (country traffic inventory) ────────────────
CREATE TABLE IF NOT EXISTS `insights` (
  `code`        VARCHAR(4)    NOT NULL,
  `name`        VARCHAR(128)  NOT NULL,
  `impressions` BIGINT        NOT NULL DEFAULT 0,
  `win_rate`    INT           NOT NULL DEFAULT 0,
  `updated_at`  INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Countries (targetable) ──────────────────────────────
CREATE TABLE IF NOT EXISTS `countries` (
  `code` VARCHAR(4)   NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Wallets (deposit addresses) ─────────────────────────
CREATE TABLE IF NOT EXISTS `wallets` (
  `code`    VARCHAR(16)  NOT NULL,
  `address` VARCHAR(255) NOT NULL DEFAULT '',
  `network` VARCHAR(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Subscriptions (plans) ───────────────────────────────
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id`         VARCHAR(64)   NOT NULL,
  `name`       VARCHAR(64)   NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL DEFAULT 0,
  `campaigns`  INT           NOT NULL DEFAULT 1,
  `tagline`    VARCHAR(255)  DEFAULT '',
  `features`   TEXT          NULL,
  `active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order` INT           NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Key / Value settings (network notice, etc.) ─────────
CREATE TABLE IF NOT EXISTS `kv_settings` (
  `k` VARCHAR(64)  NOT NULL,
  `v` LONGTEXT     NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Seed defaults ───────────────────────────────────────
INSERT IGNORE INTO `countries` (`code`,`name`) VALUES
  ('US','United States'),('UK','United Kingdom'),('CA','Canada'),
  ('AU','Australia'),('DE','Germany'),('FR','France'),
  ('NL','Netherlands'),('NZ','New Zealand'),('IE','Ireland'),('SE','Sweden');

INSERT IGNORE INTO `wallets` (`code`,`address`,`network`) VALUES
  ('BTC',  'bc1qxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'Bitcoin'),
  ('TRC20','TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',  'Tron TRC20 (USDT)'),
  ('ERC20','0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'Ethereum ERC20 (USDT)'),
  ('BEP20','0xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'BNB Smart Chain BEP20 (USDT)');

INSERT IGNORE INTO `kv_settings` (`k`,`v`) VALUES
  ('network_notice', '{"enabled":false,"text":""}');

SET FOREIGN_KEY_CHECKS = 1;
