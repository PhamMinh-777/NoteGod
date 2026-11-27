-- =====================================================================
-- FinalNote Database Schema & Seed Data
-- Based on Materials/ERD.docx & APISpecifications.docx
-- Compatible with MySQL 8.4
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `finalnote` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `finalnote`;

-- ---------------------------------------------------------------------
-- Table 1: Users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `avatar_url` VARCHAR(255) DEFAULT NULL,
    `email_verified_at` DATETIME DEFAULT NULL,
    `verification_token` VARCHAR(255) DEFAULT NULL,
    `reset_otp_hash` VARCHAR(255) DEFAULT NULL,
    `reset_otp_expires_at` DATETIME DEFAULT NULL,
    `reset_otp_attempts` TINYINT DEFAULT 0,
    `theme` VARCHAR(20) DEFAULT 'system',
    `font_size` INT DEFAULT 16,
    `default_note_color` VARCHAR(20) DEFAULT 'default',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_users_email_lower` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 2: Notes
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `title` VARCHAR(255) DEFAULT '',
    `plain_text_content` LONGTEXT DEFAULT NULL,
    `content_rich` LONGTEXT DEFAULT NULL,
    `color` VARCHAR(20) DEFAULT 'default',
    `is_pinned` BOOLEAN DEFAULT FALSE,
    `pinned_at` DATETIME DEFAULT NULL,
    `is_locked` BOOLEAN DEFAULT FALSE,
    `ai_summary` TEXT DEFAULT NULL,
    `ai_summary_updated_at` DATETIME DEFAULT NULL,
    `version` INT DEFAULT 1,
    `deleted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_notes_owner_feed` (`owner_id`, `deleted_at`, `is_pinned`, `pinned_at`, `updated_at`),
    CONSTRAINT `fk_notes_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 3: Labels
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `labels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `color` VARCHAR(20) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_labels_user_name` (`user_id`, `name`),
    CONSTRAINT `fk_labels_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 4: Note_Labels (Junction Table)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_labels` (
    `note_id` INT NOT NULL,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`note_id`, `label_id`),
    KEY `idx_note_labels_label_id` (`label_id`, `note_id`),
    CONSTRAINT `fk_nl_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_nl_label` FOREIGN KEY (`label_id`) REFERENCES `labels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 5: Note_Shares
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_shares` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `recipient_email` VARCHAR(255) NOT NULL,
    `recipient_user_id` INT DEFAULT NULL,
    `permission` ENUM('READ_ONLY', 'EDIT') DEFAULT 'READ_ONLY',
    `revoked_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_note_shares_note_email` (`note_id`, `recipient_email`),
    KEY `idx_note_shares_recipient` (`recipient_user_id`, `revoked_at`, `created_at`),
    CONSTRAINT `fk_shares_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_shares_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 6: Note_Passwords
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_passwords` (
    `note_id` INT PRIMARY KEY,
    `password_hash` VARCHAR(255) NOT NULL,
    `failed_attempts` INT DEFAULT 0,
    `locked_until` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_np_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 7: Attachments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attachments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_size` INT NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `upload_status` VARCHAR(20) DEFAULT 'ready',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_attachments_note_ready` (`note_id`, `upload_status`, `created_at`),
    CONSTRAINT `fk_attachments_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA FOR DEMO & TESTING
-- Password for all seed users is 'password123'
-- bcrypt hash cost 12: $2y$12$pt6VnfxEDuDaRudpjHqOH.tXN7cE88JPE0hBKXrXaj/f7l8jhR93e
-- =====================================================================

-- 1. Demo Users
INSERT INTO `users` (`id`, `email`, `password_hash`, `display_name`, `email_verified_at`, `theme`, `font_size`)
VALUES
(1, 'student@tdtu.edu.vn', '$2y$12$pt6VnfxEDuDaRudpjHqOH.tXN7cE88JPE0hBKXrXaj/f7l8jhR93e', 'Nguyen Van A', NOW(), 'system', 16),
(2, 'newbie@tdtu.edu.vn', '$2y$12$pt6VnfxEDuDaRudpjHqOH.tXN7cE88JPE0hBKXrXaj/f7l8jhR93e', 'Tran Thi B (Unverified)', NULL, 'light', 16),
(3, 'collaborator@tdtu.edu.vn', '$2y$12$pt6VnfxEDuDaRudpjHqOH.tXN7cE88JPE0hBKXrXaj/f7l8jhR93e', 'Le Van C (Collaborator)', NOW(), 'dark', 18)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `display_name` = VALUES(`display_name`);

-- 2. Demo Labels for User 1
INSERT INTO `labels` (`id`, `user_id`, `name`, `color`)
VALUES
(1, 1, 'Web Programming', '#4f46e5'),
(2, 1, 'Exam Prep', '#ea580c'),
(3, 1, 'Personal', '#16a34a')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Demo Notes for User 1 (Diverse states: Normal, Pinned, Locked, Shared)
INSERT INTO `notes` (`id`, `owner_id`, `title`, `plain_text_content`, `content_rich`, `color`, `is_pinned`, `pinned_at`, `is_locked`, `ai_summary`)
VALUES
-- Pinned Note
(1, 1, 'Web Programming Final Guidelines', 
'Project requires Docker Compose, PHP 8.4 Apache, MySQL 8.4. Adhere strictly to 32 criteria in Rubric.',
'<div><b>Project Requirements:</b><ul><li>Docker Compose environment</li><li>PHP 8.4 + Apache</li><li>MySQL 8.4</li><li>Adhere strictly to 32 rubric criteria.</li></ul></div>',
'lavender', 1, NOW(), 0, 'Overview of TDTU final web project requirements and tech stack.'),

-- Normal Note
(2, 1, 'Docker & MySQL Useful Commands',
'docker compose up -d --build to rebuild. docker compose down to stop. docker compose ps to view status.',
'<div><p>Quick reference for Docker commands:</p><pre>docker compose up -d --build\ndocker compose ps\ndocker compose logs -f</pre></div>',
'mint', 0, NULL, 0, 'Helpful CLI commands for maintaining Docker containers.'),

-- Locked Note (Password is 'secret123')
(3, 1, 'Confidential Exam Tips & Topics',
'This content is protected by a note password.',
'<div><p>Top secret study notes for the final oral examination.</p></div>',
'rose', 0, NULL, 1, NULL),

-- Note to be shared with User 3
(4, 1, 'Group Project Division & Roadmap',
'Phase 1: DB & Auth. Phase 2: Simple Notes. Phase 3: Realtime & AI. Phase 4: Deploy.',
'<div><p><b>Team Roadmap:</b></p><ol><li>Phase 1: DB & Auth</li><li>Phase 2: Simple Notes</li><li>Phase 3: Realtime & AI</li><li>Phase 4: Deploy</li></ol></div>',
'butter', 0, NULL, 0, 'Roadmap and division of tasks for the final project.')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- 4. Note-Label Associations
INSERT IGNORE INTO `note_labels` (`note_id`, `label_id`)
VALUES
(1, 1),
(1, 2),
(2, 1),
(3, 2),
(4, 1);

-- 5. Note Password for Note 3 (Password: 'secret123' bcrypt hash)
INSERT INTO `note_passwords` (`note_id`, `password_hash`, `failed_attempts`, `locked_until`)
VALUES
(3, '$2y$12$pt6VnfxEDuDaRudpjHqOH.tXN7cE88JPE0hBKXrXaj/f7l8jhR93e', 0, NULL)
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`), `failed_attempts` = 0;

-- 6. Share Note 4 with collaborator (User 3)
INSERT INTO `note_shares` (`id`, `note_id`, `recipient_email`, `recipient_user_id`, `permission`)
VALUES
(1, 4, 'collaborator@tdtu.edu.vn', 3, 'EDIT')
ON DUPLICATE KEY UPDATE `permission` = VALUES(`permission`);
