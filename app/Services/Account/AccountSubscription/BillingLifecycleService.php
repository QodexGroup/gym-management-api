<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountInvoiceTypeConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Constant\ReferralConstant;
use App\Jobs\SendAccountInvoiceNotificationJob;
use App\Mail\AccountInvoiceNotificationMail;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;
use App\Repositories\Account\AccountRepository;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionPlanRepository;
use App\Services\Account\ReferralService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

class BillingLifecycleService
{
    public function __construct(
        private AccountInvoiceRepository $accountInvoiceRepository,
        private AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository,
        private AccountRepository $accountRepository,
        private ReferralService $referralService
    ) {
    }

    /**
     * Billing period key (mdY) for the 5th of the given month.
     * E.g. March 2026 -> 03052026.
     */
    public static function billingPeriodForDate(Carbon $date): string
    {
        $cycleStart = $date->copy()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();

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
                $cycleStart = $now->copy()->month($quarterStartMonth)->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
                return $cycleStart->format('mdY');
            case AccountSubscriptionIntervalConstant::INTERVAL_YEAR:
                return $now->copy()->month(1)->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay()->format('mdY');
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
     * Next billing cycle start from a given date.
     */
    public static function nextCycleStart(Carbon $from, string $interval): Carbon
    {
        $next = $from->copy();
        switch ($interval) {
            case AccountSubscriptionIntervalConstant::INTERVAL_QUARTER:
                return $next->addMonths(3)->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
            case AccountSubscriptionIntervalConstant::INTERVAL_YEAR:
                return $next->addYear()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
            case AccountSubscriptionIntervalConstant::INTERVAL_MONTH:
            default:
                return $next->addMonth()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
        }
    }

    /**
     * Calculate prorated amount based on trial/subscription end date to invoice generation day (5th).
     * Uses an exclusive period end for consistent day-count calculations.
     * Returns [amount, isProrated, prorateDetails].
     *
     * @return array{amount: float, isProrated: bool, prorateDetails: array|null}
     */
    public function calculateProrate(AccountSubscriptionPlan $asp, float $planPrice, Carbon $periodStart, Carbon $periodEndExclusive): array
    {
        $effectiveEndDate = null;

        $subscriptionHasStarted = $asp->subscription_starts_at !== null;

        if (!$subscriptionHasStarted
            && $asp->trial_ends_at
            && $asp->trial_ends_at->isFuture()
            && $asp->trial_ends_at->lessThan($periodEndExclusive)
        ) {
            $effectiveEndDate = $asp->trial_ends_at;
        } elseif ($asp->subscription_ends_at && $asp->subscription_ends_at->isFuture() && $asp->subscription_ends_at->lessThan($periodEndExclusive)) {
            $effectiveEndDate = $asp->subscription_ends_at;
        }

        // If no effective end date or it's after period end, no proration needed
        if (!$effectiveEndDate) {
            return [
                'amount' => $planPrice,
                'isProrated' => false,
                'prorateDetails' => null,
            ];
        }

        // Calculate proration as "remaining uncovered service" inside the billing period.
        // periodStart..periodEndExclusive is the full billing period.
        // effectiveEndDate is when the already-paid/prepaid window ends.
        // So we charge only the remaining days: effectiveEndDate..periodEndExclusive.
        $actualEndDate = $effectiveEndDate->copy();

        // Ensure actual end date doesn't exceed period end
        if ($actualEndDate->greaterThan($periodEndExclusive)) {
            $actualEndDate = $periodEndExclusive->copy();
        }

        // Ensure actual end date is not before period start
        if ($actualEndDate->lessThan($periodStart)) {
            return [
                'amount' => 0.0,
                'isProrated' => false,
                'prorateDetails' => null,
            ];
        }

        $daysTotal = (int) $periodStart->diffInDays($periodEndExclusive);
        $daysCharged = (int) $actualEndDate->diffInDays($periodEndExclusive);

        if ($daysTotal <= 0 || $daysCharged <= 0) {
            return [
                'amount' => 0.0,
                'isProrated' => false,
                'prorateDetails' => null,
            ];
        }

        $amount = round($planPrice * $daysCharged / $daysTotal, 2);

        $prorateDetails = [
            'planPrice' => $planPrice,
            'periodStart' => $periodStart->toDateString(),
            'periodEnd' => $periodEndExclusive->copy()->subDay()->toDateString(),
            'effectiveEndDate' => $effectiveEndDate->toDateString(),
            'actualEndDate' => $actualEndDate->toDateString(),
            'daysTotal' => $daysTotal,
            'daysCharged' => $daysCharged, // remaining days from actualEndDate -> periodEndExclusive
            'proratedAmount' => $amount,
        ];

        return [
            'amount' => $amount,
            'isProrated' => true,
            'prorateDetails' => $prorateDetails,
        ];
    }

    /**
     * Generate an invoice for an account's subscription for the given billing anchor (a 5th).
     *
     * Deferred model: the account is billed only once its paid coverage has reached this
     * anchor. The invoice covers a full upcoming interval plus a one-time pro-rated bridge
     * for any gap between the previous coverage end and the anchor.
     */
    public function generateInvoiceForPeriod(AccountSubscriptionPlan $asp, string $billingPeriod, ?Carbon $activationDate = null): ?AccountInvoice
    {
        $account = $asp->account;
        $plan = $asp->subscriptionPlan;
        if (!$plan || $plan->is_trial) {
            return null;
        }

        // Idempotency: don't regenerate an invoice for this billing period.
        if ($this->accountInvoiceRepository->existsByAccountAndBillingPeriod($account->id, $billingPeriod)) {
            return null;
        }

        // Never stack a new charge while a prior invoice is still unpaid
        // (mirrors PMS doesntHave('activeSubscriptionInvoice')).
        if ($this->accountInvoiceRepository->hasPendingByAccountId($account->id)) {
            return null;
        }

        $cycleStart = Carbon::createFromFormat('mdY', $billingPeriod)->startOfDay();
        $cycleEndExclusive = self::nextCycleStart($cycleStart->copy(), $plan->interval ?? AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
        $cycleEndInclusive = $cycleEndExclusive->copy()->subDay();

        $endsAt = $asp->subscription_ends_at ? $asp->subscription_ends_at->copy()->startOfDay() : null;

        // Defer while still prepaid past this anchor.
        if ($endsAt && $endsAt->greaterThan($cycleStart)) {
            return null;
        }

        $planPrice = (float) $plan->price;
        $daysInCycle = (int) $cycleStart->diffInDays($cycleEndExclusive);

        // (1) Full upcoming interval.
        $fullAmount = $planPrice;

        // (2) One-time pro-rated bridge for the gap [ends_at -> cycleStart).
        $bridgeDays = 0;
        $bridgeAmount = 0.0;
        if ($endsAt && $endsAt->lessThan($cycleStart)) {
            $bridgeDays = (int) $endsAt->diffInDays($cycleStart);
            $perDay = $daysInCycle > 0 ? $planPrice / $daysInCycle : 0.0;
            $bridgeAmount = round($perDay * $bridgeDays, 2);
        }

        $baseTotal = round($fullAmount + $bridgeAmount, 2);

        $invoiceDetails = [
            'invoiceType' => AccountInvoiceTypeConstant::TYPE_SUBSCRIPTION,
            'subscriptionPlan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'interval' => $plan->interval,
                'price' => (float) $plan->price,
            ],
            'accountSubscriptionPlan' => [
                'id' => $asp->id,
                'planName' => $asp->plan_name,
                'trialStartsAt' => $asp->trial_starts_at?->toDateString(),
                'trialEndsAt' => $asp->trial_ends_at?->toDateString(),
                'subscriptionStartsAt' => $asp->subscription_starts_at?->toDateString(),
                'subscriptionEndsAt' => $asp->subscription_ends_at?->toDateString(),
            ],
            'fullPeriod' => [
                'from' => $cycleStart->toDateString(),
                'to' => $cycleEndInclusive->toDateString(),
                'amount' => $fullAmount,
            ],
        ];

        if ($bridgeDays > 0) {
            $invoiceDetails['bridge'] = [
                'from' => $endsAt->toDateString(),
                'to' => $cycleStart->copy()->subDay()->toDateString(),
                'days' => $bridgeDays,
                'amount' => $bridgeAmount,
            ];
        }

        // Referral discount: 5% of the plan (full) charge only — never the bridge.
        $referralDiscount = 0.0;
        if ($this->referralService->isEligibleForDiscount($account->id)) {
            $referralDiscount = $this->referralService->computeDiscount($fullAmount);
            $invoiceDetails['referralDiscount'] = [
                'percent' => ReferralConstant::DISCOUNT_PERCENT,
                'baseAmount' => $fullAmount,
                'discountAmount' => $referralDiscount,
            ];
        }

        $invoicePayload = [
            'accountId' => $account->id,
            'accountSubscriptionPlanId' => $asp->id,
            'billingPeriod' => $billingPeriod,
            'periodFrom' => $cycleStart,
            'periodTo' => $cycleEndInclusive,
            'totalAmount' => round($baseTotal - $referralDiscount, 2),
            'discountAmount' => $referralDiscount,
            'prorate' => $bridgeDays > 0 ? 1 : 0,
            'invoiceDetails' => $invoiceDetails,
        ];

        $invoice = $this->accountInvoiceRepository->createGeneratedInvoice($invoicePayload);

        // Consume referral eligibility: mark all currently-unapplied qualified referrals as spent.
        if ($referralDiscount > 0) {
            $this->referralService->consumeDiscountForInvoice($account->id, (int) $invoice->id);
        }

        Queue::push(new SendAccountInvoiceNotificationJob($invoice->id, AccountInvoiceNotificationMail::TYPE_ISSUED));

        return $invoice;
    }

    /**
     * Lock accounts that have unpaid invoice for current billing period (run on 10th).
     * Only locks accounts with PENDING invoices (not paid).
     */
    public function lockAccountsWithUnpaidInvoiceForCurrentPeriod(): int
    {
        $periods = self::currentBillingPeriodKeys();
        $invoices = $this->accountInvoiceRepository->getPendingByBillingPeriods($periods);

        if ($invoices->isEmpty()) {
            return 0;
        }

        foreach ($invoices as $inv) {
            Queue::push(new SendAccountInvoiceNotificationJob($inv->id, AccountInvoiceNotificationMail::TYPE_LOCK_NOTICE));
        }

        // Lock at the subscription-plan level instead of deactivating the account,
        $accountIds = $invoices->pluck('account_id')->unique()->values()->all();

        if (empty($accountIds)) {
            return 0;
        }
        return $this->accountSubscriptionPlanRepository->lockAccountsByIds($accountIds);
    }

    /**
     * Deactivate delinquent accounts that have been locked for at least one full billing cycle
     * and still have unpaid invoices. Intended to run at month end.
     *
     * @param int|null $accountId
     *
     * @return int number of accounts deactivated
     */
    public function deactivateDelinquentAccounts(?int $accountId = null): int
    {
        $now = Carbon::now();
        $lockedBefore = $now->copy()->subMonth();

        // Accounts with locked subscription plan before cutoff
        $lockedAccountIds = $this->accountSubscriptionPlanRepository->getAccountIdsLockedBefore($lockedBefore, $accountId);

        if (empty($lockedAccountIds)) {
            return 0;
        }

        // Those accounts that still have pending invoices
        $delinquentAccountIds = $this->accountInvoiceRepository->getAccountIdsWithPendingInvoices($lockedAccountIds);

        if (empty($delinquentAccountIds)) {
            return 0;
        }

        return $this->accountRepository->deactivateActiveAccountsByIds($delinquentAccountIds);
    }

    /**
     * Generate invoices for all intervals (monthly, quarterly, annual) for the current cycle.
     * Only generates invoices for intervals that match the current billing period.
     */
    public function generateInvoicesForCurrentCycle(): int
    {
        $now = Carbon::now();
        $day = $now->day;

        $count = 0;

        // Per-account cadence: every interval is evaluated on the account's own cycle, which
        // always lands on the 5th. All intervals therefore use the current month's 5th as the
        // candidate anchor; the deferred-billing guard skips accounts still prepaid, so a
        // quarterly/yearly account only generates when its own coverage reaches this 5th.
        if ($day === BillingCycleConstant::CYCLE_DAY_DUE) {
            $cycleStart = $now->copy()->day(BillingCycleConstant::CYCLE_DAY_DUE)->startOfDay();
            // Apply any pending plan selections that are due this billing cycle before invoice generation.
            $this->accountSubscriptionPlanRepository->applyPendingPlanSelectionsDue($cycleStart);

            $currentPeriod = self::billingPeriodForDate($now);

            $count += $this->generateInvoicesForInterval($currentPeriod, AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
            $count += $this->generateInvoicesForInterval($currentPeriod, AccountSubscriptionIntervalConstant::INTERVAL_QUARTER);
            $count += $this->generateInvoicesForInterval($currentPeriod, AccountSubscriptionIntervalConstant::INTERVAL_YEAR);
        }

        return $count;
    }

    /**
     * Generate invoices for accounts with the given plan interval for the given billing period.
     * Only generates for active subscriptions that match the billing period.
     */
    private function generateInvoicesForInterval(string $billingPeriod, string $interval): int
    {
        $count = 0;
        $cycleStart = Carbon::createFromFormat('mdY', $billingPeriod)->startOfDay();

        $this->accountSubscriptionPlanRepository->chunkBillableByInterval(
            $cycleStart,
            $interval,
            function ($plans) use ($billingPeriod, &$count) {
                foreach ($plans as $asp) {
                    $inv = $this->generateInvoiceForPeriod($asp, $billingPeriod);
                    if ($inv) {
                        $count++;
                    }
                }
            }
        );

        return $count;
    }
}
