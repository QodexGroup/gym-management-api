<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountSubscriptionIntervalConstant;
use App\Models\Account;
use App\Jobs\SendAccountInvoiceNotificationJob;
use App\Mail\AccountInvoiceNotificationMail;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\PlatformSubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class BillingLifecycleService
{
    /** Billing cycle day of month (due date). */
    public const CYCLE_DAY_DUE = 5;

    /** Lock account if unpaid by this day. */
    public const CYCLE_DAY_LOCK = 10;

    /**
     * Billing period key (mdY) for the 5th of the given month.
     * E.g. March 2026 -> 03052026.
     */
    public static function billingPeriodForDate(Carbon $date): string
    {
        $cycleStart = $date->copy()->day(self::CYCLE_DAY_DUE)->startOfDay();

        return $cycleStart->format('mdY');
    }

    /**
     * Current billing period key for the given interval (5th-based).
     * Monthly: 5th of current month. Quarterly: 5th of Jan/Apr/Jul/Oct. Annual: 5th of January.
     */
    public static function currentBillingPeriodForInterval(string $interval): string
    {
        $now = Carbon::now();
        switch ($interval) {
            case AccountSubscriptionIntervalConstant::INTERVAL_QUARTER:
                $month = (int) $now->format('n');
                $quarterStartMonth = (floor(($month - 1) / 3) * 3) + 1;
                $cycleStart = $now->copy()->month($quarterStartMonth)->day(self::CYCLE_DAY_DUE)->startOfDay();
                return $cycleStart->format('mdY');
            case AccountSubscriptionIntervalConstant::INTERVAL_YEAR:
                return $now->copy()->month(1)->day(self::CYCLE_DAY_DUE)->startOfDay()->format('mdY');
            case AccountSubscriptionIntervalConstant::INTERVAL_MONTH:
            default:
                return self::billingPeriodForDate($now);
        }
    }

    /**
     * All current billing period keys (for overdue/lock: any invoice due "this" cycle).
     *
     * @return array<string>
     */
    public static function currentBillingPeriodKeys(): array
    {
        return array_values(array_unique([
            self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH),
            self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_QUARTER),
            self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_YEAR),
        ]));
    }

    /**
     * Current billing cycle start date (5th of current month).
     */
    public static function currentCycleStart(): Carbon
    {
        return Carbon::now()->day(self::CYCLE_DAY_DUE)->startOfDay();
    }

    /**
     * Next billing cycle start from a given date.
     */
    public static function nextCycleStart(Carbon $from, string $interval): Carbon
    {
        $next = $from->copy();
        switch ($interval) {
            case AccountSubscriptionIntervalConstant::INTERVAL_QUARTER:
                return $next->addMonths(3)->day(self::CYCLE_DAY_DUE)->startOfDay();
            case AccountSubscriptionIntervalConstant::INTERVAL_YEAR:
                return $next->addYear()->day(self::CYCLE_DAY_DUE)->startOfDay();
            case AccountSubscriptionIntervalConstant::INTERVAL_MONTH:
            default:
                return $next->addMonth()->day(self::CYCLE_DAY_DUE)->startOfDay();
        }
    }

    /**
     * Days in billing period from cycle start for the given interval.
     */
    public static function daysInPeriod(Carbon $cycleStart, string $interval): int
    {
        $end = self::nextCycleStart($cycleStart->copy(), $interval);

        return (int) $cycleStart->diffInDays($end);
    }

    /**
     * Prorated amount for partial period. Returns [amount, details].
     *
     * @return array{amount: float, details: array}
     */
    public function prorate(float $planPrice, Carbon $periodStart, Carbon $periodEnd, Carbon $fromDate): array
    {
        $daysTotal = (int) $periodStart->diffInDays($periodEnd);
        $daysFrom = (int) $fromDate->diffInDays($periodEnd);
        if ($daysTotal <= 0) {
            return ['amount' => 0.0, 'details' => ['prorated' => false, 'reason' => 'invalid_period']];
        }
        $amount = round($planPrice * $daysFrom / $daysTotal, 2);
        $details = [
            'prorated' => true,
            'plan_price' => $planPrice,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'from_date' => $fromDate->toDateString(),
            'days_total' => $daysTotal,
            'days_charged' => $daysFrom,
            'amount' => $amount,
        ];

        return ['amount' => $amount, 'details' => $details];
    }

    /**
     * Generate invoice for an account's current subscription plan for the given billing period.
     * Idempotent: no duplicate per account + billing_period.
     */
    public function generateInvoiceForPeriod(
        AccountSubscriptionPlan $asp,
        string $billingPeriod,
        ?Carbon $activationDate = null
    ): ?AccountInvoice {
        $account = $asp->account;
        $plan = $asp->platformPlan;
        if (!$plan || $plan->is_trial) {
            return null;
        }

        $exists = AccountInvoice::where('account_id', $account->id)
            ->where('billing_period', $billingPeriod)
            ->exists();
        if ($exists) {
            return null;
        }

        $cycleStart = Carbon::createFromFormat('mdY', $billingPeriod)->startOfDay();
        $cycleEnd = self::nextCycleStart($cycleStart->copy(), $plan->interval ?? 'month');
        $st = $asp->subscription_starts_at;
        $fromDate = $activationDate ?? ($st ? $st->copy() : $cycleStart);
        if ($fromDate->greaterThan($cycleEnd)) {
            return null;
        }
        $proration = $this->prorate(
            (float) $plan->price,
            $cycleStart,
            $cycleEnd,
            $fromDate->greaterThan($cycleStart) ? $fromDate : $cycleStart
        );
        $invoiceNumber = $this->nextInvoiceNumber();
        $invoice = AccountInvoice::create([
            'account_id' => $account->id,
            'account_subscription_plan_id' => $asp->id,
            'invoice_number' => $invoiceNumber,
            'billing_period' => $billingPeriod,
            'plan_name' => $plan->name,
            'plan_interval' => $plan->interval,
            'plan_price' => $plan->price,
            'billing_cycle_start_at' => $cycleStart,
            'status' => AccountInvoice::STATUS_ISSUED,
            'invoice_details' => $proration['details'],
        ]);

        Queue::push(new SendAccountInvoiceNotificationJob($invoice->id, AccountInvoiceNotificationMail::TYPE_ISSUED));

        return $invoice;
    }

    /**
     * Next global invoice number (#0000001 style).
     */
    public function nextInvoiceNumber(): string
    {
        $max = (int) AccountInvoice::max(DB::raw('CAST(SUBSTRING(invoice_number, 2) AS UNSIGNED)'));
        $next = $max + 1;

        return '#' . str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Mark unpaid issued invoices for the current period as overdue (run on/after 5th).
     * Includes monthly, quarterly, and annual invoices whose billing_period is current for their interval.
     */
    public function markOverdueForCurrentPeriod(): int
    {
        $periods = self::currentBillingPeriodKeys();
        $invoices = AccountInvoice::whereIn('billing_period', $periods)
            ->where('status', AccountInvoice::STATUS_ISSUED)
            ->get();
        foreach ($invoices as $inv) {
            Queue::push(new SendAccountInvoiceNotificationJob($inv->id, AccountInvoiceNotificationMail::TYPE_OVERDUE));
        }
        $updated = AccountInvoice::whereIn('billing_period', $periods)
            ->where('status', AccountInvoice::STATUS_ISSUED)
            ->update(['status' => AccountInvoice::STATUS_OVERDUE]);

        return $updated;
    }

    /**
     * Lock accounts that have unpaid invoice for current billing period (run on/after 10th).
     * Includes monthly, quarterly, and annual invoices.
     */
    public function lockAccountsWithUnpaidInvoiceForCurrentPeriod(): int
    {
        $periods = self::currentBillingPeriodKeys();
        $invoices = AccountInvoice::whereIn('billing_period', $periods)
            ->whereIn('status', [AccountInvoice::STATUS_ISSUED, AccountInvoice::STATUS_OVERDUE])
            ->get();
        foreach ($invoices as $inv) {
            Queue::push(new SendAccountInvoiceNotificationJob($inv->id, AccountInvoiceNotificationMail::TYPE_LOCK_NOTICE));
        }
        $accountIds = $invoices->pluck('account_id')->unique();
        return Account::whereIn('id', $accountIds)
            ->where('subscription_status', 'active')
            ->update(['subscription_status' => 'locked']);
    }

    /**
     * Generate invoices for all accounts in current billing cycle (monthly plans only).
     */
    public function generateMonthlyInvoicesForCurrentCycle(): int
    {
        $period = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        return $this->generateInvoicesForInterval($period, AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
    }

    /**
     * Generate invoices for accounts on quarterly plans for the current quarter (5th of Jan/Apr/Jul/Oct).
     */
    public function generateQuarterlyInvoicesForCurrentCycle(): int
    {
        $period = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_QUARTER);
        return $this->generateInvoicesForInterval($period, AccountSubscriptionIntervalConstant::INTERVAL_QUARTER);
    }

    /**
     * Generate invoices for accounts on annual plans for the current year (5th of January).
     */
    public function generateAnnualInvoicesForCurrentCycle(): int
    {
        $period = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_YEAR);
        return $this->generateInvoicesForInterval($period, AccountSubscriptionIntervalConstant::INTERVAL_YEAR);
    }

    /**
     * Generate invoices for all intervals (monthly, quarterly, annual) for the current cycle.
     */
    public function generateInvoicesForCurrentCycle(): int
    {
        return $this->generateMonthlyInvoicesForCurrentCycle()
            + $this->generateQuarterlyInvoicesForCurrentCycle()
            + $this->generateAnnualInvoicesForCurrentCycle();
    }

    /**
     * Generate invoices for accounts with the given plan interval for the given billing period.
     */
    private function generateInvoicesForInterval(string $billingPeriod, string $interval): int
    {
        $count = 0;
        AccountSubscriptionPlan::with(['account', 'platformPlan'])
            ->whereHas('platformPlan', fn ($q) => $q->where('interval', $interval)->where('is_trial', false))
            ->whereHas('account', fn ($q) => $q->where('subscription_status', 'active'))
            ->chunkById(50, function ($plans) use ($billingPeriod, &$count) {
                foreach ($plans as $asp) {
                    $inv = $this->generateInvoiceForPeriod($asp, $billingPeriod);
                    if ($inv) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
