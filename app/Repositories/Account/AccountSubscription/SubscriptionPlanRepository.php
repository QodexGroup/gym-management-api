<?php

namespace App\Repositories\Account\AccountSubscription;

use App\Models\Account\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionPlanRepository
{
    /**
     * List paid (non-trial) plans ordered by price.
     *
     * @return Collection<int, SubscriptionPlan>
     */
    public function getPaidPlansOrderedByPrice(): Collection
    {
        return SubscriptionPlan::where('is_trial', false)
            ->orderBy('price')
            ->get();
    }

    /**
     * @param int $id
     *
     * @return SubscriptionPlan|null
     */
    public function findById(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::find($id);
    }

    /**
     * @param int $id
     *
     * @return SubscriptionPlan|null
     */
    public function findPaidPlanById(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('id', $id)
            ->where('is_trial', false)
            ->first();
    }

    /**
     * @param string $slug
     *
     * @return SubscriptionPlan|null
     */
    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('slug', $slug)->first();
    }

    /**
     * Find the default monthly paid plan (interval = month, non-trial), cheapest by price.
     *
     * @return SubscriptionPlan|null
     */
    public function findDefaultMonthlyPaidPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::where('is_trial', false)
            ->where('interval', 'month')
            ->orderBy('price')
            ->first();
    }
}
