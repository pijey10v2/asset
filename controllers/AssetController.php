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
                http_response_code(400);
                return [
                    "status" => "error",
                    "message" => "Invalid mode: $mode"
                ];
        }
    }

    private function getAllTables()
    {
        return $this->model->getAllTables();
    }

    private function getTableColumns($input)
    {
        $table = $input['asset_table_name'] ?? 'app_fd_inv_pavement';
        return $this->model->getTableColumns($table);
    }

    private function getExcelColumns($input)
    {
        $rawMapping = isset($input['rawfile_mapping']) ? json_decode($input['rawfile_mapping'], true) : [];
        return $this->model->getExcelColumns($rawMapping);
    }

    private function insertAssetData($input)
    {
        $assetTable = $input["asset_table_name"] ?? null;
        $importBatchNo = $input["import_batch_no"] ?? null;
        $dataId = $input["data_id"] ?? null;
        $rowDataJson = $input["row_data"] ?? null;

        if (empty($assetTable) || empty($importBatchNo) || empty($dataId) || empty($rowDataJson)) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Invalid input"
            ];
        }

        $rowData = json_decode($rowDataJson, true);
        if (empty($rowData)) {
            http_response_code(400);
            return [
                "status" => "error",
                "message" => "Invalid row data"
            ];
        }

        return $this->model->insertAssetData($assetTable, $importBatchNo, $dataId, $rowData);
    }

}
