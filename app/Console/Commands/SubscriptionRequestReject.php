<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Account\AccountSubscriptionRequest;
use App\Models\User;
use App\Services\Account\AccountSubscriptionRequestService;
use Illuminate\Console\Command;

class SubscriptionRequestReject extends Command
{
    protected $signature = 'subscription-request:reject {email : Account owner email or requester email} {--reason= : Optional rejection reason}';

    protected $description = 'Reject a pending subscription request by email';

    public function handle(AccountSubscriptionRequestService $service): int
    {
        $email = trim($this->argument('email'));
        $reason = $this->option('reason');
        $pending = $this->findPendingByEmail($email);

        if (! $pending) {
            $this->error("No pending subscription request found for email: {$email}");
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

    private function findPendingByEmail(string $email): ?AccountSubscriptionRequest
    {
        $account = Account::where('owner_email', $email)->first();
        if ($account) {
            $request = AccountSubscriptionRequest::where('account_id', $account->id)
                ->where('status', AccountSubscriptionRequest::STATUS_PENDING)
                ->first();
            if ($request) {
                return $request;
            }
        }

        $user = User::where('email', $email)->first();
        if ($user && $user->account_id) {
            return AccountSubscriptionRequest::where('account_id', $user->account_id)
                ->where('status', AccountSubscriptionRequest::STATUS_PENDING)
                ->first();
        }

        return null;
    }
}
