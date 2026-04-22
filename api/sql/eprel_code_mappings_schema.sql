CREATE DATABASE IF NOT EXISTS `nexled_eprel`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `nexled_eprel`;

CREATE TABLE IF NOT EXISTS `eprel_code_mappings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tecit_code` CHAR(17) NOT NULL,
  `eprel_registration_number` VARCHAR(32) NOT NULL,
  `source_type` VARCHAR(32) NULL,
  `source_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eprel_code_mappings_tecit_code` (`tecit_code`),
  KEY `idx_eprel_code_mappings_registration_number` (`eprel_registration_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
