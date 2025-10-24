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
 * Sanitize string input for safe SQL insert usage
 */
function sanitizeInsertSqlValue($conn, $value)
{
    return "'" . $conn->real_escape_string($value) . "'";
}

/**
 * Sanitize string input only
 */
function sanitize($conn, $value)
{
    return $conn->real_escape_string($value);
}

/**
 * Write a log entry to /logs/api.log with timestamp and context
 *
 * @param string $message The log message
 * @param string $level   The log level (info, warning, error)
 * @param array  $context Optional contextual data
 */
function logMessage($message, $level = 'info', $context = [])
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/api.log';
    $timestamp = date('Y-m-d H:i:s');

    $logEntry = sprintf(
        "[%s] [%s] %s%s",
        $timestamp,
        strtoupper($level),
        $message,
        !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
    );

    file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND);
}

