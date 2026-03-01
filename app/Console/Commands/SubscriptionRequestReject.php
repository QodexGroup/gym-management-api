<?php

namespace App\Console\Commands;

use App\Constant\AccountSubscriptionRequestConstant;
use App\Models\Account\AccountSubscriptionRequest;
use App\Services\Account\AccountSubscription\AccountSubscriptionRequestService;
use Illuminate\Console\Command;

class SubscriptionRequestReject extends Command
{
    protected $signature = 'subscription-request:reject {account_id : Account ID that has a pending subscription request} {--reason= : Optional rejection reason}';

    protected $description = 'Reject a pending subscription request by account ID';

    public function handle(AccountSubscriptionRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        $reason = $this->option('reason');
        $pending = AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->first();

        if (! $pending) {
            $this->error("No pending subscription request found for account ID: {$accountId}");
            return Command::FAILURE;
        }

        try {
            $service->reject($pending->id, null, $reason);
            $this->info("Subscription request #{$pending->id} rejected.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
