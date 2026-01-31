<?php

namespace App\Services\Core;

use App\Repositories\Common\ExpenseRepository;
use App\Repositories\Core\CustomerBillRepository;

class ReportService
{
    private const MAX_EXPORT_ROWS = 200;

    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private ExpenseRepository $expenseRepository,
    ) {
    }

    /**
     * Check if report data for the given range is too large for direct export.
     *
     * @param int $accountId
     * @param string $reportType collection|expense|summary
     * @param string $dateFrom Y-m-d
     * @param string $dateTo Y-m-d
     * @return array{tooLarge: bool, rowCount: int}
     */
    public function checkExportSize(int $accountId, string $reportType, string $dateFrom, string $dateTo): array
    {
        switch ($reportType) {
            case 'collection':
                $rowCount = $this->customerBillRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
                break;
            case 'expense':
            case 'summary':
                $rowCount = $this->expenseRepository->countByAccountAndDateRange($accountId, $dateFrom, $dateTo);
                break;
            default:
                $rowCount = 0;
        }

        return [
            'tooLarge' => $rowCount > self::MAX_EXPORT_ROWS,
            'rowCount' => $rowCount,
        ];
    }
}
