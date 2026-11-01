<?php
/**
 * User Logout Processor (Practical 10)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/security.php';

initSession();

// Audit log entry before destroying session
if (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../app/config/db.php';
        $mysqli = getMySQLi();
        $clientIp = getClientIp();
        $stmtLog = $mysqli->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'USER_LOGOUT', 'User logged out', ?)");
        if ($stmtLog) {
            $stmtLog->bind_param("is", $_SESSION['user_id'], $clientIp);
            $stmtLog->execute();
            $stmtLog->close();
        }
    } catch (Throwable $t) {
        // Continue logout even if audit logging fails
    }
}

// Invalidate all session variables
$_SESSION = [];

// Clear session cookie using exact configuration parameters
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session data from server storage
session_destroy();

// Start a fresh unauthenticated session with a new CSRF token ready for the next login
initSession();
getCsrfToken();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'  => true,
        'message'  => 'Logged out successfully.',
        'redirect' => 'pages/login.html?logged_out=1'
    ]);
    exit;
}

header('Location: pages/login.html?logged_out=1');
exit;
