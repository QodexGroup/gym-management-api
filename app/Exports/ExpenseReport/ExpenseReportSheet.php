<?php

namespace App\Exports\ExpenseReport;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ExpenseReportSheet implements WithMultipleSheets
{
    use Exportable;

    protected $summaryHeaderData;
    protected $data;

    public function __construct($summaryHeaderData, $data)
    {
        $this->summaryHeaderData = $summaryHeaderData;
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new SummaryExpenseReportSheet($this->summaryHeaderData);
        $sheets[] = new ExpenseDataSheet($this->data);

        return $sheets;
    }
}
