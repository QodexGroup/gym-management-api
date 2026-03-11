<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Constant\AccountStatusConstant;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\SubscriptionPlan;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class AccountSubscriptionPlanRepository
{
    /**
     * @param callable(Collection<int, AccountSubscriptionPlan>): void $callback
     */
    public function chunkBillableByInterval(Carbon $cycleStart, string $interval, callable $callback): void
    {
        AccountSubscriptionPlan::with(['account', 'subscriptionPlan'])
            ->whereHas('subscriptionPlan', fn ($q) => $q->where('interval', $interval)->where('is_trial', false))
            ->whereHas('account', fn ($q) => $q->where('status', AccountStatusConstant::STATUS_ACTIVE))
            ->whereNotNull('subscription_starts_at')
            ->where('subscription_starts_at', '<=', $cycleStart)
            ->where(function ($q) use ($cycleStart) {
                $q->whereNull('subscription_ends_at')
                    ->orWhere('subscription_ends_at', '>', $cycleStart);
            })
            ->chunkById(50, $callback);
    }

    /**
     * Find the latest account subscription plan for the given account ID.
     *
     * @param int $accountId
     *
     * @return AccountSubscriptionPlan|null
     */
    public function findLatestByAccountIdWithPlan(int $accountId): ?AccountSubscriptionPlan
    {
        return AccountSubscriptionPlan::with('subscriptionPlan')
            ->where('account_id', $accountId)
            ->latest('id')
            ->first();
    }

    /**
     * Activate a paid subscription window for the given ASP without touching trial dates.
     *
     * @param AccountSubscriptionPlan $asp
     *
     * @return void
     */
    public function activatePaidSubscriptionPlan(AccountSubscriptionPlan $asp): void
    {
        $plan = $asp->subscriptionPlan;
        if (!$plan || $plan->is_trial) {
            return;
        }

        $now = Carbon::now();
        $cycleStart = $now->copy()
            ->day(BillingCycleConstant::CYCLE_DAY_DUE)
            ->startOfDay();

        if ($now->day < BillingCycleConstant::CYCLE_DAY_DUE) {
            $cycleStart->subMonth();
        }

        $subscriptionEndsAt = BillingLifecycleService::nextCycleStart(
            $cycleStart->copy(),
            $plan->interval ?? AccountSubscriptionIntervalConstant::INTERVAL_MONTH
        );

        $asp->update([
            'subscription_starts_at' => $cycleStart,
            'subscription_ends_at'   => $subscriptionEndsAt,
            'locked_at'              => null,
        ]);
    }

    /**
     * Apply reactivation window to an ASP (keep trial dates, only move subscription window + unlock).
     *
     * @param AccountSubscriptionPlan $asp
     * @param SubscriptionPlan $newPlan
     * @param Carbon $startsAt
     * @param Carbon $endsAt
     *
     * @return void
     */
    public function applyReactivationWindow(AccountSubscriptionPlan $asp, SubscriptionPlan $newPlan, Carbon $startsAt, Carbon $endsAt): void
    {
        $asp->update([
            'subscription_plan_id' => $newPlan->id,
            'plan_name'            => $newPlan->name,
            'subscription_starts_at' => $startsAt,
            'subscription_ends_at'   => $endsAt,
            'locked_at'              => null,
        ]);
    }
}
