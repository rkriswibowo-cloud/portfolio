-- ========================================================
-- UPDATE DATABASE PORTFOLIO: FITUR TUGAS PERTEMUAN & PENILAIAN
-- ========================================================

-- 1. Buat Tabel course_assignments (Tugas Pertemuan)
CREATE TABLE IF NOT EXISTS `course_assignments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `due_date` DATETIME NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `course_assignments_meeting_id_idx` (`meeting_id`),
  KEY `course_assignments_is_active_idx` (`is_active`),
  CONSTRAINT `course_assignments_meeting_fk` FOREIGN KEY (`meeting_id`) REFERENCES `course_meetings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Buat Tabel course_assignment_submissions (Pengumpulan & Penilaian Tugas Mahasiswa)
CREATE TABLE IF NOT EXISTS `course_assignment_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `submission_type` ENUM('drive_link', 'file_upload') NOT NULL DEFAULT 'drive_link',
  `drive_url` TEXT NULL DEFAULT NULL,
  `file_path` VARCHAR(255) NULL DEFAULT NULL,
  `file_name` VARCHAR(255) NULL DEFAULT NULL,
  `student_notes` TEXT NULL DEFAULT NULL,
  `status` ENUM('submitted', 'graded', 'revision') NOT NULL DEFAULT 'submitted',
  `score` DECIMAL(5,2) NULL DEFAULT NULL,
  `feedback` TEXT NULL DEFAULT NULL,
  `graded_by` INT UNSIGNED NULL DEFAULT NULL,
  `graded_at` DATETIME NULL DEFAULT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_user_unique` (`assignment_id`, `user_id`),
  KEY `course_assignment_sub_user_idx` (`user_id`),
  KEY `course_assignment_sub_score_idx` (`score`),
  CONSTRAINT `fk_sub_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `course_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
