<?php

namespace App\Modules\ExpenseReport;

use App\Dtos\Core\ExpenseReportDto;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseReportExportInterface
{
    /**
     * @param ExpenseReportDto $expenseReportDto
     * @param Collection $expenseData
     *
     * @return mixed
     */
    public function export(ExpenseReportDto $expenseReportDto, Collection $expenseData);
}
