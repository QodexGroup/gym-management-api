<?php

namespace App\Repositories\Core;

use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerMembership;
use App\Repositories\BaseRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerMembershipRepository extends BaseRepository
{
    /**
     * @param int $accountId
     * @param Carbon $from
     * @param Carbon $to
     *
     * @return int
     */
    public function countExpiringMemberships(int $accountId): int
    {
        return CustomerMembership::where('account_id', $accountId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->whereBetween('membership_end_date', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()])
            ->count();
    }

    /**
     * @param int $accountId
     * @param Carbon $from
     * @param Carbon $to
     *
     * @return Collection
     */
    public function getExpiringMemberships(int $accountId, Carbon $from, Carbon $to): Collection
    {
        return CustomerMembership::with(['customer', 'membershipPlan'])
            ->where('account_id', $accountId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->whereBetween('membership_end_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('membership_end_date', 'asc')
            ->limit(10)
            ->get();
    }

    /**
     * @param int $accountId
     *
     * @return Collection
     */
    public function getMembershipDistributionByPlan(int $accountId): Collection
    {
        $latestIds = CustomerMembership::select('id')
            ->where('account_id', $accountId)
            ->whereIn('id', function ($q) use ($accountId) {
                $q->selectRaw('MAX(id)')
                  ->from('tb_customer_membership')
                  ->where('account_id', $accountId)
                  ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
                  ->groupBy('customer_id');
            })
            ->pluck('id');

        return CustomerMembership::select('membership_plan_id', DB::raw('count(*) as count'))
            ->where('account_id', $accountId)
            ->whereIn('id', $latestIds)
            ->groupBy('membership_plan_id')
            ->with('membershipPlan')
            ->get();
    }

    /**
     * @param int $customerId
     * @param int $membershipPlanId
     *
     * @return void
     */
    public function deactivateMembershipByPlan(int $customerId, int $membershipPlanId): void
    {
        CustomerMembership::where('customer_id', $customerId)
            ->where('membership_plan_id', $membershipPlanId)
            ->where('status', CustomerMembershipConstant::STATUS_ACTIVE)
            ->update(['status' => CustomerMembershipConstant::STATUS_DEACTIVATED]);
    }
}
