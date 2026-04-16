-- NexLed DAM schema
-- Creates separate metadata database for DAM.
-- Does not alter tecit_referencias, tecit_lampadas, or info_nexled_2024.
-- Creates canonical folder and asset tables for DAM v2.

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `nexled_dam`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `nexled_dam`;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `dam_folders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folder_id` VARCHAR(255) NOT NULL,
    `parent_id` VARCHAR(255) NULL,
    `name` VARCHAR(80) NOT NULL,
    `path` VARCHAR(255) NOT NULL,
    `scope` ENUM('brand','products','support','store','website','eprel','configurator','archive') NOT NULL,
    `kind` ENUM('system','custom') NOT NULL DEFAULT 'custom',
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `can_upload` TINYINT(1) NOT NULL DEFAULT 1,
    `can_create_children` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_dam_folders_folder_id` (`folder_id`),
    UNIQUE KEY `uniq_dam_folders_path` (`path`),
    KEY `idx_dam_folders_parent_id` (`parent_id`),
    KEY `idx_dam_folders_scope` (`scope`),
    CONSTRAINT `fk_dam_folders_parent`
        FOREIGN KEY (`parent_id`) REFERENCES `dam_folders` (`folder_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dam_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `folder_id` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(255) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `public_id` VARCHAR(255) NOT NULL,
    `asset_folder` VARCHAR(255) NOT NULL,
    `resource_type` ENUM('image','raw','video') NOT NULL,
    `format` VARCHAR(32) NOT NULL,
    `mime_type` VARCHAR(128) NULL,
    `bytes` BIGINT UNSIGNED NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `duration_ms` INT UNSIGNED NULL,
    `secure_url` VARCHAR(1024) NOT NULL,
    `thumbnail_url` VARCHAR(1024) NULL,
    `kind` VARCHAR(64) NOT NULL,
    `scope` ENUM('brand','products','support','store','website','eprel','configurator','archive') NOT NULL,
    `family_code` VARCHAR(16) NULL,
    `product_code` VARCHAR(64) NULL,
    `product_slug` VARCHAR(128) NULL,
    `locale` VARCHAR(16) NULL,
    `version` VARCHAR(64) NULL,
    `tags` JSON NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_dam_assets_public_id` (`public_id`),
    UNIQUE KEY `uniq_dam_assets_folder_filename` (`folder_id`, `filename`),
    KEY `idx_dam_assets_scope_kind` (`scope`, `kind`),
    KEY `idx_dam_assets_family_code` (`family_code`),
    KEY `idx_dam_assets_product_code` (`product_code`),
    KEY `idx_dam_assets_product_slug` (`product_slug`),
    KEY `idx_dam_assets_locale` (`locale`),
    KEY `idx_dam_assets_version` (`version`),
    CONSTRAINT `fk_dam_assets_folder`
        FOREIGN KEY (`folder_id`) REFERENCES `dam_folders` (`folder_id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `dam_folders` (
    `folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`
) VALUES
    ('nexled', NULL, 'nexled', 'nexled', 'brand', 'system', 1, 0, 1, 0)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `scope` = VALUES(`scope`),
    `kind` = VALUES(`kind`),
    `is_system` = VALUES(`is_system`),
    `can_upload` = VALUES(`can_upload`),
    `can_create_children` = VALUES(`can_create_children`),
    `sort_order` = VALUES(`sort_order`);

INSERT INTO `dam_folders` (
    `folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`
) VALUES
    ('nexled/00_brand', 'nexled', '00_brand', 'nexled/00_brand', 'brand', 'system', 1, 0, 1, 10),
    ('nexled/10_products', 'nexled', '10_products', 'nexled/10_products', 'products', 'system', 1, 0, 1, 20),
    ('nexled/60_configurator', 'nexled', '60_configurator', 'nexled/60_configurator', 'configurator', 'system', 1, 0, 1, 70)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `scope` = VALUES(`scope`),
    `kind` = VALUES(`kind`),
    `is_system` = VALUES(`is_system`),
    `can_upload` = VALUES(`can_upload`),
    `can_create_children` = VALUES(`can_create_children`),
    `sort_order` = VALUES(`sort_order`);

