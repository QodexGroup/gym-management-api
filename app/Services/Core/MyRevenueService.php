<?php

namespace App\Services\Core;

use App\Constants\ReportConstant;
use App\Helpers\GenericData;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use Carbon\Carbon;

class MyRevenueService
{
    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private CustomerPtPackageRepository $customerPtPackageRepository,
    ) {
    }

    /**
     * Get My Revenue report data for a coach (bill-based, accrual) within a date range.
     * Returns the full filtered list (capped at the page limit) plus totals.
     *
     * @param GenericData $genericData Carries userData (coach) + validated startDate/endDate
     * @return array
     */
    public function getStats(GenericData $genericData): array
    {
        $totalRows = $this->customerBillRepository->countCoachPtBillsForDateRange($genericData);
        $reportTooLarge = $totalRows > ReportConstant::MAX_EXPORT_ROWS;

        $bills = $this->customerBillRepository->getCoachPtBillsForDateRange($genericData, ReportConstant::MAX_EXPORT_ROWS);

        $totalRevenue = $this->customerBillRepository->getCoachPtRevenueForDateRange($genericData);
        $totalCollected = $this->customerBillRepository->getCoachPtCollectedForDateRange($genericData);
        $ptPackagesSold = $this->customerPtPackageRepository->countPtPackagesSoldByCoach($genericData);
        $averageBill = $totalRows > 0 ? round($totalRevenue / $totalRows, 2) : 0;

        $billsList = [];
        foreach ($bills as $bill) {
            $customer = $bill->customer;
            $customerName = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';
            $gross = (float) $bill->gross_amount;
            $net = (float) $bill->net_amount;
            $paid = (float) $bill->paid_amount;

            $billsList[] = [
                'id' => $bill->id,
                'date' => Carbon::parse($bill->bill_date)->format('Y-m-d'),
                'member' => $customerName,
                'type' => $bill->bill_type ?? 'PT Package',
                'gross' => $gross,
                'discount' => round($gross - $net, 2),
                'net' => $net,
                'paid' => $paid,
                'balance' => round($net - $paid, 2),
                'status' => $bill->bill_status ?? 'active',
            ];
        }

        return [
            'trainerStats' => [
                'totalRevenue' => $totalRevenue,
                'totalBills' => $totalRows,
                'ptPackagesSold' => $ptPackagesSold,
                'averageBill' => $averageBill,
                'totalCollected' => $totalCollected,
                'totalOutstanding' => round($totalRevenue - $totalCollected, 2),
            ],
            'bills' => $billsList,
            'reportTooLarge' => $reportTooLarge,
            'totalRows' => $totalRows,
        ];
    }
}
