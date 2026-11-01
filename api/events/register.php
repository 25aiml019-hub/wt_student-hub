<?php
/**
 * StudentHub - Event Registration API Endpoint
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019), CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/includes/auth_check.php';
require_once __DIR__ . '/../../app/services/student_service.php';
require_once __DIR__ . '/../../app/services/event_service.php';
require_once __DIR__ . '/../../app/services/audit_service.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET: Check registration eligibility & status for a specific event
if ($method === 'GET') {
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Valid event_id is required.']);
        exit;
    }

    $event = EventService::getById($eventId);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Event not found.']);
        exit;
    }

    date_default_timezone_set('Asia/Kolkata');
    $currentDate = date('Y-m-d');
    $eventDate = $event['event_date'];
    $status = strtolower($event['status'] ?? 'upcoming');
    $isPast = ($eventDate < $currentDate);
    $isCompleted = ($status === 'completed') || $isPast;
    $isCancelled = ($status === 'cancelled');
    $isClosed = ($status === 'closed');

    $isLoggedIn = isAuthenticated();
    $isRegistered = false;
    $canRegister = true;
    $reason = '';

    if ($isCompleted) {
        $canRegister = false;
        $reason = "This event has concluded. Registrations are closed.";
    } elseif ($isCancelled) {
        $canRegister = false;
        $reason = "This event has been cancelled.";
    } elseif ($isClosed) {
        $canRegister = false;
        $reason = "Registrations are closed for this event.";
    }

    if ($isLoggedIn) {
        $currentUser = currentUser();
        $student = StudentService::findByUserId((int)($currentUser['id'] ?? 0));
        if (!$student && ($currentUser['role'] ?? '') === 'admin') {
            $student = StudentService::findById(1);
        }

        if ($student) {
            $isRegistered = StudentService::isRegisteredForEvent((int)$student['id'], $eventId);
            if ($isRegistered) {
                $canRegister = false;
                $reason = "You are already registered for this event.";
            }
        }
    } else {
        $reason = "Sign in required to register for this event.";
    }

    echo json_encode([
        'success' => true,
        'event_id' => $eventId,
        'event_title' => $event['title'],
        'event_date' => $eventDate,
        'event_status' => $status,
        'is_completed' => $isCompleted,
        'is_cancelled' => $isCancelled,
        'authenticated' => $isLoggedIn,
        'is_registered' => $isRegistered,
        'can_register' => $canRegister,
        'reason' => $reason
    ]);
    exit;
}

// Handle POST: Submit Event Registration
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'Authentication required',
        'message' => 'Sign in required to register for campus events.',
        'redirect' => '../pages/login.html?error=unauthorized&redirect=events.html'
    ]);
    exit;
}

$currentUser = currentUser();
$userId = (int)($currentUser['id'] ?? 0);

// Resolve student profile
$student = StudentService::findByUserId($userId);
if (!$student && ($currentUser['role'] ?? '') === 'admin') {
    // Admin fallback to student 1 for testing
    $student = StudentService::findById(1);
}

if (!$student) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'No active student profile linked to this account.',
        'message' => 'Your user account does not have an active student profile linked.'
    ]);
    exit;
}

$studentId = (int)$student['id'];

// Read input (JSON or POST)
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$eventId = isset($data['event_id']) ? (int)$data['event_id'] : 0;
if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid event_id is required.']);
    exit;
}

$result = StudentService::registerForEvent($studentId, $eventId);
$statusCode = $result['status_code'] ?? ($result['success'] ? 200 : 400);
http_response_code($statusCode);

echo json_encode($result);
exit;
