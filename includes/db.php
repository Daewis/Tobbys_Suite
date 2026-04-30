<?php
/**
 * Tobby's Suite - Database Connection
 */
require_once __DIR__ . '/config.php';

/**
 * Global helper to get the PDO instance
 * @return PDO
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . 
                   ";port=" . DB_PORT . 
                   ";dbname=" . DB_NAME . 
                   ";charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

        } catch (PDOException $e) {
            // Log the error and stop execution
            error_log("Tobby's Suite Connection Error: " . $e->getMessage());
            
            if (ini_get('display_errors')) {
                die("Connection failed: " . $e->getMessage());
            } else {
                die("A database error occurred. Please check the logs.");
            }
        }
    }

    return $pdo;
}

// Initialize connection immediately
$db = getDB();