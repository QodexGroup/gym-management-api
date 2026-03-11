<?php

namespace App\Services\Account\AccountSubscription;

use App\Constant\AccountInvoiceTypeConstant;
use App\Constant\AccountInvoiceStatusConstant;
use App\Constant\AccountSubscriptionIntervalConstant;
use App\Constant\BillingCycleConstant;
use App\Jobs\SendAccountInvoiceNotificationJob;
use App\Mail\AccountInvoiceNotificationMail;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;
use App\Repositories\Account\AccountRepository;
use App\Repositories\Account\AccountSubscription\AccountInvoiceRepository;
use App\Repositories\Account\AccountSubscription\AccountSubscriptionPlanRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;

class BillingLifecycleService
{
    public function __construct(
        private AccountInvoiceRepository $accountInvoiceRepository,
        private AccountSubscriptionPlanRepository $accountSubscriptionPlanRepository,
        private AccountRepository $accountRepository
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
        $invoiceGenerationDay = BillingCycleConstant::CYCLE_DAY_DUE;

        // Determine the effective end date (trial_ends_at or subscription_ends_at)
        $effectiveEndDate = null;
        if ($asp->trial_ends_at && $asp->trial_ends_at->isFuture() && $asp->trial_ends_at->lessThan($periodEndExclusive)) {
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

        // Calculate proration: from period start to the 5th of the month containing effective end date
        $actualEndDate = $effectiveEndDate->copy();

        // If effective end date is on or before the 5th of that month, use it
        // Otherwise, calculate until the 5th of that month
        if ($actualEndDate->day <= $invoiceGenerationDay) {
            // Use the effective end date as-is (it's on or before the 5th)
        } else {
            // Calculate until the 5th of the month containing the effective end date
            $actualEndDate = $actualEndDate->copy()->day($invoiceGenerationDay)->startOfDay();
        }

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
        $daysCharged = (int) $periodStart->diffInDays($actualEndDate);

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
            'daysCharged' => $daysCharged,
            'proratedAmount' => $amount,
        ];

        return [
            'amount' => $amount,
            'isProrated' => true,
            'prorateDetails' => $prorateDetails,
        ];
    }

    /**
     * Generate invoice for an account's current subscription plan for the given billing period.
     */
    public function generateInvoiceForPeriod(AccountSubscriptionPlan $asp, string $billingPeriod, ?Carbon $activationDate = null): ?AccountInvoice
    {
        $account = $asp->account;
        $plan = $asp->subscriptionPlan;
        if (!$plan || $plan->is_trial) {
            return null;
        }

        $exists = $this->accountInvoiceRepository->existsByAccountAndBillingPeriod($account->id, $billingPeriod);
        if ($exists) {
            return null;
        }

        $cycleStart = Carbon::createFromFormat('mdY', $billingPeriod)->startOfDay();
        $cycleEndExclusive = self::nextCycleStart($cycleStart->copy(), $plan->interval ?? 'month');
        $cycleEndInclusive = $cycleEndExclusive->copy()->subDay();
        $st = $asp->subscription_starts_at;
        $fromDate = $activationDate ?? ($st ? $st->copy() : $cycleStart);
        if ($fromDate->greaterThan($cycleEndExclusive)) {
            return null;
        }

        // Calculate proration
        $proration = $this->calculateProrate($asp, (float) $plan->price, $cycleStart, $cycleEndExclusive);

        // Build invoice details with account subscription plan data
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
        ];

        // Add prorate details if prorated
        if ($proration['isProrated'] && $proration['prorateDetails']) {
            $invoiceDetails['prorate'] = $proration['prorateDetails'];
        }

        $invoicePayload = [
            'accountId' => $account->id,
            'accountSubscriptionPlanId' => $asp->id,
            'billingPeriod' => $billingPeriod,
            'periodFrom' => $cycleStart,
            'periodTo' => $cycleEndInclusive,
            'totalAmount' => $proration['amount'],
            'prorate' => $proration['isProrated'] ? 1 : 0,
            'invoiceDetails' => $invoiceDetails,
        ];

        $invoice = $this->accountInvoiceRepository->createGeneratedInvoice($invoicePayload);

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

        $accountIds = $invoices->pluck('account_id')->unique()->values()->all();

        return $this->accountRepository->deactivateActiveAccountsByIds($accountIds);
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

        // Generate invoices for all intervals on the 5th (account-based due filtering is handled in repository queries).
        if ($day === BillingCycleConstant::CYCLE_DAY_DUE) {
            $monthlyPeriod = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_MONTH);
            $count += $this->generateInvoicesForInterval($monthlyPeriod, AccountSubscriptionIntervalConstant::INTERVAL_MONTH);

            $quarterlyPeriod = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_QUARTER);
            $count += $this->generateInvoicesForInterval($quarterlyPeriod, AccountSubscriptionIntervalConstant::INTERVAL_QUARTER);

            $annualPeriod = self::currentBillingPeriodForInterval(AccountSubscriptionIntervalConstant::INTERVAL_YEAR);
            $count += $this->generateInvoicesForInterval($annualPeriod, AccountSubscriptionIntervalConstant::INTERVAL_YEAR);
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
