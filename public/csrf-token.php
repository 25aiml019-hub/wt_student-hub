<?php
/**
 * CSRF Token Provider Endpoint
 * StudentHub - CSPIT, CHARUSAT
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/security.php';

initSession();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'csrf_token' => getCsrfToken()
]);
