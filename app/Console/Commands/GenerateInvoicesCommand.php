<?php

namespace App\Console\Commands;

use App\Constant\BillingCycleConstant;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateInvoicesCommand extends Command
{
    protected $signature = 'account-billing:generate-invoices
                            {--force : Force generation even if not on the 5th}
                            {--account_id= : Only generate for this account; omit to run for all}';

    protected $description = 'Generate invoices for accounts based on their subscription plan interval (runs on 5th of each month)';

    public function handle(BillingLifecycleService $lifecycle): int
    {
        $today = Carbon::now();
        $day = $today->day;

        // Only run on the 5th unless forced
        if (!$this->option('force') && $day !== BillingCycleConstant::CYCLE_DAY_DUE) {
            $this->warn("Invoice generation runs on the 5th of each month. Use --force to override.");
            return Command::SUCCESS;
        }

        $accountId = $this->option('account_id');
        $accountId = $accountId === null || $accountId === '' ? null : (int) $accountId;

        $count = $lifecycle->generateInvoicesForCurrentCycle((bool) $this->option('force'), $accountId);

        $scope = $accountId === null ? 'current cycle' : "account #{$accountId}";

        if ($count > 0) {
            $this->info("Generated {$count} invoice(s) for {$scope}.");
            Log::info('Account billing: generated invoices', ['count' => $count, 'account_id' => $accountId]);
        } else {
            // Say WHY nothing happened: every skip reason in generateInvoiceForPeriod
            // is a legitimate state, and "nothing generated" alone reads as a failure.
            $this->info("No invoices generated for {$scope}.");
            $this->line('Nothing was due: the account is still prepaid past this cycle, already has an invoice for it, has an unpaid invoice pending, or is on a trial plan.');
        }

        return Command::SUCCESS;
    }
}
