<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subscriptionStatus' => $this->subscription_status,
            'trialEndsAt' => $this->trial_ends_at ? $this->trial_ends_at->toIso8601String() : null,
            'currentPeriodEndsAt' => $this->current_period_ends_at ? $this->current_period_ends_at->toIso8601String() : null,
            'subscriptionPlan' => $this->whenLoaded('subscriptionPlan', function () {
                return [
                    'id' => $this->subscriptionPlan->id,
                    'name' => $this->subscriptionPlan->name,
                    'slug' => $this->subscriptionPlan->slug,
                    'isTrial' => $this->subscriptionPlan->is_trial,
                ];
            }),
        ];
    }
}
