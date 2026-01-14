<?php

namespace App\Console\Commands;

use App\Constant\CustomerMembershipConstant;
use App\Models\Core\CustomerMembership;
use App\Repositories\Core\CustomerBillRepository;
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

    protected $customerBillRepository;

    /**
     * Create a new command instance.
     */
    public function __construct(CustomerBillRepository $customerBillRepository)
    {
        parent::__construct();
        $this->customerBillRepository = $customerBillRepository;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired memberships...');

        // Find all active memberships that have expired
        $expiredMemberships = CustomerMembership::where('account_id', 1)
            ->where('membership_end_date', '<', Carbon::now()->startOfDay())
            ->where('status', '!=', CustomerMembershipConstant::STATUS_EXPIRED) // Only update if not already expired
            ->with(['customer', 'membershipPlan'])
            ->get();

        $this->info("Found {$expiredMemberships->count()} expired memberships to check.");

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($expiredMemberships as $membership) {
            try {
                // Check if there's an automated bill for the renewal period (bill_date = membership_end_date)
                $renewalBillDate = Carbon::parse($membership->membership_end_date);
                $automatedBill = $this->customerBillRepository->findAutomatedBill(
                    $membership->customer_id,
                    $membership->account_id,
                    $membership->membership_plan_id,
                    $renewalBillDate
                );

                // If automated bill exists and has been paid (even partially), skip expiration
                if ($automatedBill && $automatedBill->paid_amount > 0) {
                    $skippedCount++;
                    $customerName = $membership->customer
                        ? "{$membership->customer->first_name} {$membership->customer->last_name}"
                        : "Customer ID {$membership->customer_id}";

                    $this->line("⊘ Skipped {$customerName} - Automated bill has payment (Bill ID: {$automatedBill->id}, Paid: {$automatedBill->paid_amount})");
                    continue;
                }

                // Update status to Expired (no payment made on automated bill)
                $membership->status = CustomerMembershipConstant::STATUS_EXPIRED;
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
                    'automated_bill_exists' => $automatedBill ? true : false,
                    'bill_paid' => $automatedBill ? ($automatedBill->paid_amount > 0) : false,
                ]);
            } catch (\Throwable $th) {
                $this->error("✗ Failed to update membership ID {$membership->id}: {$th->getMessage()}");

                Log::error('Error updating expired membership status', [
                    'membership_id' => $membership->id,
                    'customer_id' => $membership->customer_id,
                    'error' => $th->getMessage(),
                    'trace' => $th->getTraceAsString(),
                ]);
            }
        }

        $this->info("Membership status update completed. {$updatedCount} memberships updated to Expired, {$skippedCount} skipped (payment made).");

        Log::info('Membership status update completed', [
            'total_expired' => $expiredMemberships->count(),
            'updated_count' => $updatedCount,
            'skipped_count' => $skippedCount,
        ]);

        return Command::SUCCESS;
    }
}
