<?php

namespace App\Modules\SummaryReport;

use App\Helpers\GenericData;
use Illuminate\Database\Eloquent\Collection;

interface SummaryReportExportInterface
{
    /**
     * @param GenericData $genericData
     * @param Collection $billData
     * @param Collection $expenseData
     *
     * @return mixed
     */
    public function export(GenericData $genericData, Collection $billData, Collection $expenseData);
}
