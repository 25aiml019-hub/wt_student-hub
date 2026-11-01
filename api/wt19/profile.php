<?php
require_once __DIR__ . '/_bootstrap.php';
$u = wt19_require_login();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = wt19_input();
    wt19_require_csrf($d);
    $mobile  = preg_replace('/\s+/', '', (string)($d['mobile'] ?? ''));
    $address = trim((string)($d['address'] ?? ''));
    $dept    = trim((string)($d['department'] ?? ''));
    $hob     = is_array($d['hobbies'] ?? null) ? array_values(array_intersect(['Reading', 'Sports', 'Music'], $d['hobbies'])) : [];
    if ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile)) wt19_json(['success' => false, 'message' => 'Mobile number must be exactly 10 digits.'], 422);
    if (!in_array($dept, ['CSE', 'AIML', 'ECE', 'EEE', 'CIVIL', 'MECH'], true)) wt19_json(['success' => false, 'message' => 'Choose a valid department.'], 422);
    if ($address === '') wt19_json(['success' => false, 'message' => 'Address cannot be empty.'], 422);
    $q = $pdo->prepare('UPDATE students SET mobile = ?, address = ?, department = ?, hobbies = ? WHERE user_id = ?');
    $q->execute([$mobile, $address, $dept, implode(', ', $hob), $u['id']]);
    wt19_json(['success' => true, 'message' => 'Profile updated.']);
}

$q = $pdo->prepare('SELECT student_id,name,email,mobile,course,department,academic_year,gender,hobbies,address,created_at FROM students WHERE user_id = ?');
$q->execute([$u['id']]);
$p = $q->fetch(PDO::FETCH_ASSOC);
if (!$p) wt19_json(['success' => false, 'message' => 'Student profile not found.'], 404);
wt19_json(['success' => true, 'profile' => $p, 'role' => $u['role']]);
