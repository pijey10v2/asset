<?php
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

// Environment Setup
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Set Headers
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // or specify your domain instead of '*'
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Handle CORS Preflight Requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error Handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "PHP error: $errstr",
        "file" => basename($errfile),
        "line" => $errline
    ]);
    exit;
});

// Load Core Classes
require_once __DIR__ . '/controllers/AssetController.php';
require_once __DIR__ . '/models/AssetModel.php';

// Parse Input & Route Mode
$method = $_SERVER['REQUEST_METHOD'];
$input = [];

// GET Request -> query string (e.g., ?mode=get_all_tables)
if ($method === 'GET') {
    $mode = $_GET['mode'] ?? null;
    $input = $_GET;
}
// POST Request -> JSON or form-data
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!$input) $input = $_POST;
    $mode = $input['mode'] ?? null;
}
else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Method not allowed: $method. Only GET and POST are supported."
    ]);
    exit;
}

// Validate Input
if (!$mode) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameter: mode"
    ]);
    exit;
}

// Dispatch Controller
$controller = new AssetController();
$response = $controller->handleRequest($mode, $input);

// Send Response 
echo json_encode($response, JSON_PRETTY_PRINT);
