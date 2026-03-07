<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Constant\AccountSubscriptionRequestConstant;
use App\Models\Account\AccountSubscriptionRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

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
    public function getRecentByAccountId(
        int $accountId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?int $limit = null
    ): Collection
    {
        $query = AccountSubscriptionRequest::with(['invoice.accountSubscriptionPlan.platformPlan'])
            ->where('account_id', $accountId)
            ->when($dateFrom, function (Builder $builder) use ($dateFrom) {
                $builder->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function (Builder $builder) use ($dateTo) {
                $builder->whereDate('created_at', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function hasPendingForAccount(int $accountId): bool
    {
        return AccountSubscriptionRequest::where('account_id', $accountId)
            ->where('status', AccountSubscriptionRequestConstant::STATUS_PENDING)
            ->exists();
    }
}
