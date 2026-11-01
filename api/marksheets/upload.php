<?php
/**
 * StudentHub - Marksheet Upload API Endpoint
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/marksheet_service.php';
require_once __DIR__ . '/../../app/services/student_service.php';

header('Content-Type: application/json; charset=utf-8');

// 1. HTTP Method Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// 2. Authentication Check
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Authentication required. Please sign in to access marksheets.',
        'redirect' => '../pages/login.html?error=unauthorized'
    ]);
    exit;
}

$currentUser = currentUser();
$userId = (int)($currentUser['id'] ?? 0);
$student = StudentService::findByUserId($userId);
if (!$student && ($currentUser['role'] ?? '') === 'admin') {
    $student = StudentService::findById(1);
}

if (!$student) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No active student profile linked to this account.']);
    exit;
}

$studentId = (int)$student['id'];

// 3. Handle GET: Retrieve student marksheet history (Requirement 12)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $history = MarksheetService::getStudentMarksheets($studentId);
    echo json_encode([
        'success' => true,
        'student_id' => $studentId,
        'marksheets' => $history,
        'count' => count($history)
    ]);
    exit;
}

// Read POST payload (JSON or form-data)
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true);
$postData = is_array($jsonBody) ? $jsonBody : $_POST;
$action = $postData['action'] ?? '';

// 4. Handle Action: Confirm CGPA (Requirement 11: Save academic result only after confirmation)
if ($action === 'confirm') {
    $marksheetId = (int)($postData['marksheet_id'] ?? 0);
    $confirmedCgpa = isset($postData['confirmed_cgpa']) ? (float)$postData['confirmed_cgpa'] : -1.0;

    if ($marksheetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A valid marksheet ID is required for confirmation.']);
        exit;
    }

    if ($confirmedCgpa < 0.0 || $confirmedCgpa > 10.0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Please provide a valid SGPA/CGPA value between 0.00 and 10.00.']);
        exit;
    }

    // Verify marksheet belongs to this student (Requirement 7: Privacy enforcement)
    $ms = MarksheetService::getById($marksheetId);
    if (!$ms || (int)$ms['student_id'] !== $studentId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized: You can only confirm your own marksheet.']);
        exit;
    }

    $ok = MarksheetService::confirmCgpa($marksheetId, $studentId, $confirmedCgpa);
    if ($ok) {
        echo json_encode([
            'success' => true,
            'marksheet_id' => $marksheetId,
            'confirmed_cgpa' => $confirmedCgpa,
            'message' => 'CGPA confirmed and saved to your student academic record!'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update CGPA in academic records.']);
    }
    exit;
}

// 5. Handle Action: Delete marksheet
if ($action === 'delete') {
    $marksheetId = (int)($postData['marksheet_id'] ?? 0);
    if ($marksheetId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'A valid marksheet ID is required.']);
        exit;
    }

    $ok = MarksheetService::deleteMarksheet($marksheetId, $studentId);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Marksheet deleted successfully.']);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unable to delete marksheet or permission denied.']);
    }
    exit;
}

// 6. Handle File Upload Workflow
if (!isset($_FILES['marksheet_file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No marksheet file was selected for upload.']);
    exit;
}

$file = $_FILES['marksheet_file'];
$uploadErr = $file['error'];

// Comprehensive PHP Upload Error Handling (Requirement 4)
if ($uploadErr !== UPLOAD_ERR_OK) {
    http_response_code(400);
    switch ($uploadErr) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $msg = 'The uploaded file exceeds the maximum allowed upload size (5 MB).';
            break;
        case UPLOAD_ERR_PARTIAL:
            $msg = 'The file was only partially uploaded. Please check your connection and retry.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $msg = 'No file was uploaded. Please choose a PDF, PNG, JPG, or WEBP marksheet.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $msg = 'Server configuration error: Missing temporary upload directory.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $msg = 'Server storage error: Failed to write upload to server disk.';
            break;
        default:
            $msg = 'Upload failed with system error code ' . $uploadErr . '.';
            break;
    }
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$origName = basename($file['name']);
$fileSize = (int)$file['size'];
$tmpPath = $file['tmp_name'];

// Enforce 5 MB size limit (Requirement 3)
$maxBytes = 5 * 1024 * 1024;
if ($fileSize > $maxBytes || $fileSize <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File size (' . round($fileSize / 1024 / 1024, 2) . ' MB) exceeds the 5 MB limit.']);
    exit;
}

// Extension validation (Requirement 1: PDF, PNG, JPG, WEBP)
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
if (!in_array($ext, $allowedExts, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "Unsupported file type '.{$ext}'. Allowed formats are PDF, PNG, JPG, and WEBP."]);
    exit;
}

// Deep MIME validation using finfo (Requirement 2: Validate actual MIME type, not just extension)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($tmpPath);

$allowedMimes = ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'];
if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => "Invalid file content ({$mimeType}). The file content does not match an authentic PDF or image document."
    ]);
    exit;
}

// Form parameters
$semester = isset($_POST['semester']) ? max(1, min(8, (int)$_POST['semester'])) : 2;
$academicYear = sanitizeString($_POST['academic_year'] ?? '2025-2026');
if (empty($academicYear)) {
    $academicYear = '2025-2026';
}

// Secure Storage Directory outside publicly executable root (Requirement 5)
$uploadDir = __DIR__ . '/../../storage/uploads/marksheets';
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to initialize secure marksheet storage directory.']);
        exit;
    }
}

// Secure random filename preventing collisions and directory traversal
$randomName = 'marksheet_' . $studentId . '_sem' . $semester . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$destPath = $uploadDir . '/' . $randomName;

if (!move_uploaded_file($tmpPath, $destPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded document to secure storage.']);
    exit;
}

// Extract text and parse CGPA (Requirements 8 & 9: Extract only when valid, never fabricate)
$extractedText = MarksheetService::extractTextFromFile($destPath, $mimeType);
$extractedCgpa = MarksheetService::extractCgpaFromText($extractedText);

// If student manually provided a preview hint in the form, accept it only if valid float
if ($extractedCgpa === null && isset($_POST['manual_cgpa']) && is_numeric($_POST['manual_cgpa'])) {
    $manual = (float)$_POST['manual_cgpa'];
    if ($manual >= 0.0 && $manual <= 10.0) {
        $extractedCgpa = round($manual, 2);
    }
}

// Database Insertion (Transaction with cleanup on failure)
$marksheetId = MarksheetService::createMarksheet(
    $studentId,
    $semester,
    $academicYear,
    $origName,
    $randomName,
    $fileSize,
    $mimeType,
    $extractedCgpa,
    substr($extractedText, 0, 2000)
);

// If database insertion fails, remove partially uploaded file from disk!
if ($marksheetId <= 0) {
    if (file_exists($destPath)) {
        @unlink($destPath);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: Unable to record uploaded marksheet.']);
    exit;
}

// Return structured success response (Requirement 10 & 13)
echo json_encode([
    'success' => true,
    'marksheet_id' => $marksheetId,
    'original_filename' => $origName,
    'stored_filename' => $randomName,
    'semester' => $semester,
    'academic_year' => $academicYear,
    'file_size' => $fileSize,
    'mime_type' => $mimeType,
    'extracted_cgpa' => $extractedCgpa,
    'has_detected_cgpa' => ($extractedCgpa !== null),
    'message' => ($extractedCgpa !== null)
        ? "Marksheet scanned successfully! Detected score: " . number_format($extractedCgpa, 2)
        : "Marksheet uploaded successfully. Please verify or enter your SGPA/CGPA to confirm."
]);
exit;
