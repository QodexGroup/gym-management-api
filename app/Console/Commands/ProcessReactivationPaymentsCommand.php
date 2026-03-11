<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminPaymentRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessReactivationPaymentsCommand extends Command
{
    protected $signature = 'account-billing:process-reactivations
                            {--account_id= : Optional account ID to process}
                            {--limit=200 : Max approved requests to scan per run}';

    protected $description = 'Process approved reactivation fee payments, reactivate accounts, void old unpaid invoices, and apply free month';

    /**
     * @param AdminPaymentRequestService $service
     *
     * @return int
     */
    public function handle(AdminPaymentRequestService $service): int
    {
        $accountId = $this->option('account_id') !== null ? (int) $this->option('account_id') : null;
        $limit = max(1, (int) $this->option('limit'));

        try {
            $processed = $service->processApprovedReactivations($accountId, $limit);
            $this->info("Processed {$processed} reactivation payment request(s).");
            Log::info('Account billing: processed reactivation payments', [
                'processed' => $processed,
                'account_id' => $accountId,
                'limit' => $limit,
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}

