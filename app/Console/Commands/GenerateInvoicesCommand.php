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
                            {--force : Force generation even if not on the 5th}';

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

        $count = $lifecycle->generateInvoicesForCurrentCycle();

        if ($count > 0) {
            $this->info("Generated {$count} invoice(s) for current cycle.");
            Log::info('Account billing: generated invoices for current cycle', ['count' => $count]);
        } else {
            $this->info("No invoices generated for current cycle.");
        }

        return Command::SUCCESS;
    }
}
