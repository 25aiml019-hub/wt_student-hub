<?php
require_once __DIR__ . '/_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    wt19_require_admin();
    $rows = getDB()->query('SELECT id, name, message, created_at FROM feedback ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    wt19_json(['success' => true, 'feedback' => $rows]);
}
$d = wt19_require_post();
wt19_throttle('feedback', 5, 600);
$name = trim((string)($d['name'] ?? ''));
$message = trim((string)($d['feedback'] ?? ''));
if ($name === '' || $message === '') wt19_json(['success' => false, 'message' => 'Enter your name and feedback.'], 422);
if (mb_strlen($name) > 100 || mb_strlen($message) > 2000) wt19_json(['success' => false, 'message' => 'Name or feedback is too long.'], 422);
try {
    $u = wt19_user();
    $q = getDB()->prepare('INSERT INTO feedback (user_id, name, message) VALUES (?,?,?)');
    $q->execute([$u['id'] ?? null, $name, $message]);
    wt19_json(['success' => true, 'message' => 'Thank you! Your feedback has been saved.']);
} catch (Throwable $e) {
    error_log('WT19 feedback: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Feedback could not be saved. Check the database setup.'], 500);
}
