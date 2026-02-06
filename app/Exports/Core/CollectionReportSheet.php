<?php

namespace App\Exports\Core;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CollectionReportSheet implements WithMultipleSheets
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

        $sheets[] = new SummaryCollectionReportSheet($this->summaryHeaderData);
        $sheets[] = new CollectionDataSheet($this->data);

        return $sheets;
    }
}
