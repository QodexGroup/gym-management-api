<?php

namespace App\Exports\CollectionReport;

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
                $row['Type'] ?? '',
                $row['Amount'] ?? 0,
                $row['PaymentMethod'] ?? '',
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
            'Amount',
            'Payment Method',
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
