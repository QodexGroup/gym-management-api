<?php

namespace App\Console\Commands;

use App\Constant\NotificationConstant;
use App\Models\Core\CustomerMembership;
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

    /**
     * Create a new command instance.
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
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
                
                $this->line("✓ Notification sent for {$membership->customer->first_name} {$membership->customer->last_name}");
            } catch (\Throwable $th) {
                $this->error("✗ Failed to send notification for customer ID {$membership->customer_id}: {$th->getMessage()}");
                Log::error('Error in membership expiration check', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'error' => $th->getMessage(),
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
