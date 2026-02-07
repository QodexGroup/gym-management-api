<?php

namespace App\Modules\CollectionReport\Exporters;

use App\Constant\CustomerBillConstant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ExportCollectionService
{
    private const BUSINESS_NAME = 'Kaizen Gym';

    public function transformData(Collection $collectionData): array
    {
        $transformedData = [];

        foreach ($collectionData as $bill) {
            $customerName = $bill->relationLoaded('customer') && $bill->customer
                ? trim(($bill->customer->first_name ?? '') . ' ' . ($bill->customer->last_name ?? ''))
                : 'N/A';

            $rowData = [];
            $rowData['Date'] = Carbon::parse($bill->bill_date)->format('Y-m-d');
            $rowData['Member'] = $customerName ?: 'N/A';
            $rowData['BillType'] = $bill->bill_type ?? 'N/A';
            $rowData['PaidAmount'] = (float) $bill->paid_amount;
            $rowData['Status'] = $this->billStatusLabel($bill->bill_status);
            $transformedData[] = $rowData;
        }

        return $transformedData;
    }

    public function getSummaryHeaderData(Collection $collectionData): array
    {
        $totalCollected = (float) $collectionData->sum('paid_amount');
        $count = $collectionData->count();
        $average = $count > 0 ? $totalCollected / $count : 0.0;
        $today = Carbon::today()->toDateString();
        $todayRevenue = (float) $collectionData->where('bill_date', $today)->sum('paid_amount');

        return [
            'businessName' => self::BUSINESS_NAME,
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
        return ['Date', 'Member', 'Bill Type', 'Paid Amount', 'Status'];
    }

    private function formatCurrency(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function billStatusLabel(?string $status): string
    {
        return $status ? ucfirst(strtolower($status)) : 'N/A';
    }
}
