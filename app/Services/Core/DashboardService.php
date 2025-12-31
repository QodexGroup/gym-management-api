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
    public function getStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $thirtyDaysFromNow = $now->copy()->addDays(30);

        return [
            'totalMembers' => $this->getTotalMembers(),
            'activeMembers' => $this->getActiveMembers(),
            'newRegistrations' => $this->getNewRegistrations($startOfMonth, $endOfMonth),
            'todayRevenue' => $this->getTodayRevenue($now),
            'expiringMemberships' => $this->getExpiringMembershipsCount($now, $thirtyDaysFromNow),
            'expiringMembersList' => $this->getExpiringMembersList($now, $thirtyDaysFromNow),
            'membershipDistribution' => $this->getMembershipDistribution(),
        ];
    }

    /**
     * Get total number of members
     *
     * @return int
     */
    private function getTotalMembers(): int
    {
        return Customer::count();
    }

    /**
     * Get number of active members
     *
     * @return int
     */
    private function getActiveMembers(): int
    {
        return Customer::whereHas('memberships', function ($query) {
            $query->where('status', 'Active')
                  ->where('membership_end_date', '>=', now());
        })->count();
    }

    /**
     * Get new registrations for the current month
     *
     * @param Carbon $startOfMonth
     * @param Carbon $endOfMonth
     * @return int
     */
    private function getNewRegistrations(Carbon $startOfMonth, Carbon $endOfMonth): int
    {
        return Customer::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
    }

    /**
     * Get today's revenue
     *
     * @param Carbon $now
     * @return float
     */
    private function getTodayRevenue(Carbon $now): float
    {
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $now->copy()->endOfDay();

        return CustomerPayment::whereBetween('payment_date', [$startOfDay, $endOfDay])
            ->sum('amount') ?? 0.0;
    }

    /**
     * Get count of memberships expiring in the next 30 days
     *
     * @param Carbon $now
     * @param Carbon $thirtyDaysFromNow
     * @return int
     */
    private function getExpiringMembershipsCount(Carbon $now, Carbon $thirtyDaysFromNow): int
    {
        return CustomerMembership::where('status', 'Active')
            ->whereBetween('membership_end_date', [$now, $thirtyDaysFromNow])
            ->count();
    }

    /**
     * Get list of members with expiring memberships
     *
     * @param Carbon $now
     * @param Carbon $thirtyDaysFromNow
     * @return array
     */
    private function getExpiringMembersList(Carbon $now, Carbon $thirtyDaysFromNow): array
    {
        $expiringMemberships = CustomerMembership::with(['customer', 'membershipPlan'])
            ->where('status', 'Active')
            ->whereBetween('membership_end_date', [$now, $thirtyDaysFromNow])
            ->orderBy('membership_end_date', 'asc')
            ->limit(10)
            ->get();

        return $expiringMemberships->map(function ($membership) use ($now) {
            $daysUntilExpiry = $now->diffInDays($membership->membership_end_date, false);
            
            $customerName = trim(($membership->customer->first_name ?? '') . ' ' . ($membership->customer->last_name ?? ''));
            if (empty($customerName)) {
                $customerName = 'Unknown';
            }
            
            return [
                'id' => $membership->customer->id,
                'name' => $customerName,
                'email' => $membership->customer->email,
                'membership' => $membership->membershipPlan->plan_name ?? 'N/A',
                'membershipExpiry' => $membership->membership_end_date->format('Y-m-d'),
                'membershipStatus' => $daysUntilExpiry <= 7 ? 'expired' : 'expiring',
                'avatar' => $membership->customer->photo ?? null,
            ];
        })->toArray();
    }

    /**
     * Get membership distribution by plan
     *
     * @return array
     */
    private function getMembershipDistribution(): array
    {
        $distribution = CustomerMembership::select('membership_plan_id', DB::raw('count(*) as count'))
            ->where('status', 'Active')
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
