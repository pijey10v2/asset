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
            case 'insert_asset_data':
                return $this->insertAssetData($input);
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

    private function insertAssetData($input)
    {
        $assetTable = $input["asset_table_name"] ?? null;
        $importBatchNo = $input["import_batch_no"] ?? null;
        $dataId = $input["data_id"] ?? null;
        $rowDataJson = $input["row_data"] ?? null;
        $bimResultJson = $input["bim_results"] ?? null;
        
        // validate input
        if (empty($assetTable) || empty($importBatchNo) || empty($dataId) || empty($rowDataJson) || empty($bimResultJson)) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Invalid input"
            ];
        }

        // decode input
        $rowData = json_decode($rowDataJson, true);

        // validate row data
        if (empty($rowData)) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Invalid row data"
            ];
        }
 
        // decode BIM data
        $bimData = json_decode($bimResultJson, true);

        // validate BIM data
        if (empty($bimData)) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Invalid BIM data"
            ];
        }
 
        // insert data into database
        return $this->model->insertAssetData($assetTable, $importBatchNo, $dataId, $rowData, $bimData);
    }

}
