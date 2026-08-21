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
  `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  INDEX idx_service_post (service, post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;