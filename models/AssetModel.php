<?php
class AssetModel
{
    private $conn;

    public function __construct()
    {
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $user = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_NAME'] ?? 'jwdb';

        $this->conn = @new mysqli($host, $user, $password, $database, $port);
        if ($this->conn->connect_error) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "DB connection failed: " . $this->conn->connect_error
            ]);
            exit;
        }
    }

    private function tableExists($table)
    {
        $safe = $this->conn->real_escape_string($table);
        $check = $this->conn->query("SHOW TABLES LIKE '$safe'");
        return ($check && $check->num_rows > 0);
    }

    public function getTableColumns($table)
    {
        if (!$this->tableExists($table)) {
            http_response_code(404);
            return ["status" => "error", "message" => "Table '$table' does not exist."];
        }

        $cols = [];
        $res = $this->conn->query("SHOW COLUMNS FROM $table");
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row["Field"];
        }

        return ["status" => "success", "columns" => $cols];
    }

    public function getExcelColumns($rawMapping)
    {
        if (empty($rawMapping) || !is_array($rawMapping)) {
            http_response_code(400);
            return ["status" => "error", "message" => "Invalid or missing rawfile_mapping"];
        }

        $first = $rawMapping[0] ?? [];
        return ["status" => "success", "columns" => array_keys($first)];
    }

    public function processMapping($table, $importBatchNo, $dataId, $bimMapping, $rawMapping)
    {
        if (!$this->tableExists($table)) {
            http_response_code(404);
            return ["status" => "error", "message" => "Table '$table' does not exist."];
        }

        $inserted = 0;
        $updated = 0;

        foreach ($rawMapping as $row) {
            $modelEl = $this->conn->real_escape_string($row["c_model_element"] ?? "");
            if (!$modelEl) continue;

            $elementId = $bimMapping[$modelEl] ?? null;

            $exists = $this->conn->query("SELECT id FROM $table WHERE c_model_element='$modelEl' LIMIT 1");

            if ($exists && $exists->num_rows > 0) {
                $sql = "UPDATE $table 
                        SET c_element_id=" . ($elementId ? "'$elementId'" : "NULL") . ",
                            c_import_batch='$importBatchNo',
                            c_data_id='$dataId'
                        WHERE c_model_element='$modelEl'";
                if ($this->conn->query($sql)) $updated++;
            } else {
                $uuid = $this->generateUUID();
                $cols = ["id", "c_model_element", "c_element_id", "c_import_batch", "c_data_id"];
                $vals = [
                    "'$uuid'", "'$modelEl'",
                    $elementId ? "'$elementId'" : "NULL",
                    "'$importBatchNo'", "'$dataId'"
                ];

                foreach ($row as $k => $v) {
                    if ($k === "c_model_element") continue;
                    $cols[] = $this->conn->real_escape_string($k);
                    $vals[] = "'" . $this->conn->real_escape_string($v) . "'";
                }

                $insertSQL = "INSERT INTO $table (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
                if ($this->conn->query($insertSQL)) $inserted++;
            }
        }

        return [
            "status" => "success",
            "message" => "Mapping processed successfully.",
            "summary" => ["inserted" => $inserted, "updated" => $updated]
        ];
    }

    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
