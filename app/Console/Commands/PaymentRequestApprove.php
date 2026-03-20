<?php

namespace App\Console\Commands;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountSubscriptionPlan;
use App\Models\Account\AccountPaymentRequest;
use App\Services\Admin\AdminPaymentRequestService;
use Illuminate\Console\Command;

class PaymentRequestApprove extends Command
{
    protected $signature = 'payment-request:approve {account_id : Account ID that has a pending payment request}';

    protected $description = 'Approve a pending payment request by account ID (marks invoice as paid and activates account subscription)';

    public function handle(AdminPaymentRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        // Approve the latest pending request for supported flows.
        // If a single pending row is malformed/legacy, we should not fail the entire approval run.
        $pendingRequests = AccountPaymentRequest::query()
            ->where('account_id', $accountId)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->whereIn('payment_transaction', [
                AccountInvoice::class,
                AccountSubscriptionPlan::class,
                'Reactivation Fee',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($pendingRequests->isEmpty()) {
            $this->error("No pending supported payment requests found for account ID: {$accountId}");
            return Command::FAILURE;
        }

        foreach ($pendingRequests as $pending) {
            try {
                $service->approve($pending->id, null);
                $this->info("Payment request #{$pending->id} approved.");
                return Command::SUCCESS;
            } catch (\Throwable $e) {
                // Try the next candidate (e.g. skip a malformed/legacy pending row).
                $this->error("Failed to approve payment request #{$pending->id}: {$e->getMessage()}");
            }
        }

        $this->error("All pending payment requests failed for account ID: {$accountId}");
        return Command::FAILURE;
    }
}
