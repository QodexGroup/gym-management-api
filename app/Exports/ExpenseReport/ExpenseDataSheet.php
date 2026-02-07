<?php

namespace App\Exports\ExpenseReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ExpenseDataSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
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
                $row['Category'] ?? '',
                $row['Description'] ?? '',
                $row['Amount'] ?? 0,
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
            'Category',
            'Description',
            'Amount',
            'Status'
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Expense Report';
    }
}
