<?php

namespace App\Exports\SummaryReport;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SummaryReportSheet implements WithMultipleSheets
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

        $sheets[] = new SummarySummaryReportSheet($this->summaryHeaderData);
        $sheets[] = new SummaryDataSheet($this->data);

        return $sheets;
    }
}
