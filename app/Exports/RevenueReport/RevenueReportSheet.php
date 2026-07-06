<?php

namespace App\Exports\RevenueReport;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RevenueReportSheet implements WithMultipleSheets
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

        $sheets[] = new SummaryRevenueReportSheet($this->summaryHeaderData);
        $sheets[] = new RevenueDataSheet($this->data);

        return $sheets;
    }
}
