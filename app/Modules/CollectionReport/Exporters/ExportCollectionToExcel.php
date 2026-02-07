<?php

namespace App\Modules\CollectionReport\Exporters;

use App\Constants\DateFormatConstant;
use App\Exports\CollectionReport\CollectionReportSheet;
use App\Helpers\GenericData;
use App\Modules\CollectionReport\CollectionReportExportInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExportCollectionToExcel implements CollectionReportExportInterface
{
    private ExportCollectionService $exportCollectionService;

    public function __construct(ExportCollectionService $exportCollectionService)
    {
        $this->exportCollectionService = $exportCollectionService;
    }

    public function export(GenericData $genericData, Collection $collectionData)
    {
        $data = $genericData->getData();
        $records = $this->exportCollectionService->transformData($collectionData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($collectionData);
        $periodLabel = $data->periodLabel ?? $data->dateFrom . DateFormatConstant::DATE_RANGE_SEPARATOR . $data->dateTo;
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new CollectionReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
