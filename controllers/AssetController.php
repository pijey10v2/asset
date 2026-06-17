<?php
require_once __DIR__ . '/../models/AssetModel.php';

class AssetController
{
    private $model;

    public function __construct()
    {
        $this->model = new AssetModel();
    }

    public function handleRequest($mode, $input, $type)
    {
        // handle request based on mode
        switch ($mode) {
            case 'get_all_tables':
                return $this->getAllTables($type);
            case 'get_table_columns':
                return $this->getTableColumns($input, $type);
            case 'get_excel_columns':
                return $this->getExcelColumns($input);
            case 'get_hierarchylevel_1':
                return $this->getHierarchyLevel1($input, $type);
            case 'get_asset_hierarchy':
                $importBatch = $input['import_batch'] ?? '';
                return $this->getAssetHierarchy($input, $type, $importBatch);
            case 'get_asset_hierarchy_all':
                return $this->getAssetHierarchyAll($input, $type);
            case 'get_recent_importbatch_nos':
                return $this->getRecentImportBatchNos($input, $type);
            case 'update_hierarchy_mapping':
                return $this->updateHierarchyMapping($input);
            case 'bulk_insert_asset_data':
                return $this->insertBulkAssetData($input, $type);
            default:
            // invalid mode
                http_response_code(400);
                return [
                    "status" => "error",
                    "message" => "Invalid mode: $mode"
                ];
        }
    }

    private function getAllTables($type)
    {
        // get all tables from database
        return $this->model->getAllTables($type);
    }

    private function getTableColumns($input, $type)
    { 
        // get columns of a table from database 
        $table = $input['asset_table_name'] ?? 'app_fd_inv_pavement';
        return $this->model->getTableColumns($table, $type);
    }

    private function getExcelColumns($input)
    {
        // get columns of an excel file from rawfile mapping
        $rawMapping = isset($input['rawfile_mapping']) ? json_decode($input['rawfile_mapping'], true) : [];
        return $this->model->getExcelColumns($rawMapping);
    }

    private function getHierarchyLevel1($input, $type)
    { 
        // get table data - from app_fd_asset_hierarchy - level 1 (column: asset_name)
        $table = $input['asset_table_name'] ?? 'app_fd_asset_hierarchy';
        return $this->model->getHierarchyLevel1($table, $type);
    }

    private function getAssetHierarchy($input, $type, $importBatch)
    { 
        // get table data - from app_fd_asset_hierarchy
        $table = $input['asset_table_name'] ?? 'app_fd_asset_hierarchy';
        return $this->model->getAssetHierarchy($table, $type, $importBatch);
    }

    private function getAssetHierarchyAll($input, $type)
    { 
        // get table data - from app_fd_asset_hierarchy
        $table = $input['asset_table_name'] ?? 'app_fd_asset_hierarchy';
        return $this->model->getAssetHierarchyAll($table, $type);
    }

    private function getRecentImportBatchNos($input, $type)
    { 
        // get table data - from app_fd_asset_hierarchy - c_import_batch
        $table = $input['asset_table_name'] ?? 'app_fd_asset_hierarchy';
        return $this->model->getRecentImportBatchNos($table, $type);
    }

    private function insertBulkAssetData($input, $type)
    {
        if (empty($input['row_data'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing row_data']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'accepted',
            'message' => 'Insert queued'
        ]);

        // FORCE FLUSH (IIS SAFE)
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();

        ignore_user_abort(true);
        set_time_limit(0);
        ini_set('memory_limit', '10G');

        $assetTable     = $input["asset_table_name"];
        $importBatchNo  = $input["import_batch_no"];
        $dataId         = $input["data_id"];
        $rows           = json_decode($input["row_data"], true);
        $bimData        = json_decode($input["bim_results"], true);
        $createdBy      = $input["createdBy"];
        $createdByName  = $input["createdByName"];

        if (!is_array($rows) || empty($rows)) {
            logMessage("Invalid rows", "error");
            return;
        }

        // one bulk call only
        $this->model->insertAssetDataBulk(
            $assetTable,
            $importBatchNo,
            $dataId,
            $rows,
            $bimData,
            $createdBy,
            $createdByName,
            $type,
        );

        logMessage("Bulk insert finished", "info", [
            'rows' => count($rows)
        ]);
    }

    private function updateHierarchyMapping($input)
    {
        if (empty($input['mappings'])) {
            return [
                'status' => 'error',
                'message' => 'Missing mappings'
            ];
        }

        $mappings = json_decode(
            $input['mappings'],
            true
        );

        if (!is_array($mappings)) {
            return [
                'status' => 'error',
                'message' => 'Invalid mappings payload'
            ];
        }

        $updated = $this->model->updateHierarchyMapping(
            $mappings
        );

        return [
            'status' => $updated ? 'success' : 'error',
            'updated_rows' => count($mappings),
            'result' => $updated,
            'received' => count($mappings)
        ];
    }

}