INSERT INTO `dam_folders` (
    `folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`
) VALUES
    ('nexled/00_brand/logos', 'nexled/00_brand', 'logos', 'nexled/00_brand/logos', 'brand', 'system', 1, 1, 0, 10),
    ('nexled/10_products/shared', 'nexled/10_products', 'shared', 'nexled/10_products/shared', 'products', 'system', 1, 0, 1, 10),
    ('nexled/10_products/families', 'nexled/10_products', 'families', 'nexled/10_products/families', 'products', 'system', 1, 0, 1, 20),
    ('nexled/60_configurator/ui-assets', 'nexled/60_configurator', 'ui-assets', 'nexled/60_configurator/ui-assets', 'configurator', 'system', 1, 1, 0, 10),
    ('nexled/60_configurator/placeholders', 'nexled/60_configurator', 'placeholders', 'nexled/60_configurator/placeholders', 'configurator', 'system', 1, 1, 0, 20),
    ('nexled/60_configurator/imports', 'nexled/60_configurator', 'imports', 'nexled/60_configurator/imports', 'configurator', 'system', 1, 1, 0, 30)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `scope` = VALUES(`scope`),
    `kind` = VALUES(`kind`),
    `is_system` = VALUES(`is_system`),
    `can_upload` = VALUES(`can_upload`),
    `can_create_children` = VALUES(`can_create_children`),
    `sort_order` = VALUES(`sort_order`);

INSERT INTO `dam_folders` (
    `folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`
) VALUES
    ('nexled/10_products/families/01_t8-ac', 'nexled/10_products/families', '01_t8-ac', 'nexled/10_products/families/01_t8-ac', 'products', 'system', 1, 0, 1, 10),
    ('nexled/10_products/shared/temperatures', 'nexled/10_products/shared', 'temperatures', 'nexled/10_products/shared/temperatures', 'products', 'system', 1, 1, 0, 10),
    ('nexled/10_products/shared/icons', 'nexled/10_products/shared', 'icons', 'nexled/10_products/shared/icons', 'products', 'system', 1, 1, 0, 20),
    ('nexled/10_products/shared/power-supplies', 'nexled/10_products/shared', 'power-supplies', 'nexled/10_products/shared/power-supplies', 'products', 'system', 1, 1, 0, 30),
    ('nexled/10_products/shared/energy-labels', 'nexled/10_products/shared', 'energy-labels', 'nexled/10_products/shared/energy-labels', 'products', 'system', 1, 1, 0, 40),
    ('nexled/10_products/families/11_barra-t5', 'nexled/10_products/families', '11_barra-t5', 'nexled/10_products/families/11_barra-t5', 'products', 'system', 1, 0, 1, 110),
    ('nexled/10_products/families/29_downlight', 'nexled/10_products/families', '29_downlight', 'nexled/10_products/families/29_downlight', 'products', 'system', 1, 0, 1, 290),
    ('nexled/10_products/families/30_downlight', 'nexled/10_products/families', '30_downlight', 'nexled/10_products/families/30_downlight', 'products', 'system', 1, 0, 1, 300),
    ('nexled/10_products/families/32_barra-bt', 'nexled/10_products/families', '32_barra-bt', 'nexled/10_products/families/32_barra-bt', 'products', 'system', 1, 0, 1, 320),
    ('nexled/10_products/families/49_shelfled', 'nexled/10_products/families', '49_shelfled', 'nexled/10_products/families/49_shelfled', 'products', 'system', 1, 0, 1, 490),
    ('nexled/10_products/families/48_dynamic', 'nexled/10_products/families', '48_dynamic', 'nexled/10_products/families/48_dynamic', 'products', 'system', 1, 0, 1, 480),
    ('nexled/10_products/families/55_barra', 'nexled/10_products/families', '55_barra', 'nexled/10_products/families/55_barra', 'products', 'system', 1, 0, 1, 550),
    ('nexled/10_products/families/58_barra-hot', 'nexled/10_products/families', '58_barra-hot', 'nexled/10_products/families/58_barra-hot', 'products', 'system', 1, 0, 1, 580)
ON DUPLICATE KEY UPDATE
    `parent_id` = VALUES(`parent_id`),
    `name` = VALUES(`name`),
    `path` = VALUES(`path`),
    `scope` = VALUES(`scope`),
    `kind` = VALUES(`kind`),
    `is_system` = VALUES(`is_system`),
    `can_upload` = VALUES(`can_upload`),
    `can_create_children` = VALUES(`can_create_children`),
    `sort_order` = VALUES(`sort_order`);

COMMIT;
