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
            'modifiedByName',
            'c_element_id',
            //'c_model_element',
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

    public function insertAssetData($assetTable, $importBatchNo, $dataId, $rowData, $bimData)
    {
        // Verify that table exists
        if (!$this->tableExists($assetTable)) {
            http_response_code(404);
            echo json_encode([
                "status" => "error",
                "message" => "Target table '$assetTable' does not exist."
            ]);
            exit;
        }

        // If $rowData is array-of-rows, get the first one
        if (isset($rowData[0]) && is_array($rowData[0])) {
            $rowData = $rowData[0];
        }

        // Decode BIM data if JSON string
        if (is_string($bimData)) {
            $bimData = json_decode($bimData, true);
        }

        if (empty($rowData['id'])) {
            $rowData['id'] = $this->generateUUIDv4();
        }

        // Add import metadata fields (for tracking)
        $rowData["c_import_batch"] = $importBatchNo;
        $rowData["c_data_id"] = $dataId;

        // BIM Matching: match c_model_element with ps2, assign ElementId
        $cModelElement = $rowData['c_model_element'] ?? null;
        if ($cModelElement && is_array($bimData)) {
            foreach ($bimData as $bimRow) {
                if (($bimRow['ps2'] ?? null) == $cModelElement) {
                    $rowData['c_element_id'] = $bimRow['ElementId'];
                    //break;
                }
            }
        }

        // Auto-create missing columns
        foreach ($rowData as $col => $val) {
            $exists = $this->conn->query("SHOW COLUMNS FROM `$assetTable` LIKE '$col'");
            if (!$exists || $exists->num_rows === 0) {
                $this->conn->query("ALTER TABLE `$assetTable` ADD COLUMN `$col` VARCHAR(255) NULL");
            }
        }

        // Duplicate check
        $cModelElement = $this->conn->real_escape_string($rowData['c_model_element'] ?? '');
        $cImportBatch = $this->conn->real_escape_string($importBatchNo ?? '');
        $checkSql = "SELECT COUNT(*) AS total FROM `$assetTable`
                    WHERE c_model_element = '$cModelElement'
                    AND c_import_batch = '$cImportBatch'";
        $checkRes = $this->conn->query($checkSql);
        $exists = $checkRes && $checkRes->fetch_assoc()['total'] > 0;

        if ($exists) { // Record already exists

            // echo json_encode([
            //     "status" => "duplicate",
            //     "message" => "Record already exists. Skipping insert.",
            //     "criteria" => [
            //         "c_model_element" => $cModelElement,
            //         "c_import_batch" => $cImportBatch
            //     ]
            // ], JSON_PRETTY_PRINT);
            // exit;

            // Update c_element_id if BIM matched
            if (!empty($rowData['c_element_id'])) {
                $updateSql = "UPDATE `$assetTable`
                            SET c_element_id = '" . $conn->real_escape_string($rowData['c_element_id']) . "'
                            WHERE c_model_element = '" . $conn->real_escape_string($rowData['c_model_element']) . "'
                            AND c_import_batch = '" . $conn->real_escape_string($importBatchNo) . "'";
                $conn->query($updateSql);
            }

            echo json_encode([
                "status" => "duplicate",
                "message" => "Record already exists, updated BIM element if applicable.",
                "updated" => !empty($rowData['c_element_id']),
                "criteria" => [
                    "c_model_element" => $cModelElement,
                    "c_import_batch" => $cImportBatch
                ]
            ], JSON_PRETTY_PRINT);
            exit;

        }else{ // Record does not exist, insert it
            // Build Insert SQL dynamically
            $cols = [];
            $vals = [];
            foreach ($rowData as $col => $val) {
                $cols[] = "`$col`";
                $vals[] = "'" . $this->conn->real_escape_string($val) . "'";
            }

            $sql = "INSERT INTO `$assetTable` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";

            try {
                if ($this->conn->query($sql)) {
                    echo json_encode([
                        "status" => "success",
                        "message" => "Row inserted successfully.",
                        "table" => $assetTable,
                        "insert_id" => $this->conn->insert_id,
                        "data" => $rowData
                    ], JSON_PRETTY_PRINT);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        "status" => "error",
                        "message" => "Insert failed: " . $this->conn->error,
                        "sql" => $sql
                    ], JSON_PRETTY_PRINT);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => "Database exception: " . $e->getMessage()
                ]);
            }

            exit;
        }

    }
    public function updateExistingAssetData($rowData, $assetTable, $cModelElement, $cImportBatch)
    {
        $updates = [];
        foreach ($rowData as $col => $val) {
            if ($col !== 'id') {
                $updates[] = "`$col` = '" . $conn->real_escape_string($val) . "'";
            }
        }

        $updateSql = "UPDATE `$assetTable`
                    SET " . implode(", ", $updates) . "
                    WHERE c_model_element = '$cModelElement'
                    AND c_import_batch = '$cImportBatch'";

        if ($conn->query($updateSql)) {
            echo json_encode([
                "status" => "updated",
                "message" => "Existing record updated.",
                "criteria" => [
                    "c_model_element" => $cModelElement,
                    "c_import_batch" => $cImportBatch
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Update failed: " . $conn->error,
                "sql" => $updateSql
            ], JSON_PRETTY_PRINT);
        }
        exit;
    }
    public function generateUUIDv4() 
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
