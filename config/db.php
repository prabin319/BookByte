<?php
// config/db.php

/**
 * Returns a shared PDO connection.
 * Adjust $dbName, $username, $password to match your MySQL.
 */
function getDBConnection()
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    // 🔧 CHANGE THESE IF NEEDED
    $host     = 'localhost';
    $dbName   = 'bookbyte_lms';   // <--- use your actual database name
    $username = 'root';       // XAMPP default
    $password = '';           // XAMPP default (empty)

    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Friendly error page instead of ugly fatal dump
        http_response_code(500);

        // Build CSS path to /public/assets/style.css
        $publicBasePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $cssPath        = $publicBasePath . '/assets/style.css';

        echo "<!DOCTYPE html>";
        echo "<html lang='en'><head><meta charset='UTF-8'>";
        echo "<title>Database connection error</title>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<link rel='stylesheet' href='" . htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') . "'>";
        echo "</head>";
        echo "<body class='body-auth'>";
        echo "<div class='auth-page'><div class='auth-card'>";
        echo "<h1>Database connection error</h1>";
        echo "<p>We could not connect to the BookByte database. Please check your database name, username, and password in <code>config/db.php</code>.</p>";
        echo "<pre style='white-space:pre-wrap;font-size:12px;color:#b91c1c;background:#fef2f2;padding:8px;border-radius:8px;margin-top:12px;'>";
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo "</pre>";
        echo "</div></div></body></html>";
        exit;
    }
}
