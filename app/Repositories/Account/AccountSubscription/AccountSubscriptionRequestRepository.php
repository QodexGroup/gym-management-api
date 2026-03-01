<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Constant\AccountSubscriptionRequestConstant;
use App\Models\Account\AccountSubscriptionRequest;
use Illuminate\Database\Eloquent\Collection;

class AccountSubscriptionRequestRepository
{
    public function findPendingByAccountId(int $accountId): ?AccountSubscriptionRequest
    {
        return AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->first();
    }

    public function create(array $data): AccountSubscriptionRequest
    {
        return AccountSubscriptionRequest::create($data);
    }

    public function findById(int $id): ?AccountSubscriptionRequest
    {
        return AccountSubscriptionRequest::find($id);
    }

    public function findPendingById(int $id): ?AccountSubscriptionRequest
    {
        return AccountSubscriptionRequest::where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->find($id);
    }

    /**
     * @return Collection<int, AccountSubscriptionRequest>
     */
    public function getRecentByAccountId(int $accountId, int $limit = 5): Collection
    {
        return AccountSubscriptionRequest::with(['subscriptionPlan'])
            ->where('account_id', $accountId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function hasPendingForAccount(int $accountId): bool
    {
        return AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->exists();
    }
}
