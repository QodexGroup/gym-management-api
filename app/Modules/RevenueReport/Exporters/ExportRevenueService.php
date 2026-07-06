<?php

namespace App\Modules\RevenueReport\Exporters;

use App\Constant\CustomerBillConstant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportRevenueService
{
    private function getBusinessName(): string
    {
        return config('app.name');
    }

    /**
     * Transform bill collection for export (bill-based revenue).
     *
     * @param Collection $billData Collection of CustomerBill with customer loaded
     */
    public function transformData(Collection $billData): array
    {
        $transformedData = [];

        foreach ($billData as $bill) {
            $customerName = $bill->relationLoaded('customer') && $bill->customer
                ? trim(($bill->customer->first_name ?? '') . ' ' . ($bill->customer->last_name ?? ''))
                : 'N/A';

            $gross = (float) $bill->gross_amount;
            $net = (float) $bill->net_amount;
            $paid = (float) $bill->paid_amount;

            $rowData = [];
            $rowData['Date'] = Carbon::parse($bill->bill_date)->format('Y-m-d');
            $rowData['Member'] = $customerName ?: 'N/A';
            $rowData['Type'] = $bill->bill_type ?? 'N/A';
            $rowData['Gross'] = $this->formatCurrency($gross);
            $rowData['Discount'] = $this->formatCurrency($gross - $net);
            $rowData['Net (Revenue)'] = $this->formatCurrency($net);
            $rowData['Paid'] = $this->formatCurrency($paid);
            $rowData['Balance'] = $this->formatCurrency($net - $paid);
            $rowData['Status'] = $this->statusLabel($bill->bill_status);
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    /**
     * @param Collection $billData Collection of CustomerBill
     */
    public function getSummaryHeaderData(Collection $billData): array
    {
        $totalRevenue = (float) $billData->sum(fn ($b) => (float) $b->net_amount);
        $totalCollected = (float) $billData->sum(fn ($b) => (float) $b->paid_amount);
        $count = $billData->count();
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $billData->filter(function ($b) use ($today) {
            return Carbon::parse($b->bill_date)->toDateString() === $today;
        })->sum(fn ($b) => (float) $b->net_amount);

        return [
            'businessName' => $this->getBusinessName(),
            'title' => 'Revenue Report',
            'summaryRows' => [
                ['Total Revenue (Billed)', $this->formatCurrency($totalRevenue)],
                ['Total Collected', $this->formatCurrency($totalCollected)],
                ['Outstanding Balance', $this->formatCurrency($totalRevenue - $totalCollected)],
                ['Bills', (string) $count],
                ["Today's Revenue (Billed)", $this->formatCurrency($todayRevenue)],
            ],
        ];
    }

    public function getHeaders(): array
    {
        return ['Date', 'Member', 'Type', 'Gross', 'Discount', 'Net (Revenue)', 'Paid', 'Balance', 'Status'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function statusLabel(?string $status): string
    {
        if (! $status) {
            return CustomerBillConstant::BILL_STATUS_ACTIVE;
        }

        return ucfirst(strtolower($status));
    }
}
