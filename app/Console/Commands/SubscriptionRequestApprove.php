<?php

namespace App\Console\Commands;

use App\Constant\AccountSubscriptionRequestConstant;
use App\Models\Account\AccountSubscriptionRequest;
use App\Services\Account\AccountSubscription\AccountSubscriptionRequestService;
use Illuminate\Console\Command;

class SubscriptionRequestApprove extends Command
{
    protected $signature = 'subscription-request:approve {account_id : Account ID that has a pending subscription request}';

    protected $description = 'Approve a pending subscription request by account ID (activates account subscription)';

    public function handle(AccountSubscriptionRequestService $service): int
    {
        $accountId = (int) $this->argument('account_id');
        $pending = AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->first();

        if (! $pending) {
            $this->error("No pending subscription request found for account ID: {$accountId}");
            return Command::FAILURE;
        }

        try {
            $request = $service->approve($pending->id, null);
            $this->info("Subscription request #{$pending->id} approved. Account #{$request->account_id} is now active.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
