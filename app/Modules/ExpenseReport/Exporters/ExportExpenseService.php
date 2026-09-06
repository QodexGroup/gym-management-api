<?php

namespace App\Modules\ExpenseReport\Exporters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportExpenseService
{
    private function getBusinessName(): string
    {
        return config('app.name');
    }

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
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    public function getSummaryHeaderData(Collection $expenseData): array
    {
        $totalExpenses = (float) $expenseData->sum('amount');
        $transactionCount = $expenseData->count();
        $average = $transactionCount > 0 ? $totalExpenses / $transactionCount : 0.0;
        $topCategory = $this->getTopCategory($expenseData);

        return [
            'businessName' => $this->getBusinessName(),
            'title' => 'Expense Report',
            'summaryRows' => [
                ['Total Expenses', $this->formatCurrency($totalExpenses)],
                ['Transactions', (string) $transactionCount],
                ['Average per Transaction', $this->formatCurrency($average)],
                ['Top Category', $topCategory],
            ],
        ];
    }

    public function getHeaders(): array
    {
        return ['Date', 'Category', 'Description', 'Amount'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    /**
     * Resolve the category with the highest total amount.
     *
     * @param Collection $expenseData
     * @return string
     */
    private function getTopCategory(Collection $expenseData): string
    {
        $totals = [];

        foreach ($expenseData as $expense) {
            $categoryName = $expense->relationLoaded('category') && $expense->category
                ? $expense->category->name
                : 'Unknown';
            $totals[$categoryName] = ($totals[$categoryName] ?? 0) + (float) $expense->amount;
        }

        if (empty($totals)) {
            return 'N/A';
        }

        arsort($totals);
        $name = array_key_first($totals);

        return $name . ' (' . $this->formatCurrency((float) $totals[$name]) . ')';
    }
}
