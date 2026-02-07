<?php

namespace App\Modules\ExpenseReport;

use App\Constants\ExportTypeConstant;
use App\Helpers\GenericData;
use App\Repositories\Common\ExpenseRepository;

class ExpenseReportExportService
{
    private ExpenseRepository $expenseRepository;

    /**
     * @param ExpenseRepository $expenseRepository
     */
    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    /**
     * @param GenericData $genericData
     *
     * @return mixed|null
     */
    public function export(GenericData $genericData)
    {
        $data = $genericData->getData();
        $exportType = ExportTypeConstant::normalizeFormat($data->exportType ?? ExportTypeConstant::PDF);

        $exporter = ExpenseReportExportFactory::make($exportType);
        if (!$exporter) {
            return null;
        }
        $expenseData = $this->expenseRepository->getForExport(
            $genericData->userData->account_id,
            $data->dateFrom,
            $data->dateTo
        );
        return $exporter->export($genericData, $expenseData);
    }
}
