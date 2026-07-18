<?php

namespace App\Console\Commands;

use App\Constant\MembershipSettingConstant;
use App\Constant\NotificationConstant;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
use App\Repositories\Core\CustomerRepository;
use App\Services\Account\AccountSystemSettingService;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckMembershipExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membership:check-expiration {--account_id= : Optional account ID to run for a single account; omit to run for all accounts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for memberships expiring within the threshold and send notifications';

    protected $notificationService;
    protected $customerBillRepository;
    protected $customerRepository;
    protected AccountSystemSettingService $membershipSettingService;

    /** Per-account settings cache to avoid re-fetching while iterating memberships. */
    private array $accountSettingsCache = [];

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService, CustomerBillRepository $customerBillRepository, CustomerRepository $customerRepository, AccountSystemSettingService $membershipSettingService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->customerBillRepository = $customerBillRepository;
        $this->customerRepository = $customerRepository;
        $this->membershipSettingService = $membershipSettingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->output) {
            $this->info('Checking for expiring memberships...');
        }

        $threshold = NotificationConstant::MEMBERSHIP_EXPIRATION_DAYS_THRESHOLD;
        $thresholdDate = Carbon::now()->addDays($threshold);

        $query = CustomerMembership::query()
            ->where('membership_end_date', '<=', $thresholdDate);
        $accountId = $this->option('account_id');
        if ($accountId !== null && $accountId !== '') {
            $query->where('account_id', (int) $accountId);
        }

        // Get memberships expiring within threshold
        $expiringMemberships = $query
            ->where('membership_end_date', '>=', Carbon::now())
            ->with(['customer', 'membershipPlan', 'pendingPlan'])
            ->get();

        if ($this->output) {
            $this->info("Found {$expiringMemberships->count()} memberships expiring within {$threshold} days.");
        }

        $notificationsSent = 0;

        foreach ($expiringMemberships as $membership) {
            try {
                // The notification service will check if notification was already sent
                $this->notificationService->createMembershipExpiringNotification($membership);
                $notificationsSent++;

                // A scheduled (next-renewal) plan change bills the PENDING plan for the
                // upcoming period. The membership is not switched here - the switch happens
                // when the renewal bill is paid, so an unpaid renewal never loses the old plan.
                $renewalPlan = ($membership->pending_plan_id && $membership->pendingPlan)
                    ? $membership->pendingPlan
                    : $membership->membershipPlan;

                // Work out the next cycle. The natural next-period start is the day after the
                // current coverage; a full plan period from there is where it would normally end.
                $naturalStartDate = Carbon::parse($membership->membership_end_date);
                $cycleStart = $naturalStartDate->copy()->addDay();
                $naturalNextStart = $renewalPlan->calculateEndDate($cycleStart)->copy()->addDay();
                $alignedNextStart = $this->resolveNextPeriodStartDate($naturalNextStart, $membership->account_id);

                // Fixed-day alignment is a monthly concept; a sub-monthly plan (days/weeks)
                // would balloon into a much larger prorated cycle, so it keeps normal renewals.
                $supportsFixedDayAlignment = in_array($renewalPlan->plan_interval, ['months', 'years'], true);

                if ($supportsFixedDayAlignment && $alignedNextStart->greaterThan($naturalNextStart)) {
                    // Fixed-day billing, member not yet aligned: ONE prorated "month + gap"
                    // cycle — a full plan period stretched to the next billing day, dated at
                    // the cycle start (payable now) so the member stays continuously active.
                    // Subsequent cycles are clean full periods on the billing day.
                    $coverageEnd = $alignedNextStart->copy()->subDay();

                    if (!$this->customerBillRepository->automatedBillExists(
                        $membership->customer_id,
                        $membership->account_id,
                        $renewalPlan->id,
                        $cycleStart
                    )) {
                        $this->customerBillRepository->createProratedRenewalBill(
                            $membership->customer_id,
                            $renewalPlan,
                            $cycleStart,
                            $coverageEnd
                        );

                        $this->customerRepository->findCustomerById($membership->customer_id, $membership->account_id)->recalculateBalance();

                        if ($this->output) {
                            $this->line("✓ Created prorated alignment bill for {$membership->customer->first_name} {$membership->customer->last_name} ({$cycleStart->format('M d, Y')} - {$coverageEnd->format('M d, Y')})");
                        }
                    } elseif ($this->output) {
                        $this->line("✓ Alignment bill already exists for {$membership->customer->first_name} {$membership->customer->last_name}");
                    }
                } else {
                    // Anniversary billing, or already aligned to the billing day: normal
                    // full-period renewal bill dated at the (possibly snapped) period start.
                    $nextPeriodStartDate = $this->resolveNextPeriodStartDate($naturalStartDate, $membership->account_id);
                    $nextPeriodEndDate = $renewalPlan->calculateEndDate($nextPeriodStartDate);

                    if (!$this->customerBillRepository->automatedBillExists(
                        $membership->customer_id,
                        $membership->account_id,
                        $renewalPlan->id,
                        $nextPeriodStartDate
                    )) {
                        $this->customerBillRepository->createAutomatedBill(
                            $membership->account_id,
                            $membership->customer_id,
                            $renewalPlan->id,
                            $renewalPlan->price,
                            $nextPeriodStartDate
                        );

                        $this->customerRepository->findCustomerById($membership->customer_id, $membership->account_id)->recalculateBalance();

                        if ($this->output) {
                            $this->line("✓ Created automated bill for {$membership->customer->first_name} {$membership->customer->last_name} (Next period: {$nextPeriodStartDate->format('M d, Y')} - {$nextPeriodEndDate->format('M d, Y')})");
                        }
                    } elseif ($this->output) {
                        $this->line("✓ Automated bill already exists for {$membership->customer->first_name} {$membership->customer->last_name}");
                    }
                }

                if ($this->output) {
                    $this->line("✓ Notification sent for {$membership->customer->first_name} {$membership->customer->last_name}");
                }
            } catch (\Throwable $th) {
                if ($this->output) {
                    $this->error("✗ Failed to process membership ID {$membership->id} for customer ID {$membership->customer_id}: {$th->getMessage()}");
                }
                Log::error('Error in membership expiration check', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ]);
            }
        }

        if ($this->output) {
            $this->info("Membership expiration check completed. {$notificationsSent} notifications sent.");
        }
        Log::info('Membership expiration check completed', [
            'total_expiring' => $expiringMemberships->count(),
            'notifications_sent' => $notificationsSent,
        ]);

        return Command::SUCCESS;
    }

    /**
     * Resolve the renewal bill / next-period start date for an account, honouring the
     * account's billing anchor. Anniversary anchor keeps the natural renewal date;
     * fixed-day anchor snaps to the configured day of month, on or after the natural date.
     *
     * @param Carbon $naturalStartDate
     * @param int $accountId
     *
     * @return Carbon
     */
    private function resolveNextPeriodStartDate(Carbon $naturalStartDate, int $accountId): Carbon
    {
        $settings = $this->getAccountSettings($accountId);

        if (($settings['billingAnchor'] ?? null) !== MembershipSettingConstant::ANCHOR_FIXED_DAY) {
            return $naturalStartDate;
        }

        $fixedDay = (int) ($settings['fixedBillingDay'] ?? 0);
        if ($fixedDay < 1) {
            return $naturalStartDate;
        }

        $candidate = $naturalStartDate->copy()->day(min($fixedDay, $naturalStartDate->daysInMonth));

        // Never bill before the natural renewal date; roll to the fixed day next month.
        if ($candidate->lessThan($naturalStartDate)) {
            $nextMonth = $naturalStartDate->copy()->addMonthNoOverflow()->startOfMonth();
            $candidate = $nextMonth->day(min($fixedDay, $nextMonth->daysInMonth));
        }

        return $candidate;
    }

    /**
     * Fetch (and cache) the membership settings for an account.
     *
     * @param int $accountId
     *
     * @return array<string, mixed>
     */
    private function getAccountSettings(int $accountId): array
    {
        if (!isset($this->accountSettingsCache[$accountId])) {
            $this->accountSettingsCache[$accountId] = $this->membershipSettingService->getForAccount($accountId);
        }

        return $this->accountSettingsCache[$accountId];
    }
}
