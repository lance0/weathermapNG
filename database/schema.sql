-- WeathermapNG Database Schema
-- Version: 2.0.0
-- 
-- Run this manually if automatic setup fails:
-- mysql -u librenms -p librenms < database/schema.sql

-- Create maps table
CREATE TABLE IF NOT EXISTS `wmng_maps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `width` int NOT NULL DEFAULT 800,
  `height` int NOT NULL DEFAULT 600,
  `options` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wmng_maps_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create nodes table
CREATE TABLE IF NOT EXISTS `wmng_nodes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `map_id` bigint unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `x` float NOT NULL,
  `y` float NOT NULL,
  `device_id` bigint unsigned DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wmng_nodes_map_id_foreign` (`map_id`),
  KEY `wmng_nodes_device_id_index` (`device_id`),
  CONSTRAINT `wmng_nodes_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create links table
CREATE TABLE IF NOT EXISTS `wmng_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `map_id` bigint unsigned NOT NULL,
  `src_node_id` bigint unsigned NOT NULL,
  `dst_node_id` bigint unsigned NOT NULL,
  `port_id_a` bigint unsigned DEFAULT NULL,
  `port_id_b` bigint unsigned DEFAULT NULL,
  `bandwidth_bps` bigint unsigned DEFAULT NULL,
  `style` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `wmng_links_map_id_foreign` (`map_id`),
  KEY `wmng_links_src_node_id_foreign` (`src_node_id`),
  KEY `wmng_links_dst_node_id_foreign` (`dst_node_id`),
  KEY `wmng_links_port_id_a_index` (`port_id_a`),
  KEY `wmng_links_port_id_b_index` (`port_id_b`),
  CONSTRAINT `wmng_links_map_id_foreign` FOREIGN KEY (`map_id`) REFERENCES `wmng_maps` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wmng_links_src_node_id_foreign` FOREIGN KEY (`src_node_id`) REFERENCES `wmng_nodes` (`id`),
  CONSTRAINT `wmng_links_dst_node_id_foreign` FOREIGN KEY (`dst_node_id`) REFERENCES `wmng_nodes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional - uncomment if needed)
-- INSERT INTO `wmng_maps` (`name`, `description`, `width`, `height`, `created_at`, `updated_at`) 
-- VALUES ('Sample Network Map', 'Example topology map', 1200, 800, NOW(), NOW());