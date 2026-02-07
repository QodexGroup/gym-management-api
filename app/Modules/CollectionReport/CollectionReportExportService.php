<?php

namespace App\Modules\CollectionReport;

use App\Constants\ExportTypeConstant;
use App\Helpers\GenericData;
use App\Repositories\Core\CustomerBillRepository;

class CollectionReportExportService
{
    private CustomerBillRepository $customerBillRepository;

    /**
     * @param CustomerBillRepository $customerBillRepository
     */
    public function __construct(CustomerBillRepository $customerBillRepository)
    {
        $this->customerBillRepository = $customerBillRepository;
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

        $exporter = CollectionReportExportFactory::make($exportType);
        if (!$exporter) {
            return null;
        }
        $collectionData = $this->customerBillRepository->getForExport(
            $genericData->userData->account_id,
            $data->dateFrom,
            $data->dateTo
        );
        return $exporter->export($genericData, $collectionData);
    }
}
