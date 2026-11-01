-- =======================================================
-- StudentHub Extended Schema (Non-Destructive Extensions)
-- Course: AIUE203 - Web Development Fundamentals
-- Student: Daksh (25AIML019), CSPIT, CHARUSAT
-- =======================================================

USE `studenthub`;

-- 1. Counselors Table
CREATE TABLE IF NOT EXISTS `counselors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `department` VARCHAR(150) NOT NULL DEFAULT 'Department of Artificial Intelligence and Machine Learning',
    `room_no` VARCHAR(50) NULL DEFAULT 'Cabin 204',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Batches Table
CREATE TABLE IF NOT EXISTS `batches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_name` VARCHAR(50) NOT NULL UNIQUE,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026',
    `counselor_id` INT NULL,
    `start_id` VARCHAR(20) NOT NULL,
    `end_id` VARCHAR(20) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_batches_counselor` FOREIGN KEY (`counselor_id`) REFERENCES `counselors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Modify Students Table to Add Roster & Academic Columns
SET @dbname = DATABASE();
SET @tablename = "students";

-- Add student_id if missing
SET @columnname = "student_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN student_id VARCHAR(20) NULL UNIQUE AFTER id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add temp_student_id if missing
SET @columnname = "temp_student_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN temp_student_id VARCHAR(50) NULL AFTER student_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add batch_id if missing
SET @columnname = "batch_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN batch_id INT NULL AFTER course"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add counselor_id if missing
SET @columnname = "counselor_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN counselor_id INT NULL AFTER batch_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add cgpa if missing
SET @columnname = "cgpa";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE students ADD COLUMN cgpa DECIMAL(3,2) NULL DEFAULT 0.00 AFTER academic_year"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Safely allow nullable email, mobile, and gender for imported unverified student records
ALTER TABLE `students` MODIFY COLUMN `email` VARCHAR(100) NULL UNIQUE;
ALTER TABLE `students` MODIFY COLUMN `mobile` VARCHAR(20) NULL;
ALTER TABLE `students` MODIFY COLUMN `gender` ENUM('Male', 'Female', 'Other') NULL DEFAULT 'Other';

-- 4. Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(30) NOT NULL UNIQUE,
    `course_name` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Artificial Intelligence and Machine Learning',
    `degree` VARCHAR(50) NOT NULL DEFAULT 'B.Tech',
    `semester` INT NOT NULL DEFAULT 3,
    `credits` INT NOT NULL DEFAULT 4,
    `description` TEXT NULL,
    `syllabus_url` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_course_dept` (`department`),
    INDEX `idx_course_sem` (`semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Course Enrollments Table
CREATE TABLE IF NOT EXISTS `course_enrollments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `course_id` INT NOT NULL,
    `semester` INT NOT NULL DEFAULT 3,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026',
    `status` ENUM('enrolled', 'completed', 'dropped') NOT NULL DEFAULT 'enrolled',
    `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_enroll_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_enroll_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_course` (`student_id`, `course_id`, `academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Teachers Table
CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Artificial Intelligence and Machine Learning',
    `designation` VARCHAR(100) NOT NULL DEFAULT 'Assistant Professor',
    `cabin` VARCHAR(50) NULL DEFAULT 'Cabin 302',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Subjects Table
CREATE TABLE IF NOT EXISTS `subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_code` VARCHAR(30) NOT NULL UNIQUE,
    `subject_name` VARCHAR(150) NOT NULL,
    `credits` INT NOT NULL DEFAULT 4,
    `semester` INT NOT NULL DEFAULT 3,
    `department` VARCHAR(100) NOT NULL DEFAULT 'Artificial Intelligence and Machine Learning',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Teacher Assignments Table
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id` INT NOT NULL,
    `subject_id` INT NOT NULL,
    `batch_id` INT NULL,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_assign_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Timetable Table
CREATE TABLE IF NOT EXISTS `timetable` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `assignment_id` INT NOT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `room_no` VARCHAR(50) NOT NULL DEFAULT 'Classroom 301',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_time_assign` FOREIGN KEY (`assignment_id`) REFERENCES `teacher_assignments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Clubs Table
CREATE TABLE IF NOT EXISTS `clubs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `lead_name` VARCHAR(100) NULL,
    `banner_image` VARCHAR(255) NULL,
    `description` TEXT NOT NULL,
    `objectives` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Club Memberships Table
CREATE TABLE IF NOT EXISTS `club_memberships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `club_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'Member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_mem_club` FOREIGN KEY (`club_id`) REFERENCES `clubs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_mem_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_club_student` (`club_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Marksheets Table
CREATE TABLE IF NOT EXISTS `marksheets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `semester` INT NOT NULL DEFAULT 1,
    `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026',
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL UNIQUE,
    `file_size` INT NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `extracted_cgpa` DECIMAL(3,2) NULL,
    `confirmed_cgpa` DECIMAL(3,2) NULL,
    `raw_extracted_text` TEXT NULL,
    `status` ENUM('extracted', 'confirmed', 'verified', 'rejected') NOT NULL DEFAULT 'extracted',
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_marksheet_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL,
    `content` TEXT NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Academic',
    `target_audience` ENUM('all', 'students', 'faculty') NOT NULL DEFAULT 'all',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
