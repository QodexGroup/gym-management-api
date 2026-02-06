<?php

namespace App\Modules\ExpenseReport\Exporters;

use App\Constant\ExpenseStatusConstant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportExpenseService
{
    private const BUSINESS_NAME = 'Kaizen Gym';

    public function transformData(Collection $expenseData): array
    {
        $transformedData = [];

        foreach ($expenseData as $expense) {
            $categoryName = $expense->relationLoaded('category') && $expense->category
                ? $expense->category->name
                : 'Unknown';

            $rowData = [];
            $rowData['Date'] = Carbon::parse($expense->expense_date)->format('Y-m-d');
            $rowData['Category'] = $categoryName;
            $rowData['Description'] = $expense->description ?? '';
            $rowData['Amount'] = (float) $expense->amount;
            $rowData['Status'] = $this->expenseStatusLabel($expense->status);
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    public function getSummaryHeaderData(Collection $expenseData): array
    {
        $totalExpenses = (float) $expenseData->sum('amount');
        $posted = (float) $expenseData->where('status', ExpenseStatusConstant::EXPENSE_STATUS_POSTED)->sum('amount');
        $unposted = (float) $expenseData->where('status', ExpenseStatusConstant::EXPENSE_STATUS_UNPOSTED)->sum('amount');

        return [
            'businessName' => self::BUSINESS_NAME,
            'title' => 'Expense Report',
            'summaryRows' => [
                ['Total Expenses', $this->formatCurrency($totalExpenses)],
                ['Posted', $this->formatCurrency($posted)],
                ['Unposted', $this->formatCurrency($unposted)],
                ['Transactions', (string) $expenseData->count()],
            ],
        ];
    }

    public function getHeaders(): array
    {
        return ['Date', 'Category', 'Description', 'Amount', 'Status'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function expenseStatusLabel(?string $status): string
    {
        switch ($status) {
            case ExpenseStatusConstant::EXPENSE_STATUS_POSTED:
                return 'Posted';
            case ExpenseStatusConstant::EXPENSE_STATUS_UNPOSTED:
                return 'Unposted';
            default:
                return $status ?? 'N/A';
        }
    }
}
