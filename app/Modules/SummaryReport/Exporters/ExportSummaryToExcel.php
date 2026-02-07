<?php

namespace App\Modules\SummaryReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Exports\SummaryReport\SummaryReportSheet;
use App\Helpers\GenericData;
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

    public function export(GenericData $genericData, Collection $billData, Collection $expenseData)
    {
        $data = $genericData->getData();
        $records = $this->exportSummaryService->transformData($expenseData);
        $summaryHeaderData = $this->exportSummaryService->getSummaryHeaderData($billData, $expenseData);
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new SummaryReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
