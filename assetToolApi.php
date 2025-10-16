<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment file
try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to load .env file: " . $e->getMessage()
    ]);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0); // disable direct fatal output
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "PHP error: $errstr",
        "file" => $errfile,
        "line" => $errline
    ]);
    exit;
});


// Database Connection

// Read environment variables safely
$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$user = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';
$database = $_ENV['DB_NAME'] ?? 'jwdb';

$appEnv = $_ENV['APP_ENV'] ?? 'production';
$appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

$conn = @new mysqli($host, $user, $password, $database, $port);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit;
}


// Input Handling

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) $input = $_POST;

$mode            = $input["mode"] ?? "process_mapping";
$assetTableName  = $input["asset_table_name"] ?? "app_fd_inv_pavement";
$importBatchNo   = $input["import_batch_no"] ?? null;
$dataId          = $input["data_id"] ?? null;
$bimMapping      = isset($input["bim_mapping"]) ? json_decode($input["bim_mapping"], true) : [];
$rawMapping      = isset($input["rawfile_mapping"]) ? json_decode($input["rawfile_mapping"], true) : [];


// Utility: Verify if table exists

function tableExists($conn, $tableName)
{
    $safeTable = $conn->real_escape_string($tableName);
    $check = $conn->query("SHOW TABLES LIKE '$safeTable'");
    return ($check && $check->num_rows > 0);
}


// Mode: Retrieve Table Columns

if ($mode === "get_table_columns") {

    if (!tableExists($conn, $assetTableName)) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Table '$assetTableName' does not exist in database '$database'."
        ]);
        exit;
    }

    $columns = [];
    $sql = "SHOW COLUMNS FROM $assetTableName";
    $result = $conn->query($sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Error retrieving table columns: " . $conn->error
        ]);
        exit;
    }

    while ($row = $result->fetch_assoc()) {
        $columns[] = $row["Field"];
    }

    echo json_encode([
        "status" => "success",
        "message" => "Columns retrieved successfully.",
        "table" => $assetTableName,
        "columns" => $columns
    ], JSON_PRETTY_PRINT);
    exit;
}


// Mode: Retrieve Excel Columns

if ($mode === "get_excel_columns") {
    if (empty($rawMapping) || !is_array($rawMapping)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Missing or invalid rawfile_mapping data. Please send valid JSON array."
        ]);
        exit;
    }

    $firstRow = $rawMapping[0] ?? [];
    $columns = array_keys($firstRow);

    echo json_encode([
        "status" => "success",
        "message" => "Raw Excel columns retrieved successfully.",
        "columns" => $columns
    ], JSON_PRETTY_PRINT);
    exit;
}

?>
