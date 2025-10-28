<?php
require_once __DIR__ . '/../helpers/utils.php';

class AssetModel
{
    private $conn;

    public function __construct()
    {
        // Include and reuse shared DB connection
        $this->conn = require __DIR__ . '/../config/database.php';
        logMessage("Database connection initialized", "info");
    }

    private function tableExists($table)
    {   
        // Escape table name to prevent SQL injection attacks
        $safe = sanitize($this->conn, $table);
        // Check if table exists
        $check = $this->conn->query("SHOW TABLES LIKE '$safe'");
        // Return true if table exists, false otherwise
        return ($check && $check->num_rows > 0);
    }

    public function getAllTables()
    {
        logMessage("Fetching all tables", "info");
        // Get all tables starting with "app_fd_inv_"
        $sql = "SHOW TABLES LIKE 'app_fd_inv_%'";
        $result = $this->conn->query($sql);

        if (!$result) {
            logMessage("Failed to retrieve tables", "error", ["error" => $this->conn->error]);
            return ["status" => "error", "message" => $this->conn->error];
        }

        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = $row["Tables_in_jwdb (app_fd_inv_%)"];
        }

        logMessage("Tables fetched successfully", "info", ["count" => count($tables)]);

        // Return array of table names
        return ["status" => "success", "tables" => $tables];
    }

    public function getTableColumns($table)
    {
        // Verify that table exists 
        if (!$this->tableExists($table)) {
            http_response_code(404);
            logMessage("Table '$table' does not exist.", "error", ["error" => $this->conn->error]);
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        $cols = [];
        $res = $this->conn->query("SHOW COLUMNS FROM $table");

        // Check if query was successful
        if (!$res) {
            http_response_code(500);
            logMessage("Failed to fetch columns: " . $this->conn->error, "error", ["error" => $this->conn->error]);
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
            'c_fullname',
            'c_username',
            'c_package_id',
            'c_project_owner',
            'c_project_id',
            'c_data_id',
            'c_audit',
            'c_id_counter',
            'c_action',
            'c_id_type',
            'c_import_batch',
            'c_package_uuid',
        ];

        // Loop through columns and add to array
        while ($row = $res->fetch_assoc()) {
            $column = $row['Field'];

            // Only include if not in excluded list
            if (!in_array($column, $excluded, true)) {
                $cols[] = $column;
            }
        }
        logMessage("Tables columns fetched successfully", "info", ["cols" => $cols]);
        // Return array of column names
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
        // Verify that rawfile_mapping is an array
        if (empty($rawMapping) || !is_array($rawMapping)) {
            http_response_code(400);
            logMessage("Invalid or missing rawfile_mapping", "error", ["error" => "Invalid or missing rawfile_mapping"]);
            return ["status" => "error", "message" => "Invalid or missing rawfile_mapping"];
        }

        $first = $rawMapping[0] ?? []; 
        logMessage("Excel columns fetched successfully", "info", ["cols" => array_keys($first)]);
        // Verify that first row is an associative array
        return ["status" => "success", "columns" => array_keys($first)];
    }

    public function insertAssetData($assetTable, $importBatchNo, $dataId, $rowData, $bimData, $createdBy, $createdByName)
    {
        logMessage("Insert operation started", "info", ["table" => $assetTable, "data_id" => $dataId]);

        // Verify that table exists
        if (!$this->tableExists($assetTable)) {
            http_response_code(404);
            logMessage("Target table '$assetTable' does not exist.", "error", ["error" => "Target table '$assetTable' does not exist."]);
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

        // Generate UUIDv4 if missing
        if (empty($rowData['id'])) {
            $rowData['id'] = generateUUIDv4();
        }

        $rowData['dateCreated'] = date('Y-m-d h:i:s');
        $rowData['dateModified'] = date('Y-m-d h:i:s');
        $rowData['createdBy'] = $createdBy;
        $rowData['createdByName'] = $createdByName;
        $rowData['modifiedBy'] = $createdBy;
        $rowData['modifiedByName'] = $createdByName;

        // Add import metadata fields (for tracking)
        $rowData["c_import_batch"] = $importBatchNo;
        $rowData["c_data_id"] = $dataId;

        // BIM Matching: match c_model_element with ps2, assign ElementId
        $cModelElement = $rowData['c_model_element'] ?? null;
        $matched = false;

        if ($cModelElement && is_array($bimData)) {
            foreach ($bimData as $bimRow) {
                if (($bimRow['ps2'] ?? null) == $cModelElement) {
                    $rowData['c_element_id'] = $bimRow['ElementId'];
                    $matched = true;
                    break;
                }
            }
        }

        // If no match found, set c_element_id = NULL (or 'N/A')
        if (!$matched) {
            $rowData['c_element_id'] = null; // or 'N/A'
        }

        // Auto-create missing columns
        foreach ($rowData as $col => $val) {
            $exists = $this->conn->query("SHOW COLUMNS FROM `$assetTable` LIKE '$col'");
            if (!$exists || $exists->num_rows === 0) {
                $this->conn->query("ALTER TABLE `$assetTable` ADD COLUMN `$col` VARCHAR(255) NULL");
            }
        }

        // Duplicate check
        $cModelElement = sanitize($this->conn, $rowData['c_model_element'] ?? '');
        $cImportBatch = sanitize($this->conn, $importBatchNo ?? '');

        // Check if record already exists
        $checkSql = "SELECT COUNT(*) AS total FROM `$assetTable` WHERE c_model_element = '$cModelElement' AND c_import_batch = '$cImportBatch'";
        $checkRes = $this->conn->query($checkSql);
        $exists = $checkRes && $checkRes->fetch_assoc()['total'] > 0; // Check if record exists

        if ($exists) { // Record already exists, skip insert

            // Update existing records based on matched ids
            $this->updateAllExistingAssetData($rowData, $assetTable, $cModelElement, $cImportBatch);

        }else{ // Record does not exist, insert it 

            // Build Insert SQL dynamically
            $cols = [];
            $vals = [];
            foreach ($rowData as $col => $val) {
                $cols[] = "`$col`";
                if (is_null($val) || $val === '') {
                    $vals[] = "NULL";
                } else {
                    $vals[] = sanitizeInsertSqlValue($this->conn, $val);
                }
            }

            // Build SQL
            $sql = "INSERT INTO `$assetTable` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";

            // Execute SQL
            try {
                if ($this->conn->query($sql)) {
                    $logMessage = "Row inserted successfully.";
                    $logType = "info";
                    echo json_encode([
                        "status" => "success",
                        "message" => $logMessage,
                        "table" => $assetTable,
                        "data" => $rowData
                    ], JSON_PRETTY_PRINT);
                } else {
                    http_response_code(500);
                    $logMessage = "Insert failed: " . $this->conn->error;
                    $logType = "error";
                    echo json_encode([
                        "status" => $logType,
                        "message" => $logMessage,
                        "sql" => $sql
                    ], JSON_PRETTY_PRINT);
                }
            } catch (Exception $e) {
                http_response_code(500);
                $logMessage = "Database exception: " . $e->getMessage();
                $logType = "error";
                echo json_encode([
                    "status" => $logType,
                    "message" => $logMessage
                ]);
            }

            logMessage($logMessage, $logType, ["table" => $assetTable, "data_id" => $dataId]);

            exit;
        }

    }
    public function updateAllExistingAssetData($rowData, $assetTable, $cModelElement, $cImportBatch)
    {
        logMessage("Existing record started updating.", "info", ["table" => $assetTable, "c_model_element" => $cModelElement]);

        $updates = [];
        foreach ($rowData as $col => $val) {
            if ($col !== 'id') {
                $updates[] = "`$col` = '" . sanitize($this->conn, $val) . "'";
            }
        }

        // Build SQL 
        $updateSql = "UPDATE `$assetTable`
                    SET " . implode(", ", $updates) . "
                    WHERE c_model_element = '$cModelElement'
                    AND c_import_batch = '$cImportBatch'";

        // Execute SQL
        if ($this->conn->query($updateSql)) {
            logMessage("Existing record updated.", "info", ["table" => $assetTable, "c_model_element" => $cModelElement]);
            echo json_encode([
                "status" => "updated",
                "message" => "Existing record updated.",
                "criteria" => [
                    "c_model_element" => $cModelElement,
                    "c_import_batch" => $cImportBatch
                ]
            ], JSON_PRETTY_PRINT);
        } else {
            logMessage("Update failed: " . $this->conn->error, "error", ["error" => "Update failed: " . $this->conn->error]);
            echo json_encode([
                "status" => "error",
                "message" => "Update failed: " . $this->conn->error,
                "sql" => $updateSql
            ], JSON_PRETTY_PRINT);
        }
        exit;
    }
}
