<?php
/**
 * Role-Based Access Control (RBAC) Middleware
 * StudentHub - CSPIT, CHARUSAT
 */

require_once __DIR__ . '/../includes/auth_check.php';

function checkRole($role = 'admin', $redirect = '../pages/login.html') {
    requireRole($role, $redirect);
}
