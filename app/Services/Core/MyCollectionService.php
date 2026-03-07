<?php

namespace App\Services\Core;

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
     * Get My Collection stats for a coach.
     *
     * @param int $accountId
     * @param int $coachId
     * @return array
     */
    public function getStats(int $accountId, int $coachId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        // Get this month's stats
        $totalEarnings = $this->customerPaymentRepository->getCoachPtEarningsForDateRange(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        $totalPayments = $this->customerPaymentRepository->countCoachPtPaymentsForDateRange(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        $ptPackagesSold = $this->customerPtPackageRepository->countPtPackagesSoldByCoach(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        $averagePayment = $totalPayments > 0
            ? round($totalEarnings / $totalPayments, 2)
            : 0;

        $trainerStats = [
            'totalEarnings' => $totalEarnings,
            'totalPayments' => $totalPayments,
            'ptPackagesSold' => $ptPackagesSold,
            'averagePayment' => $averagePayment,
        ];

        // Get weekly earnings for this month
        $weeklyEarnings = $this->customerPaymentRepository->getCoachPtEarningsByWeek(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        // Fill in empty weeks if needed
        if (empty($weeklyEarnings)) {
            $weeklyEarnings = [
                ['week' => 'Week 1', 'payments' => 0, 'earnings' => 0],
                ['week' => 'Week 2', 'payments' => 0, 'earnings' => 0],
                ['week' => 'Week 3', 'payments' => 0, 'earnings' => 0],
                ['week' => 'Week 4', 'payments' => 0, 'earnings' => 0],
            ];
        }

        // Earnings breakdown (currently only PT Package Sales)
        $earningsBreakdown = [];
        if ($totalEarnings > 0) {
            $earningsBreakdown = [
                ['name' => 'PT Package Sales', 'value' => $totalEarnings, 'color' => '#0ea5e9'],
            ];
        } else {
            $earningsBreakdown = [['name' => 'PT Package Sales', 'value' => 0, 'color' => '#0ea5e9']];
        }

        // Get monthly progress (last 6 months)
        $monthlyProgress = $this->customerPaymentRepository->getCoachPtEarningsByMonth(
            $accountId,
            $coachId,
            6
        );

        // Fill in empty months if needed
        if (empty($monthlyProgress)) {
            $monthlyProgress = array_map(function ($m) {
                return ['month' => $m, 'earnings' => 0];
            }, ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
        }

        // Get recent PT payments
        $recentPayments = $this->customerPaymentRepository->getRecentPtPaymentsForCoach(
            $accountId,
            $coachId,
            10
        );

        $recentPaymentsList = $recentPayments->map(function ($payment) {
            $customer = $payment->customer;
            $customerName = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';

            return [
                'id' => $payment->id,
                'member' => $customerName,
                'type' => 'PT Package',
                'date' => $payment->payment_date->format('Y-m-d'),
                'amount' => (float) $payment->amount,
            ];
        })->values()->toArray();

        return [
            'trainerStats' => $trainerStats,
            'weeklyEarnings' => $weeklyEarnings,
            'earningsBreakdown' => $earningsBreakdown,
            'monthlyProgress' => $monthlyProgress,
            'recentPayments' => $recentPaymentsList,
        ];
    }
}
