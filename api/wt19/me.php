<?php
require_once __DIR__ . '/_bootstrap.php';
$u = wt19_user();
if (!$u) wt19_json(['success' => false, 'authenticated' => false, 'csrf_token' => wt19_csrf()], 401);
wt19_json(['success' => true, 'authenticated' => true, 'user' => $u, 'csrf_token' => wt19_csrf()]);
