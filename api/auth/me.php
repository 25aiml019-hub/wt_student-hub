<?php
/**
 * REST API Current Authenticated User Identity Endpoint
 * Resource: /api/auth/me.php
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/student_service.php';

initSession();

if (!isAuthenticated()) {
    echo json_encode([
        'success'       => true,
        'authenticated' => false,
        'user'          => null
    ], JSON_PRETTY_PRINT);
    exit;
}

$user = currentUser();
$displayName = $user['username'];
$studentProfile = null;

try {
    if (!empty($user['student_id'])) {
        $studentProfile = StudentService::getById((int)$user['student_id']);
    }
    if (!$studentProfile && !empty($user['id'])) {
        $studentProfile = StudentService::findByUserId((int)$user['id']);
    }
    if (!$studentProfile && !empty($user['email'])) {
        $studentProfile = StudentService::findByEmail($user['email']);
    }
    if ($studentProfile && !empty($studentProfile['name'])) {
        $displayName = $studentProfile['name'];
    }
} catch (Throwable $e) {
    error_log("api/auth/me.php student lookup: " . $e->getMessage());
}

echo json_encode([
    'success'       => true,
    'authenticated' => true,
    'user'          => [
        'id'          => $user['id'],
        'username'    => $user['username'],
        'displayName' => $displayName,
        'email'       => $user['email'],
        'role'        => $user['role'],
        'student_id'  => $user['student_id'],
        'student'     => $studentProfile
    ]
], JSON_PRETTY_PRINT);
