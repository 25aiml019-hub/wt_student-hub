<?php
/**
 * Shared HTML Head & Header Component
 * StudentHub - CSPIT, CHARUSAT
 * Author: Daksh (25AIML019)
 */

if (!isset($basePath)) {
    $basePath = '';
}
$assetPath = ($basePath === '../') ? '../public/' : $basePath;
$title = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - StudentHub' : 'StudentHub - Campus Portal';

// Relative link targets based on $basePath
$homeUrl = ($basePath === '') ? 'index.html' : (($basePath === '../public/' || $basePath === '../') ? '../public/index.html' : '../index.html');
$loginUrl = ($basePath === '') ? 'pages/login.html' : (($basePath === '../public/' || $basePath === '../') ? '../public/pages/login.html' : 'login.html');
$registerUrl = ($basePath === '') ? 'pages/registration.html' : (($basePath === '../public/' || $basePath === '../') ? '../public/pages/registration.html' : 'registration.html');
$logoutUrl = ($basePath === '') ? 'process-logout.php' : (($basePath === '../public/' || $basePath === '../') ? '../public/process-logout.php' : '../process-logout.php');
$adminPortalUrl = ($basePath === '') ? '../admin/index.php' : (($basePath === '../public/' || $basePath === '../') ? '../admin/index.php' : '../../admin/index.php');
$studentPortalUrl = ($basePath === '') ? '../student/index.php' : (($basePath === '../public/' || $basePath === '../') ? '../student/index.php' : '../../student/index.php');

$currentUser = function_exists('currentUser') ? currentUser() : null;
$userDisplayName = '';
$userRole = '';
if ($currentUser) {
    $userDisplayName = $currentUser['username'] ?? 'User';
    $userRole = $currentUser['role'] ?? 'student';
    if ($userRole === 'student' && class_exists('StudentService')) {
        try {
            $stProfile = null;
            if (!empty($currentUser['student_id'])) {
                $stProfile = StudentService::getById((int)$currentUser['student_id']);
            }
            if (!$stProfile && !empty($currentUser['id'])) {
                $stProfile = StudentService::findByUserId((int)$currentUser['id']);
            }
            if (!$stProfile && !empty($currentUser['email'])) {
                $stProfile = StudentService::findByEmail($currentUser['email']);
            }
            if ($stProfile && !empty($stProfile['name'])) {
                $userDisplayName = $stProfile['name'];
            }
        } catch (Throwable $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="icon" type="image/png" href="<?= $assetPath ?>assets/images/logo.png">
    <!-- Central Design Tokens & Component Styles -->
    <link rel="stylesheet" href="<?= $assetPath ?>assets/css/main.css">
    <?php if (isset($extraCss)): ?>
        <?php foreach ((array)$extraCss as $cssFile): ?>
            <link rel="stylesheet" href="<?= $assetPath ?>assets/css/<?= htmlspecialchars($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <div class="site-header-inner">
            <div class="brand-section">
                <a href="<?= htmlspecialchars($homeUrl) ?>" class="brand-link">
                    <img src="<?= $assetPath ?>assets/images/logo.png"
                         alt="Student Hub Logo"
                         class="site-logo"
                         data-fallback="studenthub_co_logo"
                         width="48"
                         height="48"
                         onerror="this.onerror=null; this.src='<?= $assetPath ?>assets/images/studenthub_logo.jpg';">
                    <div class="brand-text">
                        <span class="brand-title">StudentHub</span>
                        <span class="brand-subtitle">CSPIT, CHARUSAT Campus</span>
                    </div>
                </a>
            </div>

            <div class="user-header-status" id="user-header-status">
                <?php if ($currentUser): ?>
                    <div class="user-badge-wrap">
                        <div class="user-avatar-circle <?= ($userRole === 'admin') ? 'admin' : '' ?>">
                            <?= htmlspecialchars(strtoupper(substr($userDisplayName, 0, 1))) ?>
                        </div>
                        <div class="user-meta-text">
                            <span class="user-display-name"><?= htmlspecialchars($userDisplayName) ?></span>
                            <span class="user-role-badge <?= ($userRole === 'admin') ? 'admin' : 'student' ?>">
                                <?= htmlspecialchars(ucfirst($userRole)) ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="<?= htmlspecialchars($adminPortalUrl) ?>" class="btn-header-action btn-header-portal">Admin Portal</a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($studentPortalUrl) ?>" class="btn-header-action btn-header-portal">My Portal</a>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($logoutUrl) ?>" class="btn-header-action btn-header-logout">Sign Out</a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn-header-action btn-header-login">Sign In</a>
                    <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn-header-action btn-header-register">Register</a>
                <?php endif; ?>
            </div>

            <button type="button" class="nav-toggle-btn" id="nav-toggle-btn" aria-label="Toggle navigation menu">&#9776;</button>
        </div>
    </header>