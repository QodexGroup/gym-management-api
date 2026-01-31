<?php

namespace App\Services\Core;

use App\Models\Core\CustomerPtPackage;
use App\Repositories\Core\ClassSessionBookingRepository;
use App\Repositories\Core\CustomerBillRepository;
use Carbon\Carbon;

class MyCollectionService
{
    public function __construct(
        private CustomerBillRepository $customerBillRepository,
        private ClassSessionBookingRepository $classSessionBookingRepository,
    ) {
    }

    /**
     * Get trainer-specific stats for My Collection (current month + charts).
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

        $totalEarnings = $this->customerBillRepository->getCoachPtEarningsForDateRange(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        $sessionsCompleted = $this->classSessionBookingRepository->countAttendedByCoachAndDateRange(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );

        $ptPackagesSold = CustomerPtPackage::where('account_id', $accountId)
            ->where('coach_id', $coachId)
            ->whereBetween('start_date', [$startOfMonth, $endOfMonth])
            ->count();

        $monthlyTarget = null; // Not stored in DB
        $targetProgress = $monthlyTarget > 0
            ? min(100, round(($totalEarnings / $monthlyTarget) * 100, 1))
            : 0;
        $averageSessionRate = $sessionsCompleted > 0
            ? round($totalEarnings / $sessionsCompleted, 2)
            : 0;

        $trainerStats = [
            'totalEarnings' => $totalEarnings,
            'sessionsCompleted' => $sessionsCompleted,
            'ptPackagesSold' => $ptPackagesSold,
            'averageSessionRate' => $averageSessionRate,
            'monthlyTarget' => $monthlyTarget,
            'targetProgress' => $targetProgress,
        ];

        $weeklyEarnings = $this->customerBillRepository->getCoachPtEarningsByWeek(
            $accountId,
            $coachId,
            $startOfMonth,
            $endOfMonth
        );
        if (empty($weeklyEarnings)) {
            $weeklyEarnings = [
                ['week' => 'Week 1', 'sessions' => 0, 'earnings' => 0],
                ['week' => 'Week 2', 'sessions' => 0, 'earnings' => 0],
                ['week' => 'Week 3', 'sessions' => 0, 'earnings' => 0],
                ['week' => 'Week 4', 'sessions' => 0, 'earnings' => 0],
            ];
        }

        $earningsBreakdown = [];
        if ($totalEarnings > 0) {
            $earningsBreakdown = [
                ['name' => 'PT Package Sales', 'value' => $totalEarnings, 'color' => '#0ea5e9'],
            ];
        } else {
            $earningsBreakdown = [['name' => 'PT Package Sales', 'value' => 0, 'color' => '#0ea5e9']];
        }

        $monthlyProgress = $this->customerBillRepository->getCoachPtEarningsByMonth(
            $accountId,
            $coachId,
            6
        );
        if (empty($monthlyProgress)) {
            $monthlyProgress = array_map(function ($m) {
                return ['month' => $m, 'earnings' => 0, 'target' => null];
            }, ['Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
        }

        $recentBookings = $this->classSessionBookingRepository->getRecentAttendedByCoach(
            $accountId,
            $coachId,
            10
        );
        $recentSessions = $recentBookings->map(function ($b) {
            $customer = $b->customer;
            $session = $b->classScheduleSession;
            $schedule = $session ? $session->classSchedule : null;
            $sessionDate = $session && $session->start_time
                ? Carbon::parse($session->start_time)->format('Y-m-d')
                : ($b->updated_at ? $b->updated_at->format('Y-m-d') : '');
            $customerName = $customer
                ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';
            $type = $schedule ? ($schedule->class_name ?? 'Session') : 'Session';
            return [
                'id' => $b->id,
                'member' => $customerName,
                'type' => $type,
                'date' => $sessionDate,
                'amount' => 0,
            ];
        })->values()->toArray();

        return [
            'trainerStats' => $trainerStats,
            'weeklyEarnings' => $weeklyEarnings,
            'earningsBreakdown' => $earningsBreakdown,
            'monthlyProgress' => $monthlyProgress,
            'recentSessions' => $recentSessions,
        ];
    }
}
