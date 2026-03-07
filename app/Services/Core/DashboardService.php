<?php

namespace App\Services\Core;

use App\Models\Core\Customer;
use App\Models\Core\CustomerMembership;
use App\Models\Core\CustomerPayment;
use App\Models\Account\MembershipPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard statistics
     *
     * @return array
     */
    public function getStats(int $accountId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        // 7 days aligns with CheckMembershipExpiration / notify-member window
        $sevenDaysFromNow = $now->copy()->addDays(7);

        return [
            'totalMembers' => $this->getTotalMembers($accountId),
            'activeMembers' => $this->getActiveMembers($accountId),
            'newRegistrations' => $this->getNewRegistrations($accountId, $startOfMonth, $endOfMonth),
            'todayRevenue' => $this->getTodayRevenue($accountId, $now),
            'expiringMemberships' => $this->getExpiringMembershipsCount($accountId, $now, $sevenDaysFromNow),
            'expiringMembersList' => $this->getExpiringMembersList($accountId, $now, $sevenDaysFromNow),
            'membershipDistribution' => $this->getMembershipDistribution($accountId),
        ];
    }

    /**
     * Get total number of members
     *
     * @return int
     */
    private function getTotalMembers(int $accountId): int
    {
        return Customer::where('account_id', $accountId)->count();
    }

    /**
     * Get number of active members
     *
     * @return int
     */
    private function getActiveMembers(int $accountId): int
    {
        return Customer::where('account_id', $accountId)->whereHas('memberships', function ($query) use ($accountId) {
            $query->where('status', 'Active')
                  ->where('account_id', $accountId)
                  ->whereDate('membership_end_date', '>=', today());
        })->count();
    }

    /**
     * Get new registrations for the current month
     *
     * @param Carbon $startOfMonth
     * @param Carbon $endOfMonth
     * @return int
     */
    private function getNewRegistrations(int $accountId, Carbon $startOfMonth, Carbon $endOfMonth): int
    {
        return Customer::where('account_id', $accountId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();
    }

    /**
     * Get today's revenue
     *
     * @param Carbon $now
     * @return float
     */
    private function getTodayRevenue(int $accountId, Carbon $now): float
    {
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $now->copy()->endOfDay();

        return CustomerPayment::where('account_id', $accountId)
            ->whereBetween('payment_date', [$startOfDay, $endOfDay])
            ->sum('amount') ?? 0.0;
    }

    /**
     * Get count of memberships expiring in the next 7 days (aligns with notify-member window).
     *
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     * @return int
     */
    private function getExpiringMembershipsCount(int $accountId, Carbon $now, Carbon $sevenDaysFromNow): int
    {
        return CustomerMembership::where('account_id', $accountId)
            ->where('status', 'Active')
            ->whereBetween('membership_end_date', [$now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay()])
            ->count();
    }

    /**
     * Get list of members with expiring memberships (next 7 days, aligns with notify-member window).
     *
     * @param Carbon $now
     * @param Carbon $sevenDaysFromNow
     * @return array
     */
    private function getExpiringMembersList(int $accountId, Carbon $now, Carbon $sevenDaysFromNow): array
    {
        $expiringMemberships = CustomerMembership::with(['customer', 'membershipPlan'])
            ->where('account_id', $accountId)
            ->where('status', 'Active')
            ->whereBetween('membership_end_date', [$now->copy()->startOfDay(), $sevenDaysFromNow->copy()->endOfDay()])
            ->orderBy('membership_end_date', 'asc')
            ->limit(10)
            ->get();

        return $expiringMemberships->map(function ($membership) use ($now) {
            $daysUntilExpiry = $now->diffInDays($membership->membership_end_date, false);

            $customerName = trim(($membership->customer->first_name ?? '') . ' ' . ($membership->customer->last_name ?? ''));
            if (empty($customerName)) {
                $customerName = 'Unknown';
            }

            // Determine status based on days until expiry
            if ($membership->membership_end_date->isPast()) {
                $status = 'expired';
            } elseif ($daysUntilExpiry <= 7) {
                $status = 'expiring';
            } else {
                $status = 'active';
            }

            return [
                'id' => $membership->customer->id,
                'name' => $customerName,
                'email' => $membership->customer->email,
                'membership' => $membership->membershipPlan->plan_name ?? 'N/A',
                'membershipExpiry' => $membership->membership_end_date->format('Y-m-d'),
                'membershipStatus' => $status,
                'avatar' => $membership->customer->photo ?? null,
            ];
        })->toArray();
    }

    /**
     * Get membership distribution by plan
     *
     * @return array
     */
    private function getMembershipDistribution(int $accountId): array
    {
        // Get the latest membership ID for each customer
        $latestMembershipIds = CustomerMembership::select('id')
            ->where('account_id', $accountId)
            ->whereIn('id', function($query) use ($accountId) {
                $query->selectRaw('MAX(id)')
                    ->from('tb_customer_membership')
                    ->where('account_id', $accountId)
                    ->where('status', 'Active')
                    ->groupBy('customer_id');
            })
            ->pluck('id');

        // Get distribution based on latest memberships only
        $distribution = CustomerMembership::select('membership_plan_id', DB::raw('count(*) as count'))
            ->where('account_id', $accountId)
            ->whereIn('id', $latestMembershipIds)
            ->groupBy('membership_plan_id')
            ->with('membershipPlan')
            ->get();

        $colors = ['#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

        return $distribution->map(function ($item, $index) use ($colors) {
            return [
                'name' => $item->membershipPlan->plan_name ?? 'Unknown',
                'value' => $item->count,
                'color' => $colors[$index % count($colors)],
            ];
        })->toArray();
    }
}
