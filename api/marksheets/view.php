<?php
/**
 * StudentHub - Marksheet Secure Viewer / Download
 * Enforces authenticated student access control (Requirement 7)
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/marksheet_service.php';
require_once __DIR__ . '/../../app/services/student_service.php';

initSession();

if (!isAuthenticated()) {
    http_response_code(401);
    die('Access Denied: Please sign in to view academic marksheets.');
}

$currentUser = currentUser();
$userId = (int)($currentUser['id'] ?? 0);
$currentRole = $currentUser['role'] ?? 'student';
$student = StudentService::findByUserId($userId);
if (!$student && $currentRole === 'admin') {
    $student = StudentService::findById(1);
}

$currentStudentId = $student ? (int)$student['id'] : 0;
$marksheetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($marksheetId <= 0) {
    http_response_code(400);
    die('Bad Request: Valid marksheet id parameter required.');
}

$ms = MarksheetService::getById($marksheetId);
if (!$ms) {
    http_response_code(404);
    die('Marksheet not found.');
}

// Security Check: Only the owner student or an admin can view this marksheet
if ($currentRole !== 'admin' && (int)$ms['student_id'] !== $currentStudentId) {
    http_response_code(403);
    die('Forbidden: You are not authorized to view another student\'s marksheet.');
}

$storageDir = __DIR__ . '/../../storage/uploads/marksheets/';
$storedFilename = basename($ms['stored_filename']);
$filePath = $storageDir . $storedFilename;

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found on storage disk.');
}

$mime = $ms['mime_type'] ?: 'application/pdf';
$origFilename = $ms['original_filename'] ?: 'marksheet.pdf';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . addslashes($origFilename) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
