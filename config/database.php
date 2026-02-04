<?php
/**
 * Database Connection Configuration
 * Returns a shared MySQLi connection
 *
 * IMPORTANT:
 * - Do NOT load Dotenv here
 * - Do NOT echo JSON
 * - Do NOT call exit()
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: 3307;
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db   = getenv('DB_NAME') ?: 'jwb';

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    // Log only — let index.php decide response
    logMessage("Database connection failed", "error", [
        "host" => $host,
        "db"   => $db,
        "error"=> $conn->connect_error
    ]);

    // Throw exception so global handler catches it
    throw new Exception("Database connection failed");
}

$conn->set_charset('utf8mb4');

return $conn;
