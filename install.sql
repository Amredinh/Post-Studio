-- Post Studio - database schema
-- Run this in phpMyAdmin (or import) on your database.

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) NOT NULL PRIMARY KEY,
  `value` MEDIUMTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upgrade older installs where value was TEXT (analytics cache can exceed 64 KB).
ALTER TABLE settings MODIFY `value` MEDIUMTEXT;

CREATE TABLE IF NOT EXISTS posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  zernio_post_id VARCHAR(64) DEFAULT NULL,
  service VARCHAR(20) NOT NULL DEFAULT 'zernio',
  content TEXT,
  media_type VARCHAR(20) DEFAULT NULL,
  media_json TEXT,
  platforms_json TEXT,
  scheduled_for DATETIME DEFAULT NULL,
  timezone VARCHAR(50) DEFAULT 'UTC',
  status VARCHAR(20) DEFAULT 'scheduled',
  source VARCHAR(20) DEFAULT 'composer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_zernio_id (zernio_post_id),
  INDEX idx_service (service),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration for databases created with the older schema (adds the service column).
ALTER TABLE posts ADD COLUMN IF NOT EXISTS service VARCHAR(20) NOT NULL DEFAULT 'zernio' AFTER zernio_post_id;
ALTER TABLE posts ADD INDEX IF NOT EXISTS idx_service (service);

CREATE TABLE IF NOT EXISTS posts_engagement (
  id INT AUTO_INCREMENT PRIMARY KEY,
  service VARCHAR(20) NOT NULL DEFAULT 'zernio',
  post_id VARCHAR(64) NOT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_service_post (service, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration for older installs: de-duplicate engagement rows (keep the earliest),
-- then replace the plain index with a UNIQUE one so ON DUPLICATE KEY works.
DELETE e1 FROM posts_engagement e1
JOIN posts_engagement e2
  ON e1.service = e2.service AND e1.post_id = e2.post_id AND e1.id > e2.id;
ALTER TABLE posts_engagement DROP INDEX IF EXISTS idx_service_post;
ALTER TABLE posts_engagement ADD UNIQUE INDEX IF NOT EXISTS uk_service_post (service, post_id);