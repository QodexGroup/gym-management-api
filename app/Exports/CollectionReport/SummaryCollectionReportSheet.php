<?php

namespace App\Exports\CollectionReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SummaryCollectionReportSheet implements WithHeadings, FromArray, WithTitle, ShouldAutoSize
{
    protected $summaryHeaderData;

    public function __construct($summaryHeaderData)
    {
        $this->summaryHeaderData = $summaryHeaderData;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        $data = [];
        $data[] = [$this->summaryHeaderData['businessName'] ?? ''];
        $data[] = [strtoupper($this->summaryHeaderData['title'] ?? 'Collection Report')];
        if (isset($this->summaryHeaderData['periodLabel'])) {
            $data[] = ['Period: ' . $this->summaryHeaderData['periodLabel']];
        }
        if (isset($this->summaryHeaderData['generatedAt'])) {
            $data[] = ['Generated: ' . $this->summaryHeaderData['generatedAt']];
        }
        $data[] = [];
        if (!empty($this->summaryHeaderData['summaryRows'])) {
            $data[] = ['Summary', ''];
            foreach ($this->summaryHeaderData['summaryRows'] as $pair) {
                $data[] = [$pair[0] ?? '', $pair[1] ?? ''];
            }
        }

        return $data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Summary';
    }
}
