<?php

namespace App\Exports\RevenueReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class RevenueDataSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
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
                $row['Type'] ?? '',
                $row['Gross'] ?? 0,
                $row['Discount'] ?? 0,
                $row['Net (Revenue)'] ?? 0,
                $row['Paid'] ?? 0,
                $row['Balance'] ?? 0,
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
            'Type',
            'Gross',
            'Discount',
            'Net (Revenue)',
            'Paid',
            'Balance',
            'Status',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Revenue Report';
    }
}
