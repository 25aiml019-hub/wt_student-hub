<?php
/**
 * Application Configuration
 * StudentHub - CSPIT, CHARUSAT
 * Author: Daksh (25AIML019)
 */

// Define directory constants
define('ROOT_PATH', dirname(__DIR__, 2));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('DATA_PATH', STORAGE_PATH . '/data');

/**
 * Lightweight .env parser
 */
function loadEnv($envFile = null) {
    if ($envFile === null) {
        $candidates = [
            ROOT_PATH . '/.env',
            dirname(ROOT_PATH) . '/.env',
            PUBLIC_PATH . '/.env'
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_readable($cand)) {
                $envFile = $cand;
                break;
            }
        }
        if ($envFile === null) {
            $envFile = ROOT_PATH . '/.env';
        }
    }
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove wrapping quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load environment variables
loadEnv();

/**
 * Retrieve environment variable with default fallback
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false || $value === null || $value === '') {
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            $value = $_ENV[$key];
        } elseif (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            $value = $_SERVER[$key];
        } else {
            return $default;
        }
    }
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    return $value;
}

// Session security helper
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $lifetime = (int) env('SESSION_LIFETIME', 3600);
        $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_samesite', 'Lax');

        if ($isSecure) {
            ini_set('session.cookie_secure', '1');
        }

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();

        // Server-enforced inactivity timeout check
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            if ((time() - (int)$_SESSION['last_activity']) > $lifetime) {
                // Session has expired due to inactivity
                $_SESSION = [];
                if (ini_get("session.use_cookies")) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
                }
                session_destroy();
                session_start();
                $_SESSION['flash_error'] = 'Your session has expired due to inactivity. Please log in again.';
                return;
            }
        }

        // Update last activity timestamp for active sessions
        if (isset($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
    }
}

// Global Application Constants
define('APP_NAME', env('APP_NAME', 'StudentHub'));
define('APP_ENV', env('APP_ENV', 'development'));
define('APP_DEBUG', env('APP_DEBUG', true));
define('APP_URL', env('APP_URL', 'http://localhost:8000'));

// Production vs Development Error Handling & Logging
if (APP_DEBUG === false || APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    $logDir = STORAGE_PATH . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/production_error.log');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}
