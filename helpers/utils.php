<?php
/**
 * Utility functions used across the API
 */

/**
 * Generate a UUID v4 string
 * 
 * @return string UUIDv4
 */
function generateUUIDv4() 
{
    // Generate random bytes
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant 

    // Convert to UUIDv4 format
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Return a standardized JSON response
 */
function jsonResponse($status, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "status" => $status,
        "message" => $message,
        "data" => $data
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Sanitize string input for safe SQL usage
 */
function sanitize($conn, $value)
{
    return "'" . $conn->real_escape_string($value) . "'";
}
