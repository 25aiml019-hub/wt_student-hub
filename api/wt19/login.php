<?php
require_once __DIR__ . '/_bootstrap.php';
$d = wt19_require_post();
wt19_throttle('login', 8, 600);

$id = trim((string)($d['identifier'] ?? ''));
$pw = (string)($d['password'] ?? '');
if ($id === '' || $pw === '') {
    wt19_json(['success' => false, 'message' => 'Enter your email or enrollment number and your password.'], 422);
}
$id = strtolower($id);
try {
    $pdo = getDB();
    $q = $pdo->prepare('SELECT u.id,u.username,u.email,u.password,u.role,u.status,s.id AS student_pk,s.name
                        FROM users u LEFT JOIN students s ON s.user_id = u.id
                        WHERE LOWER(u.email) = ? OR LOWER(u.username) = ? LIMIT 1');
    $q->execute([$id, $id]);
    $u = $q->fetch(PDO::FETCH_ASSOC);
    if (!$u || $u['status'] !== 'active' || !password_verify($pw, $u['password'])) {
        wt19_audit($u['id'] ?? null, 'wt19_login_failed', 'Failed login for ' . substr($id, 0, 60));
        wt19_json(['success' => false, 'message' => 'Invalid login details.'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int)$u['id'];
    $_SESSION['username']      = $u['username'];
    $_SESSION['email']         = $u['email'];
    $_SESSION['role']          = $u['role'];
    $_SESSION['student_id']    = $u['student_pk'];
    $_SESSION['last_activity'] = time();
    $_SESSION['user'] = [
        'id' => (int)$u['id'], 'username' => $u['username'], 'email' => $u['email'],
        'role' => $u['role'], 'student_id' => $u['student_pk'], 'name' => $u['name'] ?? $u['username'],
    ];
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
    wt19_audit((int)$u['id'], 'wt19_login', 'Login successful');
    wt19_json([
        'success'  => true,
        'message'  => 'Login successful.',
        'role'     => $u['role'],
        'redirect' => $u['role'] === 'admin' ? 'admin.html' : 'dashboard.html',
    ]);
} catch (Throwable $e) {
    error_log('WT19 login: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Login service unavailable. Check the database setup.'], 500);
}
