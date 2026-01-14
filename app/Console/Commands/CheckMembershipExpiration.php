<?php

namespace App\Console\Commands;

use App\Constant\NotificationConstant;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
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
    protected $signature = 'membership:check-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for memberships expiring within the threshold and send notifications';

    protected $notificationService;
    protected $customerBillRepository;
    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService, CustomerBillRepository $customerBillRepository)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->customerBillRepository = $customerBillRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expiring memberships...');

        $threshold = NotificationConstant::MEMBERSHIP_EXPIRATION_DAYS_THRESHOLD;
        $thresholdDate = Carbon::now()->addDays($threshold);

        // Get memberships expiring within threshold
        $expiringMemberships = CustomerMembership::where('account_id', 1)
            ->where('membership_end_date', '<=', $thresholdDate)
            ->where('membership_end_date', '>=', Carbon::now())
            ->with(['customer', 'membershipPlan'])
            ->get();

        $this->info("Found {$expiringMemberships->count()} memberships expiring within {$threshold} days.");

        $notificationsSent = 0;

        foreach ($expiringMemberships as $membership) {
            try {
                // The notification service will check if notification was already sent
                $this->notificationService->createMembershipExpiringNotification($membership);
                $notificationsSent++;

                // Calculate next period dates
                $nextPeriodStartDate = Carbon::parse($membership->membership_end_date);
                $nextPeriodEndDate = $membership->membershipPlan->calculateEndDate($nextPeriodStartDate);

                // Check if automated bill already exists for this renewal period
                if (!$this->customerBillRepository->automatedBillExists(
                    $membership->customer_id,
                    $membership->account_id,
                    $membership->membership_plan_id,
                    $nextPeriodStartDate
                )) {
                    // Create automated bill for the next period
                    $this->customerBillRepository->createAutomatedBill(
                        $membership->account_id,
                        $membership->customer_id,
                        $membership->membership_plan_id,
                        $membership->membershipPlan->price,
                        $nextPeriodStartDate
                    );

                    $this->line("✓ Created automated bill for {$membership->customer->first_name} {$membership->customer->last_name} (Next period: {$nextPeriodStartDate->format('M d, Y')} - {$nextPeriodEndDate->format('M d, Y')})");
                } else {
                    $this->line("✓ Automated bill already exists for {$membership->customer->first_name} {$membership->customer->last_name}");
                }

                $this->line("✓ Notification sent for {$membership->customer->first_name} {$membership->customer->last_name}");
            } catch (\Throwable $th) {
                $this->error("✗ Failed to process membership ID {$membership->id} for customer ID {$membership->customer_id}: {$th->getMessage()}");
                Log::error('Error in membership expiration check', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ]);
            }
        }

        $this->info("Membership expiration check completed. {$notificationsSent} notifications sent.");
        Log::info('Membership expiration check completed', [
            'total_expiring' => $expiringMemberships->count(),
            'notifications_sent' => $notificationsSent,
        ]);

        return Command::SUCCESS;
    }
}
