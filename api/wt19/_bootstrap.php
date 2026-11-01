<?php
/**
 * WT19 API bootstrap - StudentHub by Daksh (25AIML019)
 * Shared helpers for every endpoint in api/wt19/.
 */
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/db.php';
initSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function wt19_json($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function wt19_input(): array {
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    return is_array($j) ? $j : $_POST;
}

function wt19_csrf(): string {
    if (empty($_SESSION['wt19_csrf'])) {
        $_SESSION['wt19_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['wt19_csrf'];
}

function wt19_require_csrf(array $d): void {
    $t = $d['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(wt19_csrf(), $t)) {
        wt19_json(['success' => false, 'message' => 'Security token expired. Refresh the page and try again.'], 403);
    }
}

function wt19_require_post(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        wt19_json(['success' => false, 'message' => 'POST request required.'], 405);
    }
    $d = wt19_input();
    wt19_require_csrf($d);
    return $d;
}

function wt19_user(): ?array {
    return (!empty($_SESSION['user_id']) && !empty($_SESSION['user'])) ? $_SESSION['user'] : null;
}

function wt19_require_login(): array {
    $u = wt19_user();
    if (!$u) wt19_json(['success' => false, 'message' => 'Please log in first.'], 401);
    return $u;
}

function wt19_require_admin(): array {
    $u = wt19_require_login();
    if (($u['role'] ?? '') !== 'admin') {
        wt19_json(['success' => false, 'message' => 'Administrator access required.'], 403);
    }
    return $u;
}

/** Student row id (students.id) for the logged-in user, or null. */
function wt19_student_pk(PDO $pdo, array $u): ?int {
    $q = $pdo->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    $q->execute([$u['id']]);
    $id = $q->fetchColumn();
    return $id ? (int)$id : null;
}

function wt19_strong_password(string $p): bool {
    return strlen($p) >= 6 && preg_match('/[A-Z]/', $p) && preg_match('/[a-z]/', $p) && preg_match('/[0-9]/', $p);
}

/** Simple per-session throttle: max $limit hits per $window seconds for $key. */
function wt19_throttle(string $key, int $limit, int $window): void {
    $now = time();
    $hits = array_filter($_SESSION['wt19_hits'][$key] ?? [], fn($t) => $t > $now - $window);
    if (count($hits) >= $limit) {
        wt19_json(['success' => false, 'message' => 'Too many attempts. Please wait a few minutes and try again.'], 429);
    }
    $hits[] = $now;
    $_SESSION['wt19_hits'][$key] = array_values($hits);
}

function wt19_audit(?int $uid, string $action, string $details): void {
    try {
        $q = getDB()->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?,?,?,?)');
        $q->execute([$uid, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        error_log('WT19 audit: ' . $e->getMessage());
    }
}
