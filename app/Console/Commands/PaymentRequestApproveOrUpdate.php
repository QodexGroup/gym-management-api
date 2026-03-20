<?php

namespace App\Console\Commands;

use App\Constant\AccountPaymentRequestStatusConstant;
use App\Models\Account\AccountInvoice;
use App\Models\Account\AccountPaymentRequest;
use App\Models\Account\AccountSubscriptionPlan;
use App\Services\Admin\AdminPaymentRequestService;
use Illuminate\Console\Command;

class PaymentRequestApproveOrUpdate extends Command
{
    protected $signature = 'payment-request:approve-or-update {account_id : Account ID} {--limit=50 : Max payment requests to process}';

    protected $description = 'Approve pending payment requests, or ensure approved ones are processed';

    public function handle(AdminPaymentRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        $limit = max(1, (int) $this->option('limit'));

        // 1) Approve all pending requests for supported flows.
        $pendingRequests = AccountPaymentRequest::query()
            ->where('account_id', $accountId)
            ->where('status', AccountPaymentRequestStatusConstant::STATUS_PENDING)
            ->whereIn('payment_transaction', [
                'Reactivation Fee',
                AccountSubscriptionPlan::class,
                AccountInvoice::class,
            ])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($pendingRequests as $req) {
            $service->approve($req->id, null);
            $processed++;
        }

        // 2) If there were no pending items, ensure the latest approved ones are processed.
        if ($pendingRequests->isEmpty()) {
            $latestApproved = [
                'reactivation' => AccountPaymentRequest::query()
                    ->where('account_id', $accountId)
                    ->where('status', AccountPaymentRequestStatusConstant::STATUS_APPROVED)
                    ->where('payment_transaction', 'Reactivation Fee')
                    ->orderByDesc('id')
                    ->first(),
                'subscription_upgrade' => AccountPaymentRequest::query()
                    ->where('account_id', $accountId)
                    ->where('status', AccountPaymentRequestStatusConstant::STATUS_APPROVED)
                    ->where('payment_transaction', AccountSubscriptionPlan::class)
                    ->orderByDesc('id')
                    ->first(),
            ];

            foreach ($latestApproved as $req) {
                if ($req) {
                    $service->processApprovedIfNeeded($req);
                    $processed++;
                }
            }
        }

        $this->info("Processed {$processed} payment request(s) for account {$accountId}.");
        return Command::SUCCESS;
    }
}

