<?php
/**
 * Semester 3 Timetable Service (Prepared Statements & Schedule Logic)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../config/db.php';

class TimetableService {
    /**
     * Retrieve full weekly timetable for a given student batch (e.g. 'A1', 'Batch A', or 'ALL')
     */
    public static function getTimetableForBatch(string $batch = 'A1'): array {
        $mysqli = getMySQLi();

        // Normalize batch representation (e.g. 'Batch A' -> 'A1', 'A' -> 'A1')
        $batchNormalized = self::normalizeBatch($batch);

        $stmt = $mysqli->prepare("
            SELECT id, academic_year, semester, term, division, effective_date,
                   day_of_week, slot_number, start_time, end_time, slot_type,
                   batch, subject_code, subject_name, subject_abbrev,
                   room_no, faculty_abbrev, faculty_name
            FROM timetable_slots
            WHERE batch = 'ALL' OR batch = ?
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                     start_time ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("s", $batchNormalized);
        $stmt->execute();
        $res = $stmt->get_result();
        $slots = [];
        while ($row = $res->fetch_assoc()) {
            $slots[] = $row;
        }
        $stmt->close();

        return $slots;
    }

    /**
     * Retrieve today's schedule for a batch based on current day of the week
     */
    public static function getTodayClasses(string $batch = 'A1', ?string $currentDay = null): array {
        $mysqli = getMySQLi();
        $batchNormalized = self::normalizeBatch($batch);
        $day = $currentDay ?: date('l'); // e.g. 'Monday'

        $stmt = $mysqli->prepare("
            SELECT id, slot_number, start_time, end_time, slot_type,
                   batch, subject_code, subject_name, subject_abbrev,
                   room_no, faculty_abbrev, faculty_name
            FROM timetable_slots
            WHERE day_of_week = ? AND (batch = 'ALL' OR batch = ?)
            ORDER BY start_time ASC
        ");

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("ss", $day, $batchNormalized);
        $stmt->execute();
        $res = $stmt->get_result();
        $slots = [];
        while ($row = $res->fetch_assoc()) {
            $slots[] = $row;
        }
        $stmt->close();

        return $slots;
    }

    /**
     * Identify the next upcoming class today based on current server time
     */
    public static function getNextUpcomingClass(string $batch = 'A1', ?string $currentTime = null): ?array {
        $currentTimeStr = $currentTime ?: date('H:i:s');
        $todayClasses = self::getTodayClasses($batch);

        foreach ($todayClasses as $slot) {
            if ($slot['end_time'] > $currentTimeStr) {
                return $slot;
            }
        }

        return null; // All classes for today have concluded
    }

    /**
     * Organize timetable into structured day-by-day map
     */
    public static function getWeeklySchedule(string $batch = 'A1'): array {
        $allSlots = self::getTimetableForBatch($batch);
        $days = ['Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 'Thursday' => [], 'Friday' => [], 'Saturday' => []];

        foreach ($allSlots as $slot) {
            $day = $slot['day_of_week'];
            if (isset($days[$day])) {
                $days[$day][] = $slot;
            }
        }

        return $days;
    }

    /**
     * Helper to normalize user batch to timetable batch (e.g. 'Batch A' -> 'A1')
     */
    private static function normalizeBatch(string $batch): string {
        $b = strtoupper(trim($batch));
        if ($b === 'A' || $b === 'BATCH A' || $b === 'A1') {
            return 'A1';
        } elseif ($b === 'B' || $b === 'BATCH B' || $b === 'B1') {
            return 'B1';
        } elseif ($b === 'C' || $b === 'BATCH C' || $b === 'C1') {
            return 'C1';
        } elseif ($b === 'D' || $b === 'BATCH D' || $b === 'D1') {
            return 'D1';
        }
        return $batch;
    }
}
