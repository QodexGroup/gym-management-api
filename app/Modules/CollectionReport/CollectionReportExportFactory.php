<?php

namespace App\Modules\CollectionReport;

use App\Constants\ExportTypeConstant;
use App\Modules\CollectionReport\Exporters\ExportCollectionToExcel;
use App\Modules\CollectionReport\Exporters\ExportCollectionToPDF;
use Illuminate\Support\Facades\App;

class CollectionReportExportFactory
{
    /**
     * @param string $exportType
     * @return ExportCollectionToPDF|ExportCollectionToExcel|null
     */
    public static function make(string $exportType)
    {
        switch ($exportType) {
            case ExportTypeConstant::PDF:
                return App::make(ExportCollectionToPDF::class);
            case ExportTypeConstant::EXCEL:
                return App::make(ExportCollectionToExcel::class);
            default:
                return null;
        }
    }
}
