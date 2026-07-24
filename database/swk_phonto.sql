CREATE DATABASE IF NOT EXISTS swk_phonto CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE swk_phonto;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
('site_title', 'SWK_Phonto'),
('welcome_text', 'ค้นหาและดาวน์โหลดภาพกิจกรรมของคุณ'),
('privacy_text', 'ระบบใช้รูปเซลฟีเพื่อค้นหาเท่านั้นและไม่เก็บรูปไว้ถาวร')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

CREATE TABLE IF NOT EXISTS drive_connections (
    id TINYINT UNSIGNED PRIMARY KEY,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    description TEXT NULL,
    event_date DATE NULL,
    drive_folder_id VARCHAR(255) NOT NULL,
    cover_photo_id INT UNSIGNED NULL,
    face_threshold DECIMAL(4,3) NOT NULL DEFAULT 0.720,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    allow_download TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_events_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    drive_file_id VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    local_path VARCHAR(500) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    drive_modified_at DATETIME NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    face_indexed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_drive_file (event_id, drive_file_id),
    INDEX idx_photos_event_visible (event_id, is_visible),
    CONSTRAINT fk_photos_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS face_search_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    threshold DECIMAL(4,3) NOT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_face_search_event (event_id, created_at),
    CONSTRAINT fk_face_logs_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS download_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photo_id INT UNSIGNED NOT NULL,
    ip_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_download_photo (photo_id, created_at),
    CONSTRAINT fk_download_photo FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
