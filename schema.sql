-- ============================================================
-- Interkonek — MySQL Schema
-- ============================================================

CREATE TABLE IF NOT EXISTS `routers` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `location`    VARCHAR(150) DEFAULT NULL,
    `public_key`  VARCHAR(255) NOT NULL,
    `private_key` VARCHAR(255) NOT NULL,
    `tunnel_ip`   VARCHAR(20)  NOT NULL,
    `notes`       TEXT         DEFAULT NULL,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_public_key` (`public_key`),
    UNIQUE KEY `uq_tunnel_ip`  (`tunnel_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event`       VARCHAR(100) NOT NULL,
    `router_id`   INT UNSIGNED DEFAULT NULL,
    `router_name` VARCHAR(100) DEFAULT NULL,
    `details`     TEXT         DEFAULT NULL,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
    `key`        VARCHAR(100) NOT NULL,
    `value`      TEXT         NOT NULL,
    `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `port_forwards` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `router_id`    INT UNSIGNED NOT NULL,
    `public_port`  INT UNSIGNED NOT NULL,
    `target_port`  INT UNSIGNED NOT NULL,
    `protocol`     VARCHAR(10)  NOT NULL DEFAULT 'tcp',
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_public_port_protocol` (`public_port`, `protocol`),
    CONSTRAINT `fk_router_id` FOREIGN KEY (`router_id`) REFERENCES `routers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
