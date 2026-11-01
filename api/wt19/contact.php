<?php
require_once __DIR__ . '/_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    wt19_require_admin();
    $rows = getDB()->query('SELECT id, name, email, subject, message, status, created_at FROM contact_messages ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    wt19_json(['success' => true, 'messages' => $rows]);
}
$d = wt19_require_post();
wt19_throttle('contact', 5, 600);
$name = trim((string)($d['name'] ?? ''));
$email = trim((string)($d['email'] ?? ''));
$subject = trim((string)($d['subject'] ?? ''));
$message = trim((string)($d['message'] ?? ''));
if ($name === '' || $subject === '' || $message === '') wt19_json(['success' => false, 'message' => 'Please fill in every field.'], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) wt19_json(['success' => false, 'message' => 'Enter a valid email address.'], 422);
if (mb_strlen($name) > 100 || mb_strlen($subject) > 150 || mb_strlen($message) > 3000) wt19_json(['success' => false, 'message' => 'One of the fields is too long.'], 422);
try {
    $q = getDB()->prepare('INSERT INTO contact_messages (name,email,subject,message,ip_address) VALUES (?,?,?,?,?)');
    $q->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR'] ?? null]);
    wt19_json(['success' => true, 'message' => 'Message sent. We will get back to you soon.']);
} catch (Throwable $e) {
    error_log('WT19 contact: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Message could not be sent. Please try again later.'], 500);
}
