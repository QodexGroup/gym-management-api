<?php

namespace App\Console\Commands;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Models\Account\AccountPaymentRequest;
use App\Services\Admin\AdminPaymentRequestService;
use Illuminate\Console\Command;

class PaymentRequestReject extends Command
{
    protected $signature = 'payment-request:reject {account_id : Account ID that has a pending payment request} {--reason= : Optional rejection reason}';

    protected $description = 'Reject a pending payment request by account ID';

    public function handle(AdminPaymentRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        $reason = $this->option('reason');
        $pending = AccountPaymentRequest::where('account_id', $accountId)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->first();

        if (! $pending) {
            $this->error("No pending payment request found for account ID: {$accountId}");
            return Command::FAILURE;
        }

        try {
            $service->reject($pending->id, null, $reason);
            $this->info("Payment request #{$pending->id} rejected.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
