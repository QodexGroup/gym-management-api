<?php

namespace App\Modules\CollectionReport\Exporters;

use App\Dtos\Core\CollectionReportDto;
use App\Exports\Core\CollectionReportSheet;
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

    public function export(CollectionReportDto $collectionReportDto, Collection $collectionData)
    {
        $records = $this->exportCollectionService->transformData($collectionData);
        $summaryHeaderData = $this->exportCollectionService->getSummaryHeaderData($collectionData);
        $periodLabel = $collectionReportDto->getPeriodLabel() ?? "{$collectionReportDto->getDateFrom()} – {$collectionReportDto->getDateTo()}";
        $generatedAt = Carbon::now()->toDateTimeString();

        $summaryHeaderData['periodLabel'] = $periodLabel;
        $summaryHeaderData['generatedAt'] = $generatedAt;

        $export = new CollectionReportSheet($summaryHeaderData, $records);
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        return $content;
    }
}
