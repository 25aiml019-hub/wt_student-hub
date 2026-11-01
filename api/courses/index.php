<?php
/**
 * StudentHub - Courses & Enrollment API
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/course_service.php';
require_once __DIR__ . '/../../app/services/student_service.php';

header('Content-Type: application/json; charset=utf-8');

// Strict Auth Gate
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Authentication required',
        'redirect' => '../pages/login.html?error=unauthorized&redirect=course-enrollment.php'
    ]);
    exit;
}

$currentUser = currentUser();
$userId = (int)($currentUser['id'] ?? 0);

// Resolve student profile
$student = StudentService::findByUserId($userId);
if (!$student && ($currentUser['role'] ?? '') === 'admin') {
    $student = StudentService::findById(1);
}

if (!$student) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No student profile linked to this account.']);
    exit;
}

$studentId = (int)$student['id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $courses = CourseService::getAllCourses();
    $enrollments = CourseService::getStudentEnrollments($studentId);
    $enrolledIds = array_map(function($e) { return (int)$e['course_id']; }, $enrollments);

    $totalCredits = 0;
    foreach ($enrollments as $e) {
        $totalCredits += (int)($e['credits'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'student' => [
            'id' => $student['id'],
            'name' => $student['name'],
            'student_id' => $student['student_id'] ?? '25AIML019',
            'course' => $student['course'] ?? 'B.Tech',
            'department' => $student['department'] ?? 'Information Technology'
        ],
        'courses' => $courses,
        'enrollments' => $enrollments,
        'enrolledCourseIds' => $enrolledIds,
        'totalCredits' => $totalCredits
    ]);
    exit;
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? 'enroll';
    $courseId = (int)($data['course_id'] ?? 0);

    if ($courseId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid course_id is required.']);
        exit;
    }

    if ($action === 'enroll') {
        $semester = (int)($data['semester'] ?? 3);
        $res = CourseService::enrollStudent($studentId, $courseId, $semester);
        if (!$res['success']) {
            http_response_code(400);
        }
        echo json_encode($res);
        exit;
    } elseif ($action === 'drop') {
        $res = CourseService::dropEnrollment($studentId, $courseId);
        if (!$res['success']) {
            http_response_code(400);
        }
        echo json_encode($res);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
exit;
