<?php

namespace App\Modules\SummaryReport\Exporters;

use App\Dtos\Core\SummaryReportDto;
use App\Exports\Core\SummaryReportSheet;
use App\Modules\SummaryReport\SummaryReportExportInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExportSummaryToExcel implements SummaryReportExportInterface
{
    private ExportSummaryService $exportSummaryService;

    public function __construct(ExportSummaryService $exportSummaryService)
    {
        $this->exportSummaryService = $exportSummaryService;
    }

    public function export(SummaryReportDto $summaryReportDto, Collection $billData, Collection $expenseData)
    {
        $records = $this->exportSummaryService->transformData($expenseData);
        $summaryHeaderData = $this->exportSummaryService->getSummaryHeaderData($billData, $expenseData);
        $periodLabel = $summaryReportDto->getPeriodLabel() ?? "{$summaryReportDto->getDateFrom()} – {$summaryReportDto->getDateTo()}";
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new SummaryReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
