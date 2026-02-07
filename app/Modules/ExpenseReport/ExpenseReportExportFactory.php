<?php

namespace App\Modules\ExpenseReport;

use App\Constants\ExportTypeConstant;
use App\Modules\ExpenseReport\Exporters\ExportExpenseToExcel;
use App\Modules\ExpenseReport\Exporters\ExportExpenseToPDF;
use Illuminate\Support\Facades\App;

class ExpenseReportExportFactory
{
    /**
     * @param string $exportType
     * @return ExportExpenseToPDF|ExportExpenseToExcel|null
     */
    public static function make(string $exportType)
    {
        switch ($exportType) {
            case ExportTypeConstant::PDF:
                return App::make(ExportExpenseToPDF::class);
            case ExportTypeConstant::EXCEL:
                return App::make(ExportExpenseToExcel::class);
            default:
                return null;
        }
    }
}
