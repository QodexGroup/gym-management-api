<?php

namespace App\Modules\SummaryReport\Exporters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportSummaryService
{
    private const BUSINESS_NAME = 'Kaizen Gym';

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

    public function getSummaryHeaderData(Collection $billData, Collection $expenseData): array
    {
        $totalRevenue = (float) $billData->sum('paid_amount');
        $totalExpenses = (float) $expenseData->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $billData->where('bill_date', $today)->sum('paid_amount');

        return [
            'businessName' => self::BUSINESS_NAME,
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
