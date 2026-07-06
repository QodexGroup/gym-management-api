<?php

namespace App\Services\Core;

use App\Constants\ReportConstant;
use App\Helpers\GenericData;
use App\Repositories\Core\CustomerPaymentRepository;
use App\Repositories\Core\CustomerPtPackageRepository;
use Carbon\Carbon;

class MyCollectionService
{
    public function __construct(
        private CustomerPaymentRepository $customerPaymentRepository,
        private CustomerPtPackageRepository $customerPtPackageRepository,
    ) {
    }

    /**
     * Get My Collection report data for a coach (payment-based) within a date range.
     * Returns the full filtered list (capped at the page limit) plus totals.
     *
     * @param GenericData $genericData Carries userData (coach) + validated startDate/endDate
     * @return array
     */
    public function getStats(GenericData $genericData): array
    {
        $totalRows = $this->customerPaymentRepository->countCoachPtPaymentsForDateRange($genericData);
        $reportTooLarge = $totalRows > ReportConstant::MAX_EXPORT_ROWS;

        $payments = $this->customerPaymentRepository->getCoachPtPaymentsForDateRange($genericData, ReportConstant::MAX_EXPORT_ROWS);

        $totalEarnings = $this->customerPaymentRepository->getCoachPtEarningsForDateRange($genericData);
        $ptPackagesSold = $this->customerPtPackageRepository->countPtPackagesSoldByCoach($genericData);
        $averagePayment = $totalRows > 0 ? round($totalEarnings / $totalRows, 2) : 0;

        $transactions = [];
        foreach ($payments as $payment) {
            $customer = $payment->customer;
            $customerName = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';
            $billType = ($payment->relationLoaded('bill') && $payment->bill)
                ? ($payment->bill->bill_type ?? 'PT Package')
                : 'PT Package';

            $transactions[] = [
                'id' => $payment->id,
                'date' => Carbon::parse($payment->payment_date)->format('Y-m-d'),
                'member' => $customerName,
                'type' => $billType,
                'amount' => (float) $payment->amount,
                'paymentMethod' => $payment->payment_method ?? null,
            ];
        }

        return [
            'trainerStats' => [
                'totalEarnings' => $totalEarnings,
                'totalPayments' => $totalRows,
                'ptPackagesSold' => $ptPackagesSold,
                'averagePayment' => $averagePayment,
            ],
            'transactions' => $transactions,
            'reportTooLarge' => $reportTooLarge,
            'totalRows' => $totalRows,
        ];
    }
}
