<?php
/**
 * Database Configuration & Connection Helper
 * FinalNote Project
 */

function getDb(?string $overrideDb = null): PDO {
    static $pdo = null;
    
    $host = getenv('DB_HOST') ?: 'mysql-server';
    $port = getenv('DB_PORT') ?: '3306';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'root';
    $db   = $overrideDb ?: (getenv('DB_NAME') ?: 'finalnote');

    // Return cached instance if standard connection
    if ($pdo !== null && $overrideDb === null) {
        return $pdo;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $instance = new PDO($dsn, $user, $pass, $options);
        if ($overrideDb === null) {
            $pdo = $instance;
        }
        return $instance;
    } catch (PDOException $e) {
        // Fallback to product_management if finalnote not yet created
        if ($db === 'finalnote' && $overrideDb === null) {
            try {
                $fallbackDsn = "mysql:host={$host};port={$port};dbname=product_management;charset=utf8mb4";
                $pdo = new PDO($fallbackDsn, $user, $pass, $options);
                return $pdo;
            } catch (PDOException $ex) {
                // Ignore and throw original exception below
            }
        }
        error_log("Database connection error: " . $e->getMessage());
        throw $e;
    }
}
