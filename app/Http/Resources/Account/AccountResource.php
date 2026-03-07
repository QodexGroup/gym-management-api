<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        $asp = $this->activeAccountSubscriptionPlan;
        $plan = $asp ? $asp->platformPlan : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'subscriptionStatus' => $this->subscription_status,
            'trialEndsAt' => $asp ? $asp->trial_ends_at : null,
            'currentPeriodEndsAt' => $asp ? $asp->subscription_ends_at : null,
            'subscriptionPlan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'isTrial' => $plan->is_trial,
            ] : null,
            'billingInformation' => [
                'legalName' => $this->legal_name,
                'billingEmail' => $this->billing_email,
                'addressLine1' => $this->address_line_1,
                'addressLine2' => $this->address_line_2,
                'city' => $this->city,
                'stateProvince' => $this->state_province,
                'postalCode' => $this->postal_code,
                'country' => $this->country,
            ],
        ];
    }
}
