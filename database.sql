-- =============================================
-- Stripe Payment Gateway Integration
-- Database: stripe_integration
-- =============================================

CREATE DATABASE IF NOT EXISTS `stripe_integration`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `stripe_integration`;

-- ---------------------------------
-- Table: customers
-- ---------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stripe_customer_id` VARCHAR(100) DEFAULT NULL,
  `name`              VARCHAR(150) NOT NULL,
  `email`             VARCHAR(200) NOT NULL UNIQUE,
  `phone`             VARCHAR(30)  DEFAULT NULL,
  `address_line1`     VARCHAR(255) DEFAULT NULL,
  `address_city`      VARCHAR(100) DEFAULT NULL,
  `address_country`   VARCHAR(10)  DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_stripe_customer` (`stripe_customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- Table: payments
-- ---------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `customer_id`         INT UNSIGNED DEFAULT NULL,
  `stripe_payment_intent_id` VARCHAR(150) DEFAULT NULL,
  `stripe_charge_id`    VARCHAR(150) DEFAULT NULL,
  `amount`              DECIMAL(12,2) NOT NULL,
  `currency`            CHAR(3)       NOT NULL DEFAULT 'usd',
  `status`              ENUM('pending','succeeded','failed','refunded','canceled') NOT NULL DEFAULT 'pending',
  `description`         TEXT          DEFAULT NULL,
  `card_brand`          VARCHAR(30)   DEFAULT NULL,
  `card_last4`          CHAR(4)       DEFAULT NULL,
  `card_exp_month`      TINYINT       DEFAULT NULL,
  `card_exp_year`       SMALLINT      DEFAULT NULL,
  `receipt_url`         VARCHAR(500)  DEFAULT NULL,
  `failure_message`     TEXT          DEFAULT NULL,
  `metadata`            JSON          DEFAULT NULL,
  `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_stripe_pi` (`stripe_payment_intent_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- Table: refunds
-- ---------------------------------
CREATE TABLE IF NOT EXISTS `refunds` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_id`       INT UNSIGNED NOT NULL,
  `stripe_refund_id` VARCHAR(150) DEFAULT NULL,
  `amount`           DECIMAL(12,2) NOT NULL,
  `reason`           VARCHAR(255)  DEFAULT NULL,
  `status`           ENUM('pending','succeeded','failed','canceled') NOT NULL DEFAULT 'pending',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`payment_id`) REFERENCES `payments`(`id`) ON DELETE CASCADE,
  INDEX `idx_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- Table: webhook_logs
-- ---------------------------------
CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id`     VARCHAR(150) NOT NULL,
  `event_type`   VARCHAR(100) NOT NULL,
  `payload`      LONGTEXT     DEFAULT NULL,
  `status`       ENUM('received','processed','failed') NOT NULL DEFAULT 'received',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- Table: settings
-- ---------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name`    VARCHAR(100) NOT NULL UNIQUE,
  `value`       TEXT         DEFAULT NULL,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES
  ('stripe_publishable_key', ''),
  ('stripe_secret_key',      ''),
  ('stripe_webhook_secret',  ''),
  ('currency',               'usd'),
  ('business_name',          'My Business');
