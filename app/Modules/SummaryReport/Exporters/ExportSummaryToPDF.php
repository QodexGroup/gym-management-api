<?php

namespace App\Modules\SummaryReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Constants\PdfExportConstant;
use App\Helpers\GenericData;
use App\Modules\SummaryReport\SummaryReportExportInterface;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportSummaryToPDF implements SummaryReportExportInterface
{
    private ExportSummaryService $exportSummaryService;

    public function __construct(ExportSummaryService $exportSummaryService)
    {
        $this->exportSummaryService = $exportSummaryService;
    }

    public function export(GenericData $genericData, Collection $billData, Collection $expenseData)
    {
        $expenseLength = $expenseData->count();
        if ($expenseLength > PdfExportConstant::MAXIMUM_EXPORT_LIMIT) {
            return response()->json([
                'error' => 'The report contains too many records. Please reduce the data set and try again.',
            ], 400);
        }

        $data = $genericData->getData();
        $records = $this->exportSummaryService->transformData($expenseData);
        $summaryHeaderData = $this->exportSummaryService->getSummaryHeaderData($billData, $expenseData);
        $headers = $this->exportSummaryService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $html = view('reports.summary-report', [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ])->render();

        $pdf = PdfFacade::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->stream('summary-report-' . $data->dateFrom . '.pdf');
    }
}
