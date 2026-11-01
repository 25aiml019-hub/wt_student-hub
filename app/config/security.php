<?php
/**
 * Security & Validation Module
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 7 & 8)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/config.php';

/**
 * Generate a cryptographically secure CSRF token and store it in session
 */
function generateCsrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Retrieve the current CSRF token from session (or generate if absent)
 */
function getCsrfToken(): string {
    return generateCsrfToken();
}

/**
 * Validate submitted CSRF token against session token using timing-safe comparison
 */
function validateCsrfToken(?string $token): bool {
    initSession();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize a string by stripping tags, normalizing whitespace, and removing control chars
 */
function sanitizeString(?string $value): string {
    if ($value === null) {
        return '';
    }
    $trimmed = trim($value);
    // Strip HTML/PHP tags
    $stripped = strip_tags($trimmed);
    // Remove null bytes
    return str_replace(chr(0), '', $stripped);
}

/**
 * Sanitize email input
 */
function sanitizeEmail(?string $email): string {
    if ($email === null) {
        return '';
    }
    $trimmed = trim($email);
    return filter_var($trimmed, FILTER_SANITIZE_EMAIL) ?: '';
}

/**
 * Validate email using RFC standard and regex
 */
function validateEmail(string $email): bool {
    if (empty($email)) {
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // Strict format check for username@domain.tld
    return (bool) preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

/**
 * Validate 10-digit mobile number according to Indian mobile numbering plan
 */
function validateMobile(string $mobile): bool {
    return (bool) preg_match('/^[0-9]{10}$/', trim($mobile));
}

/**
 * Hash password securely with bcrypt
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verify password against bcrypt hash
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Safe HTML escaping for output rendering
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Helper to get client IP safely
 */
function getClientIp(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return filter_var(trim($ips[0]), FILTER_VALIDATE_IP) ?: '127.0.0.1';
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}
