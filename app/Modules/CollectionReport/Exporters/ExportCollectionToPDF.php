<?php

namespace App\Modules\CollectionReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Constants\PdfExportConstant;
use App\Helpers\GenericData;
use App\Modules\CollectionReport\CollectionReportExportInterface;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportCollectionToPDF implements CollectionReportExportInterface
{
    private ExportCollectionService $exportCollectionService;

    public function __construct(ExportCollectionService $exportCollectionService)
    {
        $this->exportCollectionService = $exportCollectionService;
    }

    public function export(GenericData $genericData, Collection $collectionData)
    {
        $collectionLength = $collectionData->count();
        if ($collectionLength > PdfExportConstant::MAXIMUM_EXPORT_LIMIT) {
            return response()->json([
                'error' => 'The report contains too many records. Please reduce the data set and try again.',
            ], 400);
        }

        $data = $genericData->getData();
        $records = $this->exportCollectionService->transformData($collectionData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($collectionData);
        $headers = $this->exportCollectionService->getHeaders();
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $html = view('reports.collection-report', [
            'summaryHeaderData' => $summaryHeaderData,
            'headers' => $headers,
            'records' => $records,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
        ])->render();

        $pdf = PdfFacade::loadHTML($html)->setPaper('a4', 'landscape');

        return $pdf->stream('collection-report-' . $data->dateFrom . '.pdf');
    }
}
