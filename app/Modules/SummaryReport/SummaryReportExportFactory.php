<?php

namespace App\Modules\SummaryReport;

use App\Constants\ExportTypeConstant;
use App\Modules\SummaryReport\Exporters\ExportSummaryToExcel;
use App\Modules\SummaryReport\Exporters\ExportSummaryToPDF;
use Illuminate\Support\Facades\App;

class SummaryReportExportFactory
{
    /**
     * @param string $exportType
     * @return ExportSummaryToPDF|ExportSummaryToExcel|null
     */
    public static function make(string $exportType)
    {
        switch ($exportType) {
            case ExportTypeConstant::PDF:
                return App::make(ExportSummaryToPDF::class);
            case ExportTypeConstant::EXCEL:
                return App::make(ExportSummaryToExcel::class);
            default:
                return null;
        }
    }
}
