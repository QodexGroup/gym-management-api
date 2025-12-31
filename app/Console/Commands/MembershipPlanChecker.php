<?php

namespace App\Console\Commands;

use App\Models\Core\CustomerMembership;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MembershipPlanChecker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membership:update-expired-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update membership status to expired for memberships that have passed their end date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired memberships...');

        // Find all active memberships that have expired
        $expiredMemberships = CustomerMembership::where('account_id', 1)
            ->where('membership_end_date', '<', Carbon::now()->startOfDay())
            ->where('status', '!=', 'Expired') // Only update if not already expired
            ->with('customer')
            ->get();

        $this->info("Found {$expiredMemberships->count()} expired memberships to update.");

        $updatedCount = 0;

        foreach ($expiredMemberships as $membership) {
            try {
                // Update status to Expired
                $membership->status = 'Expired';
                $membership->save();

                $updatedCount++;
                
                $customerName = $membership->customer 
                    ? "{$membership->customer->first_name} {$membership->customer->last_name}"
                    : "Customer ID {$membership->customer_id}";
                
                $this->line("✓ Updated membership for {$customerName} to Expired");
                
                Log::info('Membership status updated to expired', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'end_date' => $membership->membership_end_date,
                ]);
            } catch (\Throwable $th) {
                $this->error("✗ Failed to update membership ID {$membership->id}: {$th->getMessage()}");
                
                Log::error('Error updating expired membership status', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'error' => $th->getMessage(),
                ]);
            }
        }

        $this->info("Membership status update completed. {$updatedCount} memberships updated to Expired.");
        
        Log::info('Membership status update completed', [
            'total_expired' => $expiredMemberships->count(),
            'updated_count' => $updatedCount,
        ]);

        return Command::SUCCESS;
    }
}
