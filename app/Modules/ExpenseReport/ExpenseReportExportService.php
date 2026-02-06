<?php

namespace App\Modules\ExpenseReport;

use App\Dtos\Core\ExpenseReportDto;
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
     * @param ExpenseReportDto $expenseReportDto
     *
     * @return mixed|null
     */
    public function export(ExpenseReportDto $expenseReportDto)
    {
        $exporter = ExpenseReportExportFactory::make($expenseReportDto->getExportType());
        if (!$exporter) {
            return null;
        }
        $expenseData = $this->expenseRepository->getForExport(
            $expenseReportDto->getAccountId(),
            $expenseReportDto->getDateFrom(),
            $expenseReportDto->getDateTo()
        );
        return $exporter->export($expenseReportDto, $expenseData);
    }
}
