<?php
/**
 * Audit Logging Service
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 10, 11 & 12)
 * Author: Daksh (25AIML019)
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

class AuditService {
    /**
     * Record an audit log entry using MySQLi prepared statements
     */
    public static function log(?int $userId, string $action, string $details): bool {
        try {
            $mysqli = getMySQLi();
            $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

            $stmt = $mysqli->prepare(
                "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)"
            );
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param("isss", $userId, $action, $details, $clientIp);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        } catch (Exception $e) {
            error_log("AuditService::log failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve recent audit logs with joined user details
     */
    public static function getRecent(int $limit = 10): array {
        try {
            $mysqli = getMySQLi();
            $stmt = $mysqli->prepare(
                "SELECT a.id, a.user_id, a.action, a.details, a.ip_address, a.created_at, u.username, u.role " .
                "FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id " .
                "ORDER BY a.created_at DESC LIMIT ?"
            );
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            $stmt->close();
            return $logs;
        } catch (Exception $e) {
            error_log("AuditService::getRecent failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total audit entries
     */
    public static function count(): int {
        try {
            $mysqli = getMySQLi();
            $res = $mysqli->query("SELECT COUNT(*) AS total FROM audit_logs");
            if ($res && $row = $res->fetch_assoc()) {
                return (int)$row['total'];
            }
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}
