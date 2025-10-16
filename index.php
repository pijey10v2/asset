<?php
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;


// Environment Setup
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

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

// Parse Input
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) $input = $_POST;

$mode = $input['mode'] ?? ''; //default is none

// Dispatch Controller
$controller = new AssetController();
$response = $controller->handleRequest($mode, $input);

// Send Response
echo json_encode($response, JSON_PRETTY_PRINT);
