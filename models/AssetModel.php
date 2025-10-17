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

    public function getAllTables(){
        $sql = "SHOW TABLES LIKE 'app_fd_inv_%'";
        $result = $this->conn->query($sql);
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = $row["Tables_in_jwdb (app_fd_inv_%)"];
        }
        return ["status" => "success", "tables" => $tables];
    }

    public function getTableColumns($table)
    {
        if (!$this->tableExists($table)) {
            http_response_code(404);
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        $cols = [];
        $res = $this->conn->query("SHOW COLUMNS FROM $table");

        if (!$res) {
            http_response_code(500);
            return [
                "status" => "error",
                "message" => "Failed to fetch columns: " . $this->conn->error
            ];
        }

        // Columns to exclude
        $excluded = [
            'id',
            'dateCreated',
            'dateModified',
            'createdBy',
            'createdByName',
            'modifiedBy',
            'modifiedByName'
        ];

        while ($row = $res->fetch_assoc()) {
            $column = $row['Field'];

            // Only include if not in excluded list
            if (!in_array($column, $excluded, true)) {
                $cols[] = $column;
            }
        }

        return [
            "status" => "success",
            "message" => "Columns retrieved successfully (excluding system fields).",
            "table" => $table,
            "columns" => $cols,
            "excluded" => $excluded
        ];
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
}
