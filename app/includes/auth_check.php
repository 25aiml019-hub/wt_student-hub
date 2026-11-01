<?php
/**
 * Authentication & Role-Based Access Control (RBAC) Guard Helpers
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 10)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../config/config.php';

initSession();

/**
 * Check if the current session has an authenticated user
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retrieve the current logged in user details array
 */
function currentUser(): ?array {
    if (!isAuthenticated()) {
        return null;
    }
    return [
        'id'         => (int)$_SESSION['user_id'],
        'username'   => $_SESSION['username'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'role'       => $_SESSION['role'] ?? 'student',
        'student_id' => $_SESSION['student_id'] ?? null
    ];
}

/**
 * Verify if current user possesses the required role
 */
function hasRole(string $role): bool {
    if (!isAuthenticated()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Guard for web pages: requires authenticated login, otherwise redirects
 */
function requireLogin(string $redirectUrl = '../pages/login.html'): void {
    if (!isAuthenticated()) {
        header("Location: {$redirectUrl}?error=unauthorized");
        exit;
    }
}

/**
 * Guard for web pages: requires specific role, otherwise blocks with 403 or redirects
 */
function requireRole(string $role, string $redirectUrl = '../pages/login.html'): void {
    if (!isAuthenticated()) {
        header("Location: {$redirectUrl}?error=unauthorized");
        exit;
    }

    if (!hasRole($role)) {
        http_response_code(403);
        // If redirect URL provided with query param
        header("Location: {$redirectUrl}?error=forbidden");
        exit;
    }
}

/**
 * Guard for REST/JSON API endpoints: enforces authentication & role
 */
function enforceApiAuth(?string $requiredRole = null): void {
    header('Content-Type: application/json; charset=UTF-8');
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'status'  => 401,
            'message' => 'Authentication required. Please log in to access this resource.'
        ], JSON_PRETTY_PRINT);
        exit;
    }

    if ($requiredRole !== null && !hasRole($requiredRole)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'status'  => 403,
            'message' => "Forbidden: Access requires '{$requiredRole}' role authorization."
        ], JSON_PRETTY_PRINT);
        exit;
    }
}
