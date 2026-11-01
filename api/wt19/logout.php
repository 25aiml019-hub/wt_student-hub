<?php
require_once __DIR__ . '/_bootstrap.php';
$d = wt19_input();
wt19_require_csrf($d);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
wt19_json(['success' => true, 'message' => 'You have been logged out.']);
