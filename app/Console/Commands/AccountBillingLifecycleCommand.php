<?php

namespace App\Console\Commands;

use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AccountBillingLifecycleCommand extends Command
{
    protected $signature = 'account-billing:lifecycle
                            {--overdue : Mark current period issued invoices as overdue (5th)}
                            {--lock : Lock accounts with unpaid invoice for current period (10th)}
                            {--generate : Generate monthly, quarterly, and annual invoices for current cycle}';

    protected $description = 'Run account subscription billing lifecycle (due 5th, lock 10th; monthly, quarterly, annual invoice generation)';

    public function handle(BillingLifecycleService $lifecycle): int
    {
        $today = Carbon::now()->day;
        $runOverdue = $this->option('overdue') || $today >= BillingLifecycleService::CYCLE_DAY_DUE;
        $runLock = $this->option('lock') || $today >= BillingLifecycleService::CYCLE_DAY_LOCK;
        $runGenerate = $this->option('generate');

        if ($runGenerate) {
            $count = $lifecycle->generateInvoicesForCurrentCycle();
            $this->info("Generated {$count} invoice(s) for current cycle (monthly, quarterly, annual).");
            Log::info('Account billing: generated invoices for current cycle', ['count' => $count]);
        }

        if ($runOverdue) {
            $updated = $lifecycle->markOverdueForCurrentPeriod();
            $this->info("Marked {$updated} invoice(s) as overdue.");
            Log::info('Account billing: marked overdue', ['count' => $updated]);
        }

        if ($runLock) {
            $locked = $lifecycle->lockAccountsWithUnpaidInvoiceForCurrentPeriod();
            $this->info("Locked {$locked} account(s).");
            Log::info('Account billing: locked accounts', ['count' => $locked]);
        }

        return Command::SUCCESS;
    }
}
