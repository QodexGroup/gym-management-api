<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Models\Account\PlatformSubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class PlatformSubscriptionPlanRepository
{
    /**
     * List paid (non-trial) plans ordered by price.
     *
     * @return Collection<int, PlatformSubscriptionPlan>
     */
    public function getPaidPlansOrderedByPrice(): Collection
    {
        return PlatformSubscriptionPlan::where('is_trial', false)
            ->orderBy('price')
            ->get();
    }

    public function findById(int $id): ?PlatformSubscriptionPlan
    {
        return PlatformSubscriptionPlan::find($id);
    }

    public function findPaidPlanById(int $id): ?PlatformSubscriptionPlan
    {
        return PlatformSubscriptionPlan::where('id', $id)
            ->where('is_trial', false)
            ->first();
    }

    public function findBySlug(string $slug): ?PlatformSubscriptionPlan
    {
        return PlatformSubscriptionPlan::where('slug', $slug)->first();
    }
}
