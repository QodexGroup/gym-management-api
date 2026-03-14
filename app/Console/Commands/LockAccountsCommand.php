<?php

namespace App\Console\Commands;

use App\Constant\BillingCycleConstant;
use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LockAccountsCommand extends Command
{
    protected $signature = 'account-billing:lock-accounts
                            {--force : Force lock even if not on the 10th}';

    protected $description = 'Lock accounts with unpaid invoices for current period (runs on 10th of each month)';

    public function handle(BillingLifecycleService $lifecycle): int
    {
        $today = Carbon::now();
        $day = $today->day;

        // Only run on the 10th unless forced
        if (!$this->option('force') && $day !== BillingCycleConstant::CYCLE_DAY_LOCK) {
            $this->warn("Account locking runs on the 10th of each month. Use --force to override.");
            return Command::SUCCESS;
        }

        $locked = $lifecycle->lockAccountsWithUnpaidInvoiceForCurrentPeriod();

        if ($locked > 0) {
            $this->info("Locked {$locked} account(s) with unpaid invoices.");
            Log::info('Account billing: locked accounts', ['count' => $locked]);
        } else {
            $this->info("No accounts locked.");
        }

        return Command::SUCCESS;
    }
}
