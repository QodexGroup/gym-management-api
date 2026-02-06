<?php

namespace App\Modules\SummaryReport;

use App\Dtos\Core\SummaryReportDto;
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
     * @param SummaryReportDto $summaryReportDto
     *
     * @return mixed|null
     */
    public function export(SummaryReportDto $summaryReportDto)
    {
        $exporter = SummaryReportExportFactory::make($summaryReportDto->getExportType());
        if (!$exporter) {
            return null;
        }
        $billData = $this->customerBillRepository->getForExport(
            $summaryReportDto->getAccountId(),
            $summaryReportDto->getDateFrom(),
            $summaryReportDto->getDateTo()
        );
        $expenseData = $this->expenseRepository->getForExport(
            $summaryReportDto->getAccountId(),
            $summaryReportDto->getDateFrom(),
            $summaryReportDto->getDateTo()
        );
        return $exporter->export($summaryReportDto, $billData, $expenseData);
    }
}
