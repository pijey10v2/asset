<?php
require_once __DIR__ . '/../models/AssetModel.php';

class AssetController
{
    private $model;

    public function __construct()
    {
        $this->model = new AssetModel();
    }

    public function handleRequest($mode, $input)
    {
        // handle request based on mode
        switch ($mode) {
            case 'get_all_tables':
                return $this->getAllTables();
            case 'get_table_columns':
                return $this->getTableColumns($input);
            case 'get_excel_columns':
                return $this->getExcelColumns($input);
            case 'bulk_insert_asset_data':
                return $this->insertBulkAssetData($input);
            default:
            // invalid mode
                http_response_code(400);
                return [
                    "status" => "error",
                    "message" => "Invalid mode: $mode"
                ];
        }
    }

    private function getAllTables()
    {
        // get all tables from database
        return $this->model->getAllTables();
    }

    private function getTableColumns($input)
    { 
        // get columns of a table from database 
        $table = $input['asset_table_name'] ?? 'app_fd_inv_pavement';
        return $this->model->getTableColumns($table);
    }

    private function getExcelColumns($input)
    {
        // get columns of an excel file from rawfile mapping
        $rawMapping = isset($input['rawfile_mapping']) ? json_decode($input['rawfile_mapping'], true) : [];
        return $this->model->getExcelColumns($rawMapping);
    }

    private function insertBulkAssetData($input)
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
            $createdByName
        );

        logMessage("Bulk insert finished", "info", [
            'rows' => count($rows)
        ]);
    }



}
