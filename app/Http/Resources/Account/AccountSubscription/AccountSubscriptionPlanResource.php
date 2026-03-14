<?php

namespace App\Http\Resources\Account\AccountSubscription;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accountId' => $this->account_id,
            'subscriptionPlanId' => $this->subscription_plan_id,
            'planName' => $this->plan_name,
            'trialStartsAt' => $this->trial_starts_at,
            'trialEndsAt' => $this->trial_ends_at,
            'subscriptionStartsAt' => $this->subscription_starts_at,
            'subscriptionEndsAt' => $this->subscription_ends_at,
            'lockedAt' => $this->locked_at,
            'subscriptionPlan' => $this->whenLoaded('subscriptionPlan', function () {
                return new SubscriptionPlanResource($this->subscriptionPlan);
            }),
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }
}
