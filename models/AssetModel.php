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

    public function getAllTables($type)
    {

        if($type == 'cobie'){
            // Explicit whitelist mapping (Label => Table Name)
            $allowedTables = [
                'Cobie Table'    => 'app_fd_asset_hierarchy',
            ];
        }else{
            // Explicit whitelist mapping (Label => Table Name)
            $allowedTables = [
                'Network'        => 'app_fd_network',
                'Bridge'         => 'app_fd_inv_bridge',
                'Culvert'        => 'app_fd_inv_culvert',
                'Drainage'       => 'app_fd_inv_drainage',
                'Pavement'       => 'app_fd_inv_pavement',
                'Road Furniture' => 'app_fd_inv_furniture',
                'Slope'          => 'app_fd_inv_slope'
            ];
        }
        

        $sql = "SHOW TABLES LIKE 'app_fd_%'";
        $result = $this->conn->query($sql);

        if (!$result) {
            logMessage("Failed to retrieve tables", "error", ["error" => $this->conn->error]);
            return [
                "status" => "error",
                "message" => "Database query failed: " . $this->conn->error
            ];
        }

        $existingTables = [];
        while ($row = $result->fetch_array()) {
            $existingTables[] = $row[0];
        }

        // Filter only the tables that exist and are whitelisted
        $filteredTables = [];
        foreach ($allowedTables as $label => $tableName) {
            if (in_array($tableName, $existingTables, true)) {
                $filteredTables[] = [
                    "label" => "{$label} - {$tableName}",
                    "table" => $tableName
                ];
            }
        }

        logMessage("Whitelisted tables retrieved", "info", ["count" => count($filteredTables)]);

        return [
            "status" => "success",
            "tables" => $filteredTables
        ];
    }


    public function getTableColumns($table, $type)
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
            //default
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
            //cobie
            'c_auto_id',
            'c_parent_asset_id',
            'c_asset_type',
            'c_is_parent',
            'c_parent_name',
            'c_full_asset_name',
            'c_full_asset_code',
            'c_item_no',
            'c_parent_id',
            'c_parent_code',
            'c_status',
        ];

        if ($type === 'cobie') {
            $excluded[] = 'c_section';
            $excluded[] = 'c_division';
        }

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

    public function insertAssetDataBulk($assetTable, $importBatchNo, $dataId, array $rows, $bimData, $createdBy, $createdByName, $type) 
    {
        // Log start of bulk insert
        logMessage("Bulk insert started", "info", [
            "table" => $assetTable,
            "rows" => count($rows)
        ]);

        // Validate rows
        if (empty($rows)) {
            return ["status" => "error", "message" => "No data to insert"];
        }

        // Validate table
        if (!$this->tableExists($assetTable)) {
            http_response_code(404);
            return ["status" => "error", "message" => "Target table does not exist"];
        }

        // Decode BIM data
        if (is_string($bimData)) {
            $bimData = json_decode($bimData, true);
        }

        // Build BIM lookup (FAST)
        $bimLookup = [];
        if (is_array($bimData)) {
            foreach ($bimData as $bim) {
                if (!empty($bim['ps2'])) {
                    $bimLookup[$bim['ps2']] = $bim['ElementId'] ?? null;
                }
            }
        }

        // Fetch existing columns ONCE
        $existingColumns = [];
        $res = $this->conn->query("SHOW COLUMNS FROM `$assetTable`");
        while ($col = $res->fetch_assoc()) {
            $existingColumns[$col['Field']] = true;
        }

        // Normalize rows + collect missing columns
        $missingColumns = [];

        $filteredRows = []; // will store only valid rows

        foreach ($rows as &$row) {

            $row['id'] = generateUUIDv4();
            $row['dateCreated'] = date('Y-m-d H:i:s');
            $row['dateModified'] = date('Y-m-d H:i:s');
            $row['createdBy'] = $createdBy;
            $row['createdByName'] = $createdByName;
            $row['modifiedBy'] = $createdBy;
            $row['modifiedByName'] = $createdByName;

            $row['c_import_batch'] = $importBatchNo;
            $row['c_data_id'] = $dataId;

            // BIM match
            $row['c_element_id'] = $bimLookup[$row['c_model_element'] ?? ''] ?? null;


            // Check for required fields
            $requiredFields = [];

            if ($type !== 'cobie') {
                $requiredFields = [
                    'c_section',
                    'c_division',
                ];
            }

            $hasEmptyRequiredField = false;

            foreach ($requiredFields as $field) {

                if (
                    !isset($row[$field]) ||
                    $row[$field] === null ||
                    trim($row[$field]) === '' ||
                    trim($row[$field]) === 'NULL'
                ) {
                    $hasEmptyRequiredField = true;
                    break;
                }
            }

            // Skip inserting this row
            if ($hasEmptyRequiredField) {
                continue;
            }

            // Keep valid row
            $filteredRows[] = $row;


            // Collect missing columns
            foreach ($row as $col => $val) {
                if (!isset($existingColumns[$col])) {
                    $missingColumns[$col] = true;
                }
            }
        }
        unset($row);

        // Add missing columns ONCE
        if (!empty($missingColumns)) {
            $alter = [];
            foreach (array_keys($missingColumns) as $col) {
                $alter[] = "ADD COLUMN `$col` VARCHAR(255) NULL";
            }
            $this->conn->query(
                "ALTER TABLE `$assetTable` " . implode(', ', $alter)
            );
        }

        // Build BULK UPSERT SQL
        $columns = array_keys($rows[0]);
        $columnSql = '`' . implode('`,`', $columns) . '`';

        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(',', array_fill(0, count($rows), $placeholders));

        $updateSql = implode(', ', array_map(
            fn($col) => "`$col` = VALUES(`$col`)",
            $columns
        ));

        // Final SQL
        $sql = "
            INSERT INTO `$assetTable` ($columnSql)
            VALUES $valuesSql
            ON DUPLICATE KEY UPDATE $updateSql
        ";

        // Prepare + bind
        $stmt = $this->conn->prepare($sql);

        $types = '';
        $values = [];

        // Flatten values for binding
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $types .= 's'; // assuming all string types for simplicity
                $values[] = $row[$col] ?? null;
            }
        }

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$values);

        // Execute inside transaction
        $this->conn->begin_transaction();
        $stmt->execute();
        $this->conn->commit();

        // Log completion of bulk insert
        logMessage("Bulk insert completed", "info", [
            "table" => $assetTable,
            "rows" => count($rows)
        ]);

        // Return success
        return [
            "status" => "success",
            "inserted" => count($rows)
        ];
    }

}
