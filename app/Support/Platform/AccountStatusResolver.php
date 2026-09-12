<?php

namespace App\Support\Platform;

use App\Constant\AccountStatusConstant;
use App\Constant\PlatformConstant;
use App\Models\Account\Account;
use App\Models\Account\AccountSubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The contract reports four account statuses; GymHub stores two.
 *
 * `accounts.status` is only `active|deactivated`. Trial and locked live on the
 * latest `account_subscription_plans` row (`trial_ends_at`, `locked_at`), and
 * `AccountSubscriptionStatusConstant` describes states no column ever holds.
 * So the richer status is DERIVED, here, in exactly one place — the SQL used
 * for list filtering and counting must agree with the PHP used for a single
 * account, or the console will show a total that does not match its own rows.
 */
class AccountStatusResolver
{
    /**
     * Derive one account's status. Expects `activeAccountSubscriptionPlan` to
     * be loaded; an account with no plan row at all reads as active.
     *
     * @param Account $account
     *
     * @return string
     */
    public static function resolve(Account $account): string
    {
        if ($account->status === AccountStatusConstant::STATUS_DEACTIVATED) {
            return PlatformConstant::ACCOUNT_DEACTIVATED;
        }

        $plan = $account->activeAccountSubscriptionPlan;

        if ($plan === null) {
            return PlatformConstant::ACCOUNT_ACTIVE;
        }

        if ($plan->locked_at !== null) {
            return PlatformConstant::ACCOUNT_LOCKED;
        }

        return self::onTrial($plan) ? PlatformConstant::ACCOUNT_TRIAL : PlatformConstant::ACCOUNT_ACTIVE;
    }

    /**
     * Trial means the trial window is still open and no paid coverage has
     * started. An expired trial is not "trial" — it is active-but-unpaid, and
     * the unpaid part shows through the account's outstanding balance.
     *
     * @param AccountSubscriptionPlan $plan
     *
     * @return bool
     */
    private static function onTrial(AccountSubscriptionPlan $plan): bool
    {
        if ($plan->trial_ends_at === null || $plan->trial_ends_at->isPast()) {
            return false;
        }

        return $plan->subscription_starts_at === null || $plan->subscription_starts_at->isFuture();
    }

    /**
     * The same rule as SQL, for filtering and counting without loading rows.
     *
     * Returns the CASE expression plus its bindings, in order. Callers must
     * splice the bindings in at the position the expression appears, which is
     * why they travel together rather than being hardcoded at each call site.
     *
     * `asp` must be joined as the account's latest subscription plan — see
     * {@see self::latestPlanJoin()}.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function sqlExpression(): array
    {
        $now = Carbon::now()->toDateTimeString();

        $sql = "CASE
            WHEN accounts.status = ? THEN ?
            WHEN asp.locked_at IS NOT NULL THEN ?
            WHEN asp.trial_ends_at IS NOT NULL
                 AND asp.trial_ends_at > ?
                 AND (asp.subscription_starts_at IS NULL OR asp.subscription_starts_at > ?) THEN ?
            ELSE ?
        END";

        return [$sql, [
            AccountStatusConstant::STATUS_DEACTIVATED,
            PlatformConstant::ACCOUNT_DEACTIVATED,
            PlatformConstant::ACCOUNT_LOCKED,
            $now,
            $now,
            PlatformConstant::ACCOUNT_TRIAL,
            PlatformConstant::ACCOUNT_ACTIVE,
        ]];
    }

    /**
     * Join the account's latest subscription plan as `asp`.
     *
     * `Account::activeAccountSubscriptionPlan()` is a `latestOfMany()`, so the
     * SQL equivalent is the row with the greatest id per account. An account
     * can have several plan rows over its life and only the newest is current.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     *
     * @return void
     */
    public static function latestPlanJoin($query): void
    {
        $query->leftJoin('account_subscription_plans as asp', 'asp.id', '=', DB::raw(
            '(select max(newest_asp.id) from account_subscription_plans as newest_asp'
            .' where newest_asp.account_id = accounts.id)'
        ));
    }
}
