<?php

namespace App\Modules\CollectionReport\Exporters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportCollectionService
{
    private function getBusinessName(): string
    {
        return config('app.name');
    }

    /**
     * Transform payment collection for export (payment-based).
     *
     * @param Collection $paymentData Collection of CustomerPayment with customer + bill loaded
     */
    public function transformData(Collection $paymentData): array
    {
        $transformedData = [];

        foreach ($paymentData as $payment) {
            $customerName = $payment->relationLoaded('customer') && $payment->customer
                ? trim(($payment->customer->first_name ?? '') . ' ' . ($payment->customer->last_name ?? ''))
                : 'N/A';

            $billType = 'N/A';
            if ($payment->relationLoaded('bill') && $payment->bill) {
                $billType = $payment->bill->bill_type ?? 'N/A';
            }

            $rowData = [];
            $rowData['Date'] = Carbon::parse($payment->payment_date)->format('Y-m-d');
            $rowData['Member'] = $customerName ?: 'N/A';
            $rowData['Type'] = $billType;
            $rowData['Amount'] = (float) $payment->amount;
            $rowData['PaymentMethod'] = $this->paymentMethodLabel($payment->payment_method);
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    /**
     * @param Collection $paymentData Collection of CustomerPayment
     */
    public function getSummaryHeaderData(Collection $paymentData): array
    {
        $totalCollected = (float) $paymentData->sum('amount');
        $count = $paymentData->count();
        $average = $count > 0 ? $totalCollected / $count : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $paymentData->filter(function ($p) use ($today) {
            return Carbon::parse($p->payment_date)->toDateString() === $today;
        })->sum('amount');

        return [
            'businessName' => $this->getBusinessName(),
            'title' => 'Collection Report',
            'summaryRows' => [
                ['Total Collected', $this->formatCurrency($totalCollected)],
                ['Transactions', (string) $count],
                ['Average Transaction', $this->formatCurrency($average)],
                ["Today's Revenue", $this->formatCurrency($todayRevenue)],
            ],
        ];
    }

    public function getHeaders(): array
    {
        return ['Date', 'Member', 'Type', 'Amount', 'Payment Method'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function paymentMethodLabel(?string $method): string
    {
        return $method ? ucfirst(strtolower($method)) : 'N/A';
    }
}
