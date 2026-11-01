<?php
/**
 * Shared Navigation Component
 * StudentHub - CSPIT, CHARUSAT
 * Author: Daksh (25AIML019)
 */

if (!isset($basePath)) {
    $basePath = '';
}
if (!isset($currentPage)) {
    $currentPage = '';
}

// Compute correct relative prefixes for all directory contexts
$homeUrl = ($basePath === '') ? 'index.html' : (($basePath === '../public/' || $basePath === '../') ? '../public/index.html' : '../index.html');
$pagesPrefix = ($basePath === '') ? 'pages/' : (($basePath === '../public/' || $basePath === '../') ? '../public/pages/' : '');

$adminUrl = ($basePath === '') ? '../admin/index.php' : (($basePath === '../public/' || $basePath === '../') ? '../admin/index.php' : '../../admin/index.php');
$studentUrl = ($basePath === '') ? '../student/index.php' : (($basePath === '../public/' || $basePath === '../') ? '../student/index.php' : '../../student/index.php');
$logoutUrl = ($basePath === '') ? 'process-logout.php' : (($basePath === '../public/' || $basePath === '../') ? '../public/process-logout.php' : '../process-logout.php');

$navLinks = [
    'home'           => ['label' => 'Home', 'url' => $homeUrl],
    'administrative' => ['label' => 'Administrative', 'url' => $pagesPrefix . 'administrative.html'],
    'campuslife'     => ['label' => 'Campus Life', 'url' => $pagesPrefix . 'campuslife.html'],
    'events'         => ['label' => 'Events', 'url' => $pagesPrefix . 'events.html'],
    'registration'   => ['label' => 'Student Registration', 'url' => $pagesPrefix . 'registration.html'],
    'dashboard'      => ['label' => 'Dashboard', 'url' => $pagesPrefix . 'dashboard.html'],
    'about'          => ['label' => 'About Us', 'url' => $pagesPrefix . 'about.html'],
    'contact'        => ['label' => 'Contact Us', 'url' => $pagesPrefix . 'contact.html'],
    'faq'            => ['label' => 'FAQ', 'url' => $pagesPrefix . 'faq.html'],
    'login'          => ['label' => 'Login', 'url' => $pagesPrefix . 'login.html']
];

if (function_exists('isAuthenticated') && isAuthenticated()) {
    $cur = currentUser();
    if ($cur && isset($cur['role']) && $cur['role'] === 'admin') {
        $navLinks['admin'] = ['label' => 'Admin Portal', 'url' => $adminUrl];
    } elseif ($cur) {
        // Authenticated Student: display Course Enrollment and Student Portal
        $navLinks['courses'] = ['label' => 'Course Enrollment', 'url' => $pagesPrefix . 'course-enrollment.html'];
        $navLinks['student'] = ['label' => 'My Portal', 'url' => $studentUrl];
    }
    $navLinks['logout'] = [
        'label' => 'Logout (' . htmlspecialchars($cur['username'] ?? 'User') . ')',
        'url'   => $logoutUrl
    ];
    unset($navLinks['login']);
}
?>
<nav class="main-nav" id="main-nav" aria-label="Main Navigation">
    <?php foreach ($navLinks as $key => $link): ?>
        <a href="<?= htmlspecialchars($link['url']) ?>" 
           class="<?= ($currentPage === $key) ? 'active' : '' ?>">
            <?= htmlspecialchars($link['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>