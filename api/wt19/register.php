<?php
require_once __DIR__ . '/_bootstrap.php';
$d = wt19_require_post();
wt19_throttle('register', 10, 600);

$name    = trim((string)($d['name'] ?? ''));
$email   = strtolower(trim((string)($d['email'] ?? '')));
$enrol   = strtolower(trim((string)($d['enrolment'] ?? '')));
$mobile  = preg_replace('/\s+/', '', (string)($d['mobile'] ?? ''));
$pass    = (string)($d['password'] ?? '');
$confirm = (string)($d['confirm_password'] ?? '');
$gender  = (string)($d['gender'] ?? '');
$dept    = trim((string)($d['department'] ?? ''));
$address = trim((string)($d['address'] ?? ''));
$hobbies = is_array($d['hobbies'] ?? null) ? $d['hobbies'] : [];

$allowedDepts = ['CSE', 'AIML', 'ECE', 'EEE', 'CIVIL', 'MECH'];
$allowedHobby = ['Reading', 'Sports', 'Music'];
$err = null;
if (mb_strlen($name) < 2 || mb_strlen($name) > 100)                $err = 'Enter your full name.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))                $err = 'Enter a valid email address.';
elseif (!preg_match('/^[0-9]{2}aiml[0-9]{3}$/', $enrol))           $err = 'Enrollment number must look like 25aiml019.';
elseif ($mobile !== '' && !preg_match('/^[0-9]{10}$/', $mobile))   $err = 'Mobile number must be exactly 10 digits.';
elseif (!wt19_strong_password($pass))                              $err = 'Password needs 6+ characters with an uppercase letter, a lowercase letter and a number.';
elseif ($pass !== $confirm)                                        $err = 'Passwords do not match.';
elseif (!in_array($gender, ['Male', 'Female', 'Other'], true))     $err = 'Please select a gender.';
elseif (!in_array($dept, $allowedDepts, true))                     $err = 'Please select a department.';
elseif ($address === '')                                           $err = 'Please enter your address.';
if ($err) wt19_json(['success' => false, 'message' => $err], 422);

$hobbies = array_values(array_intersect($allowedHobby, array_map('strval', $hobbies)));

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
    $q->execute([$email, $enrol]);
    $q2 = $pdo->prepare('SELECT id FROM students WHERE email = ? LIMIT 1');
    $q2->execute([$email]);
    if ($q->fetch() || $q2->fetch()) {
        $pdo->rollBack();
        wt19_json(['success' => false, 'message' => 'This email or enrollment number is already registered.'], 409);
    }
    $q = $pdo->prepare("INSERT INTO users (username,email,password,role,status) VALUES (?,?,?,'student','active')");
    $q->execute([$enrol, $email, password_hash($pass, PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();
    $q = $pdo->prepare("INSERT INTO students (student_id,user_id,name,email,mobile,course,department,gender,hobbies,address,status)
                        VALUES (?,?,?,?,?,?,?,?,?,?,'active')");
    $q->execute([$enrol, $uid, $name, $email, $mobile, 'AIML', $dept, $gender, implode(', ', $hobbies), $address]);
    $sid = (int)$pdo->lastInsertId();
    $pdo->commit();
    wt19_audit($uid, 'wt19_register', "Student {$enrol} registered");
    wt19_json(['success' => true, 'message' => 'Registration successful. You can now log in.', 'student_id' => $sid], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('WT19 registration: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Registration could not be completed. Check the database setup and try again.'], 500);
}
