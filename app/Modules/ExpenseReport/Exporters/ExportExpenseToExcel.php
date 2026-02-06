<?php

namespace App\Modules\ExpenseReport\Exporters;

use App\Dtos\Core\ExpenseReportDto;
use App\Exports\Core\ExpenseReportSheet;
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

    public function export(ExpenseReportDto $expenseReportDto, Collection $expenseData)
    {
        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $periodLabel = $expenseReportDto->getPeriodLabel() ?? "{$expenseReportDto->getDateFrom()} – {$expenseReportDto->getDateTo()}";
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new ExpenseReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
