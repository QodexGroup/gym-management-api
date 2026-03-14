<?php

namespace App\Http\Resources\Account\AccountSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'interval' => $this->interval,
            'price' => (float) $this->price,
            'maxCustomers' => $this->max_customers,
            'maxClassSchedules' => $this->max_class_schedules,
            'maxMembershipPlans' => $this->max_membership_plans,
            'maxUsers' => $this->max_users,
            'maxPtPackages' => $this->max_pt_packages,
            'hasPt' => (bool) $this->has_pt,
            'hasReports' => (bool) $this->has_reports,
            'isTrial' => (bool) $this->is_trial,
            'trialDays' => $this->trial_days,
        ];
    }
}
