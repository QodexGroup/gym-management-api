<?php

namespace App\Modules\CollectionReport;

use App\Dtos\Core\CollectionReportDto;
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
     * @param CollectionReportDto $collectionReportDto
     *
     * @return mixed|null
     */
    public function export(CollectionReportDto $collectionReportDto)
    {
        $exporter = CollectionReportExportFactory::make($collectionReportDto->getExportType());
        if (!$exporter) {
            return null;
        }
        $collectionData = $this->customerBillRepository->getForExport(
            $collectionReportDto->getAccountId(),
            $collectionReportDto->getDateFrom(),
            $collectionReportDto->getDateTo()
        );
        return $exporter->export($collectionReportDto, $collectionData);
    }
}
