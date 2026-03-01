<?php

namespace App\Services\Account\AccountSubscription;

use App\Repositories\Account\AccountSubscription\PlatformSubscriptionPlanRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PlatformSubscriptionPlanService
{
    public function __construct(
        private PlatformSubscriptionPlanRepository $planRepository
    ) {
    }

    /**
     * List paid plans for subscription/upgrade page.
     *
     * @return Collection<int, \App\Models\Account\PlatformSubscriptionPlan>
     */
    public function getPaidPlansForDisplay(): Collection
    {
        return $this->planRepository->getPaidPlansOrderedByPrice();
    }

    /**
     * Map plans to API response shape.
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function getPaidPlansAsApiShape(): SupportCollection
    {
        return $this->getPaidPlansForDisplay()->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'interval' => $p->interval,
                'price' => (float) $p->price,
                'maxCustomers' => $p->max_customers,
                'maxClassSchedules' => $p->max_class_schedules,
                'maxMembershipPlans' => $p->max_membership_plans,
                'maxUsers' => $p->max_users,
                'maxPtPackages' => $p->max_pt_packages,
                'hasPt' => $p->has_pt,
                'hasReports' => $p->has_reports,
                'isTrial' => (bool) $p->is_trial,
            ];
        });
    }
}
