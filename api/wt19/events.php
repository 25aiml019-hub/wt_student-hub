<?php
require_once __DIR__ . '/_bootstrap.php';
$pdo = getDB();
$u = wt19_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sid = $u ? wt19_student_pk($pdo, $u) : null;
        $q = $pdo->prepare("SELECT e.id, e.title, e.description, e.category, e.event_date, e.event_time, e.location,
                                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS registered_count,
                                   (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.student_id = ?) AS is_registered
                            FROM events e
                            WHERE e.status = 'upcoming' AND e.event_date >= CURDATE()
                            ORDER BY e.event_date ASC, e.id ASC");
        $q->execute([$sid ?? 0]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['registered_count'] = (int)$r['registered_count'];
            $r['is_registered'] = (int)$r['is_registered'] > 0;
        }
        wt19_json(['success' => true, 'logged_in' => (bool)$u, 'events' => $rows]);
    } catch (Throwable $e) {
        error_log('WT19 events: ' . $e->getMessage());
        wt19_json(['success' => false, 'message' => 'Events could not be loaded.'], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') wt19_json(['success' => false, 'message' => 'Method not allowed.'], 405);
$d = wt19_input();
wt19_require_csrf($d);
$u = wt19_require_login();
$eventId = (int)($d['event_id'] ?? 0);
$action = ($d['action'] ?? 'register') === 'cancel' ? 'cancel' : 'register';
if ($eventId <= 0) wt19_json(['success' => false, 'message' => 'Choose a valid event.'], 422);

$sid = wt19_student_pk($pdo, $u);
if (!$sid) wt19_json(['success' => false, 'message' => 'Your account has no student profile.'], 403);

$q = $pdo->prepare("SELECT id, title FROM events WHERE id = ? AND status = 'upcoming' AND event_date >= CURDATE()");
$q->execute([$eventId]);
$ev = $q->fetch(PDO::FETCH_ASSOC);
if (!$ev) wt19_json(['success' => false, 'message' => 'This event is not open for registration.'], 404);

try {
    if ($action === 'cancel') {
        $q = $pdo->prepare('DELETE FROM registrations WHERE event_id = ? AND student_id = ?');
        $q->execute([$eventId, $sid]);
        wt19_json(['success' => true, 'message' => 'Your registration for "' . $ev['title'] . '" was cancelled.']);
    }
    $q = $pdo->prepare('INSERT INTO registrations (event_id, student_id) VALUES (?, ?)');
    $q->execute([$eventId, $sid]);
    wt19_audit((int)$u['id'], 'wt19_event_register', 'Event #' . $eventId);
    wt19_json(['success' => true, 'message' => 'You are registered for "' . $ev['title'] . '".']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') wt19_json(['success' => false, 'message' => 'You are already registered for this event.'], 409);
    error_log('WT19 event registration: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Registration failed. Please try again.'], 500);
}
