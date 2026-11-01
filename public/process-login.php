<?php
/**
 * User Login Processor (Practical 10: Secure Login & Sessions)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/security.php';
require_once __DIR__ . '/../app/services/user_service.php';

initSession();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
          (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

function sendLoginResponse(int $statusCode, bool $success, string $message, array $extra = [], bool $isAjax = true): void {
    http_response_code($statusCode);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
            'status'  => $statusCode
        ], $extra));
        exit;
    }

    if ($success) {
        $redirect = $extra['redirect'] ?? 'pages/dashboard.html';
        header("Location: {$redirect}");
    } else {
        $_SESSION['login_error'] = $message;
        header('Location: pages/login.html?error=invalid');
    }
    exit;
}

// -------------------------------------------------------------
// 1. HTTP Method Enforcement (Must be POST)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    sendLoginResponse(405, false, 'Method Not Allowed. Only HTTP POST submissions are accepted.', [], $isAjax);
}

// -------------------------------------------------------------
// 2. CSRF Token Verification
// -------------------------------------------------------------
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($csrfToken) || !empty($_SESSION['csrf_token'])) {
    if (!validateCsrfToken($csrfToken)) {
        sendLoginResponse(403, false, 'CSRF verification failed. Login request rejected.', [], $isAjax);
    }
}

// -------------------------------------------------------------
// 3. Extract & Validate Credentials
// -------------------------------------------------------------
// Support both 'identifier', 'username', or 'uname' for form compatibility
$identifier = sanitizeString($_POST['identifier'] ?? $_POST['username'] ?? $_POST['uname'] ?? '');
$password   = $_POST['password'] ?? $_POST['pword'] ?? '';

if (empty($identifier) || empty($password)) {
    sendLoginResponse(422, false, 'Please enter both username/email and password.', [
        'errors' => ['identifier' => 'Username/email and password are required.']
    ], $isAjax);
}

// -------------------------------------------------------------
// 4. Authenticate Against Database via Prepared Query (Practical 10)
// -------------------------------------------------------------
try {
    $authResult = UserService::authenticate($identifier, $password);

    if (!$authResult['success']) {
        $statusCode = (strpos($authResult['message'], 'locked') !== false) ? 429 : 401;
        sendLoginResponse($statusCode, false, $authResult['message'], [], $isAjax);
    }

    $user = $authResult['user'];

    // -------------------------------------------------------------
    // 5. Session Hardening & Session ID Regeneration
    // -------------------------------------------------------------
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Store only minimal required identity and role state in server session
    $_SESSION['user_id']              = $user['id'];
    $_SESSION['username']             = $user['username'];
    $_SESSION['email']                = $user['email'];
    $_SESSION['name']                 = $user['name'] ?? $user['username'];
    $_SESSION['role']                 = $user['role'];
    $_SESSION['student_id']           = $user['student_id'];
    $_SESSION['college_id']           = $user['college_id'] ?? ($user['role'] === 'student' ? $user['username'] : null);
    $_SESSION['must_change_password'] = (int)($user['must_change_password'] ?? 0);
    $_SESSION['last_activity']        = time();

    // Compatibility object for existing currentUser() calls
    $_SESSION['user'] = $user;

    // Refresh CSRF token for the new session
    unset($_SESSION['csrf_token']);
    getCsrfToken();

    // Determine target redirect based on must_change_password and role (RBAC routing)
    if (!empty($user['must_change_password'])) {
        $targetRedirect = 'change-password.php';
    } else {
        $targetRedirect = ($user['role'] === 'admin') ? '../admin/index.php' : '../student/index.php';
    }

    sendLoginResponse(200, true, 'Login successful.', [
        'role'                 => $user['role'],
        'username'             => $user['username'],
        'must_change_password' => (int)($user['must_change_password'] ?? 0),
        'redirect'             => $targetRedirect
    ], $isAjax);

} catch (Throwable $e) {
    error_log("Login processing error: " . $e->getMessage());
    sendLoginResponse(500, false, 'An unexpected server error occurred during authentication.', [], $isAjax);
}
