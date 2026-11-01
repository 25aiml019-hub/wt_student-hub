-- =======================================================
-- StudentHub - WT19 integration (Daksh, 25AIML019)
-- Run AFTER schema.sql and extended_schema.sql.
-- Works on both MySQL 8 and MariaDB (re-runnable).
-- =======================================================
USE `studenthub`;

-- 1. students.student_id (enrollment number), added only when missing
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_id'
);
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE students ADD COLUMN student_id VARCHAR(20) NULL UNIQUE AFTER id',
  'SELECT 1');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Feedback table used by feedback.html
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `name` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_feedback_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Starter events (dates are in 2027 so they are upcoming; edit or add more in the admin panel)
INSERT INTO events (title, description, category, event_date, event_time, location, status)
SELECT 'Tech Workshop', 'Hands-on workshop on web development and AI tools for first and second year students.', 'Academic', '2027-08-10', '10:00 AM', 'Seminar Hall', 'upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Tech Workshop');

INSERT INTO events (title, description, category, event_date, event_time, location, status)
SELECT 'Coding Competition', 'Individual coding contest with problem solving rounds and prizes for the top three.', 'Technical', '2027-08-15', '10:00 AM', 'Computer Lab', 'upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Coding Competition');

INSERT INTO events (title, description, category, event_date, event_time, location, status)
SELECT 'Sports Day', 'Annual campus sports day with track, field and team events.', 'Sports', '2027-08-22', '09:00 AM', 'College Ground', 'upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Sports Day');

INSERT INTO events (title, description, category, event_date, event_time, location, status)
SELECT 'Cultural Night', 'An evening of music, dance and drama performed by students.', 'Cultural', '2027-09-05', '06:00 PM', 'Open Air Theatre', 'upcoming'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE title = 'Cultural Night');
