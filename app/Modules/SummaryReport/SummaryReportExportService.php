<?php

namespace App\Modules\SummaryReport;

use App\Constants\ExportTypeConstant;
use App\Helpers\GenericData;
use App\Repositories\Common\ExpenseRepository;
use App\Repositories\Core\CustomerBillRepository;

class SummaryReportExportService
{
    private CustomerBillRepository $customerBillRepository;
    private ExpenseRepository $expenseRepository;

    /**
     * @param CustomerBillRepository $customerBillRepository
     * @param ExpenseRepository $expenseRepository
     */
    public function __construct(
        CustomerBillRepository $customerBillRepository,
        ExpenseRepository $expenseRepository
    ) {
        $this->customerBillRepository = $customerBillRepository;
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

        $exporter = SummaryReportExportFactory::make($exportType);
        if (!$exporter) {
            return null;
        }
        $billData = $this->customerBillRepository->getForExport(
            $genericData->userData->account_id,
            $data->dateFrom,
            $data->dateTo
        );
        $expenseData = $this->expenseRepository->getForExport(
            $genericData->userData->account_id,
            $data->dateFrom,
            $data->dateTo
        );
        return $exporter->export($genericData, $billData, $expenseData);
    }
}
