<?php

namespace App\Modules\SummaryReport\Exporters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportSummaryService
{
    private function getBusinessName(): string
    {
        return config('app.name');
    }

    public function transformData(Collection $expenseData): array
    {
        $byCategory = $expenseData->groupBy('category_id')->map(function ($group) {
            $first = $group->first();
            $name = $first->relationLoaded('category') && $first->category ? $first->category->name : 'Unknown';
            return ['name' => $name, 'value' => $group->sum('amount')];
        })->values();

        $transformedData = [];
        foreach ($byCategory as $category) {
            $rowData = [];
            $rowData['Category'] = $category['name'];
            $rowData['Amount'] = $this->formatCurrency($category['value']);
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    /**
     * @param Collection $paymentData Collection of CustomerPayment (revenue source)
     * @param Collection $expenseData Collection of Expense
     */
    public function getSummaryHeaderData(Collection $paymentData, Collection $expenseData): array
    {
        $totalRevenue = (float) $paymentData->sum('amount');
        $totalExpenses = (float) $expenseData->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $paymentData->filter(function ($p) use ($today) {
            return Carbon::parse($p->payment_date)->toDateString() === $today;
        })->sum('amount');

        return [
            'businessName' => $this->getBusinessName(),
            'title' => 'Summary Report',
            'summaryRows' => [
                ['Total Revenue', $this->formatCurrency($totalRevenue)],
                ['Total Expenses', $this->formatCurrency($totalExpenses)],
                ['Net Profit', $this->formatCurrency($netProfit)],
                ['Profit Margin', "{$profitMargin}%"],
                ["Today's Revenue", $this->formatCurrency($todayRevenue)],
            ],
        ];
    }

    public function getHeaders(): array
    {
        return ['Category', 'Amount'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }
}
