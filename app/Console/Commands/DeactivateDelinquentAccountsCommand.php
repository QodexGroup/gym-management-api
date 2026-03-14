<?php

namespace App\Console\Commands;

use App\Services\Account\AccountSubscription\BillingLifecycleService;
use Illuminate\Console\Command;

class DeactivateDelinquentAccountsCommand extends Command
{
    protected $signature = 'account-billing:deactivate-accounts {--accountId=}';
    protected $description = 'Deactivate long-locked, unpaid accounts (run at month end).';

    public function handle(BillingLifecycleService $billingLifecycleService): int
    {
        $accountId = $this->option('accountId');
        $id = $accountId !== null ? (int) $accountId : null;

        $count = $billingLifecycleService->deactivateDelinquentAccounts($id);

        $this->info("Deactivated {$count} delinquent account(s).");

        return self::SUCCESS;
    }
}

