<?php
require_once __DIR__ . '/_bootstrap.php';
$u = wt19_require_login();
$pdo = getDB();
try {
    $sid = wt19_student_pk($pdo, $u);
    $mine = [];
    if ($sid) {
        $q = $pdo->prepare("SELECT e.id, e.title, e.event_date, e.event_time, e.location, e.status
                            FROM registrations r JOIN events e ON e.id = r.event_id
                            WHERE r.student_id = ? ORDER BY e.event_date ASC");
        $q->execute([$sid]);
        $mine = $q->fetchAll(PDO::FETCH_ASSOC);
    }
    $upcoming = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status='upcoming' AND event_date >= CURDATE()")->fetchColumn();
    $fb = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE user_id = ?');
    $fb->execute([$u['id']]);
    $out = [
        'success' => true,
        'user' => ['name' => $u['name'], 'username' => $u['username'], 'role' => $u['role']],
        'my_events' => $mine,
        'upcoming_total' => $upcoming,
        'my_feedback_count' => (int)$fb->fetchColumn(),
    ];
    if ($u['role'] === 'admin') {
        $out['admin'] = [
            'students'  => (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
            'events'    => (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn(),
            'feedback'  => (int)$pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn(),
            'messages'  => (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='unread'")->fetchColumn(),
        ];
    }
    wt19_json($out);
} catch (Throwable $e) {
    error_log('WT19 dashboard: ' . $e->getMessage());
    wt19_json(['success' => false, 'message' => 'Dashboard data could not be loaded.'], 500);
}
