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
            'pending_subscription_plan_id' => null,
            'pending_plan_effective_at' => null,
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
            'pending_subscription_plan_id' => null,
            'pending_plan_effective_at' => null,
            'locked_at'              => null,
        ]);
    }

    /**
     * Lock all active subscription plans for the given account IDs by setting locked_at.
     *
     * @param array<int> $accountIds
     *
     * @return int number of affected rows
     */
    public function lockAccountsByIds(array $accountIds): int
    {
        return AccountSubscriptionPlan::whereIn('account_id', $accountIds)
            ->whereNull('locked_at')
            ->update(['locked_at' => Carbon::now()]);
    }

    /**
     * Get account IDs that have been locked before the given date.
     *
     * @param Carbon $lockedBefore
     * @param int|null $accountId Optional account ID to filter by
     *
     * @return array<int>
     */
    public function getAccountIdsLockedBefore(Carbon $lockedBefore, ?int $accountId = null): array
    {
        return AccountSubscriptionPlan::query()
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', $lockedBefore)
            ->when($accountId !== null, fn ($q) => $q->where('account_id', $accountId))
            ->pluck('account_id')
            ->unique()
            ->all();
    }

    /**
     * Update subscription plan selection (takes effect on next billing cycle).
     * Only updates the plan selection without changing subscription dates.
     *
     * @param AccountSubscriptionPlan $asp
     * @param SubscriptionPlan $newPlan
     *
     * @return void
     */
    public function updatePlanSelection(AccountSubscriptionPlan $asp, SubscriptionPlan $newPlan, Carbon $effectiveAt): void
    {
        $asp->update([
            'pending_subscription_plan_id' => $newPlan->id,
            'pending_plan_effective_at' => $effectiveAt,
        ]);
    }

    /**
     * Apply pending plan selections when the effective date is reached.
     * Returns number of ASP rows updated.
     */
    public function applyPendingPlanSelectionsDue(Carbon $cycleStart): int
    {
        $updated = 0;

        AccountSubscriptionPlan::query()
            ->with(['pendingSubscriptionPlan'])
            ->whereNotNull('pending_subscription_plan_id')
            ->whereNotNull('pending_plan_effective_at')
            ->where('pending_plan_effective_at', '<=', $cycleStart)
            ->chunkById(100, function (Collection $plans) use (&$updated) {
                foreach ($plans as $asp) {
                    $pendingPlan = $asp->pendingSubscriptionPlan;
                    if (!$pendingPlan || $pendingPlan->is_trial) {
                        $asp->update([
                            'pending_subscription_plan_id' => null,
                            'pending_plan_effective_at' => null,
                        ]);
                        continue;
                    }

                    $asp->update([
                        'subscription_plan_id' => $pendingPlan->id,
                        'plan_name' => $pendingPlan->name,
                        'pending_subscription_plan_id' => null,
                        'pending_plan_effective_at' => null,
                    ]);
                    $updated++;
                }
            });

        return $updated;
    }
}
