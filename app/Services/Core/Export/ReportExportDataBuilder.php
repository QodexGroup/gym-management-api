<?php

namespace App\Services\Core\Export;

use App\Constant\CustomerBillConstant;
use App\Constant\ExpenseStatusConstant;
use App\Repositories\Common\ExpenseRepository;
use App\Repositories\Core\CustomerBillRepository;
use Carbon\Carbon;

/**
 * Builds report export payload (title, summaryRows, headers, rows) for collection, expense, summary.
 * Same structure as frontend reportPrintExport for PDF/Excel.
 */
class ReportExportDataBuilder
{
    private const BUSINESS_NAME = 'Kaizen Gym';

    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private ExpenseRepository $expenseRepository,
    ) {
    }

    /**
     * @param string $reportType collection|expense|summary
     * @return array{title: string, businessName: string, periodLabel: string, generatedAt: string, summaryRows: array<int, array{0: string, 1: string}>, headers: array<int, string>, rows: array<int, array>, sheetName: string}
     */
    public function buildPayload(
        string $reportType,
        int $accountId,
        string $dateFrom,
        string $dateTo,
        ?string $periodLabel = null
    ): array {
        $periodLabel = $periodLabel ?? "{$dateFrom} – {$dateTo}";
        $generatedAt = Carbon::now()->toDateTimeString();

        switch ($reportType) {
            case 'collection':
                return $this->buildCollectionPayload($accountId, $dateFrom, $dateTo, $periodLabel, $generatedAt);
            case 'expense':
                return $this->buildExpensePayload($accountId, $dateFrom, $dateTo, $periodLabel, $generatedAt);
            case 'summary':
                return $this->buildSummaryPayload($accountId, $dateFrom, $dateTo, $periodLabel, $generatedAt);
            default:
                throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }
    }

    private function buildCollectionPayload(
        int $accountId,
        string $dateFrom,
        string $dateTo,
        string $periodLabel,
        string $generatedAt
    ): array {
        $bills = $this->customerBillRepository->getForExport($accountId, $dateFrom, $dateTo);
        $totalCollected = (float) $bills->sum('paid_amount');
        $count = $bills->count();
        $average = $count > 0 ? $totalCollected / $count : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $bills->where('bill_date', $today)->sum('paid_amount');

        $summaryRows = [
            ['Total Collected', $this->formatCurrency($totalCollected)],
            ['Transactions', (string) $count],
            ['Average Transaction', $this->formatCurrency($average)],
            ["Today's Revenue", $this->formatCurrency($todayRevenue)],
        ];

        $headers = ['Date', 'Member', 'Bill Type', 'Paid Amount', 'Status'];
        $rows = $bills->map(function ($bill) {
            $customerName = $bill->relationLoaded('customer') && $bill->customer
                ? trim(($bill->customer->first_name ?? '') . ' ' . ($bill->customer->last_name ?? ''))
                : 'N/A';
            return [
                Carbon::parse($bill->bill_date)->format('Y-m-d'),
                $customerName ?: 'N/A',
                $bill->bill_type ?? 'N/A',
                $this->formatCurrency((float) $bill->paid_amount),
                $this->billStatusLabel($bill->bill_status),
            ];
        })->values()->all();

        return [
            'title' => 'Collection Report',
            'businessName' => self::BUSINESS_NAME,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
            'summaryRows' => $summaryRows,
            'headers' => $headers,
            'rows' => $rows,
            'sheetName' => 'Collection',
        ];
    }

    private function buildExpensePayload(
        int $accountId,
        string $dateFrom,
        string $dateTo,
        string $periodLabel,
        string $generatedAt
    ): array {
        $expenses = $this->expenseRepository->getForExport($accountId, $dateFrom, $dateTo);
        $totalExpenses = (float) $expenses->sum('amount');
        $posted = (float) $expenses->where('status', ExpenseStatusConstant::EXPENSE_STATUS_POSTED)->sum('amount');
        $unposted = (float) $expenses->where('status', ExpenseStatusConstant::EXPENSE_STATUS_UNPOSTED)->sum('amount');

        $summaryRows = [
            ['Total Expenses', $this->formatCurrency($totalExpenses)],
            ['Posted', $this->formatCurrency($posted)],
            ['Unposted', $this->formatCurrency($unposted)],
            ['Transactions', (string) $expenses->count()],
        ];

        $headers = ['Date', 'Category', 'Description', 'Amount', 'Status'];
        $rows = $expenses->map(function ($expense) {
            $categoryName = $expense->relationLoaded('category') && $expense->category
                ? $expense->category->name
                : 'Unknown';
            return [
                Carbon::parse($expense->expense_date)->format('Y-m-d'),
                $categoryName,
                $expense->description ?? '',
                $this->formatCurrency((float) $expense->amount),
                $this->expenseStatusLabel($expense->status),
            ];
        })->values()->all();

        return [
            'title' => 'Expense Report',
            'businessName' => self::BUSINESS_NAME,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
            'summaryRows' => $summaryRows,
            'headers' => $headers,
            'rows' => $rows,
            'sheetName' => 'Expenses',
        ];
    }

    private function buildSummaryPayload(
        int $accountId,
        string $dateFrom,
        string $dateTo,
        string $periodLabel,
        string $generatedAt
    ): array {
        $bills = $this->customerBillRepository->getForExport($accountId, $dateFrom, $dateTo);
        $expenses = $this->expenseRepository->getForExport($accountId, $dateFrom, $dateTo);

        $totalRevenue = (float) $bills->sum('paid_amount');
        $totalExpenses = (float) $expenses->sum('amount');
        $netProfit = $totalRevenue - $totalExpenses;
        $profitMargin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $bills->where('bill_date', $today)->sum('paid_amount');

        $summaryRows = [
            ['Total Revenue', $this->formatCurrency($totalRevenue)],
            ['Total Expenses', $this->formatCurrency($totalExpenses)],
            ['Net Profit', $this->formatCurrency($netProfit)],
            ['Profit Margin', "{$profitMargin}%"],
            ["Today's Revenue", $this->formatCurrency($todayRevenue)],
        ];

        $byCategory = $expenses->groupBy('category_id')->map(function ($group) {
            $first = $group->first();
            $name = $first->relationLoaded('category') && $first->category ? $first->category->name : 'Unknown';
            return ['name' => $name, 'value' => $group->sum('amount')];
        })->values();

        $headers = ['Category', 'Amount'];
        $rows = $byCategory->map(fn ($c) => [$c['name'], $this->formatCurrency($c['value'])])->all();

        return [
            'title' => 'Summary Report',
            'businessName' => self::BUSINESS_NAME,
            'periodLabel' => $periodLabel,
            'generatedAt' => $generatedAt,
            'summaryRows' => $summaryRows,
            'headers' => $headers,
            'rows' => $rows,
            'sheetName' => 'Summary',
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function billStatusLabel(?string $status): string
    {
        return $status ? ucfirst(strtolower($status)) : 'N/A';
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
