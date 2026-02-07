<?php

namespace App\Modules\ExpenseReport;

use App\Helpers\GenericData;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseReportExportInterface
{
    /**
     * @param GenericData $genericData
     * @param Collection $expenseData
     *
     * @return mixed
     */
    public function export(GenericData $genericData, Collection $expenseData);
}
