<?php
/**
 * Database Connection Configuration
 * 
 * This file initializes and returns a shared MySQL connection
 * using environment variables from the .env file.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load .env file (only once globally)
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database credentials from .env
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3307';
$user = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';
$database = $_ENV['DB_NAME'] ?? 'jwdb';

// Create connection
$conn = @new mysqli($host, $user, $password, $database, $port);

// Check for connection error
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit;
}

// Optional: set charset
$conn->set_charset('utf8mb4');

// Return connection
return $conn;
