<?php
// Run from CLI after importing schema.sql: php database/create_admin.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../app/config/db.php';
$username = trim(readline('Admin username: '));
$email = trim(readline('Admin email: '));
$password = '';
echo 'Admin password: ';
if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
    $password = trim((string)readline());
} else {
    system('stty -echo'); $password = trim((string)fgets(STDIN)); system('stty echo'); echo PHP_EOL;
}
if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Use a username, valid email, and password of at least 12 characters.\n"); exit(1);
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = getDB()->prepare("INSERT INTO users (username,email,password,role,status) VALUES (:u,:e,:p,'admin','active')");
$stmt->execute([':u'=>$username, ':e'=>$email, ':p'=>$hash]);
echo "Admin account created. Store credentials securely.\n";
