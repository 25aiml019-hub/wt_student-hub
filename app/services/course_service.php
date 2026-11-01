<?php
/**
 * StudentHub - Course & Enrollment Service
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../config/db.php';

class CourseService {

    /**
     * Get all active academic courses
     */
    public static function getAllCourses(): array {
        $mysqli = getMySQLi();
        $query = "SELECT id, course_code, course_name, department, degree, semester, credits, description, syllabus_url " .
                 "FROM courses ORDER BY course_code ASC";
        $res = $mysqli->query($query);
        if (!$res) {
            return [];
        }
        $courses = [];
        while ($row = $res->fetch_assoc()) {
            $courses[] = $row;
        }
        return $courses;
    }

    /**
     * Get courses enrolled by a specific student
     */
    public static function getStudentEnrollments(int $studentId): array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare(
            "SELECT ce.id AS enrollment_id, ce.enrolled_at, ce.semester AS enrollment_semester, " .
            "c.id AS course_id, c.course_code, c.course_name, c.department, c.credits, c.description " .
            "FROM course_enrollments ce " .
            "JOIN courses c ON ce.course_id = c.id " .
            "WHERE ce.student_id = ? " .
            "ORDER BY c.course_code ASC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $enrollments = [];
        while ($row = $res->fetch_assoc()) {
            $enrollments[] = $row;
        }
        $stmt->close();
        return $enrollments;
    }

    /**
     * Check if a student is enrolled in a course
     */
    public static function isEnrolled(int $studentId, int $courseId): bool {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("SELECT id FROM course_enrollments WHERE student_id = ? AND course_id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("ii", $studentId, $courseId);
        $stmt->execute();
        $stmt->store_result();
        $enrolled = ($stmt->num_rows > 0);
        $stmt->close();
        return $enrolled;
    }

    /**
     * Enroll a student in a course
     */
    public static function enrollStudent(int $studentId, int $courseId, int $semester = 3): array {
        if (self::isEnrolled($studentId, $courseId)) {
            return ['success' => false, 'message' => 'Student is already enrolled in this course.'];
        }

        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("INSERT INTO course_enrollments (student_id, course_id, semester, enrolled_at) VALUES (?, ?, ?, NOW())");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $mysqli->error];
        }
        $stmt->bind_param("iii", $studentId, $courseId, $semester);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            return ['success' => true, 'message' => 'Enrolled successfully!'];
        }
        return ['success' => false, 'message' => 'Enrollment failed: ' . $mysqli->error];
    }

    /**
     * Drop a course enrollment
     */
    public static function dropEnrollment(int $studentId, int $courseId): array {
        $mysqli = getMySQLi();
        $stmt = $mysqli->prepare("DELETE FROM course_enrollments WHERE student_id = ? AND course_id = ?");
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $mysqli->error];
        }
        $stmt->bind_param("ii", $studentId, $courseId);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($ok && $affected > 0) {
            return ['success' => true, 'message' => 'Course dropped successfully.'];
        }
        return ['success' => false, 'message' => 'No active enrollment found to drop.'];
    }
}
