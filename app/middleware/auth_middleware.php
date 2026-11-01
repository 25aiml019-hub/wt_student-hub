<?php
/**
 * Authentication Middleware
 * StudentHub - CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../includes/auth_check.php';

function checkAuth($redirect = '../pages/login.html') {
    requireLogin($redirect);
}
