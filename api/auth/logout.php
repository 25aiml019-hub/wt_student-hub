<?php
/**
 * REST API Auth - Logout Endpoint (Practical 10 & 13)
 * Resource: /api/auth/logout.php
 * StudentHub - CSPIT, CHARUSAT
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../app/config/config.php';

initSession();

$_SESSION = [];
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
session_destroy();

http_response_code(200);
echo json_encode([
    'success' => true,
    'status'  => 200,
    'message' => 'Logged out successfully.'
], JSON_PRETTY_PRINT);
