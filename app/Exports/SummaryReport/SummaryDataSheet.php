<?php

namespace App\Exports\SummaryReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SummaryDataSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function array(): array
    {
        return array_map(function ($row) {
            return [
                $row['Category'] ?? '',
                $row['Amount'] ?? '',
            ];
        }, $this->data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Category',
            'Amount'
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Summary Report';
    }
}
