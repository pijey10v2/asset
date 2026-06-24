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
            //'c_asset_type',
            'c_is_parent',
            'c_parent_name',
            'c_full_asset_name',
            'c_full_asset_code',
            'c_item_no',
            'c_parent_id',
            'c_parent_code',
            'c_status',
            //manual input by user
            //'c_asset_name',
            'c_asset_code', 
            'c_sub_asset_code',
            'c_sub_asset_name',
            'c_sub_category_code',
            'c_type_asset_code',
            'c_type_asset_name',
            'c_category_code',  
            //additional for hierarchy
            'c_level1_id',
            'c_level2_id',
            'c_level3_id',
            'c_level4_id',
            'c_matched_level1_id',
            'c_matched_level2_id',
            'c_matched_level3_id',
            'c_matched_level4_id',
            'c_keywords',
            'c_level', //excluded for updating level in INT/Numbers
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

    public function getHierarchyLevel1($table, $type)
    {
        // Verify that table exists
        if (!$this->tableExists($table)) {
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        $sql = "SELECT id, c_asset_name FROM `$table` 
        WHERE 
        c_level = 1 AND
        c_asset_name IS NOT NULL AND 
        TRIM(c_asset_name) <> '' 
        ORDER BY c_asset_name ASC";
        $result = $this->conn->query($sql);

        if (!$result) {
            return [
                "status" => "error",
                "message" => $this->conn->error
            ];
        }

        $hierarchies = [];

        while ($row = $result->fetch_assoc()) {
            $hierarchies[] = [
                "id" => $row['id'],
                "c_asset_name" => $row['c_asset_name']
            ];
        }

        return [
            "status" => "success",
            "hierarchies" => $hierarchies
        ];
    }

    public function getHierarchyKeywords()
    {
        $sql = "
            SELECT
                id,
                c_asset_name,
                c_asset_code,
                c_keywords,
                c_parent_id,
                c_level
            FROM app_fd_asset_hierarchy
            WHERE c_asset_name IS NOT NULL
            AND TRIM(c_asset_name) <> ''
        ";

        $result = $this->conn->query($sql);

        $rows = [];

        while ($row = $result->fetch_assoc())
        {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getAssetHierarchy($table, $type, $importBatch)
    {
        if (!$this->tableExists($table)) {
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        $sql = "
            SELECT *
            FROM `$table`
            WHERE
                c_import_batch = '$importBatch'
                AND (
                    c_parent_id IS NULL
                    OR TRIM(c_parent_id) = ''
                )
            ORDER BY id DESC
        ";

        $result = $this->conn->query($sql);

        $assets = [];

        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        return [
            "status" => "success",
            "total_count" => $result->num_rows,
            "assets" => $assets
        ];
    }

    public function getAssetHierarchyAll($table, $type)
    {
        // Verify that table exists
        if (!$this->tableExists($table)) {
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        //select all data without condition
        $sql = "SELECT * FROM `$table` ORDER BY c_asset_name ASC";
        $result = $this->conn->query($sql);

        if (!$result) {
            return [
                "status" => "error",
                "message" => $this->conn->error
            ];
        }

        $assets = [];

        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        return [
            "status" => "success",
            "assets" => $assets
        ];
    }

    public function getRecentImportBatchNos($table, $type)
    {
        // Verify that table exists
        if (!$this->tableExists($table)) {
            return [
                "status" => "error",
                "message" => "Table '$table' does not exist."
            ];
        }

        //select all data without condition
        $sql = "
            SELECT
                c_import_batch,
                MAX(dateCreated) AS dateCreated
            FROM `$table`
            WHERE c_import_batch IS NOT NULL
            AND TRIM(c_import_batch) <> ''
            GROUP BY c_import_batch
            ORDER BY MAX(dateCreated) DESC
        ";
        $result = $this->conn->query($sql);

        if (!$result) {
            return [
                "status" => "error",
                "message" => $this->conn->error
            ];
        }

        $assets = [];

        while ($row = $result->fetch_assoc()) {
            $assets[] = $row;
        }

        return [
            "status" => "success",
            "assets" => $assets
        ];
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

        $hierarchyKeywords = $this->getHierarchyKeywords();

        $hierarchyLookup = [];

        foreach ($hierarchyKeywords as $item)
        {
            $hierarchyLookup[$item['id']] = $item;
        }

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

            $row['c_keywords'] =
            $this->buildAssetKeywords(
                $row
            );

            // $matched =
            // $this->autoMatchHierarchy(
            //     $row,
            //     $hierarchyKeywords
            // );

            // if ($matched)
            // {
            //     $path =
            //         $this->buildHierarchyPath(
            //             $matched,
            //             $hierarchyLookup
            //         );

            //     $row['c_matched_level1_id'] =
            //         $path[1] ?? null;

            //     $row['c_matched_level2_id'] =
            //         $path[2] ?? null;

            //     $row['c_matched_level3_id'] =
            //         $path[3] ?? null;

            //     $row['c_matched_level4_id'] =
            //         $path[4] ?? null;
            // }
            // else
            // {
            //     $row['c_matched_level1_id'] = null;
            //     $row['c_matched_level2_id'] = null;
            //     $row['c_matched_level3_id'] = null;
            //     $row['c_matched_level4_id'] = null;
            // }
            $assetText =
                strtolower(
                    $row['c_keywords']
                );

            $row['c_matched_level1_id'] =
                $this->findBestMatchByLevel(
                    $assetText,
                    $hierarchyKeywords,
                    1
                );

            $row['c_matched_level2_id'] =
                $this->findBestMatchByLevel(
                    $assetText,
                    $hierarchyKeywords,
                    2
                );

            $row['c_matched_level3_id'] =
                $this->findBestMatchByLevel(
                    $assetText,
                    $hierarchyKeywords,
                    3
                );

            $row['c_matched_level4_id'] =
                $this->findBestMatchByLevel(
                    $assetText,
                    $hierarchyKeywords,
                    4
                );

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
        if(empty($filteredRows))
        {
            return [
                "status" => "error",
                "message" => "No valid rows to insert"
            ];
        }

        $columns = array_keys($filteredRows[0]);
        $columnSql = '`' . implode('`,`', $columns) . '`';

        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $valuesSql = implode(',', array_fill(0, count($filteredRows), $placeholders));

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
        if (!$stmt)
        {
            logMessage(
                "Prepare failed",
                "error",
                [
                    "error" => $this->conn->error
                ]
            );

            return [
                "status" => "error",
                "message" => $this->conn->error
            ];
        }

        $types = '';
        $values = [];

        // Flatten values for binding
        foreach ($filteredRows as $row){
            foreach ($columns as $col) {
                $types .= 's'; // assuming all string types for simplicity
                $values[] = $row[$col] ?? null;
            }
        }

        logMessage(
            "Bind count",
            "info",
            [
                "types_length" => strlen($types),
                "values_count" => count($values)
            ]
        );

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$values);        
        // $this->conn->begin_transaction();
        // $stmt->execute();
        $result = $stmt->execute();
        // $this->conn->commit();
        try {
            // Execute inside transaction
            $this->conn->begin_transaction();

            logMessage(
                "About to execute insert",
                "info",
                [
                    "rows" => count($filteredRows),
                    "columns" => count($columns)
                ]
            );

            if(!$stmt->execute())
            {
                throw new Exception(
                    $stmt->error
                );
            }

            logMessage(
                "Execute result",
                "info",
                [
                    "success" => $result,
                    "error" => $stmt->error
                ]
            );

            $this->conn->commit();

            //sync to general asset table
            foreach($filteredRows as $row)
            {
                $this->syncToGeneralAssetTable(
                    $row
                );
            }

        }
        catch(Throwable $e)
        {
            $this->conn->rollback();

            logMessage(
                "Insert failed",
                "error",
                [
                    "message" => $e->getMessage()
                ]
            );

            throw $e;
        }

        // Log completion of bulk insert
        logMessage("Bulk insert completed", "info", [
            "table" => $assetTable,
            "rows" => count($rows)
        ]);

        // Return success
        return [
            "status" => "success",
            "inserted" => count($filteredRows)
        ];
    }

    public function updateHierarchyMapping($mappings)
    {
        try {

            $updatedRows = 0;

            foreach ($mappings as $mapping)
            {
                $currentId = $mapping['id'] ?? null;

                if (empty($currentId)) {
                    continue;
                }

                
                //Determine Parent
                $parentId = null;

                if (!empty($mapping['level4_id'])) {

                    $parentId = $mapping['level4_id'];

                } elseif (!empty($mapping['level3_id'])) {

                    $parentId = $mapping['level3_id'];

                } elseif (!empty($mapping['level2_id'])) {

                    $parentId = $mapping['level2_id'];

                } elseif (!empty($mapping['level1_id'])) {

                    $parentId = $mapping['level1_id'];
                }

                if (empty($parentId)) {
                    continue;
                }

                
                //Get Parent Item No
                $stmtParent = $this->conn->prepare("
                    SELECT
                        c_item_no,
                        c_level
                    FROM app_fd_asset_hierarchy
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$stmtParent) {
                    return [
                        'status' => 'error',
                        'message' => $this->conn->error
                    ];
                }

                $stmtParent->bind_param(
                    "s",
                    $parentId
                );

                $stmtParent->execute();

                $parentItemNo = null;
                $parentLevel  = null;

                $stmtParent->bind_result(
                    $parentItemNo,
                    $parentLevel
                );

                $stmtParent->fetch();
                $stmtParent->close();

                if (empty($parentItemNo)) {

                    $parentItemNo = '1';
                }

                
                //Get Last Child Under Parent
                $stmtLastChild = $this->conn->prepare("
                    SELECT c_item_no
                    FROM app_fd_asset_hierarchy
                    WHERE c_parent_id = ?
                    AND c_item_no IS NOT NULL
                    AND TRIM(c_item_no) <> ''
                    ORDER BY LENGTH(c_item_no) DESC,
                            c_item_no DESC
                    LIMIT 1
                ");

                if (!$stmtLastChild) {
                    return [
                        'status' => 'error',
                        'message' => $this->conn->error
                    ];
                }

                $stmtLastChild->bind_param(
                    "s",
                    $parentId
                );

                $stmtLastChild->execute();

                $lastItemNo = null;

                $stmtLastChild->bind_result(
                    $lastItemNo
                );

                $stmtLastChild->fetch();
                $stmtLastChild->close();

                
                //Generate New Item No
                if (empty($lastItemNo))
                {
                    $childSequence = 1;
                }
                else
                {
                    $parts = explode(
                        '.',
                        $lastItemNo
                    );

                    $lastSegment = end($parts);

                    $childSequence =
                        ((int)$lastSegment) + 1;

                    if ($childSequence < 1) {
                        $childSequence = 1;
                    }
                }

                $newItemNo =
                    $parentItemNo .
                    '.' .
                    $childSequence;

                
                //Generate Level
                $newLevel =
                    substr_count(
                        $newItemNo,
                        '.'
                    ) + 1;

                
                //Update
                //removed c_level = ?,
                $stmtUpdate = $this->conn->prepare("
                    UPDATE app_fd_asset_hierarchy
                    SET
                        c_item_no = ?,
                        c_level = ?,
                        c_parent_id = ?,
                        c_level1_id = ?,
                        c_level2_id = ?,
                        c_level3_id = ?,
                        c_level4_id = ?,
                        c_matched_level1_id = ?,
                        c_matched_level2_id = ?,
                        c_matched_level3_id = ?,
                        c_matched_level4_id = ?
                    WHERE id = ?
                ");

                if (!$stmtUpdate) {

                    return [
                        'status' => 'error',
                        'message' => $this->conn->error
                    ];
                }

                $level1Id = $mapping['level1_id'] ?? null;
                $level2Id = $mapping['level2_id'] ?? null;
                $level3Id = $mapping['level3_id'] ?? null;
                $level4Id = $mapping['level4_id'] ?? null;
                $matchedLevel1 = $level1Id;
                $matchedLevel2 = $level2Id;
                $matchedLevel3 = $level3Id;
                $matchedLevel4 = $level4Id;

                $stmtUpdate->bind_param(
                    //"sissssss",
                    "sissssssssss",
                    $newItemNo,
                    $newLevel,
                    $parentId,
                    $level1Id,
                    $level2Id,
                    $level3Id,
                    $level4Id,
                    $matchedLevel1,
                    $matchedLevel2,
                    $matchedLevel3,
                    $matchedLevel4,
                    $currentId
                );

                if (!$stmtUpdate->execute()) {

                    return [
                        'status' => 'error',
                        'message' => $stmtUpdate->error
                    ];
                }

                $stmtUpdate->close();

                $stmtGeneral =
                    $this->conn->prepare("
                        UPDATE app_fd_general_asset_table
                        SET
                            c_item_no = ?,
                            c_level = ?,
                            c_parent_id = ?,
                            c_level1_id = ?,
                            c_level2_id = ?,
                            c_level3_id = ?,
                            c_level4_id = ?,
                            c_matched_level1_id = ?,
                            c_matched_level2_id = ?,
                            c_matched_level3_id = ?,
                            c_matched_level4_id = ?
                        WHERE id = ?
                    ");

                $stmtGeneral->bind_param(
                    'sissssssssss',
                    $newItemNo,
                    $newLevel,
                    $parentId,
                    $level1Id,
                    $level2Id,
                    $level3Id,
                    $level4Id,
                    $matchedLevel1,
                    $matchedLevel2,
                    $matchedLevel3,
                    $matchedLevel4,
                    $currentId
                );

                $stmtGeneral->execute();
                $stmtGeneral->close();

                $updatedRows++;
            }

            logMessage(
                json_encode([
                    'updated_rows' => $updatedRows,
                    'received_rows' => count($mappings)
                ]),
                'info'
            );

            return [
                'status' => 'success',
                'updated_rows' => $updatedRows,
                'received_rows' => count($mappings)
            ];

        } catch (Throwable $e) {

            logMessage(
                'updateHierarchyMapping: ' .
                $e->getMessage(),
                'error'
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function autoMatchHierarchy(
        array $row,
        array $hierarchies
    )
    {
        $assetText =
            strtolower(
                $row['c_keywords'] ?? ''
            );

        if(empty($assetText)){
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach($hierarchies as $hierarchy)
        {
            $assetName =
                strtolower(
                    trim(
                        $hierarchy['c_asset_name']
                        ?? ''
                    )
                );

            $keywords =
                strtolower(
                    trim(
                        $hierarchy['c_keywords']
                        ?? ''
                    )
                );

            $score = 0;

            // asset name match
            if(
                !empty($assetName)
                &&
                str_contains(
                    $assetText,
                    $assetName
                )
            ){
                $score += 1000;
            }

            // keyword match
            if(!empty($keywords))
            {
                $score +=
                    $this->calculateMatchScore(
                        $assetText,
                        $keywords
                    );
            }

            if($score > $bestScore)
            {
                $bestScore = $score;
                $bestMatch = $hierarchy;
            }
        }

        logMessage(
            'BEST MATCH',
            'info',
            [
                'asset' => $row['c_model_element'] ?? '',
                'score' => $bestScore,
                'match' => $bestMatch['c_asset_name'] ?? null
            ]
        );

        // prevent weak matches
        if($bestScore < 10){
            return null;
        }

        return $bestMatch;
    }
    
    private function buildHierarchyPath(array $matched, array $hierarchyLookup)
    {
        $path = [];

        $currentId = $matched['id'] ?? null;

        while (
            $currentId &&
            isset($hierarchyLookup[$currentId])
        )
        {
            $row = $hierarchyLookup[$currentId];

            $level =
                (int)($row['c_level'] ?? 0);

            if (
                $level >= 1 &&
                $level <= 4
            ) {
                $path[$level] = $currentId;
            }

            $currentId =
                $row['c_parent_id'] ?? null;
        }

        ksort($path);

        return $path;
    }

    private function buildAssetKeywords(array $row)
    {
        $keywords = [];

        $excludeColumns = [

            'id',
            'dateCreated',
            'dateModified',
            'createdBy',
            'createdByName',
            'modifiedBy',
            'modifiedByName',

            'c_import_batch',
            'c_data_id',

            'c_matched_level1_id',
            'c_matched_level2_id',
            'c_matched_level3_id',
            'c_matched_level4_id',

            'c_level1_id',
            'c_level2_id',
            'c_level3_id',
            'c_level4_id'
        ];

        foreach($row as $column => $value)
        {
            if(in_array($column, $excludeColumns)){
                continue;
            }

            if(is_array($value) || is_object($value)){
                continue;
            }

            $value = trim((string)$value);

            if(empty($value)){
                continue;
            }

            if(strlen($value) < 3){
                continue;
            }

            $keywords[] =
                strtolower($value);
        }

        return implode(
            ',',
            array_unique($keywords)
        );
    }

    private function calculateMatchScore(
        string $assetText,
        string $hierarchyText
    ): int
    {
        $score = 0;

        $assetText =
            strtolower($assetText);

        $hierarchyText =
            strtolower($hierarchyText);

        $assetWords =
            array_unique(
                preg_split(
                    '/[\s,;_\-]+/',
                    $assetText
                )
            );

        $hierarchyWords =
            array_unique(
                preg_split(
                    '/[\s,;_\-]+/',
                    $hierarchyText
                )
            );

        // exact phrase match
        if(
            !empty($hierarchyText)
            &&
            str_contains(
                $assetText,
                $hierarchyText
            )
        ){
            $score += 100;
        }

        foreach($hierarchyWords as $word)
        {
            if(strlen($word) < 3){
                continue;
            }

            // exact keyword
            if(
                in_array(
                    $word,
                    $assetWords
                )
            ){
                $score += 20;
            }

            // partial keyword
            elseif(
                str_contains(
                    $assetText,
                    $word
                )
            ){
                $score += 5;
            }
        }

        return $score;
    }

    private function findBestMatchByLevel(
        string $assetText,
        array $hierarchies,
        int $level
    )
    {
        $bestId = null;
        $bestScore = 0;

        foreach($hierarchies as $hierarchy)
        {
            if(
                (int)$hierarchy['c_level']
                !== $level
            ){
                continue;
            }

            $searchText =
                strtolower(
                    ($hierarchy['c_asset_name'] ?? '')
                    .' '.
                    ($hierarchy['c_keywords'] ?? '')
                );

            $score =
                $this->calculateMatchScore(
                    $assetText,
                    $searchText
                );

            if($score > $bestScore)
            {
                $bestScore = $score;
                $bestId = $hierarchy['id'];
            }
        }

        return $bestId;
    }

    private function syncToGeneralAssetTable(array $row)
    {
        $table = 'app_fd_general_asset_table';

        $columns = array_keys($row);

        $columnSql =
            '`' .
            implode('`,`', $columns) .
            '`';

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($columns),
                    '?'
                )
            );

        $updateSql =
            implode(
                ',',
                array_map(
                    fn($col) =>
                        "`$col` = VALUES(`$col`)",
                    $columns
                )
            );

        $sql = "
            INSERT INTO `$table`
            ($columnSql)
            VALUES ($placeholders)
            ON DUPLICATE KEY UPDATE
            $updateSql
        ";

        $stmt = $this->conn->prepare($sql);

        $types =
            str_repeat(
                's',
                count($columns)
            );

        $values = [];

        foreach($columns as $col)
        {
            $values[] =
                $row[$col] ?? null;
        }

        $stmt->bind_param(
            $types,
            ...$values
        );

       if(!$stmt->execute())
        {
            logMessage(
                "General Asset Sync Failed",
                "error",
                [
                    "error" => $stmt->error
                ]
            );
        }
    }

}
