<?php

namespace App\Modules\ExpenseReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Exports\ExpenseReport\ExpenseReportSheet;
use App\Helpers\GenericData;
use App\Modules\ExpenseReport\ExpenseReportExportInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExportExpenseToExcel implements ExpenseReportExportInterface
{
    private ExportExpenseService $exportExpenseService;

    public function __construct(ExportExpenseService $exportExpenseService)
    {
        $this->exportExpenseService = $exportExpenseService;
    }

    public function export(GenericData $genericData, Collection $expenseData)
    {
        $data = $genericData->getData();
        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new ExpenseReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
