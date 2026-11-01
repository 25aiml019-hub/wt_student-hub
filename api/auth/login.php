<?php
/**
 * REST API Auth - Login Endpoint (Practical 10 & 13)
 * Resource: /api/auth/login.php
 * StudentHub - CSPIT, CHARUSAT
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/security.php';
require_once __DIR__ . '/../../app/services/user_service.php';

initSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'status'  => 405,
        'message' => 'Method Not Allowed. Use POST.'
    ], JSON_PRETTY_PRINT);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$identifier = sanitizeString($input['identifier'] ?? $input['username'] ?? '');
$password   = $input['password'] ?? '';

if (empty($identifier) || empty($password)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'status'  => 422,
        'message' => 'Username/email and password are required.'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    $res = UserService::authenticate($identifier, $password);
    if (!$res['success']) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'status'  => 401,
            'message' => $res['message']
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $user = $res['user'];
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['student_id']    = $user['student_id'];
    $_SESSION['last_activity'] = time();
    $_SESSION['user']          = $user;

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status'  => 200,
        'message' => 'Authentication successful.',
        'data'    => [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role']
        ]
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("API login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'status'  => 500,
        'message' => 'An internal server error occurred.'
    ], JSON_PRETTY_PRINT);
}
