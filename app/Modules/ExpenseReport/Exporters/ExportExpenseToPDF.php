<?php

namespace App\Modules\ExpenseReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Constants\PdfExportConstant;
use App\Helpers\GenericData;
use App\Modules\ExpenseReport\ExpenseReportExportInterface;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportExpenseToPDF implements ExpenseReportExportInterface
{
    private ExportExpenseService $exportExpenseService;

    public function __construct(ExportExpenseService $exportExpenseService)
    {
        $this->exportExpenseService = $exportExpenseService;
    }

    public function export(GenericData $genericData, Collection $expenseData)
    {
        $expenseLength = $expenseData->count();
        if ($expenseLength > PdfExportConstant::MAXIMUM_EXPORT_LIMIT) {
            return response()->json([
                'error' => 'The report contains too many records. Please reduce the data set and try again.',
            ], 400);
        }

        $data = $genericData->getData();
        $records = $this->exportExpenseService->transformData($expenseData);
        $summaryHeaderData = $this->exportExpenseService->getSummaryHeaderData($expenseData);
        $headers = $this->exportExpenseService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $html = view('reports.expense-report', [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ])->render();

        $pdf = PdfFacade::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->stream('expense-report-' . $data->dateFrom . '.pdf');
    }
}
