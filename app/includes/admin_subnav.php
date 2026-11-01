<?php
/**
 * Shared Admin Subnavigation Tab Bar
 * StudentHub - CSPIT, CHARUSAT
 * Author: Daksh (25AIML019)
 */

if (!isset($adminActiveTab)) {
    $adminActiveTab = 'dashboard';
}
?>
<div class="admin-subnav-bar" role="navigation" aria-label="Admin Subnavigation">
    <span class="admin-subnav-label">Admin Control:</span>
    <a href="index.php" class="admin-subnav-link <?= ($adminActiveTab === 'dashboard') ? 'active' : '' ?>">
        Dashboard
    </a>
    <a href="students.php" class="admin-subnav-link <?= ($adminActiveTab === 'students') ? 'active' : '' ?>">
        Student Management
    </a>
    <a href="events.php" class="admin-subnav-link <?= ($adminActiveTab === 'events') ? 'active' : '' ?>">
        Event Management
    </a>
    <a href="../index.html" data-target="public/index.html" class="admin-subnav-link public-site-link">
        &larr; View Public Portal
    </a>
</div>