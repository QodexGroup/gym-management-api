<?php

namespace App\Exports\Core;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CollectionDataSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
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
                $row['Date'] ?? '',
                $row['Member'] ?? '',
                $row['BillType'] ?? '',
                $row['PaidAmount'] ?? 0,
                $row['Status'] ?? '',
            ];
        }, $this->data);
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Date',
            'Member',
            'Bill Type',
            'Paid Amount',
            'Status'
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Collection Report';
    }
}
