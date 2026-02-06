<?php

namespace App\Modules\SummaryReport;

use App\Dtos\Core\SummaryReportDto;
use Illuminate\Database\Eloquent\Collection;

interface SummaryReportExportInterface
{
    /**
     * @param SummaryReportDto $summaryReportDto
     * @param Collection $billData
     * @param Collection $expenseData
     *
     * @return mixed
     */
    public function export(SummaryReportDto $summaryReportDto, Collection $billData, Collection $expenseData);
}
