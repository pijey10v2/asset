<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

/**
 * ---------------------------------------------------------
 * Bootstrap / Environment
 * ---------------------------------------------------------
 */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

logMessage("API bootstrap initialized", "info");

/**
 * ---------------------------------------------------------
 * Headers / CORS
 * ---------------------------------------------------------
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    logMessage("CORS preflight handled", "info");
    http_response_code(200);
    exit;
}

/**
 * ---------------------------------------------------------
 * Error Handling (fatal only)
 * ---------------------------------------------------------
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }

    logMessage("PHP runtime error", "error", [
        "error" => $errstr,
        "file"  => basename($errfile),
        "line"  => $errline,
        "errno" => $errno
    ]);

    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Internal server error"
    ]);
    exit;
});

/**
 * ---------------------------------------------------------
 * Load Core Classes
 * ---------------------------------------------------------
 */
require_once __DIR__ . '/controllers/AssetController.php';
require_once __DIR__ . '/models/AssetModel.php';

logMessage("Core classes loaded", "info");

/**
 * ---------------------------------------------------------
 * Request Parsing
 * ---------------------------------------------------------
 */
$method = $_SERVER['REQUEST_METHOD'];
$input  = [];
$mode   = null;

logMessage("Incoming request", "info", [
    "method" => $method,
    "uri"    => $_SERVER['REQUEST_URI'] ?? null,
    "ip"     => $_SERVER['REMOTE_ADDR'] ?? null
]);

if ($method === 'GET') {
    $input = $_GET;
    $mode  = $_GET['mode'] ?? null;

    logMessage("Parsed GET request", "debug", [
        "mode" => $mode
    ]);
}
elseif ($method === 'POST') {

    $rawBody  = file_get_contents("php://input");
    $jsonBody = json_decode($rawBody, true);

    if (is_array($jsonBody)) {
        $input = $jsonBody;
        logMessage("Parsed POST JSON body", "debug");
    } elseif (!empty($_POST)) {
        $input = $_POST;
        logMessage("Parsed POST form-data", "debug", [
            "keys" => array_keys($_POST)
        ]);
    } else {
        logMessage("POST body empty", "warning");
    }

    $mode = $input['mode'] ?? ($_GET['mode'] ?? null);

    logMessage("POST mode resolved", "debug", [
        "mode" => $mode
    ]);
}
else {
    logMessage("Unsupported HTTP method", "error", [
        "method" => $method
    ]);

    http_response_code(405);
    echo json_encode([
        "status"  => "error",
        "message" => "Method not allowed"
    ]);
    exit;
}

/**
 * ---------------------------------------------------------
 * Validation
 * ---------------------------------------------------------
 */
if (!$mode) {
    logMessage("Validation failed: missing mode", "error", [
        "input_keys" => array_keys($input)
    ]);

    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Missing required parameter: mode"
    ]);
    exit;
}

/**
 * ---------------------------------------------------------
 * Dispatch Controller
 * ---------------------------------------------------------
 */
logMessage("Dispatching controller", "info", [
    "mode" => $mode
]);

$controller = new AssetController();
$response   = $controller->handleRequest($mode, $input);

/**
 * ---------------------------------------------------------
 * SQL-aware Response Logging
 * ---------------------------------------------------------
 */
if (isset($response['status']) && $response['status'] === 'error') {

    $logContext = [
        "mode"    => $mode,
        "message" => $response['message'] ?? null
    ];

    // Detect SQL errors explicitly
    if (isset($response['sql_error'])) {
        $logContext['sql_error'] = $response['sql_error'];
        $logContext['sql_errno'] = $response['sql_errno'] ?? null;

        // Log SQL only if explicitly provided (debug mode)
        if (!empty($response['sql'])) {
            $logContext['sql'] = $response['sql'];
        }

        logMessage("SQL execution error", "error", $logContext);
    } else {
        logMessage("Controller returned error", "error", $logContext);
    }

} else {
    logMessage("Request completed successfully", "info", [
        "mode" => $mode,
        "status" => $response['status'] ?? 'ok'
    ]);
}

/**
 * ---------------------------------------------------------
 * Output Response
 * ---------------------------------------------------------
 */
echo json_encode($response, JSON_PRETTY_PRINT);
