<?php
/**
 * Database Connection Module (PDO & MySQLi)
 * StudentHub - CSPIT, CHARUSAT
 * Course: AIUE203 - Web Development Fundamentals (Practical 8)
 * Author: Daksh (25AIML019)
 *
 * Implements dual database connectivity supporting:
 * 1. Object-Oriented MySQLi ($mysqli)
 * 2. Procedural MySQLi (mysqli_connect)
 * 3. PDO (PHP Data Objects) singleton
 * Credentials loaded securely from .env
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $pdoInstance = null;
    private static ?mysqli $mysqliInstance = null;

    /**
     * Get singleton PDO connection instance
     */
    public static function getConnection(): PDO {
        if (self::$pdoInstance === null) {
            $host    = env('DB_HOST', '127.0.0.1');
            $port    = (int) env('DB_PORT', 3306);
            $dbName  = env('DB_NAME', 'studenthub');
            $user    = env('DB_USER', 'root');
            $pass    = env('DB_PASS', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$pdoInstance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Log safe error without exposing credentials in web responses
                error_log("Database Connection Error (PDO): " . $e->getMessage());

                if (defined('APP_DEBUG') && APP_DEBUG === true) {
                    throw new RuntimeException("Database connection failed: " . htmlspecialchars($e->getMessage()));
                } else {
                    throw new RuntimeException("A database error occurred. Please contact the administrator.");
                }
            }
        }

        return self::$pdoInstance;
    }

    /**
     * Get singleton MySQLi (Object-Oriented) connection instance
     */
    public static function getMySQLi(): mysqli {
        if (self::$mysqliInstance === null || !@self::$mysqliInstance->ping()) {
            $host    = env('DB_HOST', '127.0.0.1');
            $port    = (int) env('DB_PORT', 3306);
            $dbName  = env('DB_NAME', 'studenthub');
            $user    = env('DB_USER', 'root');
            $pass    = env('DB_PASS', '');
            $charset = env('DB_CHARSET', 'utf8mb4');

            // Disable default reporting to manage errors cleanly
            mysqli_report(MYSQLI_REPORT_OFF);

            $mysqli = @new mysqli($host, $user, $pass, $dbName, $port);

            if ($mysqli->connect_errno) {
                $errMsg = $mysqli->connect_error;
                error_log("Database Connection Error (MySQLi): [{$mysqli->connect_errno}] {$errMsg}");

                if (defined('APP_DEBUG') && APP_DEBUG === true) {
                    throw new RuntimeException("MySQLi connection failed: " . htmlspecialchars($errMsg));
                } else {
                    throw new RuntimeException("A database error occurred. Please contact the administrator.");
                }
            }

            // Set character set to utf8mb4
            if (!$mysqli->set_charset($charset)) {
                error_log("Error loading character set {$charset}: " . $mysqli->error);
            }

            self::$mysqliInstance = $mysqli;
        }

        return self::$mysqliInstance;
    }

    /**
     * Procedural MySQLi connection helper
     */
    public static function getProcedural(): mysqli {
        $host    = env('DB_HOST', '127.0.0.1');
        $port    = (int) env('DB_PORT', 3306);
        $dbName  = env('DB_NAME', 'studenthub');
        $user    = env('DB_USER', 'root');
        $pass    = env('DB_PASS', '');
        $charset = env('DB_CHARSET', 'utf8mb4');

        mysqli_report(MYSQLI_REPORT_OFF);
        $link = @mysqli_connect($host, $user, $pass, $dbName, $port);

        if (!$link) {
            $errMsg = mysqli_connect_error();
            error_log("Procedural MySQLi Connection Error: " . $errMsg);
            if (defined('APP_DEBUG') && APP_DEBUG === true) {
                throw new RuntimeException("Procedural MySQLi connection failed: " . htmlspecialchars($errMsg));
            } else {
                throw new RuntimeException("A database error occurred. Please contact the administrator.");
            }
        }

        mysqli_set_charset($link, $charset);
        return $link;
    }
}

/**
 * Functional helpers for convenient access
 */
function getDB(): PDO {
    return Database::getConnection();
}

function getMySQLi(): mysqli {
    return Database::getMySQLi();
}

function getMySQLiProcedural(): mysqli {
    return Database::getProcedural();
}
