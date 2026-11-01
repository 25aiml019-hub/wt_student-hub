-- =======================================================
-- StudentHub Database Migration: Practicals 11 & 12
-- Course: AIUE203 - Web Development Fundamentals
-- Author: Daksh (25AIML019), CSPIT, CHARUSAT
-- =======================================================

USE `studenthub`;

-- 1. Add academic_year column and index to students table if not exists
ALTER TABLE `students` 
    ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(20) NOT NULL DEFAULT '2025-2026' AFTER `department`,
    ADD INDEX IF NOT EXISTS `idx_students_academic_year` (`academic_year`);

-- 2. Add event_time column to events table if not exists
ALTER TABLE `events` 
    ADD COLUMN IF NOT EXISTS `event_time` VARCHAR(20) NULL DEFAULT '10:00 AM' AFTER `event_date`;

-- 3. Update existing sample students with varied academic years for realistic filtering
UPDATE `students` SET `academic_year` = '2023-2024' WHERE `id` IN (1, 6, 11, 16);
UPDATE `students` SET `academic_year` = '2024-2025' WHERE `id` IN (2, 7, 12, 17);
UPDATE `students` SET `academic_year` = '2025-2026' WHERE `id` IN (3, 4, 5, 8, 9, 10, 13, 14, 15, 18, 19, 20);

-- 4. Update existing sample events with realistic times
UPDATE `events` SET `event_time` = '09:00 AM' WHERE `id` = 1;
UPDATE `events` SET `event_time` = '08:30 AM' WHERE `id` = 2;
UPDATE `events` SET `event_time` = '10:00 AM' WHERE `id` = 3;
UPDATE `events` SET `event_time` = '02:00 PM' WHERE `id` = 4;
UPDATE `events` SET `event_time` = '06:00 PM' WHERE `id` = 5;
