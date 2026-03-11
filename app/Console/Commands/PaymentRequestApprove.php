<?php

namespace App\Console\Commands;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Models\Account\AccountPaymentRequest;
use App\Services\Account\AccountSubscription\AccountPaymentRequestService;
use Illuminate\Console\Command;

class PaymentRequestApprove extends Command
{
    protected $signature = 'payment-request:approve {account_id : Account ID that has a pending payment request}';

    protected $description = 'Approve a pending payment request by account ID (marks invoice as paid and activates account subscription)';

    public function handle(AccountPaymentRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        $pending = AccountPaymentRequest::where('account_id', $accountId)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->first();

        if (! $pending) {
            $this->error("No pending payment request found for account ID: {$accountId}");
            return Command::FAILURE;
        }

        try {
            $request = $service->approve($pending->id, null);
            $this->info("Payment request #{$pending->id} approved. Invoice marked as paid.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
